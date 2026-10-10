<?php

/*
 * ANUIDADE do filiado (#37, decisões do Gleyson em 09/10/2026):
 * - anuidades: o valor de cada ano (configurável). 2026 é isento; 2027 custa R$ 90,00.
 * - anuidade_pagamentos: a "baixa" de cada filiado em cada ano, feita à mão pelo
 *   administrador, até existir o pagamento online (#42). Cada pagamento é LIGADO
 *   à receita que ele gerou no livro-caixa (#59, bloco 3): a forma de pagamento
 *   e o estorno vêm de lá.
 * Vale pelo ANO CIVIL: pagou a de 2027 = em dia até 31/12/2027.
 * Roda DEPOIS das tabelas do financeiro (precisa de fin_lancamentos).
 *
 * Os valores iniciais entram pela própria migration (o servidor não tem SSH para
 * rodar seeder; o Cron aplica no pós-deploy). Compatível com o MySQL 5.7.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria as duas tabelas e coloca os valores de 2026 e 2027.
     */
    public function up(): void
    {
        Schema::create('anuidades', function (Blueprint $table) {
            // Número de identificação, criado pelo banco.
            $table->id();
            // O ano. Ex.: 2027. Único: não existem dois valores para o mesmo ano.
            $table->unsignedSmallInteger('ano')->unique();
            // Valor em CENTAVOS (R$ 90,00 = 9000): número inteiro não erra conta
            // de dinheiro, como acontece com números com vírgula.
            $table->unsignedInteger('valor_centavos')->default(0);
            // Ano isento: ninguém paga e todos ficam em dia. Ex.: 2026.
            $table->boolean('isenta')->default(false);
            $table->timestamps();
        });

        Schema::create('anuidade_pagamentos', function (Blueprint $table) {
            $table->id();
            // De quem é o pagamento. restrictOnDelete: o banco não deixa apagar um
            // filiado que tem pagamento (o histórico de dinheiro não pode sumir).
            $table->foreignId('filiado_id')->constrained('filiados')->restrictOnDelete();
            // De qual ano é a anuidade paga. Ex.: 2027.
            $table->unsignedSmallInteger('ano');
            // Valor recebido, em centavos (pode ser diferente do valor do ano, ex.: desconto).
            $table->unsignedInteger('valor_centavos');
            // Dia em que o dinheiro entrou. Ex.: 2027-03-15.
            $table->date('pago_em');
            // A receita que este pagamento gerou no livro-caixa (forma de pagamento,
            // estorno e histórico ficam lá). unique: uma receita, um pagamento.
            $table->foreignId('lancamento_id')->unique()->constrained('fin_lancamentos')->restrictOnDelete();
            // Quem deu a baixa (vazio se a conta dessa pessoa for apagada; o
            // pagamento continua).
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // Índice para achar rápido os pagamentos de um filiado num ano. Não é
            // "unique": se um pagamento for ESTORNADO, a pessoa pode pagar de novo.
            // A regra "um pagamento válido por ano" fica no serviço (App\Services\Anuidades).
            $table->index(['filiado_id', 'ano']);
        });

        // Valores iniciais (decisão do Gleyson, 09/10/2026).
        $agora = now();
        DB::table('anuidades')->insert([
            ['ano' => 2026, 'valor_centavos' => 0, 'isenta' => true, 'created_at' => $agora, 'updated_at' => $agora],
            ['ano' => 2027, 'valor_centavos' => 9000, 'isenta' => false, 'created_at' => $agora, 'updated_at' => $agora],
        ]);
    }

    /**
     * Desfaz: apaga as tabelas (os pagamentos primeiro, porque apontam para os filiados).
     */
    public function down(): void
    {
        Schema::dropIfExists('anuidade_pagamentos');
        Schema::dropIfExists('anuidades');
    }
};
