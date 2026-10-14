<?php

/*
 * CURSOS e TRILHAS (#39 / #38, bloco C1; esboço do Gleyson de 08/10/2026 e
 * desenho aprovado em 10/10/2026):
 * - cursos: nome, descrição, área, modalidade (presencial/online), acesso
 *   (aberto/restrito), valor em centavos (0 = gratuito), vagas, datas,
 *   horário, local, carga horária, instrutor, imagem, link de pagamento e
 *   situação (rascunho → publicado → encerrado);
 * - trilhas: um conjunto de cursos EM ORDEM (ex.: formação do Praticante ao
 *   Instrutor Expert);
 * - curso_trilha: quais cursos estão em cada trilha e em que posição.
 * Compatível com o MySQL 5.7. Não existe "apagar": desativa ou encerra.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria as três tabelas e a permissão "cursos.gerenciar".
     */
    public function up(): void
    {
        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            // Nome do curso. Ex.: 'Combate Corpo a Corpo'.
            $table->string('titulo', 150);
            // O que se aprende, para quem é, pré-requisitos.
            $table->text('descricao')->nullable();
            // Área de atuação (ex.: Defesa pessoal). restrict: área usada não some.
            $table->foreignId('area_atuacao_id')->nullable()->constrained('areas_atuacao')->restrictOnDelete();
            // 'presencial' ou 'online'.
            $table->string('modalidade', 12);
            // 'aberto' (qualquer pessoa) ou 'restrito' (só filiados ativos).
            $table->string('acesso', 10);
            // Valor da inscrição em CENTAVOS; 0 = gratuito.
            $table->unsignedInteger('valor_centavos')->default(0);
            // Número de vagas; vazio = sem limite (comum em curso online).
            $table->unsignedSmallInteger('vagas')->nullable();
            // Datas e horário (presencial); vazios no online "no seu ritmo".
            $table->date('data_inicio')->nullable();
            $table->date('data_fim')->nullable();
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fim')->nullable();
            // Cidade e lugar do curso presencial. Ex.: 'Recife · PE'.
            $table->string('local', 150)->nullable();
            // Carga horária em horas. Ex.: 12.
            $table->unsignedSmallInteger('carga_horaria')->nullable();
            // Instrutor responsável (vazio se a conta for apagada; o curso continua).
            $table->foreignId('instrutor_id')->nullable()->constrained('users')->nullOnDelete();
            // Caminho da imagem de capa (dentro de storage/app/public).
            $table->string('imagem')->nullable();
            // Link de pagamento (PIX/checkout) enquanto não há gateway (#42).
            $table->string('link_pagamento', 500)->nullable();
            // 'rascunho' (só o Comando Geral vê), 'publicado' ou 'encerrado'.
            $table->string('situacao', 12)->default('rascunho');
            $table->timestamps();
            // Busca rápida dos publicados por data (catálogo).
            $table->index(['situacao', 'data_inicio']);
        });

        Schema::create('trilhas', function (Blueprint $table) {
            $table->id();
            // Ex.: 'Formação do Praticante ao Instrutor Expert'. Único.
            $table->string('nome', 120)->unique();
            $table->text('descricao')->nullable();
            // Desativada some para o aluno, sem apagar.
            $table->boolean('ativa')->default(true);
            $table->timestamps();
        });

        Schema::create('curso_trilha', function (Blueprint $table) {
            $table->foreignId('trilha_id')->constrained('trilhas')->cascadeOnDelete();
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            // Posição do curso na trilha (1 = primeira etapa).
            $table->unsignedSmallInteger('ordem');
            // Um curso aparece uma vez em cada trilha.
            $table->primary(['trilha_id', 'curso_id']);
        });

        // Permissão do Comando Geral para cuidar de cursos e trilhas.
        $agora = now();
        $permissaoId = DB::table('permissoes')->insertGetId([
            'slug' => 'cursos.gerenciar',
            'nome' => 'Cadastrar cursos e trilhas',
            'grupo' => 'Cursos',
            'ordem' => 80,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);
        foreach (['administrador', 'tecnologia'] as $papel) {
            DB::table('papel_permissao')->insert([
                'papel_id' => DB::table('papeis')->where('slug', $papel)->value('id'),
                'permissao_id' => $permissaoId,
            ]);
        }
    }

    /**
     * Desfaz: apaga a permissão e as tabelas (a ligação primeiro).
     */
    public function down(): void
    {
        DB::table('permissoes')->where('slug', 'cursos.gerenciar')->delete();
        Schema::dropIfExists('curso_trilha');
        Schema::dropIfExists('trilhas');
        Schema::dropIfExists('cursos');
    }
};
