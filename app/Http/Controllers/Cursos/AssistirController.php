<?php

/*
 * SALA DE AULA (#39, bloco C4c): assistir as aulas de um curso EM SEQUÊNCIA,
 * com "Anterior" / "Próxima" e a posição ("3 / 10"), como a videoteca do
 * Dojo Manager.
 *
 * Quem entra: quem tem acesso ao conteúdo (Gate "curso.conteudo": o Comando
 * Geral, quem chancela e quem propôs o curso; os INSCRITOS entram no bloco C5).
 * Sem acesso: 403, mesmo com o link.
 *
 * Regra do nível (do Dojo Manager): módulo com "nível mínimo" só abre para o
 * filiado daquele nível ou de um abaixo (CursoModulo::liberadoPara). A equipe
 * do curso vê tudo, para conferir.
 */

namespace App\Http\Controllers\Cursos;

use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Models\CursoAula;
use App\Models\CursoModulo;
use App\Models\Nivel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AssistirController extends Controller
{
    /**
     * A tela de uma aula, com a lista de todas e os botões de sequência.
     *
     * @param  Request  $request  Quem está assistindo.
     * @param  Curso  $curso  O curso do endereço. Ex.: /cursos/7/assistir/31 → curso 7.
     * @param  CursoAula  $aula  A aula do endereço. Ex.: aula 31.
     * @return Response A tela "cursos/assistir".
     */
    public function show(Request $request, Curso $curso, CursoAula $aula): Response
    {
        // Sem acesso ao conteúdo do curso: 403.
        Gate::authorize('curso.conteudo', $curso);

        /** @var User $usuario */
        $usuario = $request->user();
        $sequencia = $this->sequencia($curso, $usuario);

        // A posição da aula pedida na sequência. Não está nela (de outro curso,
        // desativada ou de módulo bloqueado pelo nível): 404, como se não existisse.
        $posicao = $sequencia->search(fn (CursoAula $daLista) => $daLista->id === $aula->id);
        abort_if($posicao === false, 404);

        // A anterior e a próxima (null na primeira e na última).
        $anterior = $sequencia->get($posicao - 1);
        $proxima = $sequencia->get($posicao + 1);

        return Inertia::render('cursos/assistir', [
            'curso' => ['id' => $curso->id, 'titulo' => $curso->titulo],
            'aula' => [
                'id' => $aula->id,
                'titulo' => $aula->titulo,
                'modulo' => $aula->modulo->titulo,
                'conteudo' => $aula->conteudo,
                'duracao_minutos' => $aula->duracao_minutos,
                // Como tocar o vídeo: YouTube, arquivo por link ou enviado (rota protegida).
                'video' => $aula->video(),
                // Link da apostila (rota protegida pelo mesmo Gate), ou null.
                'apostila_url' => $aula->apostila !== null ? route('cursos.aulas.apostila', $aula->id) : null,
                // Encontro presencial (cronograma): dia, horário e quem aplica.
                'data' => $aula->data?->toDateString(),
                'hora_inicio' => $aula->hora_inicio !== null ? substr($aula->hora_inicio, 0, 5) : null,
                'hora_fim' => $aula->hora_fim !== null ? substr($aula->hora_fim, 0, 5) : null,
                'quem_aplica' => $aula->quem_aplica,
            ],
            // "3 / 10": a posição começa em 1 para as pessoas.
            'posicao' => $posicao + 1,
            'total' => $sequencia->count(),
            'anterior_id' => $anterior?->id,
            'proxima_id' => $proxima?->id,
            // A lista lateral: só id, título e módulo de cada aula.
            'aulas' => $sequencia->map(fn (CursoAula $daLista) => [
                'id' => $daLista->id,
                'titulo' => $daLista->titulo,
                'modulo' => $daLista->modulo->titulo,
            ])->values(),
        ]);
    }

    /**
     * Todas as aulas que a pessoa pode ver, na ordem: módulo por módulo (pela
     * ordem do módulo), e dentro dele pela ordem da aula. Ficam de fora as
     * aulas e os módulos desativados e, para quem não é da equipe do curso,
     * os módulos acima do nível da pessoa.
     *
     * @param  Curso  $curso  O curso.
     * @param  User  $usuario  Quem está assistindo.
     * @return Collection<int, CursoAula> As aulas em sequência (0, 1, 2...).
     */
    private function sequencia(Curso $curso, User $usuario): Collection
    {
        // Equipe do curso (Comando Geral, quem chancela ou quem propôs): vê tudo.
        $daEquipe = $usuario->temPermissao('cursos.gerenciar')
            || $usuario->temPermissao('cursos.chancelar')
            || $curso->responsavel_id === $usuario->id;

        // Nível do filiado (null = sem nível) e a hierarquia dos níveis.
        $nivelDoAluno = $usuario->filiado?->nivel_id;
        $posicoes = $daEquipe ? [] : Nivel::posicoes();

        return $curso->modulos()
            ->where('ativo', true)
            ->with(['aulas' => fn ($aulas) => $aulas->where('ativa', true)])
            ->get()
            // Módulo bloqueado pelo nível: sai inteiro (a equipe não passa por este filtro).
            ->filter(fn (CursoModulo $modulo) => $daEquipe || $modulo->liberadoPara($nivelDoAluno, $posicoes))
            // Junta as aulas de todos os módulos numa lista só, já na ordem.
            ->flatMap(fn (CursoModulo $modulo) => $modulo->aulas->each(fn (CursoAula $aula) => $aula->setRelation('modulo', $modulo)))
            ->values();
    }
}
