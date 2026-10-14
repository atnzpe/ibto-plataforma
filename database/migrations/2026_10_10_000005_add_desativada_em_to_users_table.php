<?php

/*
 * DESATIVAR CONTA (#36; pedido do Gleyson em 10/10/2026: botão de "soft
 * delete" na tela de Usuários).
 *
 * "Desativar" NÃO apaga nada: a conta ganha a data em que foi desativada e,
 * enquanto ela estiver preenchida, a pessoa não consegue entrar (o porteiro
 * BloquearContaDesativada confere). "Reativar" limpa a data. O nome continua
 * nas listas, no financeiro e na auditoria (por isso não usamos o SoftDeletes
 * do Laravel, que esconderia a conta de todas as consultas).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a coluna "desativada_em" (vazia = conta ativa).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Quando a conta foi desativada. NULL = ativa (todas as contas de hoje).
            $table->timestamp('desativada_em')->nullable()->after('email_verified_at');
        });
    }

    /**
     * Desfaz: tira a coluna (todas as contas voltam a poder entrar).
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('desativada_em');
        });
    }
};
