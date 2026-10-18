<?php

/*
 * MEU PAINEL — INSTRUTOR E ADMIN DE EQUIPE (#79): números que geram decisão.
 *
 * Por curso: inscritos (por situação), comparecimento (presenças ÷ confirmados ×
 * encontros com chamada), a receber e recebido (o repasse ao instrutor no
 * livro-caixa) e certificados (emitidos e aptos ainda sem certificado).
 *
 * - Instrutor: só os cursos DELE (responsável). Admin de equipe: os da EQUIPE dele.
 * - Nenhum dado pessoal de aluno: só contagens, percentuais e valores.
 * - Filtro De/Até (regra da casa) pela data de início do curso; curso sem data
 *   (gravado) aparece sempre. Sem filtro: o ano atual.
 * - Exportar CSV (para planilha); o PDF entra com o módulo de relatórios (#73).
 * - 17/10/2026: as AULAS REGULARES que a pessoa ministra (por programa) e a SAÚDE
 *   FINANCEIRA dela (cursos + aulas): recebido, pendente de repasse, a vencer e total.
 */

namespace App\Http\Controllers;

use App\Actions\Cursos\Certificados;
use App\Enums\SituacaoMatricula;
use App\Models\Curso;
use App\Models\CursoAula;
use App\Models\CursoCertificado;
use App\Models\CursoMatricula;
use App\Models\CursoPresenca;
use App\Models\FinLancamento;
use App\Models\User;
use App\Relatorios\PainelAulasRegulares;
use App\Support\Periodo;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MeuPainelController extends Controller
{
    public function __construct(private readonly Certificados $certificados) {}

    /**
     * A tela do painel, com os totais e um cartão por curso.
     *
     * @param  Request  $request  Com "de" e "até" (opcionais). Ex.: ?de=2026-01-01&ate=2026-12-31
     */
    public function index(Request $request): Response
    {
        $periodo = $this->periodo($request);
        $linhas = $this->cursos($request->user(), $periodo)->map(fn (Curso $curso) => $this->indicadores($curso))->values();

        // AULAS REGULARES QUE A PESSOA MINISTRA (17/10/2026): só os programas em que ela é o
        // instrutor (mesmo quem vê tudo no relatório vê aqui só o próprio dinheiro).
        $aulas = (new PainelAulasRegulares($periodo, $request->user(), soOsMeus: true))->montar();
        $somaAulas = $aulas['totais'];

        return Inertia::render('meu-painel', [
            'periodo' => $periodo->paraTela(),
            'cursos' => $linhas,
            'aulas' => [
                'programas' => $aulas['por_programa'],
                'alunos' => $somaAulas['alunos'],
                'alunos_em_dia' => $somaAulas['alunos_em_dia'],
            ],
            // SAÚDE FINANCEIRA (17/10/2026): cursos + aulas regulares, só a parte da pessoa.
            // - recebido: o IBTO já pagou (repasse com baixa);
            // - pendente: o aluno já pagou, falta o IBTO repassar;
            // - a_vencer: mensalidades ainda não pagas pelos alunos (no prazo ou em atraso);
            // - total: tudo o que ainda vai entrar (pendente + a vencer).
            'saude' => [
                'recebido_centavos' => (int) $linhas->sum('recebido_centavos') + $somaAulas['repassado'],
                'pendente_centavos' => (int) $linhas->sum('a_receber_centavos') + $somaAulas['a_repassar'],
                'a_vencer_centavos' => $somaAulas['instrutor_previsto'] - $somaAulas['instrutor_recebido'],
                'total_a_receber_centavos' => (int) $linhas->sum('a_receber_centavos') + $somaAulas['a_repassar'] + $somaAulas['instrutor_previsto'] - $somaAulas['instrutor_recebido'],
            ],
            // Totais do período (somas simples; o comparecimento total é a média ponderada).
            'totais' => [
                'inscritos' => $linhas->sum('confirmados') + $linhas->sum('aguardando'),
                'confirmados' => $linhas->sum('confirmados'),
                'a_receber_centavos' => $linhas->sum('a_receber_centavos'),
                'recebido_centavos' => $linhas->sum('recebido_centavos'),
                'certificados' => $linhas->sum('certificados'),
                'comparecimento' => $this->percentual($linhas->sum('presencas'), $linhas->sum('vagas_de_presenca')),
            ],
        ]);
    }

    /**
     * Os mesmos números em CSV (abre no Excel ou no Google Planilhas).
     */
    public function csv(Request $request): StreamedResponse
    {
        $periodo = $this->periodo($request);
        $linhas = $this->cursos($request->user(), $periodo)->map(fn (Curso $curso) => $this->indicadores($curso));

        return response()->streamDownload(function () use ($linhas): void {
            $saida = fopen('php://output', 'w');
            // Não abriu a saída (raro): não há onde escrever, então para sem erro.
            if ($saida === false) {
                return;
            }
            // BOM: o Excel abre os acentos certos.
            fwrite($saida, "\xEF\xBB\xBF");
            fputcsv($saida, ['Curso', 'Início', 'Aguardando pagamento', 'Confirmados', 'Cancelados', 'Comparecimento (%)', 'A receber (R$)', 'Recebido (R$)', 'Certificados', 'Aptos sem certificado'], ';');
            foreach ($linhas as $linha) {
                fputcsv($saida, [
                    $linha['titulo'], $linha['data_inicio'] ?? '', $linha['aguardando'], $linha['confirmados'], $linha['cancelados'],
                    $linha['comparecimento'] ?? '', number_format($linha['a_receber_centavos'] / 100, 2, ',', ''),
                    number_format($linha['recebido_centavos'] / 100, 2, ',', ''), $linha['certificados'], $linha['aptos_sem_certificado'],
                ], ';');
            }
            fclose($saida);
        }, 'meu-painel-ibto.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Os cursos desta pessoa no período: os dela (instrutor) e os da equipe (admin de equipe).
     *
     * @return Collection<int, Curso>
     */
    private function cursos(User $usuario, Periodo $periodo): Collection
    {
        $equipe = $usuario->temPermissao('filiados.equipe') ? $usuario->filiado?->equipe_id : null;

        return Curso::query()
            // Os do instrutor OU os da equipe do admin (quem não é nenhum dos dois não chega aqui: rota protegida).
            ->where(function (Builder $consulta) use ($usuario, $equipe): void {
                $consulta->where('responsavel_id', $usuario->id);
                if ($equipe !== null) {
                    $consulta->orWhere('equipe_id', $equipe);
                }
            })
            // No período pela data de início; curso sem data (gravado) aparece sempre.
            ->where(fn (Builder $consulta) => $consulta->whereNull('data_inicio')->orWhereBetween('data_inicio', [$periodo->inicio->toDateString(), $periodo->fim->toDateString()]))
            ->orderByDesc('data_inicio')
            ->get();
    }

    /**
     * Os números de UM curso (sem nenhum dado pessoal).
     *
     * @return array<string, mixed>
     */
    private function indicadores(Curso $curso): array
    {
        // Inscritos por situação.
        $porSituacao = CursoMatricula::query()->where('curso_id', $curso->id)->selectRaw('situacao, count(*) as total')->groupBy('situacao')->pluck('total', 'situacao');
        $confirmados = (int) ($porSituacao[SituacaoMatricula::Confirmada->value] ?? 0);

        // Comparecimento: presenças ÷ (confirmados × encontros que já tiveram chamada).
        $encontros = CursoAula::query()->whereNotNull('chamada_feita_em')->whereHas('modulo', fn ($modulo) => $modulo->where('curso_id', $curso->id))->pluck('id');
        $presencas = CursoPresenca::query()->whereIn('aula_id', $encontros)->where('presente', true)->count();
        $vagasDePresenca = $confirmados * $encontros->count();

        // A receber e recebido: o repasse ao instrutor (conta a pagar "Instrutores") das matrículas deste curso.
        $matriculas = CursoMatricula::query()->where('curso_id', $curso->id)->pluck('id');
        $repasses = FinLancamento::query()->where('origem', 'repasse')->whereIn('origem_id', $matriculas);
        $aReceber = (int) (clone $repasses)->where('situacao', 'pendente')->sum('valor_centavos');
        $recebido = (int) (clone $repasses)->where('situacao', 'pago')->sum('valor_centavos');

        // Certificados válidos e quem está apto mas ainda sem certificado.
        $certificados = CursoCertificado::query()->whereNull('cancelado_em')->whereIn('matricula_id', $matriculas)->count();
        $aptosSem = CursoMatricula::query()->where('curso_id', $curso->id)->where('situacao', SituacaoMatricula::Confirmada->value)->whereDoesntHave('certificado')->get()
            ->filter(fn (CursoMatricula $matricula) => $this->certificados->apto($matricula)['apto'])->count();

        return [
            'id' => $curso->id,
            'titulo' => $curso->titulo,
            'data_inicio' => $curso->data_inicio?->format('d/m/Y'),
            'situacao_rotulo' => $curso->situacao->rotulo(),
            'aguardando' => (int) ($porSituacao[SituacaoMatricula::AguardandoPagamento->value] ?? 0),
            'confirmados' => $confirmados,
            'cancelados' => (int) ($porSituacao[SituacaoMatricula::Cancelada->value] ?? 0),
            'encontros_com_chamada' => $encontros->count(),
            'presencas' => $presencas,
            'vagas_de_presenca' => $vagasDePresenca,
            // null = ainda sem chamada (não dá para calcular).
            'comparecimento' => $this->percentual($presencas, $vagasDePresenca),
            'a_receber_centavos' => $aReceber,
            'recebido_centavos' => $recebido,
            'certificados' => $certificados,
            'aptos_sem_certificado' => $aptosSem,
        ];
    }

    /**
     * Percentual inteiro, ou null quando não há base. Ex.: 18 de 24 → 75.
     */
    private function percentual(int $parte, int $todo): ?int
    {
        return $todo > 0 ? (int) round($parte * 100 / $todo) : null;
    }

    /**
     * O período: o do filtro, ou o ano atual quando não veio nenhum.
     */
    private function periodo(Request $request): Periodo
    {
        if (! $request->filled('de') && ! $request->filled('ate')) {
            $hoje = CarbonImmutable::now();

            return new Periodo($hoje->startOfYear(), $hoje->endOfYear());
        }

        return Periodo::doPedido($request);
    }
}
