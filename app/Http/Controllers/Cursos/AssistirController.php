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

use App\Actions\Cursos\Certificados;
use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Models\CursoAula;
use App\Models\User;
use App\Services\SalaDeAula;
use App\Support\SalaAoVivo;
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
                // Sala do encontro online ao vivo (#71), ou null sem sala. Ver salaAoVivo().
                'sala_ao_vivo' => $this->salaAoVivo($aula, $curso, $usuario),
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
     * A sala ao vivo do encontro (#71), do jeito que a tela mostra.
     *
     * O LINK só vai para a tela quando a sala está aberta (30 minutos antes do
     * início até o fim). Quem organiza o curso (pode editá-lo) recebe o link
     * sempre, porque é quem abre a reunião. Assim o link não circula antes da
     * hora nem fica guardado na tela de quem só vai assistir.
     *
     * @param  CursoAula  $aula  O encontro.
     * @param  Curso  $curso  O curso dele.
     * @param  User  $usuario  Quem está na tela.
     * @return array{situacao: string, abre_as: string|null, link: string|null}|null null = encontro sem sala ao vivo.
     */
    private function salaAoVivo(CursoAula $aula, Curso $curso, User $usuario): ?array
    {
        // Sem link cadastrado: não é encontro ao vivo.
        if ($aula->link_ao_vivo === null || $aula->link_ao_vivo === '') {
            return null;
        }

        $situacao = SalaAoVivo::situacao($aula);
        // Organizador (pode editar o curso) vê sempre; o aluno, só com a sala aberta.
        $organizador = Gate::forUser($usuario)->allows('curso.editar', $curso);
        $mostraLink = $organizador || $situacao === 'aberta';

        return [
            // 'antes', 'aberta', 'encerrada' ou 'sem_horario'.
            'situacao' => $situacao,
            // Hora em que a sala abre (HH:MM), para a mensagem "a sala abre às 18:30".
            'abre_as' => $situacao === 'sem_horario' ? null : SalaAoVivo::janela($aula)[0]->format('H:i'),
            'link' => $mostraLink ? $aula->link_ao_vivo : null,
        ];
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
    public function marcar(Request $request, Curso $curso, CursoAula $aula, SalaDeAula $sala, Certificados $certificados): RedirectResponse
    {
        // Mesma porta da tela: sem acesso ao conteúdo, 403.
        Gate::authorize('curso.conteudo', $curso);

        /** @var User $usuario */
        $usuario = $request->user();

        // Só aula que a pessoa pode ver (deste curso, ativa, nível liberado).
        // Se não for, 404: não dá para marcar aula de outro curso pelo endereço.
        abort_unless($sala->sequencia($curso, $usuario)->contains('id', $aula->id), 404);

        // "assistida" vem como true/false (ou 1/0); sem o campo, conta como marcar.
        $assistida = $request->boolean('assistida', true);
        $sala->marcar($aula, $usuario, $assistida);

        // Certificado automático (#72): marcou uma aula de curso SÓ gravado e concluiu
        // tudo? O certificado sai na hora, com o parabéns. Senão, segue normal.
        if ($assistida && $certificados->emitirSeConcluiu($curso, $usuario) !== null) {
            return back()->with('sucesso', 'Parabéns! Você concluiu o curso. O seu certificado está pronto no "Meu dossiê".');
        }

        return back();
    }
}
