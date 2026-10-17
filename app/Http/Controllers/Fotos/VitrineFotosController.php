<?php

/*
 * VENDA DE FOTOS — LADO DO ALUNO (#90).
 *
 * - Vitrine do álbum: as prévias com marca d'água, para escolher. Só para quem
 *   tem matrícula confirmada no curso (e a venda ligada); os outros recebem 404.
 * - Comprar: cria o pedido e leva à página dele, com o Pix copia e cola.
 * - Download: o ORIGINAL, só do pedido pago, da própria pessoa, dentro do prazo.
 * Cada arquivo sai por rota que confere a permissão (pasta privada).
 */

namespace App\Http\Controllers\Fotos;

use App\Actions\Fotos\VendaFotos;
use App\Exceptions\RegraCurso;
use App\Http\Controllers\Controller;
use App\Models\FotoAlbumVenda;
use App\Models\FotoVenda;
use App\Models\PedidoFoto;
use App\Support\DadosPagamento;
use App\Support\FotoComMarcaDagua;
use App\Support\PixCopiaECola;
use App\Support\QrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VitrineFotosController extends Controller
{
    /**
     * FOTOS DOS EVENTOS (menu, decisão do Gleyson em 17/10/2026): os álbuns à venda
     * que a pessoa pode ver (só os dos cursos em que ela tem matrícula confirmada)
     * e todos os pedidos dela (Pix pendente, pago com download, cancelado).
     *
     * @param  Request  $request  O pedido de quem está logado.
     * @return Response A tela "fotos/index".
     */
    public function index(Request $request): Response
    {
        $usuario = $request->user();

        // Os álbuns ativos e no prazo; o filtro final é a mesma regra da vitrine (podeVer).
        $albuns = FotoAlbumVenda::query()
            ->with('curso:id,titulo')
            ->where('ativo', true)->where('expirado', false)
            ->orderByDesc('id')
            ->get()
            ->filter(fn (FotoAlbumVenda $album) => VendaFotos::podeVer($album, $usuario))
            ->map(fn (FotoAlbumVenda $album) => [
                'id' => $album->id,
                'titulo' => $album->titulo,
                'curso' => $album->curso->titulo,
                'preco_centavos' => $album->preco_centavos,
                'expira_em' => $album->expira_em->format('d/m/Y'),
                'fotos' => $album->fotos()->count(),
            ])
            ->values()
            ->all();

        return Inertia::render('fotos/index', [
            'ligada' => VendaFotos::ligada(),
            'albuns' => $albuns,
            'pedidos' => PedidoFoto::query()->with('album:id,titulo')->where('user_id', $usuario->id)->orderByDesc('id')->limit(50)->get()
                ->map(fn (PedidoFoto $pedido) => [
                    'id' => $pedido->id,
                    'album' => $pedido->album->titulo,
                    'situacao' => $pedido->situacao,
                    'valor_centavos' => $pedido->valor_centavos,
                    'data' => $pedido->created_at?->format('d/m/Y'),
                    'pode_baixar' => $pedido->podeBaixar(),
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * A vitrine de um álbum: prévias para escolher e o preço por foto.
     */
    public function show(Request $request, FotoAlbumVenda $album): Response
    {
        abort_unless(VendaFotos::podeVer($album, $request->user()), 404);

        return Inertia::render('fotos/vitrine', [
            'album' => [
                'id' => $album->id,
                'titulo' => $album->titulo,
                'curso' => $album->curso->titulo,
                'preco_centavos' => $album->preco_centavos,
                'expira_em' => $album->expira_em->format('d/m/Y'),
                'fotos' => $album->fotos()->get()->map(fn (FotoVenda $foto) => ['id' => $foto->id, 'previa_url' => route('fotos.previa', $foto)]),
            ],
            // Os pedidos desta pessoa neste álbum (para voltar ao Pix ou baixar).
            'meus_pedidos' => PedidoFoto::query()->where('album_id', $album->id)->where('user_id', $request->user()->id)->orderByDesc('id')->get()
                ->map(fn (PedidoFoto $pedido) => ['id' => $pedido->id, 'situacao' => $pedido->situacao, 'valor_centavos' => $pedido->valor_centavos]),
        ]);
    }

    /**
     * Cria o pedido com as fotos escolhidas e leva à página dele (com o Pix).
     */
    public function comprar(Request $request, FotoAlbumVenda $album, VendaFotos $venda): RedirectResponse
    {
        $dados = $request->validate(['fotos' => ['required', 'array', 'max:200'], 'fotos.*' => ['integer']], ['fotos.required' => 'Escolha pelo menos uma foto.']);

        try {
            $pedido = $venda->criarPedido($album, $request->user(), array_values(array_map('intval', $dados['fotos'])));
        } catch (RegraCurso $erro) {
            return back()->withErrors(['fotos' => "{$erro->getMessage()} (código {$erro->codigo})"]);
        }

        return to_route('fotos.pedido', $pedido)->with('sucesso', 'Pedido criado! Faça o Pix abaixo; quando o IBTO confirmar, as fotos ficam liberadas para baixar.');
    }

    /**
     * A página do pedido: o Pix (aguardando) ou os links de download (pago).
     */
    public function pedido(Request $request, PedidoFoto $pedido): Response
    {
        // Só a própria pessoa (ou o Comando Geral); os outros: 404.
        abort_unless($pedido->user_id === $request->user()->id || $request->user()->temPermissao('fotos.gerenciar'), 404);

        $pix = null;
        if ($pedido->situacao === 'aguardando_pagamento') {
            // Pix copia e cola com o valor exato e o identificador do pedido (dados do IBTO).
            $pagamento = DadosPagamento::doIbto();
            if ($pagamento['pix_chave'] !== '') {
                $txid = AlbunsVendaController::txid($pedido->id);
                $codigo = PixCopiaECola::gerar($pagamento['pix_chave'], $pagamento['favorecido'] !== '' ? $pagamento['favorecido'] : 'IBTO', (string) config('app.pix_cidade'), $pedido->valor_centavos, $txid);
                $pix = ['copia_e_cola' => $codigo, 'qr_svg' => QrCode::svg($codigo, 220), 'txid' => $txid, 'favorecido' => $pagamento['favorecido'], 'chave' => $pagamento['pix_chave']];
            }
        }

        return Inertia::render('fotos/pedido', [
            'pedido' => [
                'id' => $pedido->id,
                'album' => $pedido->album->titulo,
                'album_id' => $pedido->album_id,
                'situacao' => $pedido->situacao,
                'valor_centavos' => $pedido->valor_centavos,
                'download_ate' => $pedido->download_ate?->format('d/m/Y'),
                'pode_baixar' => $pedido->podeBaixar(),
                // Links do original: só quando pago e dentro do prazo.
                'fotos' => $pedido->fotos()->get()->map(fn (FotoVenda $foto) => [
                    'id' => $foto->id,
                    'previa_url' => route('fotos.previa', $foto),
                    'download_url' => $pedido->podeBaixar() ? route('fotos.original', [$pedido, $foto]) : null,
                ]),
            ],
            'pix' => $pix,
        ]);
    }

    /**
     * A prévia com marca d'água (vitrine, álbum do fotógrafo, pedido).
     */
    public function previa(Request $request, FotoVenda $foto): StreamedResponse
    {
        abort_unless(VendaFotos::podeVer($foto->album, $request->user()), 404);
        $disco = Storage::disk(FotoComMarcaDagua::DISCO);
        abort_unless($disco->exists($foto->previa), 404);

        return $disco->response($foto->previa, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    /**
     * O ORIGINAL: só do pedido pago da própria pessoa, dentro do prazo, e só foto do pedido.
     */
    public function original(Request $request, PedidoFoto $pedido, FotoVenda $foto): StreamedResponse
    {
        abort_unless($pedido->user_id === $request->user()->id && $pedido->podeBaixar(), 404);
        abort_unless($pedido->fotos()->whereKey($foto->id)->exists(), 404);
        $disco = Storage::disk(FotoComMarcaDagua::DISCO);
        abort_unless($disco->exists($foto->original), 404);

        // Baixa como arquivo, com um nome simples. Ex.: 'ibto-foto-12.jpg'.
        return $disco->download($foto->original, 'ibto-foto-'.$foto->id.'.'.pathinfo($foto->original, PATHINFO_EXTENSION), ['Cache-Control' => 'private, no-store']);
    }
}
