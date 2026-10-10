<?php

/*
 * TRILHAS no Comando Geral (#39, bloco C1): lista, nova e edição, com os
 * cursos em ordem. Não existe "apagar": desativa. Auditoria em cada mudança.
 * Permissão "cursos.gerenciar" (routes/web.php).
 */

namespace App\Http\Controllers\Cursos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cursos\TrilhaRequest;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\Trilha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TrilhaController extends Controller
{
    /**
     * Lista com a quantidade de cursos de cada trilha.
     *
     * @return Response A tela "cursos/trilhas/index".
     */
    public function index(): Response
    {
        return Inertia::render('cursos/trilhas/index', [
            'trilhas' => Trilha::query()->withCount('cursos')->orderBy('nome')->get()
                ->map(fn (Trilha $trilha) => [
                    'id' => $trilha->id,
                    'nome' => $trilha->nome,
                    'ativa' => $trilha->ativa,
                    'cursos' => (int) $trilha->getAttribute('cursos_count'),
                ]),
        ]);
    }

    /**
     * Formulário vazio.
     */
    public function create(): Response
    {
        return Inertia::render('cursos/trilhas/formulario', ['trilha' => null, 'cursos' => $this->cursos()]);
    }

    /**
     * Grava a trilha nova com os cursos em ordem.
     *
     * @param  TrilhaRequest  $request  O formulário, já conferido.
     * @return RedirectResponse A lista, com a mensagem.
     */
    public function store(TrilhaRequest $request): RedirectResponse
    {
        $trilha = DB::transaction(function () use ($request): Trilha {
            $trilha = Trilha::create($request->safe()->only(['nome', 'descricao', 'ativa']));
            // sync: grava exatamente estes cursos, cada um com a sua posição.
            $trilha->cursos()->sync($request->cursosEmOrdem());
            Auditoria::registrar('TRILHA_CRIADA', ['trilha_id' => $trilha->id, 'cursos' => array_keys($request->cursosEmOrdem())]);

            return $trilha;
        });

        return to_route('comando-geral.trilhas.index')->with('sucesso', "Trilha \"{$trilha->nome}\" cadastrada.");
    }

    /**
     * Formulário preenchido, com os cursos já na ordem.
     *
     * @param  Trilha  $trilha  A do endereço.
     */
    public function edit(Trilha $trilha): Response
    {
        return Inertia::render('cursos/trilhas/formulario', [
            'trilha' => [
                'id' => $trilha->id,
                'nome' => $trilha->nome,
                'descricao' => $trilha->descricao,
                'ativa' => $trilha->ativa,
                // Ids na ordem das etapas. Ex.: [7, 3, 12].
                'cursos' => $trilha->cursos()->pluck('cursos.id')->all(),
            ],
            'cursos' => $this->cursos(),
        ]);
    }

    /**
     * Grava as mudanças (inclusive a nova ordem dos cursos), com auditoria.
     *
     * @param  TrilhaRequest  $request  O formulário, já conferido.
     * @param  Trilha  $trilha  A que está sendo editada.
     */
    public function update(TrilhaRequest $request, Trilha $trilha): RedirectResponse
    {
        DB::transaction(function () use ($request, $trilha): void {
            $trilha->update($request->safe()->only(['nome', 'descricao', 'ativa']));
            $trilha->cursos()->sync($request->cursosEmOrdem());
            Auditoria::registrar('TRILHA_EDITADA', ['trilha_id' => $trilha->id, 'cursos' => array_keys($request->cursosEmOrdem())]);
        });

        return to_route('comando-geral.trilhas.index')->with('sucesso', "Trilha \"{$trilha->nome}\" atualizada.");
    }

    /**
     * Todos os cursos que podem entrar numa trilha (menos os encerrados).
     *
     * @return list<array{id: int, titulo: string, situacao: string}>
     */
    private function cursos(): array
    {
        return array_values(Curso::query()->where('situacao', '!=', 'encerrado')->orderBy('titulo')->get()
            ->map(fn (Curso $curso) => ['id' => $curso->id, 'titulo' => $curso->titulo, 'situacao' => $curso->situacao->rotulo()])
            ->all());
    }
}
