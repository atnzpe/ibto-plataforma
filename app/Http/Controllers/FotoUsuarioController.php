<?php

/*
 * ENTREGA a foto de perfil de uma pessoa (#78), conferindo a permissão.
 *
 * A foto fica na pasta PRIVADA; este é o único caminho até ela. Só passa quem
 * o portão "usuario.foto" libera (a própria pessoa, o Comando Geral, o admin
 * da equipe dela, o instrutor de um curso em que ela está matriculada...).
 * Os outros recebem 404, como se a foto não existisse (não confirma nada).
 */

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\FotoDePerfil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FotoUsuarioController extends Controller
{
    /**
     * @param  Request  $request  A requisição de quem está logado.
     * @param  User  $usuario  A pessoa dona da foto (ex.: /fotos/usuarios/12).
     * @return StreamedResponse A foto, guardada só no navegador de quem pediu (private) por 1 dia.
     */
    public function __invoke(Request $request, User $usuario): StreamedResponse
    {
        $disco = Storage::disk(FotoDePerfil::DISCO);

        // Sem permissão, sem foto ou arquivo que sumiu: 404.
        abort_unless(Gate::allows('usuario.foto', $usuario), 404);
        abort_if($usuario->foto === null || ! $disco->exists($usuario->foto), 404);

        // "private": só o navegador de quem pediu guarda (nunca um cache no meio do caminho).
        // Quando a foto muda, o endereço muda junto (?v=...), então 1 dia é seguro.
        return $disco->response($usuario->foto, null, ['Cache-Control' => 'private, max-age=86400']);
    }
}
