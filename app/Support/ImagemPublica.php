<?php

/*
 * Guarda a imagem enviada num cadastro (imagem da área, logo da equipe...) e
 * apaga a antiga (#37). Fica num lugar só para as duas telas usarem a mesma regra.
 *
 * Os arquivos vão para storage/app/public (fora da parte pública do site) e
 * são entregues por rotas próprias, sem precisar do storage:link (servidor sem SSH).
 */

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImagemPublica
{
    /**
     * Troca a imagem de um registro.
     *
     * @param  Model  $modelo  O registro dono da imagem. Ex.: a área "Selva".
     * @param  string  $coluna  A coluna que guarda o caminho. Ex.: 'imagem' ou 'logo'.
     * @param  string  $pasta  Pasta dentro de storage/app/public. Ex.: 'areas-atuacao'.
     * @param  UploadedFile|null  $arquivo  O arquivo enviado; null = a pessoa não escolheu imagem nova (nada muda).
     */
    public static function trocar(Model $modelo, string $coluna, string $pasta, ?UploadedFile $arquivo): void
    {
        // Nenhuma imagem nova escolhida: mantém a atual (ou nenhuma).
        if ($arquivo === null) {
            return;
        }

        $disco = Storage::disk('public');

        // Nome aleatório (ex.: "areas-atuacao/a1b2c3.webp"): o nome original pode
        // ter acentos, espaços ou até dados pessoais.
        $caminhoNovo = $disco->putFile($pasta, $arquivo);

        // Falhou ao gravar (ex.: disco cheio): não deixa passar em silêncio (#58).
        if ($caminhoNovo === false) {
            throw new \RuntimeException('IMAGEM_NAO_GRAVADA');
        }

        // Lembra o caminho antigo antes de trocar, para apagar depois.
        $caminhoAntigo = $modelo->getAttribute($coluna);

        // forceFill: a coluna da imagem não é preenchível pelo formulário (segurança).
        $modelo->forceFill([$coluna => $caminhoNovo])->save();

        // Só apaga a antiga depois que a nova foi gravada e salva no banco.
        if (is_string($caminhoAntigo)) {
            $disco->delete($caminhoAntigo);
        }
    }
}
