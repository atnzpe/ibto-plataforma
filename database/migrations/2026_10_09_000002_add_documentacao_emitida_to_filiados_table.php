<?php

/*
 * "Documentação do IBTO emitida" (#37, Mestre e Gleyson, 09/10/2026).
 *
 * Alguns filiados entraram no IBTO já graduados por OUTRA entidade e nunca
 * receberam carteirinha nem certificado do IBTO; recebem na próxima graduação.
 * true = já recebeu a documentação do IBTO; false = ainda não.
 * Começa em true (a maioria já tem); as exceções são desmarcadas na tela.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('filiados', function (Blueprint $table) {
            $table->boolean('documentacao_emitida')->default(true)->after('abrangencia');
        });
    }

    public function down(): void
    {
        Schema::table('filiados', function (Blueprint $table) {
            $table->dropColumn('documentacao_emitida');
        });
    }
};
