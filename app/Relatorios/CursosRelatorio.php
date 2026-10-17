<?php

/*
 * RELATÓRIO: CURSOS (#73). Por curso que começa no período (gravado, sem data,
 * aparece sempre): vagas, inscritos, confirmados, receita confirmada, repasse
 * ao instrutor e a parte do IBTO. Valores em centavos, conferidos com o
 * livro-caixa (a receita é o valor das matrículas confirmadas; o repasse é a
 * conta a pagar "Instrutores").
 */

namespace App\Relatorios;

use App\Enums\SituacaoMatricula;
use App\Models\Curso;
use App\Models\CursoMatricula;
use App\Models\FinLancamento;
use App\Models\User;
use App\Support\Periodo;

class CursosRelatorio extends Relatorio
{
    public function chave(): string
    {
        return 'cursos';
    }

    public function titulo(): string
    {
        return 'Cursos: inscritos, receita e repasse';
    }

    public function descricao(): string
    {
        return 'Vagas, inscritos, confirmados, receita, repasse ao instrutor e a parte do IBTO de cada curso.';
    }

    public function permissao(): string
    {
        return 'relatorio.cursos';
    }

    public function colunas(): array
    {
        return [
            ['chave' => 'curso', 'rotulo' => 'Curso', 'tipo' => 'texto'],
            ['chave' => 'inicio', 'rotulo' => 'Início', 'tipo' => 'texto'],
            ['chave' => 'instrutor', 'rotulo' => 'Instrutor', 'tipo' => 'texto'],
            ['chave' => 'vagas', 'rotulo' => 'Vagas', 'tipo' => 'numero'],
            ['chave' => 'inscritos', 'rotulo' => 'Inscritos', 'tipo' => 'numero'],
            ['chave' => 'confirmados', 'rotulo' => 'Confirmados', 'tipo' => 'numero'],
            ['chave' => 'receita', 'rotulo' => 'Receita', 'tipo' => 'dinheiro'],
            ['chave' => 'repasse', 'rotulo' => 'Repasse ao instrutor', 'tipo' => 'dinheiro'],
            ['chave' => 'parte_ibto', 'rotulo' => 'Parte do IBTO', 'tipo' => 'dinheiro'],
        ];
    }

    public function linhas(Periodo $periodo, User $usuario): array
    {
        return array_values(Curso::query()
            ->where(fn ($consulta) => $consulta->whereNull('data_inicio')->orWhereBetween('data_inicio', [$periodo->inicio->toDateString(), $periodo->fim->toDateString()]))
            ->orderByDesc('data_inicio')->get()
            ->map(function (Curso $curso): array {
                $matriculas = CursoMatricula::query()->where('curso_id', $curso->id);
                $confirmadas = (clone $matriculas)->where('situacao', SituacaoMatricula::Confirmada->value);
                $receita = (int) (clone $confirmadas)->sum('valor_centavos');
                $repasse = (int) FinLancamento::query()->where('origem', 'repasse')->whereIn('origem_id', (clone $matriculas)->pluck('id'))->where('situacao', '!=', 'cancelado')->sum('valor_centavos');

                return [
                    'curso' => $curso->titulo,
                    'inicio' => $curso->data_inicio?->format('d/m/Y') ?? 'Sem data',
                    'instrutor' => $curso->instrutorExibido() ?? '—',
                    'vagas' => (int) ($curso->vagas ?? 0),
                    'inscritos' => (clone $matriculas)->where('situacao', '!=', SituacaoMatricula::Cancelada->value)->count(),
                    'confirmados' => (clone $confirmadas)->count(),
                    'receita' => $receita,
                    'repasse' => $repasse,
                    'parte_ibto' => $receita - $repasse,
                ];
            })->all());
    }
}
