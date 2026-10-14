<?php

/*
 * Tela de USUÁRIOS (#36): o administrador busca uma pessoa e marca os papéis
 * dela (Aluno, Filiado, Instrutor, Administrador, Tecnologia). Só quem tem
 * "usuarios.gerenciar" entra. A pessoa cria a própria conta: aqui ninguém vê
 * nem define senha.
 *
 * Segurança: o papel "Tecnologia" (que mexe nas permissões) só pode ser dado
 * ou tirado por quem tem "permissoes.gerenciar". Senão, um Administrador
 * poderia se dar esse papel e ganhar acesso a tudo ("escalada de privilégio").
 * Cada papel dado ou tirado vai para a auditoria.
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Papel;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioController extends Controller
{
    // Papel que só quem mexe nas permissões pode dar ou tirar.
    private const PAPEL_PROTEGIDO = 'tecnologia';

    /**
     * Lista de usuários com busca por nome ou e-mail, 20 por página.
     *
     * @param  Request  $request  Pode trazer ?busca=texto. Ex.: ?busca=vulcan
     * @return Response A tela "admin/usuarios/index".
     */
    public function index(Request $request): Response
    {
        // Texto buscado, sem espaços nas pontas; vazio = todos.
        $busca = trim($request->string('busca')->toString());

        $usuarios = User::query()
            ->with('papeis:id,nome')
            ->when($busca !== '', fn ($consulta) => $consulta->where(fn ($ou) => $ou
                ->where('name', 'like', "%{$busca}%")
                ->orWhere('email', 'like', "%{$busca}%")))
            ->orderBy('name')
            ->paginate(20)
            // Mantém a busca nos links de "próxima página".
            ->withQueryString()
            ->through(fn (User $usuario) => [
                'id' => $usuario->id,
                'nome' => $usuario->name,
                'email' => $usuario->email,
                'papeis' => $usuario->papeis->pluck('nome'),
                // Estado da conta, para os botões de ação (#36, 10/10/2026):
                // false = desativada (não entra; botão "Reativar").
                'ativa' => $usuario->desativada_em === null,
                // true = já criou a senha / confirmou o e-mail (sem botão de convite).
                'senha_criada' => $usuario->hasVerifiedEmail(),
                // true = é a própria pessoa logada (não pode se desativar).
                'eu' => $usuario->id === $request->user()?->id,
            ]);

        return Inertia::render('admin/usuarios/index', [
            'usuarios' => $usuarios,
            'busca' => $busca,
        ]);
    }

    /**
     * Formulário com os papéis da pessoa para marcar.
     *
     * @param  User  $usuario  A pessoa do endereço (ex.: /admin/usuarios/7/edit).
     * @return Response A tela "admin/usuarios/formulario".
     */
    public function edit(Request $request, User $usuario): Response
    {
        return Inertia::render('admin/usuarios/formulario', [
            'usuario' => [
                'id' => $usuario->id,
                'nome' => $usuario->name,
                'email' => $usuario->email,
                'papeis' => $usuario->papeis()->pluck('papeis.id'),
            ],
            'papeis' => Papel::query()->orderBy('id')->get(['id', 'slug', 'nome', 'descricao']),
            // Se a pessoa logada pode mexer no papel protegido (a tela mostra a caixa travada se não puder).
            'pode_papel_protegido' => $this->podeMexerNoProtegido($request),
            'papel_protegido' => self::PAPEL_PROTEGIDO,
        ]);
    }

    /**
     * Grava os papéis marcados, registra cada mudança e volta para a lista.
     *
     * @param  Request  $request  O formulário. Ex.: papeis[] = 2, papeis[] = 4
     * @param  User  $usuario  A pessoa que está recebendo os papéis.
     * @return RedirectResponse Volta para a lista com a mensagem de sucesso.
     */
    public function update(Request $request, User $usuario): RedirectResponse
    {
        $request->validate([
            'papeis' => ['nullable', 'array'],
            'papeis.*' => ['integer', 'distinct', 'exists:papeis,id'],
        ]);

        $protegido = Papel::where('slug', self::PAPEL_PROTEGIDO)->value('id');
        $antes = $usuario->papeis()->pluck('papeis.id')->all();
        $depois = array_map('intval', $request->input('papeis', []));

        // Sem permissão para o papel protegido: ele fica como estava, venha o que vier
        // (não dá nem para dar, nem para tirar).
        if (! $this->podeMexerNoProtegido($request)) {
            $depois = array_values(array_diff($depois, [$protegido]));
            if (in_array($protegido, $antes, true)) {
                $depois[] = $protegido;
            }
        }

        DB::transaction(function () use ($usuario, $antes, $depois) {
            $slugs = Papel::pluck('slug', 'id');

            // Auditoria só com números e códigos (LGPD): quem recebeu (id) e qual papel (slug).
            foreach (array_diff($depois, $antes) as $id) {
                Auditoria::registrar('PAPEL_CONCEDIDO', ['usuario_id' => $usuario->id, 'papel' => $slugs[$id]]);
            }
            foreach (array_diff($antes, $depois) as $id) {
                Auditoria::registrar('PAPEL_REMOVIDO', ['usuario_id' => $usuario->id, 'papel' => $slugs[$id]]);
            }

            $usuario->papeis()->sync($depois);
        });

        return to_route('admin.usuarios.index')
            ->with('sucesso', "Papéis de \"{$usuario->name}\" atualizados.");
    }

    /**
     * Diz se a pessoa logada pode dar ou tirar o papel protegido (Tecnologia).
     *
     * @return bool true só para quem tem a permissão de mexer nas permissões.
     */
    private function podeMexerNoProtegido(Request $request): bool
    {
        // A mesma permissão que abre a tela de permissões (só a Tecnologia tem).
        return (bool) $request->user()?->can('permissoes.gerenciar');
    }
}
