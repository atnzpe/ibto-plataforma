<?php

/*
 * FOTO DE PERFIL da própria pessoa (#78), em Configurações → Perfil.
 * Enviar troca a foto (e apaga a antiga); tirar volta para as iniciais.
 * Cada mudança vai para a auditoria (sem o arquivo, só o código da ação).
 */

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\FotoRequest;
use App\Models\Auditoria;
use App\Support\FotoDePerfil;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class FotoController extends Controller
{
    /**
     * Grava a foto nova (recortada, 400 px, WebP) e apaga a antiga.
     *
     * @param  FotoRequest  $request  O envio já validado (JPG, PNG ou WebP até 5 MB).
     * @return RedirectResponse Volta para a tela de perfil com o aviso "Foto atualizada".
     */
    public function update(FotoRequest $request): RedirectResponse
    {
        $usuario = $request->user();

        try {
            // Recorta, reduz e grava na pasta privada. Ex.: 'fotos/usuarios/9f1c...webp'.
            $nova = FotoDePerfil::gravar($request->file('foto'), 'fotos/usuarios');
        } catch (RuntimeException) {
            // Arquivo que diz ser foto mas não abre: erro no campo, sem detalhe interno.
            throw ValidationException::withMessages(['foto' => 'Não foi possível ler esta foto. Tente outra.']);
        }

        // Guarda o caminho antigo para apagar DEPOIS de gravar o novo (nunca fica sem nenhuma).
        $antiga = $usuario->foto;
        $usuario->forceFill(['foto' => $nova])->save();
        FotoDePerfil::apagar($antiga);

        Auditoria::registrar('FOTO_PERFIL_ATUALIZADA', ['usuario_id' => $usuario->id]);

        return back()->with('status', 'foto-atualizada');
    }

    /**
     * Tira a foto: apaga o arquivo e volta a mostrar as iniciais.
     *
     * @param  Request  $request  A requisição de quem está logado.
     * @return RedirectResponse Volta para a tela de perfil.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        // Sem foto: nada a fazer (não grava auditoria à toa).
        if ($usuario->foto !== null) {
            FotoDePerfil::apagar($usuario->foto);
            $usuario->forceFill(['foto' => null])->save();
            Auditoria::registrar('FOTO_PERFIL_REMOVIDA', ['usuario_id' => $usuario->id]);
        }

        return back()->with('status', 'foto-removida');
    }
}
