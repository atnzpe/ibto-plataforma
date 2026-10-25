<?php

/*
 * PAÍS DO FILIADO (#11, decisão do Gleyson em 24/10/2026).
 *
 * Até aqui todo filiado era do Brasil (só havia município e UF). Para a Rede
 * Nacional separar os credenciados por país, o filiado ganha o país: a sigla
 * internacional de 2 letras (BR, PT, US...). Todos os que já existem ficam
 * como Brasil ('BR', o padrão). Quem mora fora do Brasil não tem UF e recebe o
 * registro com a sigla do país no início (ex.: PT20260046).
 * Compatível com o MySQL 5.7 do servidor (uma coluna simples com valor padrão).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a coluna, logo depois da UF, com o Brasil como padrão.
     */
    public function up(): void
    {
        Schema::table('filiados', function (Blueprint $table) {
            // Sigla do país (ex.: 'BR'); os que já existem ficam como Brasil.
            $table->char('pais', 2)->default('BR')->after('uf');
        });
    }

    /**
     * Desfaz: tira a coluna (todos voltam a ser tratados como do Brasil).
     */
    public function down(): void
    {
        Schema::table('filiados', function (Blueprint $table) {
            $table->dropColumn('pais');
        });
    }
};
