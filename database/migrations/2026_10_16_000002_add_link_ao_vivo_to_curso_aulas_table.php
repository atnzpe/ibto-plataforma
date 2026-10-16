<?php

/*
 * SALA AO VIVO (#71): o link da sala (Google Meet, Zoom, Teams ou live do
 * YouTube) de um encontro ONLINE ao vivo. Opcional: aula gravada e encontro
 * presencial ficam sem. Só a matrícula confirmada vê o link, e só na janela
 * do encontro (regras em App\Support\SalaAoVivo).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a coluna do link, logo depois do horário de fim do encontro.
     */
    public function up(): void
    {
        Schema::table('curso_aulas', function (Blueprint $table) {
            // 500 caracteres: os links do Teams são longos.
            $table->string('link_ao_vivo', 500)->nullable()->after('hora_fim');
        });
    }

    /**
     * Desfaz: tira a coluna.
     */
    public function down(): void
    {
        Schema::table('curso_aulas', function (Blueprint $table) {
            $table->dropColumn('link_ao_vivo');
        });
    }
};
