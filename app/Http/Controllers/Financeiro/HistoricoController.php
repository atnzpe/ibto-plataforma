<?php

/*
 * HISTÓRICO do financeiro (#59, pedido do Gleyson em 09/10/2026): o log de
 * TODA movimentação (criar, editar, parcelar, dar baixa, cancelar, estornar),
 * lido da tabela de auditoria. SÓ LEITURA: não existe rota para apagar nem
 * alterar o histórico. Permissão "financeiro.ver" (routes/web.php).
 */

namespace App\Http\Controllers\Financeiro;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Support\Periodo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HistoricoController extends Controller
{
    // Os códigos de auditoria que são do financeiro (os outros, ex.: PAPEL_CONCEDIDO, ficam de fora).
    public const ACOES = [
        'LANCAMENTO_CRIADO',
        'LANCAMENTO_EDITADO',
        'LANCAMENTO_PARCELADO',
        'LANCAMENTO_PAGO',
        'LANCAMENTO_CANCELADO',
        'LANCAMENTO_ESTORNADO',
        'LANCAMENTO_PAGO_PARCIAL',
        // Recebimento de anuidade (#37): pagamento + receita ligados.
        'ANUIDADE_PAGA',
        // Cancelamento de todas as parcelas em aberto de um título (10/10/2026).
        'PARCELAS_CANCELADAS',
    ];

    /**
     * Lista do histórico, do mais novo para o mais antigo, 50 por página.
     *
     * @param  Request  $request  Filtros do endereço. Ex.: /financeiro/historico?mes=2026-10&lancamento=7
     * @return Response A tela "financeiro/historico".
     */
    public function __invoke(Request $request): Response
    {
        // Período ("de" e "até", pedido do Gleyson em 10/10/2026). Só filtra se a
        // pessoa escolher alguma data; sem datas = todo o histórico (assim a "vida
        // inteira" de um lançamento nunca fica cortada).
        $temPeriodo = $request->filled('de') || $request->filled('ate');
        $periodo = $temPeriodo ? Periodo::doPedido($request) : null;

        // Número do lançamento pedido, ou 0 = todos.
        $lancamentoId = (int) $request->query('lancamento', 0);

        $consulta = Auditoria::query()
            // Só as ações do financeiro.
            ->whereIn('acao', self::ACOES)
            // Já traz o nome de quem fez (uma consulta só, não uma por linha).
            ->with('usuario:id,name')
            ->orderByDesc('id');

        // Filtro por período: do início do 1º dia ao fim do último, pela hora da ação.
        if ($periodo !== null) {
            $consulta->whereBetween('created_at', [$periodo->inicio, $periodo->fim]);
        }

        // Filtro por lançamento: a "vida inteira" dele, incluindo o parcelamento
        // que o criou, o estorno que aponta para ele e a baixa parcial que gerou
        // o saldo (saldo_id).
        if ($lancamentoId > 0) {
            $consulta->where(function (Builder $onde) use ($lancamentoId): void {
                $onde->where('detalhes->lancamento_id', $lancamentoId)
                    ->orWhere('detalhes->estorno_de_id', $lancamentoId)
                    ->orWhere('detalhes->saldo_id', $lancamentoId)
                    ->orWhereJsonContains('detalhes->lancamento_ids', $lancamentoId);
            });
        }

        $pagina = $consulta->paginate(50)->withQueryString();

        return Inertia::render('financeiro/historico', [
            'filtros' => [
                'de' => $periodo?->inicio->toDateString() ?? '',
                'ate' => $periodo?->fim->toDateString() ?? '',
                'lancamento' => $lancamentoId > 0 ? $lancamentoId : null,
            ],
            'linhas' => collect($pagina->items())->map(fn (Auditoria $linha) => $this->paraTela($linha)),
            'paginacao' => [
                'atual' => $pagina->currentPage(),
                'ultima' => $pagina->lastPage(),
                'total' => $pagina->total(),
                'anterior' => $pagina->previousPageUrl(),
                'proxima' => $pagina->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Uma linha do histórico, só com o que a tela mostra.
     *
     * @return array<string, mixed>
     */
    private function paraTela(Auditoria $linha): array
    {
        $detalhes = $linha->detalhes ?? [];

        // Lançamento(s) da ação: um número, ou a lista das parcelas.
        $ids = isset($detalhes['lancamento_ids']) ? (array) $detalhes['lancamento_ids'] : array_filter([$detalhes['lancamento_id'] ?? null]);

        // Lançamento do saldo, na baixa parcial (vai junto na lista de números).
        if (isset($detalhes['saldo_id'])) {
            $ids[] = $detalhes['saldo_id'];
        }

        // Valor: o total no parcelamento, o novo valor na edição, ou o valor do lançamento.
        $valor = $detalhes['valor_total_centavos'] ?? $detalhes['depois']['valor_centavos'] ?? $detalhes['valor_centavos'] ?? null;

        return [
            'id' => $linha->id,
            // Ex.: '2026-10-09 15:42' (a tela formata).
            'quando' => $linha->created_at?->format('Y-m-d H:i'),
            // Quem fez: o nome da conta; se a conta foi apagada, avisa (o registro
            // continua). O "??" já protege: usuario vazio não dá erro, vira o texto.
            'quem' => $linha->usuario->name ?? 'Conta removida',
            'acao' => $linha->acao,
            'lancamentos' => array_values(array_map('intval', $ids)),
            'estorno_de' => isset($detalhes['estorno_de_id']) ? (int) $detalhes['estorno_de_id'] : null,
            'parcelas' => $detalhes['parcelas'] ?? null,
            // Baixa parcial: quanto ficou em aberto. Ex.: 60000.
            'saldo_centavos' => isset($detalhes['saldo_centavos']) ? (int) $detalhes['saldo_centavos'] : null,
            'valor_centavos' => $valor !== null ? (int) $valor : null,
            // Só na edição: o que mudou (valor e vencimento), para mostrar "antes → depois".
            'antes' => isset($detalhes['antes']) ? ['valor_centavos' => $detalhes['antes']['valor_centavos'] ?? null, 'vencimento' => $detalhes['antes']['vencimento'] ?? null] : null,
            'depois' => isset($detalhes['depois']) ? ['valor_centavos' => $detalhes['depois']['valor_centavos'] ?? null, 'vencimento' => $detalhes['depois']['vencimento'] ?? null] : null,
        ];
    }
}
