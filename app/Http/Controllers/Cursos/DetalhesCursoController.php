<?php

/*
 * DETALHES DO CURSO no Comando Geral (tela 3 do desenho aprovado pelo
 * Gleyson em 10/10/2026): o resumo vagas → pagamento → presença →
 * certificado e a lista dos INSCRITOS com o próximo passo de cada um.
 *
 * LGPD (decisão de 10/10/2026): a lista mostra só o REGISTRO (ou o nº da
 * matrícula) e a equipe; o nome fica na ficha da matrícula.
 * Pagamento e vaga são UM passo só (confirmar o pagamento já confirma a vaga).
 * Presença e certificado entram com a #44 (aparecem como "em breve").
 * Quem vê: Gate "curso.inscritos" (Comando Geral, quem chancela, quem
 * confirma pagamentos e o dono do curso).
 */

namespace App\Http\Controllers\Cursos;

use App\Enums\SituacaoMatricula;
use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Models\CursoMatricula;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DetalhesCursoController extends Controller
{
    /**
     * A tela de detalhes de um curso.
     *
     * @param  Request  $request  Quem está vendo.
     * @param  Curso  $curso  O do endereço. Ex.: /comando-geral/cursos/7/inscritos
     * @return Response A tela "cursos/detalhes".
     */
    public function __invoke(Request $request, Curso $curso): Response
    {
        Gate::authorize('curso.inscritos', $curso);

        // Todas as matrículas do curso (canceladas também: o histórico aparece), as mais novas primeiro.
        $matriculas = $curso->matriculas()
            ->with(['usuario.filiado:id,user_id,registro,equipe_id', 'usuario.filiado.equipe:id,nome'])
            ->latest('id')
            ->get();

        // Quantas em cada situação. Ex.: ['aguardando_pagamento' => 2, 'confirmada' => 12].
        $porSituacao = $matriculas->countBy(fn (CursoMatricula $matricula) => $matricula->situacao->value);
        $ocupadas = ($porSituacao[SituacaoMatricula::AguardandoPagamento->value] ?? 0) + ($porSituacao[SituacaoMatricula::Confirmada->value] ?? 0);

        // Só quem confirma pagamentos abre a ficha (nome e e-mail).
        $podeConfirmar = (bool) $request->user()?->can('pagamentos.confirmar');

        return Inertia::render('cursos/detalhes', [
            'curso' => [
                'id' => $curso->id,
                'titulo' => $curso->titulo,
                'modalidade' => $curso->modalidade->rotulo(),
                'data_inicio' => $curso->data_inicio?->toDateString(),
                'local' => $curso->local,
                'situacao_rotulo' => $curso->situacao->rotulo(),
                'pode_editar' => Gate::allows('curso.editar', $curso),
            ],
            'resumo' => [
                'vagas' => $curso->vagas,
                'ocupadas' => $ocupadas,
                'aguardando' => $porSituacao[SituacaoMatricula::AguardandoPagamento->value] ?? 0,
                'confirmadas' => $porSituacao[SituacaoMatricula::Confirmada->value] ?? 0,
                'canceladas' => $porSituacao[SituacaoMatricula::Cancelada->value] ?? 0,
            ],
            'inscritos' => $matriculas->map(fn (CursoMatricula $matricula) => [
                'id' => $matricula->id,
                // LGPD: registro do filiado; não filiado = só o nº da matrícula (o nome fica na ficha).
                'registro' => $matricula->usuario->filiado?->registro,
                'equipe' => $matricula->usuario->filiado !== null ? ($matricula->usuario->filiado->equipe->nome ?? 'Sem equipe') : 'Não filiado',
                'situacao' => $matricula->situacao->value,
                'situacao_rotulo' => $matricula->situacao->rotulo(),
                'tem_comprovante' => $matricula->comprovante !== null,
            ])->values(),
            'pode_confirmar' => $podeConfirmar,
        ]);
    }
}
