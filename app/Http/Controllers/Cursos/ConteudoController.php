<?php

/*
 * CONTEÚDO do curso no Comando Geral (#39, bloco C2): módulos e aulas, em
 * ordem (subir/descer), com nível mínimo no módulo, vídeo por link e apostila
 * em PDF no disco PRIVADO. Mesma lógica de "vídeos e programas" do Dojo
 * Manager (pedido do Gleyson, 10/10/2026). Sem apagar: desativa.
 * Permissão "cursos.gerenciar" (routes/web.php).
 */

namespace App\Http\Controllers\Cursos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cursos\AulaRequest;
use App\Http\Requests\Cursos\ModuloRequest;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\CursoAula;
use App\Models\CursoModulo;
use App\Models\Nivel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ConteudoController extends Controller
{
    // Pastas (no disco PRIVADO, storage/app/private) das apostilas e dos vídeos enviados.
    private const PASTA_APOSTILAS = 'apostilas';

    private const PASTA_VIDEOS = 'videos-aulas';

    /**
     * A tela do conteúdo: módulos com as suas aulas, na ordem.
     *
     * @param  Curso  $curso  O do endereço (ex.: /comando-geral/cursos/3/conteudo).
     * @return Response A tela "cursos/conteudo".
     */
    public function show(Curso $curso): Response
    {
        // Ver o conteúdo: Comando Geral, quem chancela ou o dono do curso (senão, 403).
        Gate::authorize('curso.conteudo', $curso);
        // Pode mexer? (o dono, só em rascunho/rejeitado.) A tela esconde os botões se não.
        $podeEditar = Gate::allows('curso.editar', $curso);

        $curso->load('modulos.aulas', 'modulos.nivelMinimo');

        return Inertia::render('cursos/conteudo', [
            'curso' => ['id' => $curso->id, 'titulo' => $curso->titulo, 'situacao' => $curso->situacao->rotulo()],
            // false = só olhar (ex.: curso do dono já em análise ou publicado).
            'pode_editar' => $podeEditar,
            'modulos' => $curso->modulos->map(fn (CursoModulo $modulo) => [
                'id' => $modulo->id,
                'titulo' => $modulo->titulo,
                'ordem' => $modulo->ordem,
                'nivel_minimo_id' => $modulo->nivel_minimo_id,
                'nivel_minimo' => $modulo->nivelMinimo?->nome(),
                'ativo' => $modulo->ativo,
                'aulas' => $modulo->aulas->map(fn (CursoAula $aula) => [
                    'id' => $aula->id,
                    'titulo' => $aula->titulo,
                    'ordem' => $aula->ordem,
                    'video_url' => $aula->video_url,
                    // 'youtube', 'arquivo' (link .mp4), 'enviado' ou null (sem vídeo).
                    'video_tipo' => $aula->video()['tipo'] ?? null,
                    // Escolha atual, para o formulário abrir certo.
                    'fonte_video' => $aula->video_arquivo !== null ? 'arquivo' : 'link',
                    'conteudo' => $aula->conteudo,
                    'duracao_minutos' => $aula->duracao_minutos,
                    // Encontro presencial: dia (AAAA-MM-DD), horário (HH:MM) e quem aplica.
                    'data' => $aula->data?->toDateString(),
                    'hora_inicio' => $aula->hora_inicio !== null ? substr($aula->hora_inicio, 0, 5) : null,
                    'hora_fim' => $aula->hora_fim !== null ? substr($aula->hora_fim, 0, 5) : null,
                    'quem_aplica' => $aula->quem_aplica,
                    // Introdução pública (aparece na janela do cronograma, C4d).
                    'introducao' => $aula->introducao,
                    'tem_apostila' => $aula->apostila !== null,
                    'ativa' => $aula->ativa,
                ])->values(),
            ])->values(),
            // Níveis na ordem da hierarquia, para o "nível mínimo".
            'niveis' => Nivel::emOrdem(Nivel::all())->map(fn (Nivel $nivel) => ['id' => $nivel->id, 'nome' => $nivel->nome()])->values(),
            // Limite do vídeo enviado, em MB (vem do modelo: um lugar só).
            'video_maximo_mb' => intdiv(CursoAula::VIDEO_MAXIMO_KB, 1024),
        ]);
    }

    /**
     * Cria um módulo no FIM do curso.
     *
     * @param  ModuloRequest  $request  O formulário, já conferido.
     * @param  Curso  $curso  O curso.
     */
    public function storeModulo(ModuloRequest $request, Curso $curso): RedirectResponse
    {
        Gate::authorize('curso.editar', $curso);

        $modulo = DB::transaction(function () use ($request, $curso): CursoModulo {
            $modulo = $curso->modulos()->create([
                ...$request->validated(),
                // Próxima posição: a maior + 1 (o primeiro é 1).
                'ordem' => (int) $curso->modulos()->max('ordem') + 1,
            ]);
            Auditoria::registrar('MODULO_CRIADO', ['curso_id' => $curso->id, 'modulo_id' => $modulo->id]);

            return $modulo;
        });

        return back()->with('sucesso', "Módulo \"{$modulo->titulo}\" criado.");
    }

    /**
     * Edita um módulo (título, nível mínimo, ativo).
     */
    public function updateModulo(ModuloRequest $request, CursoModulo $modulo): RedirectResponse
    {
        Gate::authorize('curso.editar', $modulo->curso);

        $modulo->update($request->validated());
        Auditoria::registrar('MODULO_EDITADO', ['curso_id' => $modulo->curso_id, 'modulo_id' => $modulo->id]);

        return back()->with('sucesso', "Módulo \"{$modulo->titulo}\" atualizado.");
    }

    /**
     * Sobe ou desce o módulo uma posição (troca com o vizinho).
     *
     * @param  Request  $request  Com "direcao": 'subir' ou 'descer'.
     */
    public function moverModulo(Request $request, CursoModulo $modulo): RedirectResponse
    {
        Gate::authorize('curso.editar', $modulo->curso);

        $this->mover($modulo, CursoModulo::query()->where('curso_id', $modulo->curso_id), (string) $request->input('direcao'));

        return back();
    }

    /**
     * Cria uma aula no FIM do módulo (com a apostila, se veio).
     *
     * @param  AulaRequest  $request  O formulário, já conferido.
     * @param  CursoModulo  $modulo  O módulo.
     */
    public function storeAula(AulaRequest $request, CursoModulo $modulo): RedirectResponse
    {
        Gate::authorize('curso.editar', $modulo->curso);

        $aula = DB::transaction(function () use ($request, $modulo): CursoAula {
            $aula = $modulo->aulas()->create([
                ...$request->safe()->except(['apostila', 'video_arquivo', 'fonte_video']),
                'ordem' => (int) $modulo->aulas()->max('ordem') + 1,
            ]);
            $this->aplicarFonteDoVideo($aula, $request);
            $this->trocarApostila($aula, $request->file('apostila'));
            Auditoria::registrar('AULA_CRIADA', ['modulo_id' => $modulo->id, 'aula_id' => $aula->id]);

            return $aula;
        });

        return back()->with('sucesso', "Aula \"{$aula->titulo}\" criada.");
    }

    /**
     * Edita uma aula (e troca a apostila, se veio uma nova).
     */
    public function updateAula(AulaRequest $request, CursoAula $aula): RedirectResponse
    {
        Gate::authorize('curso.editar', $aula->modulo->curso);

        DB::transaction(function () use ($request, $aula): void {
            $aula->update($request->safe()->except(['apostila', 'video_arquivo', 'fonte_video']));
            $this->aplicarFonteDoVideo($aula, $request);
            $this->trocarApostila($aula, $request->file('apostila'));
            Auditoria::registrar('AULA_EDITADA', ['modulo_id' => $aula->modulo_id, 'aula_id' => $aula->id]);
        });

        return back()->with('sucesso', "Aula \"{$aula->titulo}\" atualizada.");
    }

    /**
     * Sobe ou desce a aula uma posição dentro do módulo.
     */
    public function moverAula(Request $request, CursoAula $aula): RedirectResponse
    {
        Gate::authorize('curso.editar', $aula->modulo->curso);

        $this->mover($aula, CursoAula::query()->where('modulo_id', $aula->modulo_id), (string) $request->input('direcao'));

        return back();
    }

    /**
     * Aplica a escolha "link ou arquivo" do vídeo (Gleyson, 10/10/2026):
     * - link: apaga o vídeo enviado (se havia) e fica só o link;
     * - arquivo: se veio um vídeo novo, guarda no disco PRIVADO e limpa o link.
     *
     * @param  CursoAula  $aula  A aula.
     * @param  AulaRequest  $request  O formulário (fonte_video e o arquivo).
     */
    private function aplicarFonteDoVideo(CursoAula $aula, AulaRequest $request): void
    {
        $disco = Storage::disk('local');

        if ($request->input('fonte_video') === 'link') {
            // Escolheu link: o arquivo enviado antes (se havia) sai do servidor.
            if ($aula->video_arquivo !== null) {
                $disco->delete($aula->video_arquivo);
                $aula->forceFill(['video_arquivo' => null])->save();
            }

            return;
        }

        // Escolheu arquivo, mas não enviou um novo: mantém o que já existe.
        $arquivo = $request->file('video_arquivo');
        if ($arquivo === null) {
            return;
        }

        $caminho = $disco->putFile(self::PASTA_VIDEOS, $arquivo);
        if ($caminho === false) {
            throw new \RuntimeException('VIDEO_NAO_GRAVADO');
        }
        $antigo = $aula->video_arquivo;
        $aula->forceFill(['video_arquivo' => $caminho, 'video_url' => null])->save();
        // Só apaga o antigo depois que o novo foi gravado e salvo no banco.
        if ($antigo !== null) {
            $disco->delete($antigo);
        }
    }

    /**
     * Troca a posição de um item com o vizinho de cima ou de baixo.
     *
     * @param  CursoModulo|CursoAula  $item  O que vai mudar de lugar.
     * @param  Builder<CursoModulo>|Builder<CursoAula>  $irmaos  Os da mesma lista (mesmo curso ou mesmo módulo).
     * @param  string  $direcao  'subir' ou 'descer' (outra coisa: não faz nada).
     */
    private function mover(Model $item, $irmaos, string $direcao): void
    {
        DB::transaction(function () use ($item, $irmaos, $direcao): void {
            // O vizinho: o de ordem logo menor (subir) ou logo maior (descer).
            $vizinho = match ($direcao) {
                'subir' => (clone $irmaos)->where('ordem', '<', $item->getAttribute('ordem'))->orderByDesc('ordem')->first(),
                'descer' => (clone $irmaos)->where('ordem', '>', $item->getAttribute('ordem'))->orderBy('ordem')->first(),
                default => null,
            };
            // Já é o primeiro (ou o último): nada a fazer.
            if ($vizinho === null) {
                return;
            }

            // Troca as duas posições.
            $minha = $item->getAttribute('ordem');
            $item->forceFill(['ordem' => $vizinho->getAttribute('ordem')])->save();
            $vizinho->forceFill(['ordem' => $minha])->save();
        });
    }

    /**
     * Guarda a apostila nova no disco PRIVADO e apaga a antiga.
     *
     * @param  CursoAula  $aula  A aula.
     * @param  UploadedFile|null  $arquivo  O PDF enviado; null = nada muda.
     */
    private function trocarApostila(CursoAula $aula, ?UploadedFile $arquivo): void
    {
        if ($arquivo === null) {
            return;
        }

        $disco = Storage::disk('local');
        // Nome aleatório: o nome original pode ter acentos ou dados pessoais.
        $caminho = $disco->putFile(self::PASTA_APOSTILAS, $arquivo);
        if ($caminho === false) {
            throw new \RuntimeException('APOSTILA_NAO_GRAVADA');
        }

        $antiga = $aula->apostila;
        $aula->forceFill(['apostila' => $caminho])->save();
        // Só apaga a antiga depois que a nova foi gravada e salva no banco.
        if ($antiga !== null) {
            $disco->delete($antiga);
        }
    }
}
