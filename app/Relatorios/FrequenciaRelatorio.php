<?php

/*
 * RELATÓRIO: FREQUÊNCIA E CERTIFICADOS (#73). Por curso que começa no período:
 * encontros com chamada, confirmados, presenças, comparecimento e certificados
 * emitidos. O Comando Geral vê todos; o instrutor, só os cursos dele.
 */

namespace App\Relatorios;

use App\Enums\SituacaoMatricula;
use App\Models\Curso;
use App\Models\CursoAula;
use App\Models\CursoCertificado;
use App\Models\CursoMatricula;
use App\Models\CursoPresenca;
use App\Models\User;
use App\Support\Periodo;

class FrequenciaRelatorio extends Relatorio
{
    public function chave(): string
    {
        return 'frequencia';
    }

    public function titulo(): string
    {
        return 'Frequência e certificados';
    }

    public function descricao(): string
    {
        return 'Encontros com chamada, presenças, comparecimento e certificados emitidos por curso.';
    }

    public function permissao(): string
    {
        return 'relatorio.frequencia';
    }

    public function colunas(): array
    {
        return [
            ['chave' => 'curso', 'rotulo' => 'Curso', 'tipo' => 'texto'],
            ['chave' => 'inicio', 'rotulo' => 'Início', 'tipo' => 'texto'],
            ['chave' => 'encontros', 'rotulo' => 'Encontros com chamada', 'tipo' => 'numero'],
            ['chave' => 'confirmados', 'rotulo' => 'Confirmados', 'tipo' => 'numero'],
            ['chave' => 'presencas', 'rotulo' => 'Presenças', 'tipo' => 'numero'],
            ['chave' => 'comparecimento', 'rotulo' => 'Comparecimento', 'tipo' => 'percentual'],
            ['chave' => 'certificados', 'rotulo' => 'Certificados', 'tipo' => 'numero'],
        ];
    }

    public function linhas(Periodo $periodo, User $usuario): array
    {
        // Quem não cuida de todos os cursos (o instrutor): só os dele.
        $soDele = ! $usuario->temPermissao('cursos.gerenciar');

        return array_values(Curso::query()
            ->when($soDele, fn ($consulta) => $consulta->where('responsavel_id', $usuario->id))
            ->where(fn ($consulta) => $consulta->whereNull('data_inicio')->orWhereBetween('data_inicio', [$periodo->inicio->toDateString(), $periodo->fim->toDateString()]))
            ->orderByDesc('data_inicio')->get()
            ->map(function (Curso $curso): array {
                $encontros = CursoAula::query()->whereNotNull('chamada_feita_em')->whereHas('modulo', fn ($modulo) => $modulo->where('curso_id', $curso->id))->pluck('id');
                $confirmados = CursoMatricula::query()->where('curso_id', $curso->id)->where('situacao', SituacaoMatricula::Confirmada->value)->count();
                $presencas = CursoPresenca::query()->whereIn('aula_id', $encontros)->where('presente', true)->count();
                $base = $confirmados * $encontros->count();

                return [
                    'curso' => $curso->titulo,
                    'inicio' => $curso->data_inicio?->format('d/m/Y') ?? 'Sem data',
                    'encontros' => $encontros->count(),
                    'confirmados' => $confirmados,
                    'presencas' => $presencas,
                    // Percentual inteiro; sem chamada ainda: null (mostra "—").
                    'comparecimento' => $base > 0 ? (int) round($presencas * 100 / $base) : null,
                    'certificados' => CursoCertificado::query()->whereNull('cancelado_em')->whereHas('matricula', fn ($matricula) => $matricula->where('curso_id', $curso->id))->count(),
                ];
            })->all());
    }
}
