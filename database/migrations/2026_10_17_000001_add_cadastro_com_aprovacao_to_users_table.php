<?php

/*
 * CADASTRO COM APROVAÇÃO (#84; o IBTO é um grupo fechado, só de indicados).
 *
 * A pessoa que se cadastra sozinha nasce "em análise"; o admin da equipe
 * escolhida ou o Comando Geral aprova (vira Aluno) ou recusa com motivo.
 * Contas criadas por convite ou importação nascem "aprovado".
 *
 * Colunas novas na conta:
 * - situacao_cadastro: 'aprovado' (padrão: todas as contas que já existem
 *   continuam entrando), 'em_analise' ou 'recusado';
 * - equipe_solicitada_id: a equipe que a pessoa escolheu no cadastro (define
 *   qual admin de equipe pode analisar);
 * - indicado_por: o nome de quem indicou (texto livre, só para conferência);
 * - cadastro_analisado_por / _em / cadastro_motivo: quem analisou, quando e,
 *   na recusa, o motivo (também vão para a auditoria).
 * Roda no MySQL 5.7.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria as colunas. Contas que já existem ficam 'aprovado' (o padrão).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('situacao_cadastro', 12)->default('aprovado')->after('desativada_em');
            // A equipe escolhida no cadastro. Equipe apagada: fica vazio (nullOnDelete).
            $table->foreignId('equipe_solicitada_id')->nullable()->after('situacao_cadastro')->constrained('equipes')->nullOnDelete();
            $table->string('indicado_por', 150)->nullable()->after('equipe_solicitada_id');
            $table->foreignId('cadastro_analisado_por')->nullable()->after('indicado_por')->constrained('users')->nullOnDelete();
            $table->timestamp('cadastro_analisado_em')->nullable()->after('cadastro_analisado_por');
            $table->string('cadastro_motivo', 500)->nullable()->after('cadastro_analisado_em');
            // A lista "Solicitações de cadastro" filtra por situação: o índice deixa rápido.
            $table->index('situacao_cadastro');
        });
    }

    /**
     * Desfaz: tira as colunas (e as chaves estrangeiras delas).
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['situacao_cadastro']);
            $table->dropConstrainedForeignId('cadastro_analisado_por');
            $table->dropConstrainedForeignId('equipe_solicitada_id');
            $table->dropColumn(['situacao_cadastro', 'indicado_por', 'cadastro_analisado_em', 'cadastro_motivo']);
        });
    }
};
