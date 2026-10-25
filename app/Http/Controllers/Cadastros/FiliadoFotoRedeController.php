<?php

/*
 * FOTO DA REDE NACIONAL do filiado (#11, pedido do Mestre Eduardo, 23/10/2026).
 *
 * É a foto PERSONALIZADA (ex.: de uniforme, com o fundo do IBTO) que aparece na
 * página Rede Nacional do site, diferente da 3x4 da carteirinha. Guardada INTEIRA,
 * sem recorte (o quadrado do centro cortava o rosto de quem posa em pé; Gleyson,
 * 24/10/2026), reduzida até 800 px, em WebP e na pasta PRIVADA, como a 3x4 (#78).
 *
 * - Enviar, trocar, tirar e ver a prévia: a própria pessoa e quem já pode editar
 *   aquele filiado (Comando Geral ou o admin da equipe dele). Portão "filiado.foto".
 * - Endereço PÚBLICO (para o site): só entrega a foto de quem está na Rede
 *   Nacional (regra única em App\Actions\Filiados\RedeNacional). Para todo o
 *   resto, 404 (não confirma nem que a pessoa existe).
 */

namespace App\Http\Controllers\Cadastros;

use App\Actions\Filiados\RedeNacional;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\FotoRequest;
use App\Models\Auditoria;
use App\Models\Filiado;
use App\Support\FotoDePerfil;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FiliadoFotoRedeController extends Controller
{
    // Pasta (dentro da pasta privada) onde as fotos da Rede Nacional ficam.
    private const PASTA = 'fotos/rede-nacional';

    /**
     * Prévia PROTEGIDA (na ficha e em "Meus dados"). Sem permissão ou sem foto: 404.
     *
     * @param  Filiado  $filiado  O filiado do endereço (ex.: /fotos/rede-nacional/7).
     * @return StreamedResponse A foto, guardada só no navegador de quem pediu, por 1 dia.
     */
    public function show(Filiado $filiado): StreamedResponse
    {
        $disco = Storage::disk(FotoDePerfil::DISCO);

        abort_unless(Gate::allows('filiado.foto', $filiado), 404);
        abort_if($filiado->foto_rede === null || ! $disco->exists($filiado->foto_rede), 404);

        return $disco->response($filiado->foto_rede, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    /**
     * Foto PÚBLICA, para o site: só de quem está na Rede Nacional; o resto recebe 404.
     *
     * @param  Filiado  $filiado  O filiado do endereço (ex.: /imagens/rede-nacional/7).
     * @param  RedeNacional  $rede  A regra de quem aparece.
     * @return StreamedResponse A foto, que navegadores e o site podem guardar por 1 hora.
     */
    public function publica(Filiado $filiado, RedeNacional $rede): StreamedResponse
    {
        $disco = Storage::disk(FotoDePerfil::DISCO);

        // Fora da Rede Nacional (sem autorização, inativo, Praticante...) ou arquivo sumido: 404.
        abort_unless($rede->aparece($filiado) && $disco->exists((string) $filiado->foto_rede), 404);

        return $disco->response((string) $filiado->foto_rede, null, ['Cache-Control' => 'public, max-age=3600']);
    }

    /**
     * Grava a foto nova (inteira, maior lado até 800 px) e apaga a antiga.
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
            // Inteira, sem recorte: no cartão do site a pessoa aparece de corpo inteiro.
            $nova = FotoDePerfil::gravarInteira($request->file('foto'), self::PASTA);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['foto' => 'Não foi possível ler esta foto. Tente outra.']);
        }

        // Grava a nova ANTES de apagar a antiga: nunca fica sem nenhuma no meio do caminho.
        $antiga = $filiado->foto_rede;
        $filiado->forceFill(['foto_rede' => $nova])->save();
        FotoDePerfil::apagar($antiga);

        Auditoria::registrar('FOTO_REDE_ATUALIZADA', ['filiado_id' => $filiado->id]);

        return back()->with('sucesso', 'Foto da Rede Nacional atualizada.');
    }

    /**
     * Tira a foto da Rede Nacional (apaga o arquivo): a pessoa sai da página do site.
     *
     * @param  Filiado  $filiado  O filiado dono da foto.
     * @return RedirectResponse Volta para a tela de onde veio.
     */
    public function destroy(Filiado $filiado): RedirectResponse
    {
        Gate::authorize('filiado.foto', $filiado);

        // Sem foto: nada a fazer.
        if ($filiado->foto_rede !== null) {
            FotoDePerfil::apagar($filiado->foto_rede);
            $filiado->forceFill(['foto_rede' => null])->save();
            Auditoria::registrar('FOTO_REDE_REMOVIDA', ['filiado_id' => $filiado->id]);
        }

        return back()->with('sucesso', 'Foto da Rede Nacional removida.');
    }
}
