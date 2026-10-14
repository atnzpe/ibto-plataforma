<?php

/*
 * DADOS PARA PAGAMENTO no Comando Geral (#41; pedido do Gleyson em
 * 10/10/2026: "as duas opções e uma chave liga/desliga, assim podemos
 * escolher o que queremos").
 *
 * - edit/update: os dados do IBTO (PIX, favorecido, banco, agência, conta,
 *   instruções) e a chave "permitir dados por curso";
 * - curso(): os dados PRÓPRIOS de um curso (painel na edição do curso).
 *
 * Só quem tem "pagamentos.configurar" (routes/web.php). Cada troca vai para a
 * auditoria com o ANTES e o DEPOIS: mudar para onde vai o dinheiro é a ação
 * mais sensível do sistema, e o histórico precisa mostrar quem mudou e de quê
 * para quê (anti-fraude). São dados da conta que RECEBE (do IBTO ou da
 * equipe), não dados pessoais de aluno.
 *
 * TRAVAS DE SEGURANÇA (Gleyson, 10/10/2026: "tela bem segura"):
 * 1) permissão própria (pagamentos.configurar), separada do formulário do curso;
 * 2) SENHA DE NOVO para abrir e salvar (vale 15 minutos; routes/web.php):
 *    um computador esquecido aberto não basta para trocar o PIX;
 * 3) auditoria com o antes e o depois de cada campo;
 * 4) E-MAIL DE ALERTA a todos os responsáveis a cada troca (PagamentoAlterado);
 * 5) "Última alteração: quem e quando" no topo da tela.
 */

namespace App\Http\Controllers\Cursos;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\PagamentoConfig;
use App\Models\User;
use App\Notifications\PagamentoAlterado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class PagamentoConfigController extends Controller
{
    // Regras dos campos (as mesmas para o IBTO e para o curso).
    private const REGRAS = [
        'pix_chave' => ['nullable', 'string', 'max:140'],
        'favorecido' => ['nullable', 'string', 'max:150'],
        'banco' => ['nullable', 'string', 'max:100'],
        'agencia' => ['nullable', 'string', 'max:20'],
        'conta' => ['nullable', 'string', 'max:30'],
    ];

    // Nome de cada campo para o e-mail de alerta (sem os valores).
    private const NOMES = [
        'pix_chave' => 'Chave PIX', 'pag_pix_chave' => 'Chave PIX',
        'favorecido' => 'Favorecido', 'pag_favorecido' => 'Favorecido',
        'banco' => 'Banco', 'pag_banco' => 'Banco',
        'agencia' => 'Agência', 'pag_agencia' => 'Agência',
        'conta' => 'Conta', 'pag_conta' => 'Conta',
        'instrucoes' => 'Instruções',
        'por_curso' => 'Chave "permitir dados por curso"',
    ];

    /**
     * A tela com os dados do IBTO e a chave liga/desliga.
     *
     * @return Response A tela "cursos/pagamento".
     */
    public function edit(): Response
    {
        $config = PagamentoConfig::atual();
        // A troca mais recente (do IBTO ou de algum curso), para "Última alteração".
        $ultima = Auditoria::query()
            ->whereIn('acao', ['PAGAMENTO_CONFIG_ALTERADO', 'PAGAMENTO_CURSO_ALTERADO'])
            ->with('usuario:id,name')
            ->latest('id')
            ->first();

        return Inertia::render('cursos/pagamento', [
            'config' => $config->only(['pix_chave', 'favorecido', 'banco', 'agencia', 'conta', 'instrucoes', 'por_curso']),
            // Quantos cursos têm dados próprios (para a pessoa saber o efeito de ligar/desligar).
            'cursos_com_dados' => Curso::query()->whereNotNull('pag_pix_chave')->count(),
            // Ex.: { quem: 'Ana Souza', quando: '10/10/2026 14:32', onde: 'IBTO' }, ou null.
            'ultima_alteracao' => $ultima === null ? null : [
                'quem' => $ultima->usuario->name ?? 'Sistema',
                'quando' => $ultima->created_at?->format('d/m/Y H:i'),
                'onde' => $ultima->acao === 'PAGAMENTO_CURSO_ALTERADO' ? 'Curso nº '.($ultima->detalhes['curso_id'] ?? '?') : 'Dados do IBTO',
            ],
        ]);
    }

    /**
     * Grava os dados do IBTO e a chave, com o antes e o depois na auditoria
     * e o alerta por e-mail aos responsáveis.
     *
     * @param  Request  $request  O formulário.
     * @return RedirectResponse Volta para a tela com a mensagem.
     */
    public function update(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            ...self::REGRAS,
            'instrucoes' => ['nullable', 'string', 'max:500'],
            'por_curso' => ['required', 'boolean'],
        ]);

        $config = PagamentoConfig::atual();
        $config->fill($dados);
        $this->gravarComHistorico($request, $config, 'PAGAMENTO_CONFIG_ALTERADO', ['config_id' => $config->id], 'dados para pagamento do IBTO');

        return to_route('comando-geral.pagamento.edit')->with('sucesso', 'Dados para pagamento atualizados.');
    }

    /**
     * Grava os dados PRÓPRIOS de um curso (vazios = o curso usa os do IBTO).
     *
     * @param  Request  $request  O formulário do painel na edição do curso.
     * @param  Curso  $curso  O curso do endereço.
     * @return RedirectResponse Volta para a edição do curso.
     */
    public function curso(Request $request, Curso $curso): RedirectResponse
    {
        $dados = $request->validate(self::REGRAS);

        // forceFill: estes campos ficam FORA do formulário comum do curso (quem
        // propõe o curso não muda para onde vai o dinheiro).
        $curso->forceFill([
            'pag_pix_chave' => $dados['pix_chave'] ?? null,
            'pag_favorecido' => $dados['favorecido'] ?? null,
            'pag_banco' => $dados['banco'] ?? null,
            'pag_agencia' => $dados['agencia'] ?? null,
            'pag_conta' => $dados['conta'] ?? null,
        ]);
        $this->gravarComHistorico($request, $curso, 'PAGAMENTO_CURSO_ALTERADO', ['curso_id' => $curso->id], "dados para pagamento do curso \"{$curso->titulo}\"");

        return to_route('comando-geral.cursos.edit', $curso)->with('sucesso', 'Dados para pagamento do curso atualizados.');
    }

    /**
     * Grava o registro e, se algo MUDOU: anota na auditoria o antes e o depois
     * de cada campo e manda o e-mail de alerta para todos os responsáveis.
     * Tudo numa transação: se algo falhar, nada fica pela metade (e o e-mail não sai).
     *
     * @param  Request  $request  Quem está trocando.
     * @param  Model  $registro  A linha do IBTO ou o curso, já com os valores novos (ainda não gravados).
     * @param  string  $acao  Ex.: 'PAGAMENTO_CONFIG_ALTERADO'
     * @param  array<string, mixed>  $extra  Ex.: ['curso_id' => 7]
     * @param  string  $onde  Para o e-mail. Ex.: 'dados para pagamento do IBTO'
     */
    private function gravarComHistorico(Request $request, Model $registro, string $acao, array $extra, string $onde): void
    {
        $mudou = $registro->getDirty();
        // Nada mudou: não grava, não anota, não alerta.
        if ($mudou === []) {
            return;
        }

        // O antes e o depois de cada campo que mudou.
        $mudancas = [];
        foreach ($mudou as $campo => $novo) {
            $mudancas[$campo] = ['antes' => $registro->getOriginal($campo), 'depois' => $novo];
        }

        DB::transaction(function () use ($request, $registro, $acao, $extra, $onde, $mudancas): void {
            $registro->save();
            Auditoria::registrar($acao, [...$extra, 'mudancas' => $mudancas]);

            // Todos os responsáveis (permissão "pagamentos.configurar"), inclusive quem trocou.
            $responsaveis = User::query()
                ->whereHas('papeis.permissoes', fn ($permissao) => $permissao->where('slug', 'pagamentos.configurar'))
                ->whereNotNull('email')
                ->get();
            Notification::send($responsaveis, new PagamentoAlterado(
                quem: (string) $request->user()?->name,
                onde: $onde,
                campos: array_values(array_unique(array_map(fn (string $campo) => self::NOMES[$campo] ?? $campo, array_keys($mudancas)))),
            ));
        });
    }
}
