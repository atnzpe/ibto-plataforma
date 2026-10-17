<?php

/*
 * ACEITES (LGPD, #47): o registro de QUEM aceitou QUAL documento, em QUE versão
 * e QUANDO (ex.: a política de privacidade 0.1 em 17/10/2026 às 10h). Revogar
 * não apaga a linha: grava a data da revogação, para a história ficar completa.
 * Sem IP nem aparelho: só o necessário (LGPD, minimização). Roda no MySQL 5.7.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela dos aceites.
     */
    public function up(): void
    {
        Schema::create('aceites', function (Blueprint $table) {
            $table->id();
            // Quem aceitou. Conta apagada: as linhas dela vão junto.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // O documento. Ex.: 'privacidade'.
            $table->string('documento', 30);
            // A versão do texto aceito. Ex.: '0.1'.
            $table->string('versao', 10);
            $table->timestamp('aceito_em');
            // null = o aceite vale; com data = a pessoa revogou nesse dia.
            $table->timestamp('revogado_em')->nullable();
            $table->timestamps();
            // Busca rápida de "esta pessoa aceitou a versão X?".
            $table->index(['user_id', 'documento', 'versao']);
        });
    }

    /**
     * Desfaz: apaga a tabela.
     */
    public function down(): void
    {
        Schema::dropIfExists('aceites');
    }
};
