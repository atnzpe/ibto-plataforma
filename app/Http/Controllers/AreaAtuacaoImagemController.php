<?php

/*
 * Entrega a IMAGEM de uma área de atuação (#37 e #57).
 *
 * É pública (sem login), porque o site também mostra as imagens. O arquivo fica
 * fora da parte pública (storage/app/public) e sai por aqui, sem depender do
 * comando storage:link (o servidor não tem SSH).
 */

namespace App\Http\Controllers;

use App\Models\AreaAtuacao;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AreaAtuacaoImagemController extends Controller
{
    /**
     * @param  AreaAtuacao  $area  A área do endereço (ex.: /imagens/areas-atuacao/4 = área 4).
     * @return StreamedResponse O arquivo da imagem, guardado no navegador por 1 dia.
     */
    public function __invoke(AreaAtuacao $area): StreamedResponse
    {
        $disco = Storage::disk('public');

        // Área sem imagem, ou arquivo que sumiu da pasta: "não encontrado" (404).
        abort_if($area->imagem === null || ! $disco->exists($area->imagem), 404);

        // Cache de 1 dia: o navegador não baixa de novo a cada visita. Quando a
        // imagem muda, o endereço muda junto (?v=..., ver AreaAtuacao::imagemUrl).
        return $disco->response($area->imagem, null, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
