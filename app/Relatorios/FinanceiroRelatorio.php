<?php

/*
 * RELATÓRIO: FINANCEIRO POR CATEGORIA (#73). No período (pela data de
 * vencimento): por categoria, o que já foi pago/recebido, o que está em aberto
 * (a receber ou a pagar) e o total. Cancelados não entram. Valores em centavos.
 */

namespace App\Relatorios;

use App\Models\FinCategoria;
use App\Models\FinLancamento;
use App\Models\User;
use App\Support\Periodo;

class FinanceiroRelatorio extends Relatorio
{
    public function chave(): string
    {
        return 'financeiro';
    }

    public function titulo(): string
    {
        return 'Financeiro por categoria';
    }

    public function descricao(): string
    {
        return 'Receitas e despesas de cada categoria no período: pago, em aberto e total.';
    }

    public function permissao(): string
    {
        return 'relatorio.financeiro';
    }

    public function colunas(): array
    {
        return [
            ['chave' => 'categoria', 'rotulo' => 'Categoria', 'tipo' => 'texto'],
            ['chave' => 'tipo', 'rotulo' => 'Tipo', 'tipo' => 'texto'],
            ['chave' => 'pago', 'rotulo' => 'Pago / recebido', 'tipo' => 'dinheiro'],
            ['chave' => 'aberto', 'rotulo' => 'Em aberto', 'tipo' => 'dinheiro'],
            ['chave' => 'total', 'rotulo' => 'Total', 'tipo' => 'dinheiro'],
        ];
    }

    public function linhas(Periodo $periodo, User $usuario): array
    {
        // Somas por categoria e situação numa consulta só (MySQL 5.7: só GROUP BY simples).
        $somas = FinLancamento::query()
            ->whereBetween('vencimento', [$periodo->inicio->toDateString(), $periodo->fim->toDateString()])
            ->where('situacao', '!=', 'cancelado')
            ->selectRaw('categoria_id, situacao, sum(valor_centavos) as total')
            ->groupBy('categoria_id', 'situacao')
            ->get();

        return array_values(FinCategoria::query()->orderBy('tipo')->orderBy('nome')->get()
            ->map(function (FinCategoria $categoria) use ($somas): array {
                $daCategoria = $somas->where('categoria_id', $categoria->id);
                $pago = (int) $daCategoria->where('situacao', 'pago')->sum('total');
                $aberto = (int) $daCategoria->where('situacao', 'pendente')->sum('total');

                return [
                    'categoria' => $categoria->nome,
                    'tipo' => $categoria->tipo === 'receita' ? 'Receita' : 'Despesa',
                    'pago' => $pago,
                    'aberto' => $aberto,
                    'total' => $pago + $aberto,
                ];
            })
            // Só as categorias com movimento no período.
            ->filter(fn (array $linha) => $linha['total'] > 0)->all());
    }
}
