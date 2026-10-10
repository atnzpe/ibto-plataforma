<?php

/*
 * Cadastro de CATEGORIAS do financeiro (#59): lista, nova e edição. Não existe
 * "apagar": desativar tira a categoria das opções novas, e os lançamentos
 * antigos continuam com ela. Cada mudança vai para a auditoria.
 * Permissão "financeiro.lancar" (routes/web.php).
 */

namespace App\Http\Controllers\Financeiro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financeiro\CategoriaRequest;
use App\Models\Auditoria;
use App\Models\FinCategoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CategoriaController extends Controller
{
    /**
     * Lista: receitas primeiro, depois despesas, em ordem de nome, com quantos
     * lançamentos usam cada uma.
     *
     * @return Response A tela "financeiro/categorias/index".
     */
    public function index(): Response
    {
        return Inertia::render('financeiro/categorias/index', [
            'categorias' => FinCategoria::query()
                // withCount: o banco conta os lançamentos de cada uma (lancamentos_count).
                ->withCount('lancamentos')
                ->orderByDesc('tipo')->orderBy('nome')
                ->get()
                ->map(fn (FinCategoria $categoria) => [
                    'id' => $categoria->id,
                    'nome' => $categoria->nome,
                    'tipo' => $categoria->tipo,
                    'ativa' => $categoria->ativa,
                    'em_uso' => (int) $categoria->getAttribute('lancamentos_count'),
                ]),
        ]);
    }

    /**
     * Formulário vazio.
     *
     * @return Response A tela do formulário, sem categoria.
     */
    public function create(): Response
    {
        return Inertia::render('financeiro/categorias/formulario', ['categoria' => null]);
    }

    /**
     * Grava a categoria nova (com auditoria) e volta para a lista.
     *
     * @param  CategoriaRequest  $request  O formulário, já conferido.
     * @return RedirectResponse A lista, com a mensagem.
     */
    public function store(CategoriaRequest $request): RedirectResponse
    {
        $categoria = DB::transaction(function () use ($request): FinCategoria {
            $categoria = FinCategoria::create($request->safe()->only(['nome', 'tipo', 'ativa']));
            Auditoria::registrar('CATEGORIA_CRIADA', ['categoria_id' => $categoria->id, 'tipo' => $categoria->tipo]);

            return $categoria;
        });

        return to_route('financeiro.categorias.index')->with('sucesso', "Categoria \"{$categoria->nome}\" cadastrada.");
    }

    /**
     * Formulário preenchido.
     *
     * @param  FinCategoria  $categoria  A do endereço (ex.: /financeiro/categorias/3/edit).
     * @return Response A tela do formulário.
     */
    public function edit(FinCategoria $categoria): Response
    {
        return Inertia::render('financeiro/categorias/formulario', [
            'categoria' => [
                'id' => $categoria->id,
                'nome' => $categoria->nome,
                'tipo' => $categoria->tipo,
                'ativa' => $categoria->ativa,
                // Já usada? A tela avisa que o tipo não pode mudar.
                'em_uso' => $categoria->lancamentos()->exists(),
            ],
        ]);
    }

    /**
     * Grava as mudanças (inclusive ativar/desativar), com auditoria do antes e depois.
     *
     * @param  CategoriaRequest  $request  O formulário, já conferido (inclusive a troca de tipo).
     * @param  FinCategoria  $categoria  A que está sendo editada.
     * @return RedirectResponse A lista, com a mensagem.
     */
    public function update(CategoriaRequest $request, FinCategoria $categoria): RedirectResponse
    {
        DB::transaction(function () use ($request, $categoria): void {
            $antes = ['tipo' => $categoria->tipo, 'ativa' => $categoria->ativa];
            $categoria->update($request->safe()->only(['nome', 'tipo', 'ativa']));
            Auditoria::registrar('CATEGORIA_EDITADA', [
                'categoria_id' => $categoria->id,
                'antes' => $antes,
                'depois' => ['tipo' => $categoria->tipo, 'ativa' => $categoria->ativa],
            ]);
        });

        return to_route('financeiro.categorias.index')->with('sucesso', "Categoria \"{$categoria->nome}\" atualizada.");
    }
}
