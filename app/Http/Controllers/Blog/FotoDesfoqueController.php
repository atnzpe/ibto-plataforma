<?php

/*
 * DESFOCAR ÁREAS DE UMA FOTO DA GALERIA (#90, pedido do Gleyson em 17/10/2026).
 *
 * No álbum (Comando Geral), "Desfocar áreas" abre a foto; a pessoa marca os
 * retângulos (rosto, distintivo, placa) e salva. Regras:
 * - Na PRIMEIRA vez, a foto original vai para uma pasta PRIVADA (disco "local"),
 *   que só serve para o botão "Restaurar original".
 * - O desfoque é sempre feito a partir do ORIGINAL, com a lista completa de
 *   áreas (o editor abre com as áreas já marcadas). Assim, tirar uma área e
 *   salvar de novo "desfaz" só aquela área.
 * - Quem vê a galeria recebe só a versão desfocada.
 * - Desfocar e restaurar vão para a auditoria.
 */

namespace App\Http\Controllers\Blog;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\GaleriaFoto;
use App\Support\DesfocarAreas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class FotoDesfoqueController extends Controller
{
    /** O disco onde fica a foto da galeria (o mesmo do envio). */
    private const DISCO_GALERIA = 'public';

    /** O disco PRIVADO dos originais (fora da pasta pública). */
    private const DISCO_ORIGINAIS = 'local';

    /**
     * Aplica o desfoque nas áreas enviadas.
     *
     * @param  Request  $request  Com 'areas': lista de {x, y, w, h} em frações (0 a 1).
     * @param  GaleriaFoto  $foto  A foto do endereço (ex.: /comando-geral/galeria/fotos/12/desfoque).
     * @return RedirectResponse De volta ao álbum, com o aviso.
     */
    public function desfocar(Request $request, GaleriaFoto $foto): RedirectResponse
    {
        $dados = $request->validate([
            // De 1 a 20 áreas; cada uma dentro da foto (0 a 1) e com tamanho mínimo de 1% (senão nem se vê).
            'areas' => ['required', 'array', 'min:1', 'max:'.DesfocarAreas::MAXIMO_DE_AREAS],
            'areas.*.x' => ['required', 'numeric', 'between:0,1'],
            'areas.*.y' => ['required', 'numeric', 'between:0,1'],
            'areas.*.w' => ['required', 'numeric', 'between:0.01,1'],
            'areas.*.h' => ['required', 'numeric', 'between:0.01,1'],
        ], [
            'areas.required' => 'Marque pelo menos uma área na foto.',
            'areas.max' => 'No máximo '.DesfocarAreas::MAXIMO_DE_AREAS.' áreas por foto.',
        ]);

        // Só números, na ordem certa (o que vier a mais no pedido é ignorado).
        $areas = array_values(array_map(fn (array $a) => [
            'x' => (float) $a['x'], 'y' => (float) $a['y'], 'w' => (float) $a['w'], 'h' => (float) $a['h'],
        ], $dados['areas']));

        $galeria = Storage::disk(self::DISCO_GALERIA);
        $originais = Storage::disk(self::DISCO_ORIGINAIS);

        // Primeira vez: guarda o original na pasta privada (antes de mexer em qualquer coisa).
        if ($foto->original === null) {
            $destino = 'galeria-originais/'.$foto->album_id.'/'.basename($foto->caminho);
            $originais->put($destino, (string) $galeria->get($foto->caminho));
            $foto->forceFill(['original' => $destino])->save();
        }

        try {
            $desfocada = DesfocarAreas::aplicar((string) $originais->get($foto->original), $areas, $this->formato($foto->caminho));
        } catch (RuntimeException) {
            // Arquivo que não abre como imagem: avisa sem detalhes internos.
            throw ValidationException::withMessages(['areas' => 'Não foi possível abrir esta foto para desfocar (DESFOQUE_IMAGEM_INVALIDA).']);
        }

        $galeria->put($foto->caminho, $desfocada);
        // Grava as áreas e muda a data (o "?v=" do endereço muda e o navegador pega a nova).
        $foto->forceFill(['desfoque' => json_encode($areas)])->touch();
        Auditoria::registrar('FOTO_DESFOCADA', ['foto_id' => $foto->id, 'areas' => count($areas)]);

        return back()->with('sucesso', 'Áreas desfocadas. Quem vê a galeria recebe só esta versão.');
    }

    /**
     * Volta a foto original (tira todo o desfoque) e apaga a cópia privada.
     *
     * @param  GaleriaFoto  $foto  A foto do endereço.
     * @return RedirectResponse De volta ao álbum, com o aviso.
     */
    public function restaurar(GaleriaFoto $foto): RedirectResponse
    {
        // Nunca foi desfocada: não há o que restaurar.
        if ($foto->original === null) {
            return back()->with('sucesso', 'Esta foto não tem desfoque.');
        }

        $originais = Storage::disk(self::DISCO_ORIGINAIS);
        Storage::disk(self::DISCO_GALERIA)->put($foto->caminho, (string) $originais->get($foto->original));
        $originais->delete($foto->original);
        $foto->forceFill(['original' => null, 'desfoque' => null])->touch();
        Auditoria::registrar('FOTO_DESFOQUE_RESTAURADO', ['foto_id' => $foto->id]);

        return back()->with('sucesso', 'Foto original restaurada (sem desfoque).');
    }

    /**
     * O formato pela extensão do arquivo.
     *
     * @param  string  $caminho  Ex.: 'galeria/3/abc.webp'.
     * @return string 'jpg', 'png' ou 'webp'.
     */
    private function formato(string $caminho): string
    {
        $extensao = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));

        return in_array($extensao, ['png', 'webp'], true) ? $extensao : 'jpg';
    }
}
