<?php

/*
 * GALERIA DE EVENTOS no Comando Geral (#61, bloco B2): a lista dos álbuns, o
 * cadastro e a edição (com as fotos), o envio de várias fotos de uma vez, a
 * descrição e o crédito de cada foto, apagar uma foto e as ações do caminho
 * (publicar, arquivar, voltar para rascunho). Quem entra: "blog.publicar".
 * As regras de publicar ficam em App\Actions\Blog\PublicacaoAlbum.
 */

namespace App\Http\Controllers\Blog;

use App\Actions\Blog\PublicacaoAlbum;
use App\Exceptions\RegraBlog;
use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\GaleriaAlbum;
use App\Models\GaleriaFoto;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AlbumController extends Controller
{
    // Quantas fotos por envio (para o envio não ficar pesado demais para o servidor).
    public const FOTOS_POR_ENVIO = 20;

    // Tamanho máximo de cada foto, em KB (4 MB).
    public const FOTO_MAXIMA_KB = 4096;

    /**
     * Lista dos álbuns, os eventos mais novos primeiro.
     *
     * @return Response A tela "galeria-admin/index".
     */
    public function index(): Response
    {
        return Inertia::render('galeria-admin/index', [
            'albuns' => GaleriaAlbum::query()
                ->withCount('fotos')
                ->orderByDesc('data_evento')->orderByDesc('id')
                ->limit(300)->get()
                ->map(fn (GaleriaAlbum $album) => [
                    'id' => $album->id,
                    'titulo' => $album->titulo,
                    'data_evento' => $album->data_evento?->toDateString(),
                    'local_evento' => $album->local_evento,
                    // Quantas fotos o álbum tem (o withCount conta no banco).
                    'fotos' => (int) $album->getAttribute('fotos_count'),
                    'situacao' => $album->situacao->value,
                    'situacao_rotulo' => $album->situacao->rotulo(),
                ])->values(),
        ]);
    }

    /**
     * Formulário vazio.
     *
     * @return Response A tela "galeria-admin/formulario".
     */
    public function create(): Response
    {
        return Inertia::render('galeria-admin/formulario', ['album' => null, 'posts' => $this->posts()]);
    }

    /**
     * Grava o álbum novo como RASCUNHO e abre a edição, para enviar as fotos.
     *
     * @param  Request  $request  O formulário.
     * @return RedirectResponse A edição do álbum.
     */
    public function store(Request $request): RedirectResponse
    {
        $album = new GaleriaAlbum($this->validarAlbum($request));
        // Quem criou (não vem do formulário).
        $album->forceFill(['autor_id' => $request->user()?->id])->save();
        Auditoria::registrar('ALBUM_CRIADO', ['album_id' => $album->id]);

        return to_route('comando-geral.galeria.edit', $album)->with('sucesso', "Álbum \"{$album->titulo}\" criado. Agora envie as fotos.");
    }

    /**
     * Edição: os dados do álbum, as fotos (com descrição e crédito) e os botões do caminho.
     *
     * @param  GaleriaAlbum  $album  O álbum do endereço (ex.: /comando-geral/galeria/3/edit).
     * @param  PublicacaoAlbum  $publicacao  As regras (quais botões mostrar).
     * @return Response A tela "galeria-admin/formulario".
     */
    public function edit(GaleriaAlbum $album, PublicacaoAlbum $publicacao): Response
    {
        return Inertia::render('galeria-admin/formulario', [
            'album' => [
                'id' => $album->id,
                'titulo' => $album->titulo,
                'slug' => $album->slug,
                'descricao' => $album->descricao,
                'data_evento' => $album->data_evento?->toDateString(),
                'local_evento' => $album->local_evento,
                'post_id' => $album->post_id,
                'credito_padrao' => $album->credito_padrao,
                'autorizacao_imagem' => $album->autorizacao_imagem,
                'autorizacao_origem' => $album->autorizacao_origem,
                'tem_menores' => $album->tem_menores,
                'autorizacao_responsaveis' => $album->autorizacao_responsaveis,
                'situacao' => $album->situacao->value,
                'situacao_rotulo' => $album->situacao->rotulo(),
                'acoes' => $publicacao->acoesPara($album),
                'fotos' => $album->fotos()->get()->map(fn (GaleriaFoto $foto) => [
                    'id' => $foto->id,
                    'url' => $foto->url(),
                    'descricao' => $foto->descricao,
                    'credito' => $foto->credito,
                    'ordem' => $foto->ordem,
                    // false = falta descrever (aparece o aviso na foto).
                    'descrita' => $foto->descrita(),
                ])->values(),
            ],
            'posts' => $this->posts(),
            'fotos_por_envio' => self::FOTOS_POR_ENVIO,
        ]);
    }

    /**
     * Grava os dados do álbum (a situação não muda aqui).
     *
     * @param  Request  $request  O formulário.
     * @param  GaleriaAlbum  $album  O álbum.
     */
    public function update(Request $request, GaleriaAlbum $album): RedirectResponse
    {
        $album->update($this->validarAlbum($request));
        Auditoria::registrar('ALBUM_EDITADO', ['album_id' => $album->id]);

        return back()->with('sucesso', 'Álbum salvo.');
    }

    /**
     * Uma ação do caminho (publicar, arquivar, voltar para rascunho).
     *
     * @param  Request  $request  Com "acao". Ex.: acao=publicar
     * @param  GaleriaAlbum  $album  O álbum.
     * @param  PublicacaoAlbum  $publicacao  As regras.
     * @return RedirectResponse De volta, com a mensagem ou o erro da regra (com código).
     */
    public function situacao(Request $request, GaleriaAlbum $album, PublicacaoAlbum $publicacao): RedirectResponse
    {
        try {
            $publicacao->aplicar($album, (string) $request->input('acao'));
        } catch (RegraBlog $erro) {
            return back()->withErrors(['acao' => "{$erro->getMessage()} ({$erro->codigo})"]);
        }

        return back()->with('sucesso', "Álbum agora: {$album->situacao->rotulo()}.");
    }

    /**
     * Envia várias fotos de uma vez (até 20 por envio, 4 MB cada). Elas entram
     * no fim do álbum, sem descrição (a pessoa descreve logo depois).
     *
     * @param  Request  $request  Com "fotos[]" (os arquivos).
     * @param  GaleriaAlbum  $album  O álbum.
     */
    public function enviarFotos(Request $request, GaleriaAlbum $album): RedirectResponse
    {
        $request->validate([
            'fotos' => ['required', 'array', 'min:1', 'max:'.self::FOTOS_POR_ENVIO],
            'fotos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::FOTO_MAXIMA_KB],
        ], [
            'fotos.required' => 'Escolha pelo menos uma foto.',
            'fotos.max' => 'Envie no máximo '.self::FOTOS_POR_ENVIO.' fotos por vez.',
            'fotos.*.image' => 'Cada arquivo precisa ser uma imagem.',
            'fotos.*.mimes' => 'As fotos precisam ser JPG, PNG ou WEBP.',
            'fotos.*.max' => 'Cada foto pode ter no máximo 4 MB.',
        ]);

        $disco = Storage::disk('public');
        // A próxima posição: depois da última foto do álbum.
        $ordem = (int) $album->fotos()->max('ordem');

        $enviadas = DB::transaction(function () use ($request, $album, $disco, &$ordem): int {
            $quantas = 0;
            foreach ($request->file('fotos', []) as $arquivo) {
                // Nome aleatório na pasta do álbum (o nome original pode ter dados pessoais).
                $caminho = $disco->putFile("galeria/{$album->id}", $arquivo);
                // Falhou ao gravar (ex.: disco cheio): não passa em silêncio (#58).
                if ($caminho === false) {
                    throw new \RuntimeException('FOTO_NAO_GRAVADA');
                }
                $ordem++;
                (new GaleriaFoto)->forceFill([
                    'album_id' => $album->id,
                    'caminho' => $caminho,
                    'ordem' => $ordem,
                ])->save();
                $quantas++;
            }

            return $quantas;
        });

        Auditoria::registrar('ALBUM_FOTOS_ENVIADAS', ['album_id' => $album->id, 'quantas' => $enviadas]);

        return back()->with('sucesso', "{$enviadas} foto(s) enviada(s). Agora descreva cada uma.");
    }

    /**
     * Salva a descrição, o crédito e a ordem de todas as fotos de uma vez.
     *
     * @param  Request  $request  Com "fotos": [id => {descricao, credito, ordem}].
     * @param  GaleriaAlbum  $album  O álbum (só as fotos dele são alteradas).
     */
    public function salvarFotos(Request $request, GaleriaAlbum $album): RedirectResponse
    {
        $dados = $request->validate([
            'fotos' => ['array'],
            'fotos.*.descricao' => ['nullable', 'string', 'max:200'],
            'fotos.*.credito' => ['nullable', 'string', 'max:120'],
            'fotos.*.ordem' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        foreach ($dados['fotos'] ?? [] as $id => $campos) {
            // Só foto DESTE álbum (um id de outro álbum no formulário é ignorado).
            $foto = $album->fotos()->whereKey((int) $id)->first();
            if ($foto === null) {
                continue;
            }
            $foto->update([
                'descricao' => $campos['descricao'] ?? null,
                'credito' => $campos['credito'] ?? null,
                'ordem' => $campos['ordem'] ?? $foto->ordem,
            ]);
        }

        return back()->with('sucesso', 'Fotos salvas.');
    }

    /**
     * Apaga uma foto (o arquivo e a linha). Com o álbum no site, a foto some dele.
     *
     * @param  GaleriaFoto  $foto  A foto do endereço.
     */
    public function apagarFoto(GaleriaFoto $foto): RedirectResponse
    {
        Storage::disk('public')->delete($foto->caminho);
        $foto->delete();
        Auditoria::registrar('ALBUM_FOTO_APAGADA', ['album_id' => $foto->album_id, 'foto_id' => $foto->id]);

        return back()->with('sucesso', 'Foto apagada.');
    }

    /**
     * Confere os dados do álbum (título, data, local, post ligado, crédito e autorização).
     *
     * @return array<string, mixed> Os dados conferidos, prontos para gravar.
     */
    private function validarAlbum(Request $request): array
    {
        // Caixas de marcar desmarcadas não vêm no envio: viram false aqui.
        $request->merge([
            'autorizacao_imagem' => $request->boolean('autorizacao_imagem'),
            'tem_menores' => $request->boolean('tem_menores'),
            'autorizacao_responsaveis' => $request->boolean('autorizacao_responsaveis'),
        ]);

        return $request->validate([
            'titulo' => ['required', 'string', 'max:150'],
            'descricao' => ['nullable', 'string', 'max:2000'],
            'data_evento' => ['nullable', 'date_format:Y-m-d'],
            'local_evento' => ['nullable', 'string', 'max:150'],
            'post_id' => ['nullable', 'integer', Rule::exists('posts', 'id')],
            'credito_padrao' => ['required', 'string', 'max:120'],
            'autorizacao_imagem' => ['boolean'],
            'autorizacao_origem' => ['nullable', 'string', 'max:200'],
            'tem_menores' => ['boolean'],
            'autorizacao_responsaveis' => ['boolean'],
        ], [
            'titulo.required' => 'Informe o título do álbum.',
            'credito_padrao.required' => 'Informe o crédito das fotos (ex.: Divulgação / IBTO).',
        ]);
    }

    /**
     * Os posts do blog para ligar ao álbum (os mais novos primeiro).
     *
     * @return list<array{id: int, titulo: string}>
     */
    private function posts(): array
    {
        return array_values(Post::query()->latest('id')->limit(200)->get(['id', 'titulo'])
            ->map(fn (Post $post) => ['id' => $post->id, 'titulo' => $post->titulo])
            ->all());
    }
}
