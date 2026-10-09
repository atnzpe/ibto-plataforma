<?php

/*
 * CATEGORIA do filiado (#37, Gleyson 09/10/2026): a FUNÇÃO no IBTO (aluno,
 * monitor, instrutor, instrutor expert, adm de equipe). O NÍVEL (graduação)
 * continua no campo nivel_id. Valores em App\Enums\CategoriaFiliado.
 * Vazia (null) enquanto não informada (ex.: filiado antigo).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('filiados', function (Blueprint $table) {
            // Ex.: "instrutor_expert". Fica antes do nível, na ordem em que aparece na tela.
            $table->string('categoria', 30)->nullable()->after('equipe_id');
        });
    }

    public function down(): void
    {
        Schema::table('filiados', function (Blueprint $table) {
            $table->dropColumn('categoria');
        });
    }
};
