<?php

/*
 * Entrega a LOGO de uma equipe (#37). Pública (sem login): a rede nacional do
 * site também mostra as logos. O arquivo fica em storage/app/public e sai por
 * aqui, sem depender do storage:link (servidor sem SSH).
 */

namespace App\Http\Controllers;

use App\Models\Equipe;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EquipeLogoController extends Controller
{
    /**
     * @param  Equipe  $equipe  A equipe do endereço (ex.: /imagens/equipes/3/logo = equipe 3).
     * @return StreamedResponse O arquivo da logo, guardado no navegador por 1 dia.
     */
    public function __invoke(Equipe $equipe): StreamedResponse
    {
        $disco = Storage::disk('public');

        // Equipe sem logo, ou arquivo que sumiu: "não encontrado" (404).
        abort_if($equipe->logo === null || ! $disco->exists($equipe->logo), 404);

        // Cache de 1 dia; quando a logo muda, o endereço muda junto (?v=...).
        return $disco->response($equipe->logo, null, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
