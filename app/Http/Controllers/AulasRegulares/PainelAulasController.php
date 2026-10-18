<?php

/*
 * TELA E CSV DO PAINEL FINANCEIRO DAS AULAS REGULARES (17/10/2026).
 * A conta fica em App\Relatorios\PainelAulasRegulares; aqui só lemos os
 * filtros do endereço, conferimos a permissão "relatorio.aulas" e entregamos
 * a tela (Inertia) ou o CSV (uma linha por mensalidade). Toda exportação vai
 * para a auditoria, como nos outros relatórios.
 */

namespace App\Http\Controllers\AulasRegulares;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Programa;
use App\Relatorios\PainelAulasRegulares;
use App\Support\Periodo;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PainelAulasController extends Controller
{
    /**
     * A tela do painel: cartões, tabelas por programa/instrutor/mês, quem treina e as mensalidades.
     */
    public function index(Request $request): Response
    {
        [$painel, $periodo, $filtros] = $this->painel($request);

        return Inertia::render('relatorios/aulas-regulares', [
            ...$painel->montar(),
            'periodo' => $periodo->paraTela(),
            'filtros' => $filtros,
            've_tudo' => $painel->veTudo(),
            // As opções dos filtros: só os programas (e instrutores) que a pessoa pode ver.
            'programas' => $painel->programasVisiveis()->get(['id', 'titulo'])->map(fn (Programa $programa) => ['id' => $programa->id, 'nome' => $programa->titulo])->values(),
            'instrutores' => $painel->programasVisiveis()->whereNotNull('instrutor_id')->get(['instrutor_id', 'instrutor_nome'])
                ->unique('instrutor_id')->map(fn (Programa $programa) => ['id' => $programa->instrutor_id, 'nome' => $programa->instrutor_nome ?: 'Instrutor nº '.$programa->instrutor_id])->values(),
            'situacoes' => collect(PainelAulasRegulares::SITUACOES)->map(fn (string $rotulo, string $chave) => ['id' => $chave, 'nome' => $rotulo])->values(),
        ]);
    }

    /**
     * O CSV: uma linha por mensalidade, com a divisão IBTO/instrutor e o repasse.
     */
    public function csv(Request $request): StreamedResponse
    {
        [$painel, $periodo, $filtros] = $this->painel($request);
        Auditoria::registrar('RELATORIO_EXPORTADO', ['relatorio' => 'aulas-regulares', 'formato' => 'csv', ...$periodo->paraTela(), 'filtros' => array_filter($filtros, fn ($valor) => $valor !== null && $valor !== '')]);
        $linhas = $painel->mensalidades();

        return response()->streamDownload(function () use ($linhas): void {
            $saida = fopen('php://output', 'w');
            // Não abriu a saída (raro): não há onde escrever.
            if ($saida === false) {
                return;
            }
            // BOM: faz o Excel ler os acentos certos.
            fwrite($saida, "\xEF\xBB\xBF");
            fputcsv($saida, ['Referência', 'Vencimento', 'Pago em', 'Aluno (pagador)', 'Programa', 'Turma', 'Instrutor (recebedor)', 'Mensalidade', 'Situação', 'Parte do IBTO', 'Parte do instrutor', 'Repasse pago'], ';');
            foreach ($linhas as $linha) {
                fputcsv($saida, [
                    $linha['referencia'], $linha['vence_em'], $linha['pago_em'] ?? '', $linha['aluno'], $linha['programa'], $linha['turma'], $linha['instrutor'],
                    self::reais($linha['valor']), $linha['situacao_rotulo'], self::reais($linha['ibto']), self::reais($linha['instrutor_parte']),
                    // O repasse só existe depois do pagamento.
                    $linha['situacao'] !== 'pago' ? '—' : ($linha['repassado'] ? 'Sim' : 'Não'),
                ], ';');
            }
            fclose($saida);
        }, 'relatorio-aulas-regulares-ibto.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Lê o período e os filtros do endereço e monta o painel.
     *
     * @return array{0: PainelAulasRegulares, 1: Periodo, 2: array{programa: int|null, instrutor: int|null, pagador: string, situacao: string|null}}
     */
    private function painel(Request $request): array
    {
        // Sem a permissão do relatório: 403.
        abort_unless($request->user()->temPermissao('relatorio.aulas'), 403);

        // Sem De/Até: o ano atual inteiro (igual aos outros relatórios).
        $hoje = CarbonImmutable::now();
        $periodo = $request->filled('de') || $request->filled('ate')
            ? Periodo::doPedido($request)
            : new Periodo($hoje->startOfYear(), $hoje->endOfYear());

        // Filtros: número só se for número; situação só se for uma das conhecidas.
        $situacao = (string) $request->query('situacao', '');
        $filtros = [
            'programa' => ctype_digit((string) $request->query('programa', '')) ? (int) $request->query('programa') : null,
            'instrutor' => ctype_digit((string) $request->query('instrutor', '')) ? (int) $request->query('instrutor') : null,
            'pagador' => mb_substr(trim((string) $request->query('pagador', '')), 0, 100),
            'situacao' => array_key_exists($situacao, PainelAulasRegulares::SITUACOES) ? $situacao : null,
        ];

        return [new PainelAulasRegulares($periodo, $request->user(), $filtros), $periodo, $filtros];
    }

    /**
     * Centavos → "120,00" (para o CSV).
     */
    private static function reais(int $centavos): string
    {
        return number_format($centavos / 100, 2, ',', '');
    }
}
