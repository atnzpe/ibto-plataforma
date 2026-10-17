<?php

/*
 * DESFOQUE POR ÁREA NAS FOTOS DA GALERIA (#90, pedido do Gleyson em 17/10/2026).
 *
 * - original: onde fica a foto SEM desfoque, numa pasta PRIVADA (só o Comando
 *   Geral, para o botão "Restaurar original"). null = a foto nunca foi desfocada.
 * - desfoque: as áreas marcadas, em frações da foto (0 a 1), para o editor abrir
 *   com elas de novo. Ex.: [{"x":0.1,"y":0.2,"w":0.15,"h":0.2}].
 * Texto (e não JSON) para rodar igual no MySQL 5.7 e nos testes.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria as duas colunas.
     */
    public function up(): void
    {
        Schema::table('galeria_fotos', function (Blueprint $table) {
            $table->string('original')->nullable();
            $table->text('desfoque')->nullable();
        });
    }

    /**
     * Desfaz: tira as colunas.
     */
    public function down(): void
    {
        Schema::table('galeria_fotos', function (Blueprint $table) {
            $table->dropColumn(['original', 'desfoque']);
        });
    }
};
