<?php

/*
 * RELATÓRIO: FILIADOS POR EQUIPE (#73). Ativos, inativos, novos no período e a
 * anuidade do ano (em dia ou pendente). O Comando Geral vê todas as equipes; o
 * admin de equipe, só a dele. Só contagens (nenhum dado pessoal).
 */

namespace App\Relatorios;

use App\Models\Anuidade;
use App\Models\Equipe;
use App\Models\Filiado;
use App\Models\User;
use App\Support\Periodo;
use Illuminate\Support\Collection;

class FiliadosRelatorio extends Relatorio
{
    public function chave(): string
    {
        return 'filiados';
    }

    public function titulo(): string
    {
        return 'Filiados por equipe';
    }

    public function descricao(): string
    {
        return 'Ativos, inativos, novos no período e a anuidade do ano de cada equipe.';
    }

    public function permissao(): string
    {
        return 'relatorio.filiados';
    }

    public function colunas(): array
    {
        return [
            ['chave' => 'equipe', 'rotulo' => 'Equipe', 'tipo' => 'texto'],
            ['chave' => 'ativos', 'rotulo' => 'Ativos', 'tipo' => 'numero'],
            ['chave' => 'inativos', 'rotulo' => 'Inativos', 'tipo' => 'numero'],
            ['chave' => 'novos', 'rotulo' => 'Novos no período', 'tipo' => 'numero'],
            ['chave' => 'anuidade_em_dia', 'rotulo' => 'Anuidade em dia', 'tipo' => 'numero'],
            ['chave' => 'anuidade_pendente', 'rotulo' => 'Anuidade pendente', 'tipo' => 'numero'],
        ];
    }

    public function linhas(Periodo $periodo, User $usuario): array
    {
        // Admin de equipe (sem cuidar de todos): só a própria equipe.
        $soEquipe = ! $usuario->temPermissao('filiados.gerenciar') && $usuario->temPermissao('filiados.equipe')
            ? ($usuario->filiado->equipe_id ?? 0)
            : null;
        // Anuidade do ano do fim do período; ano isento (ou sem valor): ninguém fica pendente.
        $doAno = Anuidade::where('ano', $periodo->fim->year)->first();
        $cobra = $doAno !== null && ! $doAno->isenta;

        $filiados = Filiado::query()->when($soEquipe !== null, fn ($consulta) => $consulta->where('equipe_id', $soEquipe))->get();
        $nomes = Equipe::query()->pluck('nome', 'id');

        return array_values($filiados->groupBy(fn (Filiado $filiado) => $filiado->equipe_id ?? 0)
            ->map(function (Collection $daEquipe, int $equipeId) use ($nomes, $periodo, $cobra): array {
                $ativos = $daEquipe->where('status', 'ativo');
                // Em dia: só conta entre os ativos (inativo não paga anuidade).
                $emDia = $cobra ? $ativos->filter(fn (Filiado $filiado) => $filiado->anuidadeEmDia())->count() : $ativos->count();

                return [
                    'equipe' => $nomes[$equipeId] ?? 'Sem equipe',
                    'ativos' => $ativos->count(),
                    'inativos' => $daEquipe->where('status', '!=', 'ativo')->count(),
                    'novos' => $daEquipe->filter(fn (Filiado $filiado) => $filiado->created_at !== null && $filiado->created_at->between($periodo->inicio, $periodo->fim))->count(),
                    'anuidade_em_dia' => $emDia,
                    'anuidade_pendente' => $ativos->count() - $emDia,
                ];
            })
            ->sortBy('equipe')->all());
    }
}
