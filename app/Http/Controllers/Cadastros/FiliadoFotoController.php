<?php

/*
 * FOTO 3x4 DO FILIADO e AUTORIZAÇÃO DE IMAGEM (#78).
 *
 * - A foto é a da carteirinha (#46) e, só com autorização, da Rede Nacional do
 *   site (#11). Fica na pasta PRIVADA e só sai por aqui, conferindo a permissão.
 * - Quem mexe: a própria pessoa e quem já pode editar aquele filiado (Comando
 *   Geral ou o admin da equipe dele). Portão "filiado.foto".
 * - A autorização começa DESMARCADA (LGPD). Marcar grava a data; marcar ou
 *   desmarcar vai para a auditoria com quem fez.
 */

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\FotoRequest;
use App\Models\Auditoria;
use App\Models\Filiado;
use App\Support\FotoDePerfil;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FiliadoFotoController extends Controller
{
    /**
     * Entrega a foto 3x4. Sem permissão ou sem foto: 404 (não confirma nada).
     *
     * @param  Filiado  $filiado  O filiado do endereço (ex.: /fotos/filiados/7).
     * @return StreamedResponse A foto, guardada só no navegador de quem pediu, por 1 dia.
     */
    public function show(Filiado $filiado): StreamedResponse
    {
        $disco = Storage::disk(FotoDePerfil::DISCO);

        abort_unless(Gate::allows('filiado.foto', $filiado), 404);
        abort_if($filiado->foto === null || ! $disco->exists($filiado->foto), 404);

        return $disco->response($filiado->foto, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    /**
     * Grava a foto 3x4 nova (recortada, 400 px) e apaga a antiga.
     *
     * @param  FotoRequest  $request  O envio já validado (JPG, PNG ou WebP até 5 MB).
     * @param  Filiado  $filiado  O filiado dono da foto.
     * @return RedirectResponse Volta para a tela de onde veio.
     */
    public function update(FotoRequest $request, Filiado $filiado): RedirectResponse
    {
        // Sem permissão: 403 (a pessoa chegou aqui por um formulário que não devia ver).
        Gate::authorize('filiado.foto', $filiado);

        try {
            $nova = FotoDePerfil::gravar($request->file('foto'), 'fotos/filiados');
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['foto' => 'Não foi possível ler esta foto. Tente outra.']);
        }

        // Grava a nova ANTES de apagar a antiga: nunca fica sem nenhuma no meio do caminho.
        $antiga = $filiado->foto;
        $filiado->forceFill(['foto' => $nova])->save();
        FotoDePerfil::apagar($antiga);

        Auditoria::registrar('FOTO_FILIADO_ATUALIZADA', ['filiado_id' => $filiado->id]);

        return back()->with('sucesso', 'Foto 3x4 atualizada.');
    }

    /**
     * Tira a foto 3x4 (apaga o arquivo).
     *
     * @param  Filiado  $filiado  O filiado dono da foto.
     * @return RedirectResponse Volta para a tela de onde veio.
     */
    public function destroy(Filiado $filiado): RedirectResponse
    {
        Gate::authorize('filiado.foto', $filiado);

        // Sem foto: nada a fazer.
        if ($filiado->foto !== null) {
            FotoDePerfil::apagar($filiado->foto);
            $filiado->forceFill(['foto' => null])->save();
            Auditoria::registrar('FOTO_FILIADO_REMOVIDA', ['filiado_id' => $filiado->id]);
        }

        return back()->with('sucesso', 'Foto 3x4 removida.');
    }

    /**
     * Marca ou desmarca a autorização de uso da foto na Rede Nacional do site.
     *
     * @param  Request  $request  Traz "autoriza": true (marcou) ou false (desmarcou).
     * @param  Filiado  $filiado  O filiado da autorização.
     * @return RedirectResponse Volta para a tela de onde veio.
     */
    public function autorizacao(Request $request, Filiado $filiado): RedirectResponse
    {
        Gate::authorize('filiado.foto', $filiado);

        // Só aceita sim ou não (true/false, 1/0).
        $autoriza = (bool) $request->validate(['autoriza' => ['required', 'boolean']])['autoriza'];

        $filiado->forceFill([
            'autoriza_imagem_rede' => $autoriza,
            // Marcou: grava a data de agora. Desmarcou: limpa a data.
            'autoriza_imagem_em' => $autoriza ? now() : null,
        ])->save();

        // A auditoria guarda QUEM marcou (o usuário logado) e quando (a própria linha).
        Auditoria::registrar($autoriza ? 'AUTORIZACAO_IMAGEM_MARCADA' : 'AUTORIZACAO_IMAGEM_DESMARCADA', ['filiado_id' => $filiado->id]);

        return back()->with('sucesso', $autoriza ? 'Autorização de imagem registrada.' : 'Autorização de imagem retirada.');
    }
}
