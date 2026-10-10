<?php

/*
 * MATRÍCULAS (#41: matrícula com pagamento manual por PIX e trava de vagas;
 * decisão de 29/09/2026: quem confirma o PIX é a secretaria/tesouraria, com a
 * permissão "pagamentos.confirmar"; Gleyson, 10/10/2026: baixa MANUAL no
 * piloto, igual à anuidade).
 *
 * Situações de uma matrícula:
 * - aguardando_pagamento: curso pago; a pessoa paga o PIX e envia o comprovante;
 * - confirmada: curso gratuito (na hora) ou pagamento confirmado pelo IBTO;
 * - cancelada: SEM apagar (a vaga volta e o histórico fica).
 *
 * O comprovante fica no disco PRIVADO (só a pessoa e quem confirma veem).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela e a permissão "pagamentos.confirmar".
     */
    public function up(): void
    {
        Schema::create('curso_matriculas', function (Blueprint $table) {
            $table->id();
            // De qual curso. restrictOnDelete: curso com matrícula não some (cursos não se apagam).
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            // Quem se matriculou.
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            // aguardando_pagamento, confirmada ou cancelada.
            $table->string('situacao', 30);
            // Valor do curso NO DIA da matrícula, em centavos (se o preço mudar depois, este fica).
            $table->unsignedInteger('valor_centavos');
            // Caminho do comprovante no disco privado (vazio até a pessoa enviar).
            $table->string('comprovante', 255)->nullable();
            // A receita no livro-caixa (a "referência do pagamento"); vazia no gratuito.
            $table->foreignId('lancamento_id')->nullable()->constrained('fin_lancamentos')->restrictOnDelete();
            // Quem confirmou e quando (o pagamento ou a matrícula gratuita).
            $table->foreignId('confirmada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmada_em')->nullable();
            // Cancelamento: quando e por quê (a linha nunca é apagada).
            $table->timestamp('cancelada_em')->nullable();
            $table->string('motivo_cancelamento', 500)->nullable();
            $table->timestamps();

            // Busca rápida das matrículas de um curso por situação (contar vagas, listar).
            $table->index(['curso_id', 'situacao']);
        });

        // Permissão de confirmar pagamentos (configurável na tela de Permissões).
        $agora = now();
        $permissaoId = DB::table('permissoes')->insertGetId([
            'slug' => 'pagamentos.confirmar', 'nome' => 'Confirmar pagamentos (PIX) das matrículas',
            'grupo' => 'Cursos', 'ordem' => 83, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        // Começa com o Administrador e a Tecnologia (a secretaria/tesouraria usa um desses papéis).
        foreach (['administrador', 'tecnologia'] as $papel) {
            DB::table('papel_permissao')->insert([
                'papel_id' => DB::table('papeis')->where('slug', $papel)->value('id'),
                'permissao_id' => $permissaoId,
            ]);
        }
    }

    /**
     * Desfaz: apaga a permissão e a tabela.
     */
    public function down(): void
    {
        DB::table('permissoes')->where('slug', 'pagamentos.confirmar')->delete();
        Schema::dropIfExists('curso_matriculas');
    }
};
