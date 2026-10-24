<?php

/*
 * FOTO DA REDE NACIONAL (#11, pedido do Mestre Eduardo em 23/10/2026).
 *
 * A página Rede Nacional do site mostra a foto PERSONALIZADA de cada monitor e
 * instrutor (ex.: de uniforme, com o fundo do IBTO), organizada por estado. Ela
 * é diferente da foto 3x4 da carteirinha (documento), por isso ganha a própria
 * coluna: guarda o caminho do arquivo na pasta PRIVADA (o mesmo cuidado da 3x4).
 * Vazia = a pessoa não aparece na Rede Nacional.
 * Compatível com o MySQL 5.7 do servidor (uma coluna simples).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a coluna, logo depois da foto 3x4.
     */
    public function up(): void
    {
        Schema::table('filiados', function (Blueprint $table) {
            // Caminho da foto (ex.: 'fotos/rede-nacional/9f1c...webp'); null = sem foto.
            $table->string('foto_rede')->nullable()->after('foto');
        });
    }

    /**
     * Desfaz: tira a coluna (os arquivos ficam na pasta, sem uso).
     */
    public function down(): void
    {
        Schema::table('filiados', function (Blueprint $table) {
            $table->dropColumn('foto_rede');
        });
    }
};
