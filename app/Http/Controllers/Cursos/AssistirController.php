<?php

/*
 * SALA DE AULA (#39, bloco C4c): assistir as aulas de um curso EM SEQUÊNCIA,
 * com "Anterior" / "Próxima" e a posição ("3 / 10"), como a videoteca do
 * Dojo Manager.
 *
 * Quem entra: quem tem acesso ao conteúdo (Gate "curso.conteudo": o Comando
 * Geral, quem chancela, quem propôs o curso e os inscritos confirmados).
 * Sem acesso: 403, mesmo com o link.
 *
 * As regras (a sequência, o nível e o PROGRESSO "3 de 10 aulas") ficam em
 * App\Services\SalaDeAula, porque "Meus cursos" usa as mesmas.
 * Progresso (14/10/2026): o botão "Marcar como assistida" de cada aula.
 */

namespace App\Http\Controllers\Cursos;

use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Models\CursoAula;
use App\Models\User;
use App\Services\SalaDeAula;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AssistirController extends Controller
{
    /**
     * A tela de uma aula, com a lista de todas, o progresso e os botões de sequência.
     *
     * @param  Request  $request  Quem está assistindo.
     * @param  Curso  $curso  O curso do endereço. Ex.: /cursos/7/assistir/31 → curso 7.
     * @param  CursoAula  $aula  A aula do endereço. Ex.: aula 31.
     * @param  SalaDeAula  $sala  As regras da sala de aula (o Laravel entrega pronto).
     * @return Response A tela "cursos/assistir".
     */
    public function show(Request $request, Curso $curso, CursoAula $aula, SalaDeAula $sala): Response
    {
        // Sem acesso ao conteúdo do curso: 403.
        Gate::authorize('curso.conteudo', $curso);

        /** @var User $usuario */
        $usuario = $request->user();
        $sequencia = $sala->sequencia($curso, $usuario);
        // As aulas que a pessoa já marcou como assistidas. Ex.: [31, 32].
        $assistidas = $sala->assistidas($sequencia, $usuario);

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
                // Esta aula já foi marcada como assistida? (o botão muda de texto)
                'assistida' => in_array($aula->id, $assistidas, true),
            ],
            // O progresso: quantas assistidas de quantas. Ex.: 3 de 10 (30%).
            'progresso' => [
                'assistidas' => count($assistidas),
                'total' => $sequencia->count(),
                // Sem aulas, 0% (não divide por zero). intdiv = sem casas decimais.
                'percentual' => $sequencia->count() > 0 ? intdiv(count($assistidas) * 100, $sequencia->count()) : 0,
            ],
            // "3 / 10": a posição começa em 1 para as pessoas.
            'posicao' => $posicao + 1,
            'total' => $sequencia->count(),
            'anterior_id' => $anterior?->id,
            'proxima_id' => $proxima?->id,
            // A lista lateral: id, título, módulo e o ✓ de cada aula.
            'aulas' => $sequencia->map(fn (CursoAula $daLista) => [
                'id' => $daLista->id,
                'titulo' => $daLista->titulo,
                'modulo' => $daLista->modulo->titulo,
                'assistida' => in_array($daLista->id, $assistidas, true),
            ])->values(),
        ]);
    }

    /**
     * Marca ou desmarca a aula como assistida (botão "Marcar como assistida").
     *
     * @param  Request  $request  Com "assistida": true (marcar) ou false (desmarcar).
     * @param  Curso  $curso  O curso do endereço. Ex.: /cursos/7/assistir/31/assistida → curso 7.
     * @param  CursoAula  $aula  A aula do endereço. Ex.: aula 31.
     * @param  SalaDeAula  $sala  As regras da sala de aula.
     * @return RedirectResponse Volta para a mesma aula.
     */
    public function marcar(Request $request, Curso $curso, CursoAula $aula, SalaDeAula $sala): RedirectResponse
    {
        // Mesma porta da tela: sem acesso ao conteúdo, 403.
        Gate::authorize('curso.conteudo', $curso);

        /** @var User $usuario */
        $usuario = $request->user();

        // Só aula que a pessoa pode ver (deste curso, ativa, nível liberado).
        // Se não for, 404: não dá para marcar aula de outro curso pelo endereço.
        abort_unless($sala->sequencia($curso, $usuario)->contains('id', $aula->id), 404);

        // "assistida" vem como true/false (ou 1/0); sem o campo, conta como marcar.
        $sala->marcar($aula, $usuario, $request->boolean('assistida', true));

        return back();
    }
}
