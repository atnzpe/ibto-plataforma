<?php

/*
 * VENDA DE FOTOS — LADO DO FOTÓGRAFO (#90). Permissão "fotos.vender".
 *
 * O fotógrafo vê SÓ o que é dele: os álbuns para os quais foi escalado, o envio
 * das fotos (o original fica privado; a prévia ganha a marca d'água), e "Meu
 * financeiro" (os pedidos pagos dos álbuns dele e a parte que cabe a ele).
 */

namespace App\Http\Controllers\Fotos;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\FotoAlbumVenda;
use App\Models\FotoVenda;
use App\Models\PedidoFoto;
use App\Support\FotoComMarcaDagua;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class FotografoController extends Controller
{
    /**
     * Os álbuns do fotógrafo logado.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('fotos/fotografo/index', [
            'albuns' => FotoAlbumVenda::query()->where('fotografo_id', $request->user()->id)->with('curso:id,titulo')->withCount('fotos')->orderByDesc('id')->get()
                ->map(fn (FotoAlbumVenda $album) => [
                    'id' => $album->id,
                    'titulo' => $album->titulo,
                    'curso' => $album->curso->titulo,
                    'fotos' => (int) $album->getAttribute('fotos_count'),
                    'expira_em' => $album->expira_em->format('d/m/Y'),
                    'expirado' => $album->expirado,
                ]),
        ]);
    }

    /**
     * Um álbum do fotógrafo: as prévias e o envio de fotos novas.
     */
    public function album(Request $request, FotoAlbumVenda $album): Response
    {
        // Só o fotógrafo do álbum (ou o Comando Geral); os outros: 404.
        abort_unless($album->fotografo_id === $request->user()->id || $request->user()->temPermissao('fotos.gerenciar'), 404);

        return Inertia::render('fotos/fotografo/album', [
            'album' => [
                'id' => $album->id,
                'titulo' => $album->titulo,
                'expirado' => $album->expirado,
                'expira_em' => $album->expira_em->format('d/m/Y'),
                'fotos' => $album->fotos()->get()->map(fn (FotoVenda $foto) => ['id' => $foto->id, 'previa_url' => route('fotos.previa', $foto)]),
            ],
        ]);
    }

    /**
     * Recebe as fotos (várias de uma vez): guarda o original e gera a prévia com marca d'água.
     */
    public function enviar(Request $request, FotoAlbumVenda $album): RedirectResponse
    {
        abort_unless($album->fotografo_id === $request->user()->id || $request->user()->temPermissao('fotos.gerenciar'), 404);
        // Álbum expirado: as fotos já foram apagadas; não recebe mais.
        abort_if($album->expirado, 404);

        // Até 30 fotos por envio, cada uma JPG, PNG ou WebP de até 15 MB.
        $request->validate(
            ['fotos' => ['required', 'array', 'max:30'], 'fotos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:15360']],
            ['fotos.required' => 'Escolha as fotos.', 'fotos.max' => 'Envie no máximo 30 fotos por vez.', 'fotos.*.max' => 'Cada foto pode ter no máximo 15 MB.', 'fotos.*.mimes' => 'Use fotos JPG, PNG ou WebP.'],
        );

        $ordem = (int) $album->fotos()->max('ordem');
        $enviadas = 0;
        foreach ($request->file('fotos') as $arquivo) {
            try {
                $caminhos = FotoComMarcaDagua::gravar($arquivo, $album->fotografo->name);
            } catch (RuntimeException) {
                throw ValidationException::withMessages(['fotos' => 'Uma das fotos não pôde ser lida. Confira o arquivo e envie de novo.']);
            }
            $foto = new FotoVenda;
            $foto->forceFill(['album_id' => $album->id, 'original' => $caminhos['original'], 'previa' => $caminhos['previa'], 'ordem' => ++$ordem])->save();
            $enviadas++;
        }

        Auditoria::registrar('FOTOS_VENDA_ENVIADAS', ['album_id' => $album->id, 'quantidade' => $enviadas]);

        return back()->with('sucesso', $enviadas === 1 ? '1 foto enviada.' : "{$enviadas} fotos enviadas.");
    }

    /**
     * Tira uma foto do álbum (apaga o original e a prévia). Foto já comprada fica no pedido, sem o arquivo.
     */
    public function apagar(Request $request, FotoVenda $foto): RedirectResponse
    {
        abort_unless($foto->album->fotografo_id === $request->user()->id || $request->user()->temPermissao('fotos.gerenciar'), 404);

        FotoComMarcaDagua::apagar($foto->original, $foto->previa);
        $foto->delete();
        Auditoria::registrar('FOTO_VENDA_APAGADA', ['album_id' => $foto->album_id]);

        return back()->with('sucesso', 'Foto tirada do álbum.');
    }

    /**
     * "Meu financeiro": os pedidos pagos dos álbuns do fotógrafo e a parte dele.
     */
    public function financeiro(Request $request): Response
    {
        $pedidos = PedidoFoto::query()
            ->where('situacao', 'pago')
            ->whereHas('album', fn ($album) => $album->where('fotografo_id', $request->user()->id))
            ->with('album')->withCount('fotos')->orderByDesc('confirmado_em')->get();

        $linhas = $pedidos->map(fn (PedidoFoto $pedido) => [
            'id' => $pedido->id,
            'album' => $pedido->album->titulo,
            'fotos' => (int) $pedido->getAttribute('fotos_count'),
            'valor_centavos' => $pedido->valor_centavos,
            'parte_centavos' => $pedido->album->divisao($pedido->valor_centavos)['fotografo'],
            'pago_em' => $pedido->confirmado_em?->format('d/m/Y'),
        ]);

        return Inertia::render('fotos/fotografo/financeiro', [
            'pedidos' => $linhas,
            'total_parte_centavos' => $linhas->sum('parte_centavos'),
        ]);
    }
}
