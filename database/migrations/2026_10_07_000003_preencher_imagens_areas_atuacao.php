<?php

/*
 * Coloca nas 15 áreas de atuação as imagens que o site já usa (#37, pedido do
 * Gleyson em 07/10/2026). As imagens vieram de landpage/public/images/services
 * e estão em database/dados/areas-atuacao/ (WEBP de 38 a 51 KB, conferidas).
 *
 * Por que numa migration: o servidor não tem SSH, e a migration roda sozinha
 * pelo Cron (plataforma:pos-deploy). Ela é segura para rodar mais de uma vez:
 * só preenche a área que ainda NÃO tem imagem (não apaga uma imagem trocada
 * pela tela) e não copia de novo um arquivo que já está na pasta.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Nome da área => arquivo da imagem (mesma ligação do site, em content.ts).
     *
     * @var array<string, string>
     */
    private const IMAGENS = [
        'Operador de Armas de Fogo' => 'operador-armas-de-fogo.webp',
        'Tactical Combat Casualty Care' => 'tccc.webp',
        'Sobrevivência Urbana' => 'sobrevivencia-urbana.webp',
        'Krav Maga' => 'krav-maga.webp',
        'Combate com Faca' => 'combate-com-faca.webp',
        'Selva' => 'selva.webp',
        'Tiro Tático' => 'tiro-tatico.webp',
        'APH Tático' => 'aph-tatico.webp',
        'Combate Corpo-a-corpo' => 'combate-corpo-a-corpo.webp',
        'Defesa Pessoal' => 'defesa-pessoal.webp',
        'Combate com Bastão' => 'combate-com-bastao.webp',
        'KAPAP' => 'kapap.webp',
        'Operações com Drones' => 'operacoes-com-drones.webp',
        'Imobilizações Táticas' => 'imobilizacoes-taticas.webp',
        'Close Quarters Battle' => 'close-quarters-battle.webp',
    ];

    /**
     * Copia cada imagem para storage/app/public/areas-atuacao e grava o caminho na área.
     */
    public function up(): void
    {
        $disco = Storage::disk('public');

        foreach (self::IMAGENS as $nome => $arquivo) {
            // Onde a imagem está no projeto (vai junto no deploy).
            $origem = database_path("dados/areas-atuacao/{$arquivo}");
            // Onde ela fica para a plataforma entregar (ex.: "areas-atuacao/selva.webp").
            $destino = "areas-atuacao/{$arquivo}";

            // Arquivo de origem faltando: avisa no log (nada em silêncio, #58) e segue para a próxima.
            if (! is_file($origem)) {
                logger()->error('AREA_IMAGEM_ORIGEM_FALTANDO', ['arquivo' => $arquivo]);

                continue;
            }

            // Só copia se ainda não está na pasta (rodar de novo não duplica nada).
            if (! $disco->exists($destino)) {
                $disco->put($destino, (string) file_get_contents($origem));
            }

            // Grava o caminho só na área com esse nome que AINDA não tem imagem.
            DB::table('areas_atuacao')
                ->where('nome', $nome)
                ->whereNull('imagem')
                ->update(['imagem' => $destino, 'updated_at' => now()]);
        }
    }

    /**
     * Desfaz: tira a ligação só das áreas que ainda usam a imagem colocada aqui
     * (uma imagem trocada pela tela continua). Os arquivos ficam na pasta.
     */
    public function down(): void
    {
        DB::table('areas_atuacao')
            ->whereIn('imagem', array_map(fn (string $arquivo) => "areas-atuacao/{$arquivo}", self::IMAGENS))
            ->update(['imagem' => null]);
    }
};
