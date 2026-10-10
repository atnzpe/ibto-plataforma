<?php

/*
 * ANUIDADE na ficha do filiado (#37 + #59, bloco 3): monta o quadro (situação
 * do ano e pagamentos) e registra um pagamento novo pelo App\Services\Anuidades
 * (pagamento + receita no livro-caixa, juntos).
 */

namespace App\Http\Controllers\Cadastros;

use App\Exceptions\RegraFinanceira;
use App\Http\Controllers\Controller;
use App\Http\Requests\Financeiro\AnuidadePagamentoRequest;
use App\Models\Anuidade;
use App\Models\AnuidadePagamento;
use App\Models\Filiado;
use App\Models\FinFormaPagamento;
use App\Models\User;
use App\Services\Anuidades;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class FiliadoAnuidadeController extends Controller
{
    /**
     * Registra o pagamento da anuidade e volta para a ficha.
     *
     * @param  AnuidadePagamentoRequest  $request  O formulário, já conferido. Ex.: ano 2027, PIX, 15/03/2027.
     * @param  Filiado  $filiado  O filiado do endereço (ex.: /cadastros/filiados/7/anuidade).
     * @param  Anuidades  $anuidades  O serviço da anuidade (o Laravel entrega pronto).
     * @return RedirectResponse De volta à ficha, com a mensagem ou o erro da regra (com código).
     */
    public function store(AnuidadePagamentoRequest $request, Filiado $filiado, Anuidades $anuidades): RedirectResponse
    {
        // Administrador de equipe: só filiados da própria equipe (senão, 403).
        Gate::authorize('filiados.editar', $filiado);

        try {
            $anuidades->registrarPagamento(
                $filiado,
                $request->integer('ano'),
                $request->string('pago_em')->toString(),
                $request->integer('forma_pagamento_id'),
            );
        } catch (RegraFinanceira $erro) {
            // Regra recusou (ex.: já paga): volta com a mensagem e o código, sem erro 500.
            return back()->withErrors(['anuidade' => "{$erro->getMessage()} ({$erro->codigo})"]);
        }

        return back()->with('sucesso', "Anuidade {$request->integer('ano')} registrada e lançada no financeiro.");
    }

    /**
     * O quadro "Anuidade" da ficha, ou null se a pessoa não pode ver o financeiro.
     *
     * @param  Filiado  $filiado  O filiado da ficha.
     * @param  User|null  $usuario  Quem está olhando a ficha.
     * @return array<string, mixed>|null Situação do ano, pagamentos e opções do formulário.
     */
    public static function quadro(Filiado $filiado, ?User $usuario): ?array
    {
        // Sem "financeiro.ver": o quadro nem aparece.
        if ($usuario === null || ! $usuario->can('financeiro.ver')) {
            return null;
        }

        // O ano de hoje e o valor dele (null se ninguém cadastrou).
        $ano = today()->year;
        $doAno = Anuidade::where('ano', $ano)->first();

        // Situação do ano: isenta (ou sem valor) / em dia / vencida.
        $situacao = match (true) {
            $doAno === null || $doAno->isenta => 'isenta',
            $filiado->anuidadeEmDia() => 'em_dia',
            default => 'vencida',
        };

        return [
            'ano' => $ano,
            'situacao' => $situacao,
            'valor_centavos' => $doAno?->valor_centavos,
            // Pagamentos do mais novo ao mais antigo, com a forma e se foi estornado.
            'pagamentos' => $filiado->pagamentosAnuidade()
                ->with('lancamento.formaPagamento')
                // "estornado": o banco diz se a receita ligada tem estorno.
                ->withExists(['lancamento as estornado' => fn ($consulta) => $consulta->has('estorno')])
                ->orderByDesc('ano')->orderByDesc('id')
                ->get()
                ->map(fn (AnuidadePagamento $pagamento) => [
                    'id' => $pagamento->id,
                    'ano' => $pagamento->ano,
                    'valor_centavos' => $pagamento->valor_centavos,
                    'pago_em' => $pagamento->pago_em->toDateString(),
                    'forma' => $pagamento->lancamento?->formaPagamento?->nome,
                    'lancamento_id' => $pagamento->lancamento_id,
                    'estornado' => (bool) $pagamento->getAttribute('estornado'),
                ]),
            // Formulário: só para quem pode lançar no financeiro.
            'pode_registrar' => $usuario->can('financeiro.lancar'),
            // Anos que podem ser pagos (com valor e não isentos).
            'anos' => Anuidade::where('isenta', false)->orderBy('ano')->pluck('ano'),
            'formas' => FinFormaPagamento::where('ativa', true)->orderBy('nome')->get(['id', 'nome']),
        ];
    }
}
