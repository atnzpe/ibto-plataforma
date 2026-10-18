<?php

/*
 * CHAMADA DO ENCONTRO (#44; desenho aprovado em 10/10/2026): para um encontro
 * (aula com data), a lista dos inscritos CONFIRMADOS, só pelo REGISTRO (LGPD),
 * com "presente" para marcar, e o campo do que foi trabalhado no encontro.
 * Pode ser refeita (corrige); cada versão vai para a auditoria.
 * Quem faz: Gate "curso.chamada" (Comando Geral, dono do curso, admin da equipe dona).
 */

namespace App\Http\Controllers\Cursos;

use App\Enums\SituacaoMatricula;
use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\CursoAula;
use App\Models\CursoMatricula;
use App\Models\CursoPresenca;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChamadaController extends Controller
{
    /**
     * A tela da chamada de um encontro.
     *
     * @param  Curso  $curso  Ex.: /comando-geral/cursos/7/aulas/31/chamada → curso 7.
     * @param  CursoAula  $aula  O encontro (aula 31).
     * @return Response A tela "cursos/chamada".
     */
    public function edit(Curso $curso, CursoAula $aula): Response
    {
        $this->conferir($curso, $aula);

        // Presenças já gravadas neste encontro: [matricula_id => true/false].
        $marcadas = CursoPresenca::where('aula_id', $aula->id)->pluck('presente', 'matricula_id');

        return Inertia::render('cursos/chamada', [
            'curso' => ['id' => $curso->id, 'titulo' => $curso->titulo],
            'aula' => [
                'id' => $aula->id,
                'titulo' => $aula->titulo,
                'data' => $aula->data?->toDateString(),
                'hora_inicio' => $aula->hora_inicio !== null ? substr($aula->hora_inicio, 0, 5) : null,
                'registro_encontro' => $aula->registro_encontro,
                'chamada_feita_em' => $aula->chamada_feita_em?->format('d/m/Y H:i'),
            ],
            // LGPD: só o registro (ou o nº da matrícula) e a equipe.
            'inscritos' => $this->confirmadas($curso)->map(fn (CursoMatricula $matricula) => [
                'matricula_id' => $matricula->id,
                'registro' => $matricula->usuario->filiado?->registro,
                // Foto de perfil (#78), só se quem está vendo pode ver a foto desta pessoa; senão null.
                'foto' => Gate::allows('usuario.foto', $matricula->usuario) ? $matricula->usuario->avatar : null,
                'equipe' => $matricula->usuario->filiado !== null ? ($matricula->usuario->filiado->equipe->nome ?? 'Sem equipe') : 'Não filiado',
                // Já marcado antes (refazer a chamada abre como estava). null = ainda não chamado.
                'presente' => $marcadas->has($matricula->id) ? (bool) $marcadas[$matricula->id] : null,
            ])->values(),
        ]);
    }

    /**
     * Grava a chamada: presente para os marcados, falta para os demais.
     *
     * @param  Request  $request  Ex.: presentes[] = 12, presentes[] = 15; registro_encontro = 'Guarda e base'
     * @param  Curso  $curso  O curso.
     * @param  CursoAula  $aula  O encontro.
     * @return RedirectResponse Volta para os detalhes do curso.
     */
    public function update(Request $request, Curso $curso, CursoAula $aula): RedirectResponse
    {
        $this->conferir($curso, $aula);

        $dados = $request->validate([
            'presentes' => ['nullable', 'array'],
            'presentes.*' => ['integer'],
            // Obrigatório (18/10/2026): o que foi trabalhado vai para o dossiê de cada aluno.
            'registro_encontro' => ['required', 'string', 'min:3', 'max:2000'],
        ], [
            'registro_encontro.required' => 'Escreva o que foi trabalhado no encontro (vai para o dossiê dos alunos).',
            'registro_encontro.min' => 'Descreva o que foi trabalhado com pelo menos 3 letras.',
        ]);

        $confirmadas = $this->confirmadas($curso);
        // Só valem as matrículas confirmadas DESTE curso (um número de fora é ignorado).
        $presentes = array_values(array_intersect(array_map('intval', $dados['presentes'] ?? []), $confirmadas->modelKeys()));

        DB::transaction(function () use ($request, $aula, $confirmadas, $presentes, $dados): void {
            foreach ($confirmadas as $matricula) {
                // Uma linha por pessoa no encontro: cria ou corrige.
                CursoPresenca::query()->updateOrInsert(
                    ['aula_id' => $aula->id, 'matricula_id' => $matricula->id],
                    ['presente' => in_array($matricula->id, $presentes, true), 'updated_at' => now(), 'created_at' => now()],
                );
            }

            $aula->forceFill([
                'registro_encontro' => trim($dados['registro_encontro']),
                'chamada_feita_em' => now(),
                'chamada_por' => $request->user()?->id,
            ])->save();

            // Só números (LGPD): quais matrículas vieram.
            Auditoria::registrar('CHAMADA_REGISTRADA', [
                'aula_id' => $aula->id,
                'curso_id' => $aula->modulo->curso_id,
                'presentes' => $presentes,
                'total' => $confirmadas->count(),
            ]);
        });

        return to_route('comando-geral.cursos.inscritos', $curso)
            ->with('sucesso', 'Chamada de "'.$aula->titulo.'" registrada: '.count($presentes).' de '.$confirmadas->count().' presente(s).');
    }

    /**
     * Confere a permissão e se a aula é um ENCONTRO ativo deste curso (com data).
     *
     * @param  Curso  $curso  O curso do endereço.
     * @param  CursoAula  $aula  A aula do endereço.
     */
    private function conferir(Curso $curso, CursoAula $aula): void
    {
        Gate::authorize('curso.chamada', $curso);
        // Aula de outro curso, desativada ou sem data (não é encontro): 404.
        abort_if($aula->modulo->curso_id !== $curso->id || ! $aula->ativa || $aula->data === null, 404);
    }

    /**
     * As matrículas CONFIRMADAS do curso, com o registro e a equipe.
     *
     * @param  Curso  $curso  O curso.
     * @return Collection<int, CursoMatricula>
     */
    private function confirmadas(Curso $curso): Collection
    {
        return $curso->matriculas()
            ->where('situacao', SituacaoMatricula::Confirmada->value)
            ->with(['usuario.filiado:id,user_id,registro,equipe_id', 'usuario.filiado.equipe:id,nome'])
            ->orderBy('id')
            ->get();
    }
}
