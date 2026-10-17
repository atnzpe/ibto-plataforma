<?php

/*
 * LIMPEZA AUTOMÁTICA DAS FOTOS À VENDA (#90), pela saúde do servidor.
 *
 * Roda uma vez por dia (agendador, routes/console.php). Para cada álbum cuja data
 * de expiração já passou: apaga o original e a prévia de cada foto, tira as fotos
 * do banco e marca o álbum como "expirado". Os pedidos e os lançamentos ficam
 * (histórico e financeiro); os itens dos pedidos perdem só a foto.
 * Como a validade dos produtos no estoque: venceu, sai da prateleira.
 */

namespace App\Console\Commands;

use App\Models\Auditoria;
use App\Models\FotoAlbumVenda;
use App\Models\FotoVenda;
use App\Support\FotoComMarcaDagua;
use Illuminate\Console\Command;

class ExpirarFotosVenda extends Command
{
    protected $signature = 'fotos:expirar';

    protected $description = 'Apaga as fotos dos álbuns à venda que já expiraram (guarda pedidos e financeiro)';

    /**
     * Expira os álbuns vencidos.
     *
     * @return int 0 = deu certo.
     */
    public function handle(): int
    {
        $vencidos = FotoAlbumVenda::query()->where('expirado', false)->whereDate('expira_em', '<', today())->get();

        foreach ($vencidos as $album) {
            // Apaga foto por foto (arquivos e linha), em lotes, para não carregar tudo na memória.
            $apagadas = 0;
            $album->fotos()->chunkById(100, function ($fotos) use (&$apagadas): void {
                foreach ($fotos as $foto) {
                    /** @var FotoVenda $foto */
                    FotoComMarcaDagua::apagar($foto->original, $foto->previa);
                    $foto->delete();
                    $apagadas++;
                }
            });

            $album->forceFill(['expirado' => true, 'ativo' => false])->save();
            Auditoria::registrar('ALBUM_VENDA_EXPIRADO', ['album_id' => $album->id, 'fotos_apagadas' => $apagadas]);
        }

        $this->info($vencidos->count().' álbum(ns) expirado(s).');

        return self::SUCCESS;
    }
}
