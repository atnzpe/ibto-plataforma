<?php

/*
 * SOLICITAÇÕES DE CADASTRO (#84; o IBTO é um grupo fechado, só de indicados).
 *
 * Lista quem se cadastrou e está "em análise", e permite APROVAR (a pessoa
 * vira Aluno e recebe o e-mail) ou RECUSAR com motivo (recebe o e-mail com o
 * motivo). Quem analisa:
 * - o Comando Geral e a Tecnologia (permissão "usuarios.gerenciar"): todas;
 * - o admin de equipe (permissão "filiados.equipe"): só as da PRÓPRIA equipe.
 * Tudo vai para a auditoria (quem, quando, a decisão e, na recusa, o motivo).
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Papel;
use App\Models\User;
use App\Notifications\CadastroAnalisado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SolicitacoesCadastroController extends Controller
{
    /**
     * A lista das solicitações em análise (as mais antigas primeiro: quem espera há
     * mais tempo é atendido antes).
     *
     * @param  Request  $request  Quem está analisando.
     * @return Response A tela "admin/solicitacoes/index".
     */
    public function index(Request $request): Response
    {
        Gate::authorize('cadastros.aprovar');

        /** @var User $analista */
        $analista = $request->user();
        $consulta = User::query()
            ->where('situacao_cadastro', 'em_analise')
            ->with('equipeSolicitada:id,nome')
            ->orderBy('created_at');

        // Admin de equipe (sem a permissão geral): só os pedidos da própria equipe.
        if (! $analista->temPermissao('usuarios.gerenciar')) {
            // Sem registro de filiado (ou sem equipe): 0, que não é equipe nenhuma (lista vazia).
            $consulta->where('equipe_solicitada_id', $analista->filiado->equipe_id ?? 0);
        }

        return Inertia::render('admin/solicitacoes/index', [
            'solicitacoes' => $consulta->get()->map(fn (User $usuario) => [
                'id' => $usuario->id,
                'nome' => $usuario->name,
                'email' => $usuario->email,
                'equipe' => $usuario->equipeSolicitada?->nome,
                'indicado_por' => $usuario->indicado_por,
                // E-mail confirmado? (ajuda a saber se o endereço é real)
                'email_confirmado' => $usuario->email_verified_at !== null,
                // Quando pediu. Ex.: '17/10/2026 09:30'.
                'pedido_em' => $usuario->created_at?->format('d/m/Y H:i'),
            ])->values(),
        ]);
    }

    /**
     * Aprova o pedido: a pessoa ganha o papel Aluno e recebe o e-mail.
     *
     * @param  Request  $request  Quem aprova.
     * @param  User  $usuario  Quem pediu acesso. Ex.: /admin/solicitacoes/12/aprovar.
     * @return RedirectResponse Volta para a lista com a confirmação.
     */
    public function aprovar(Request $request, User $usuario): RedirectResponse
    {
        Gate::authorize('cadastros.analisar', $usuario);

        DB::transaction(function () use ($request, $usuario): void {
            $usuario->forceFill([
                'situacao_cadastro' => 'aprovado',
                'cadastro_analisado_por' => $request->user()?->id,
                'cadastro_analisado_em' => now(),
                'cadastro_motivo' => null,
            ])->save();

            // O papel Aluno (pelo apelido fixo 'aluno'). Sem o papel no banco: registra no
            // log (#58) e segue, para a aprovação não travar.
            $aluno = Papel::where('slug', 'aluno')->value('id');
            if ($aluno !== null) {
                $usuario->papeis()->syncWithoutDetaching([$aluno]);
            } else {
                Log::error('PAPEL_ALUNO_AUSENTE', ['usuario_id' => $usuario->id]);
            }

            Auditoria::registrar('CADASTRO_APROVADO', ['usuario_id' => $usuario->id, 'equipe_id' => $usuario->equipe_solicitada_id]);
            $usuario->notify(new CadastroAnalisado(true));
        });

        return back()->with('sucesso', "Acesso de {$usuario->name} aprovado. A pessoa vai receber um e-mail.");
    }

    /**
     * Recusa o pedido, com o motivo (vai no e-mail e na auditoria).
     *
     * @param  Request  $request  Com "motivo". Ex.: { motivo: 'A equipe não confirmou a indicação.' }
     * @param  User  $usuario  Quem pediu acesso.
     * @return RedirectResponse Volta para a lista com a confirmação.
     */
    public function recusar(Request $request, User $usuario): RedirectResponse
    {
        Gate::authorize('cadastros.analisar', $usuario);

        $dados = $request->validate(
            ['motivo' => ['required', 'string', 'min:5', 'max:500']],
            ['motivo.required' => 'Escreva o motivo: ele vai no e-mail para a pessoa.', 'motivo.min' => 'Escreva um motivo um pouco mais claro.'],
        );

        DB::transaction(function () use ($request, $usuario, $dados): void {
            $usuario->forceFill([
                'situacao_cadastro' => 'recusado',
                'cadastro_analisado_por' => $request->user()?->id,
                'cadastro_analisado_em' => now(),
                'cadastro_motivo' => $dados['motivo'],
            ])->save();

            Auditoria::registrar('CADASTRO_RECUSADO', ['usuario_id' => $usuario->id, 'motivo' => $dados['motivo']]);
            $usuario->notify(new CadastroAnalisado(false, $dados['motivo']));
        });

        return back()->with('sucesso', "Pedido de {$usuario->name} recusado. A pessoa vai receber o motivo por e-mail.");
    }
}
