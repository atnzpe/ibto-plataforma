<?php

/*
 * NÍVEIS LIVRES (pedido do Gleyson, 18/10/2026): até aqui a hierarquia era uma
 * lista fechada (Praticante, Monitor I–II, Instrutor I–V, Instrutor Expert I–V).
 * Esta migration acrescenta três colunas para o Comando Geral criar níveis novos
 * (ex.: "Grão-Mestre" ou "Monitor III") e mudar a ordem:
 *   - nome_personalizado: o nome escolhido; vazio = nome automático (categoria + grau);
 *   - posicao: a ordem na hierarquia (10, 20, 30...; o mais baixo primeiro);
 *   - letra: a letra do número de série do certificado de graduação; vazio = a da categoria.
 * Os 13 níveis que já existem ganham a posição na ordem de hoje, sem mudar nada.
 * Compatível com o MySQL 5.7 do servidor (só colunas simples).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ordem das categorias, copiada aqui de propósito (a migration não pode mudar
     * se o código mudar depois). Ex.: Praticante vem primeiro.
     *
     * @var array<string, int>
     */
    private const ORDEM_CATEGORIAS = [
        'Praticante' => 1,
        'Monitor' => 2,
        'Instrutor' => 3,
        'Instrutor Expert' => 4,
    ];

    /**
     * Cria as colunas e numera a ordem dos níveis que já existem.
     */
    public function up(): void
    {
        Schema::table('niveis', function (Blueprint $table) {
            // Nome escolhido pelo Comando Geral (até 60 letras); vazio = nome automático.
            $table->string('nome_personalizado', 60)->nullable()->after('grau');
            // Ordem na hierarquia: número menor = nível mais baixo.
            $table->unsignedInteger('posicao')->default(0)->after('nome_personalizado');
            // Uma letra (A a Z) para o número de série; vazio = a letra da categoria.
            $table->char('letra', 1)->nullable()->after('posicao');
        });

        // Lê os níveis de hoje e põe na ordem antiga: categoria e, dentro dela, o grau.
        $niveis = DB::table('niveis')->get(['id', 'categoria', 'grau'])
            ->sortBy([
                // Categoria desconhecida vai para o fim (99), sem quebrar.
                fn ($a, $b) => (self::ORDEM_CATEGORIAS[$a->categoria] ?? 99) <=> (self::ORDEM_CATEGORIAS[$b->categoria] ?? 99),
                fn ($a, $b) => ($a->grau ?? 0) <=> ($b->grau ?? 0),
            ])->values();

        // Grava 10, 20, 30... (o espaço entre eles facilita encaixar um nível no meio).
        foreach ($niveis as $indice => $nivel) {
            DB::table('niveis')->where('id', $nivel->id)->update(['posicao' => ($indice + 1) * 10]);
        }
    }

    /**
     * Desfaz: tira as três colunas (os níveis criados livremente continuam, com o nome automático).
     */
    public function down(): void
    {
        Schema::table('niveis', function (Blueprint $table) {
            $table->dropColumn(['nome_personalizado', 'posicao', 'letra']);
        });
    }
};
