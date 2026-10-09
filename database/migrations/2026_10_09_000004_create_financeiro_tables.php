<?php

/*
 * FINANCEIRO do IBTO (#59): o LIVRO-CAIXA, no molde do Dojo Manager, adaptado.
 * - fin_categorias: para que é o dinheiro (ex.: Anuidade = receita; Taxas bancárias = despesa).
 * - fin_formas_pagamento: como o dinheiro entrou ou saiu (PIX, dinheiro...).
 * - fin_lancamentos: cada conta a receber (receita) ou a pagar (despesa).
 *
 * Regras (Gleyson, 09/10/2026): valor em CENTAVOS; vencimento separado da data
 * do pagamento; lançamento PAGO nunca muda: corrige-se com um ESTORNO ligado a ele.
 * Os cadastros iniciais entram pela própria migration (sem SSH no servidor).
 * Compatível com o MySQL 5.7.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Categorias iniciais (decisão do Gleyson, 09/10/2026): nome => tipo.
     *
     * @var array<string, string>
     */
    private const CATEGORIAS_INICIAIS = [
        'Anuidade' => 'receita',
        'Inscrição em curso' => 'receita',
        'Doação' => 'receita',
        'Outras receitas' => 'receita',
        'Hospedagem e sistemas' => 'despesa',
        'Material de curso' => 'despesa',
        'Instrutores' => 'despesa',
        'Taxas bancárias' => 'despesa',
        'Outras despesas' => 'despesa',
    ];

    /**
     * Formas de pagamento iniciais (decisão do Gleyson, 09/10/2026).
     *
     * @var list<string>
     */
    private const FORMAS_INICIAIS = ['PIX', 'Dinheiro', 'Transferência', 'Cartão'];

    /**
     * Cria as três tabelas e coloca os cadastros iniciais.
     */
    public function up(): void
    {
        Schema::create('fin_categorias', function (Blueprint $table) {
            $table->id();
            // Nome que aparece na tela. Ex.: 'Anuidade'.
            $table->string('nome', 100);
            // 'receita' (dinheiro que entra) ou 'despesa' (dinheiro que sai).
            $table->string('tipo', 10);
            // Desativada some das opções novas, mas continua nos lançamentos antigos.
            $table->boolean('ativa')->default(true);
            $table->timestamps();
            // Não existem duas categorias com o mesmo nome no mesmo tipo.
            $table->unique(['nome', 'tipo']);
        });

        Schema::create('fin_formas_pagamento', function (Blueprint $table) {
            $table->id();
            // Ex.: 'PIX'. Único.
            $table->string('nome', 50)->unique();
            $table->boolean('ativa')->default(true);
            $table->timestamps();
        });

        Schema::create('fin_lancamentos', function (Blueprint $table) {
            $table->id();
            // 'receita' (a receber) ou 'despesa' (a pagar).
            $table->string('tipo', 10);
            // restrictOnDelete: o banco não deixa apagar uma categoria que já foi usada.
            $table->foreignId('categoria_id')->constrained('fin_categorias')->restrictOnDelete();
            // Como foi pago (vazio enquanto pendente).
            $table->foreignId('forma_pagamento_id')->nullable()->constrained('fin_formas_pagamento')->restrictOnDelete();
            // O que é. Ex.: 'Anuidade 2027 — BR20270001PE' ou 'Hospedagem HostGator (out/2027)'.
            $table->string('descricao', 200);
            // Valor em CENTAVOS (R$ 90,00 = 9000), sempre maior que zero.
            $table->unsignedInteger('valor_centavos');
            // Até quando deve ser pago. Ex.: 2027-03-31.
            $table->date('vencimento');
            // Dia em que foi pago de verdade (vazio enquanto pendente).
            $table->date('pago_em')->nullable();
            // 'pendente', 'pago' ou 'cancelado' (só pendente pode ser cancelado).
            $table->string('situacao', 10)->default('pendente');
            // Por que foi cancelado ou estornado (obrigatório nesses casos).
            $table->string('motivo', 300)->nullable();
            // Se este lançamento é um ESTORNO, aponta para o original. unique: um
            // original só pode ser estornado uma vez.
            $table->foreignId('estorno_de_id')->nullable()->unique()->constrained('fin_lancamentos')->restrictOnDelete();
            // Equipe ligada ao lançamento (opcional). Ex.: inscrição de alguém da equipe X.
            $table->foreignId('equipe_id')->nullable()->constrained('equipes')->restrictOnDelete();
            // De onde veio, quando foi gerado por outra tela. Ex.: origem 'anuidade' + origem_id 12.
            $table->string('origem', 30)->nullable();
            $table->unsignedBigInteger('origem_id')->nullable();
            // Quem lançou (vazio se a conta dessa pessoa for apagada; o lançamento continua).
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // Índices: deixam rápidas as buscas de vencidos e do saldo do mês.
            $table->index(['situacao', 'vencimento']);
            $table->index('pago_em');
            // Uma origem só gera um lançamento (ex.: a mesma anuidade não vira duas receitas).
            $table->unique(['origem', 'origem_id']);
        });

        // Cadastros iniciais.
        $agora = now();
        foreach (self::CATEGORIAS_INICIAIS as $nome => $tipo) {
            DB::table('fin_categorias')->insert(['nome' => $nome, 'tipo' => $tipo, 'ativa' => true, 'created_at' => $agora, 'updated_at' => $agora]);
        }
        foreach (self::FORMAS_INICIAIS as $nome) {
            DB::table('fin_formas_pagamento')->insert(['nome' => $nome, 'ativa' => true, 'created_at' => $agora, 'updated_at' => $agora]);
        }
    }

    /**
     * Desfaz: apaga as tabelas (os lançamentos primeiro, porque apontam para as outras).
     */
    public function down(): void
    {
        Schema::dropIfExists('fin_lancamentos');
        Schema::dropIfExists('fin_formas_pagamento');
        Schema::dropIfExists('fin_categorias');
    }
};
