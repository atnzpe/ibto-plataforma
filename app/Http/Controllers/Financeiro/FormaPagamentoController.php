<?php

/*
 * Cadastro de FORMAS DE PAGAMENTO do financeiro (#59): lista, nova e edição.
 * Não existe "apagar": desativar tira a forma das opções da baixa, e os
 * lançamentos antigos continuam com ela. Cada mudança vai para a auditoria.
 * Permissão "financeiro.lancar" (routes/web.php).
 */

namespace App\Http\Controllers\Financeiro;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financeiro\FormaPagamentoRequest;
use App\Models\Auditoria;
use App\Models\FinFormaPagamento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FormaPagamentoController extends Controller
{
    /**
     * Lista em ordem de nome.
     *
     * @return Response A tela "financeiro/formas/index".
     */
    public function index(): Response
    {
        return Inertia::render('financeiro/formas/index', [
            'formas' => FinFormaPagamento::orderBy('nome')->get(['id', 'nome', 'ativa']),
        ]);
    }

    /**
     * Formulário vazio.
     *
     * @return Response A tela do formulário, sem forma.
     */
    public function create(): Response
    {
        return Inertia::render('financeiro/formas/formulario', ['forma' => null]);
    }

    /**
     * Grava a forma nova (com auditoria) e volta para a lista.
     *
     * @param  FormaPagamentoRequest  $request  O formulário, já conferido.
     * @return RedirectResponse A lista, com a mensagem.
     */
    public function store(FormaPagamentoRequest $request): RedirectResponse
    {
        $forma = DB::transaction(function () use ($request): FinFormaPagamento {
            $forma = FinFormaPagamento::create($request->safe()->only(['nome', 'ativa']));
            Auditoria::registrar('FORMA_PAGAMENTO_CRIADA', ['forma_pagamento_id' => $forma->id]);

            return $forma;
        });

        return to_route('financeiro.formas.index')->with('sucesso', "Forma \"{$forma->nome}\" cadastrada.");
    }

    /**
     * Formulário preenchido.
     *
     * @param  FinFormaPagamento  $forma  A do endereço (ex.: /financeiro/formas-pagamento/2/edit).
     * @return Response A tela do formulário.
     */
    public function edit(FinFormaPagamento $forma): Response
    {
        return Inertia::render('financeiro/formas/formulario', [
            'forma' => ['id' => $forma->id, 'nome' => $forma->nome, 'ativa' => $forma->ativa],
        ]);
    }

    /**
     * Grava as mudanças (inclusive ativar/desativar), com auditoria.
     *
     * @param  FormaPagamentoRequest  $request  O formulário, já conferido.
     * @param  FinFormaPagamento  $forma  A que está sendo editada.
     * @return RedirectResponse A lista, com a mensagem.
     */
    public function update(FormaPagamentoRequest $request, FinFormaPagamento $forma): RedirectResponse
    {
        DB::transaction(function () use ($request, $forma): void {
            $antes = $forma->ativa;
            $forma->update($request->safe()->only(['nome', 'ativa']));
            Auditoria::registrar('FORMA_PAGAMENTO_EDITADA', ['forma_pagamento_id' => $forma->id, 'ativa_antes' => $antes, 'ativa_depois' => $forma->ativa]);
        });

        return to_route('financeiro.formas.index')->with('sucesso', "Forma \"{$forma->nome}\" atualizada.");
    }
}
