<?php

/*
 * AULAS REGULARES POR ASSINATURA (#80): Krav Maga, Kapap, Combate corpo a corpo,
 * online ou presencial, com mensalidade. Diferente do curso (que começa e termina,
 * com certificado), o programa é CONTÍNUO.
 *
 * - programas: a modalidade (ex.: "Krav Maga online"), com instrutor, valor
 *   mensal, divisão IBTO/instrutor (padrão 20/80), vagas e "só filiados".
 * - programa_turmas: os dias e horários (ex.: ter e qui, 19h), presencial (local)
 *   ou online (link da sala).
 * - assinaturas: a pessoa no programa. Vence todo mês no dia de vencimento; até
 *   5 dias depois (carência) o acesso continua; depois, trava SÓ as aulas
 *   (a carteirinha e o passaporte não mudam; decisão de 17/10/2026).
 * - assinatura_pagamentos: uma mensalidade por mês (referência AAAA-MM), paga
 *   pelo Pix e confirmada pelo Comando Geral (receita + repasse ao instrutor).
 * - programa_aulas e programa_presencas: a chamada de cada aula (frequência no dossiê).
 * Roda no MySQL 5.7.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria as tabelas e a categoria de receita "Aulas regulares".
     */
    public function up(): void
    {
        Schema::create('programas', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 150);
            $table->text('descricao')->nullable();
            $table->foreignId('area_atuacao_id')->nullable()->constrained('areas_atuacao')->nullOnDelete();
            // presencial, online ou hibrido (as duas).
            $table->string('modalidade', 20)->default('presencial');
            // O instrutor responsável (conta) e o nome que aparece na vitrine.
            $table->foreignId('instrutor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('instrutor_nome', 150)->nullable();
            // Mensalidade em centavos (sem vírgula). Ex.: 12000 = R$ 120,00.
            $table->unsignedInteger('valor_mensal_centavos');
            // Parte do IBTO (%); o instrutor fica com o resto. Padrão 20 (decisão de 17/10/2026).
            $table->unsignedTinyInteger('percentual_ibto')->default(20);
            $table->unsignedSmallInteger('vagas')->nullable();
            // true = só filiados assinam; false = filiado e aluno indicado.
            $table->boolean('so_filiados')->default(false);
            // Dia do mês em que a mensalidade vence (1 a 28, para existir em todo mês).
            $table->unsignedTinyInteger('dia_vencimento')->default(10);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('programa_turmas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_id')->constrained('programas')->cascadeOnDelete();
            $table->string('nome', 100);
            // Dias da semana, separados por vírgula. Ex.: 'ter,qui'.
            $table->string('dias_semana', 40);
            $table->time('hora_inicio');
            $table->time('hora_fim')->nullable();
            // Presencial: o local. Online: o link da sala (Meet, Zoom, Teams, YouTube).
            $table->string('local', 200)->nullable();
            $table->string('link_sala', 500)->nullable();
            $table->boolean('ativa')->default(true);
            $table->timestamps();
        });

        Schema::create('assinaturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_id')->constrained('programas')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('turma_id')->nullable()->constrained('programa_turmas')->nullOnDelete();
            // aguardando_pagamento, ativa ou cancelada (vencida é calculada pela data).
            $table->string('situacao', 30)->default('aguardando_pagamento');
            // Até quando a mensalidade está paga (o acesso vai até 5 dias depois).
            $table->date('pago_ate')->nullable();
            $table->timestamp('cancelada_em')->nullable();
            $table->timestamps();
            $table->index(['programa_id', 'situacao']);
        });

        Schema::create('assinatura_pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assinatura_id')->constrained('assinaturas')->cascadeOnDelete();
            // O mês pago. Ex.: '2026-11'.
            $table->string('referencia', 7);
            $table->unsignedInteger('valor_centavos');
            // aguardando, pago ou cancelado.
            $table->string('situacao', 20)->default('aguardando');
            $table->date('vence_em');
            $table->timestamp('pago_em')->nullable();
            $table->foreignId('lancamento_id')->nullable()->constrained('fin_lancamentos')->nullOnDelete();
            $table->foreignId('confirmado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // Uma mensalidade por mês por assinatura.
            $table->unique(['assinatura_id', 'referencia']);
        });

        Schema::create('programa_aulas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('programa_turmas')->cascadeOnDelete();
            $table->date('data');
            $table->timestamp('chamada_feita_em')->nullable();
            $table->timestamps();
            $table->unique(['turma_id', 'data']);
        });

        Schema::create('programa_presencas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aula_id')->constrained('programa_aulas')->cascadeOnDelete();
            $table->foreignId('assinatura_id')->constrained('assinaturas')->cascadeOnDelete();
            $table->boolean('presente')->default(false);
            $table->timestamps();
            $table->unique(['aula_id', 'assinatura_id']);
        });

        // A categoria de receita das mensalidades no livro-caixa (o repasse usa "Instrutores").
        $agora = now();
        DB::table('fin_categorias')->insertOrIgnore(['nome' => 'Aulas regulares', 'tipo' => 'receita', 'ativa' => true, 'created_at' => $agora, 'updated_at' => $agora]);
    }

    /**
     * Desfaz: apaga as tabelas (a categoria fica, se já tiver lançamentos).
     */
    public function down(): void
    {
        Schema::dropIfExists('programa_presencas');
        Schema::dropIfExists('programa_aulas');
        Schema::dropIfExists('assinatura_pagamentos');
        Schema::dropIfExists('assinaturas');
        Schema::dropIfExists('programa_turmas');
        Schema::dropIfExists('programas');
    }
};
