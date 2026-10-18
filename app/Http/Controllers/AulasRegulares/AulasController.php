<?php

/*
 * AULAS REGULARES, LADO DO ALUNO (#80): a lista dos programas abertos, a página
 * de cada um (turmas, valor, "Assinar"), "Minhas aulas" (situação, próximo
 * vencimento, o Pix da mensalidade em aberto e o link da sala quando liberada) e
 * cancelar a própria assinatura. Só com login (o IBTO é fechado).
 * Filiado e aluno indicado assinam; programa "só filiados" recusa quem não é.
 */

namespace App\Http\Controllers\AulasRegulares;

use App\Actions\AulasRegulares\Assinaturas;
use App\Exceptions\RegraCurso;
use App\Http\Controllers\Controller;
use App\Models\Assinatura;
use App\Models\AssinaturaPagamento;
use App\Models\Programa;
use App\Models\ProgramaTurma;
use App\Support\DadosPagamento;
use App\Support\PixCopiaECola;
use App\Support\QrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AulasController extends Controller
{
    /**
     * Os programas abertos (ativos), para escolher.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('aulas-regulares/index', [
            'programas' => Programa::query()->where('ativo', true)->with(['turmas' => fn ($q) => $q->where('ativa', true)])->orderBy('titulo')->get()
                ->map(fn (Programa $p) => $this->cartao($p))->values()->all(),
        ]);
    }

    /**
     * A página do programa: descrição, turmas, valor e "Assinar".
     */
    public function show(Request $request, Programa $programa): Response
    {
        // Desativado: só o Comando Geral vê (prévia); os outros, "não existe".
        abort_if(! $programa->ativo && ! $request->user()->can('cursos.gerenciar'), 404);
        $programa->load(['turmas' => fn ($q) => $q->where('ativa', true)]);

        return Inertia::render('aulas-regulares/programa', [
            'programa' => [...$this->cartao($programa), 'descricao' => $programa->descricao, 'dia_vencimento' => $programa->dia_vencimento],
            // Já assina? (aguardando ou ativa): a tela mostra "Ver em Minhas aulas" em vez de "Assinar".
            'ja_assina' => $programa->assinaturas()->where('user_id', $request->user()->id)->where('situacao', '!=', 'cancelada')->exists(),
        ]);
    }

    /**
     * Assina o programa (cria a assinatura e a 1ª mensalidade) e leva a "Minhas aulas", com o Pix.
     */
    public function assinar(Request $request, Programa $programa, Assinaturas $assinaturas): RedirectResponse
    {
        $dados = $request->validate(['turma_id' => ['nullable', 'integer']]);

        try {
            $assinaturas->assinar($programa, $request->user(), isset($dados['turma_id']) ? (int) $dados['turma_id'] : null);
        } catch (RegraCurso $erro) {
            return back()->withErrors(['assinar' => "{$erro->getMessage()} (código {$erro->codigo})"]);
        }

        return to_route('aulas.minhas')->with('sucesso', 'Assinatura criada! Faça o Pix da primeira mensalidade abaixo; quando o IBTO confirmar, as aulas ficam liberadas.');
    }

    /**
     * Minhas aulas: cada assinatura, com a situação, o Pix em aberto e o link da sala (se liberada).
     */
    public function minhas(Request $request): Response
    {
        $assinaturas = Assinatura::query()->with('programa', 'turma')->where('user_id', $request->user()->id)->where('situacao', '!=', 'cancelada')->latest('id')->get();
        $pagamento = DadosPagamento::doIbto();

        return Inertia::render('aulas-regulares/minhas', [
            'assinaturas' => $assinaturas->map(function (Assinatura $a) use ($pagamento) {
                $acesso = $a->acesso();
                $emAberto = $a->pagamentos()->where('situacao', 'aguardando')->orderBy('vence_em')->first();

                return [
                    'id' => $a->id,
                    'programa' => $a->programa->titulo,
                    'programa_id' => $a->programa_id,
                    'acesso' => $acesso,
                    'pago_ate' => $a->pago_ate?->format('d/m/Y'),
                    'turma' => $a->turma?->horarioPorExtenso(),
                    'local' => $a->turma?->local,
                    // O link da sala só aparece com o acesso liberado (em dia ou na carência).
                    'link_sala' => $a->liberada() ? $a->turma?->link_sala : null,
                    'em_aberto' => $emAberto === null ? null : $this->pixDaMensalidade($emAberto, $pagamento),
                ];
            })->values()->all(),
        ]);
    }

    /**
     * Cancela a própria assinatura.
     */
    public function cancelar(Request $request, Assinatura $assinatura, Assinaturas $assinaturas): RedirectResponse
    {
        // Só a própria pessoa (o Comando Geral cancela pela tela de mensalidades); os outros: 404.
        abort_unless($assinatura->user_id === $request->user()->id, 404);
        $assinaturas->cancelar($assinatura);

        return back()->with('sucesso', 'Assinatura cancelada. Você pode assinar de novo quando quiser.');
    }

    /**
     * Os dados do cartão do programa.
     *
     * @return array<string, mixed>
     */
    private function cartao(Programa $p): array
    {
        return [
            'id' => $p->id,
            'titulo' => $p->titulo,
            'modalidade' => Programa::MODALIDADES[$p->modalidade] ?? $p->modalidade,
            'instrutor' => $p->instrutor_nome,
            'valor_mensal_centavos' => $p->valor_mensal_centavos,
            'so_filiados' => $p->so_filiados,
            'vagas_restantes' => $p->vagas === null ? null : max(0, $p->vagas - $p->assinaturas()->where('situacao', '!=', 'cancelada')->count()),
            'turmas' => $p->turmas->map(fn (ProgramaTurma $t) => ['id' => $t->id, 'nome' => $t->nome, 'horario' => $t->horarioPorExtenso(), 'local' => $t->local])->values()->all(),
        ];
    }

    /**
     * O Pix copia e cola de uma mensalidade (dados do IBTO), ou só o valor se a chave não está configurada.
     *
     * @param  array{pix_chave: string, favorecido: string}  $pagamento
     * @return array<string, mixed>
     */
    private function pixDaMensalidade(AssinaturaPagamento $mensalidade, array $pagamento): array
    {
        $dados = ['id' => $mensalidade->id, 'referencia' => $mensalidade->referencia, 'valor_centavos' => $mensalidade->valor_centavos, 'vence_em' => $mensalidade->vence_em->format('d/m/Y'), 'pix' => null];
        // Sem a chave Pix configurada: mostra só o valor (o Comando Geral precisa configurar).
        if ($pagamento['pix_chave'] === '') {
            return $dados;
        }
        $txid = MensalidadeController::txid($mensalidade->id);
        $codigo = PixCopiaECola::gerar($pagamento['pix_chave'], $pagamento['favorecido'] !== '' ? $pagamento['favorecido'] : 'IBTO', (string) config('app.pix_cidade'), $mensalidade->valor_centavos, $txid);
        $dados['pix'] = ['copia_e_cola' => $codigo, 'qr_svg' => QrCode::svg($codigo, 220), 'txid' => $txid, 'favorecido' => $pagamento['favorecido'], 'chave' => $pagamento['pix_chave']];

        return $dados;
    }
}
