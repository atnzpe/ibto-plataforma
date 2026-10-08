<?php

/*
 * Acrescenta a IMAGEM de cada área de atuação (#37, pedido do Gleyson em 07/10/2026).
 *
 * A coluna guarda só o CAMINHO do arquivo (ex.: "areas-atuacao/a1b2c3.webp"),
 * dentro de storage/app/public. O arquivo em si fica fora da parte pública do
 * site e é entregue pela rota "areas-atuacao.imagem" (sem precisar do comando
 * storage:link, que o servidor sem SSH não roda).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a coluna "imagem", vazia (null) para as áreas que ainda não têm imagem.
     */
    public function up(): void
    {
        Schema::table('areas_atuacao', function (Blueprint $table) {
            // Caminho do arquivo; null = área sem imagem (o site usa uma imagem padrão).
            $table->string('imagem', 255)->nullable()->after('nome');
        });
    }

    /**
     * Desfaz: tira a coluna (os arquivos já enviados continuam na pasta).
     */
    public function down(): void
    {
        Schema::table('areas_atuacao', function (Blueprint $table) {
            $table->dropColumn('imagem');
        });
    }
};
