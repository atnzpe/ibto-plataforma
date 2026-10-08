<?php

/*
 * Cria a tabela dos NÍVEIS do IBTO (#37) e já coloca os 13 níveis da lista do
 * Gleyson (07/10/2026): Praticante; Monitor I–II; Instrutor I–V; Instrutor Expert I–V.
 *
 * Como nas áreas de atuação, os níveis entram pela própria migration (o
 * servidor não tem SSH para rodar seeder; o Cron aplica no pós-deploy).
 * Compatível com o MySQL 5.7 do servidor.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Categoria => quantos graus ela tem (0 = sem grau). Mesma regra do
     * App\Enums\CategoriaNivel, copiada aqui de propósito: a migration precisa
     * continuar igual mesmo se a regra mudar no futuro.
     *
     * @var array<string, int>
     */
    private const NIVEIS_INICIAIS = [
        'Praticante' => 0,
        'Monitor' => 2,
        'Instrutor' => 5,
        'Instrutor Expert' => 5,
    ];

    /**
     * Cria a tabela e coloca os 13 níveis.
     */
    public function up(): void
    {
        Schema::create('niveis', function (Blueprint $table) {
            // Número de identificação do nível, criado pelo banco.
            $table->id();
            // Categoria (Praticante, Monitor, Instrutor, Instrutor Expert).
            $table->string('categoria', 30);
            // Grau de 1 a 5 (I a V); vazio (null) na categoria sem grau (Praticante).
            $table->unsignedTinyInteger('grau')->nullable();
            // Ativo aparece nas listas de escolha; desativado continua no banco
            // (filiados antigos com esse nível não ficam "órfãos").
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            // O banco recusa "Instrutor III" duas vezes. (Para o Praticante, sem
            // grau, quem barra a repetição é a validação do formulário: no MySQL,
            // dois valores vazios não contam como repetidos.)
            $table->unique(['categoria', 'grau']);
        });

        $agora = now();
        $linhas = [];

        // Monta uma linha por nível: Praticante sem grau; as outras, de 1 até o máximo.
        foreach (self::NIVEIS_INICIAIS as $categoria => $graus) {
            // Sem grau: uma linha só, com grau vazio.
            if ($graus === 0) {
                $linhas[] = ['categoria' => $categoria, 'grau' => null, 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora];

                continue;
            }

            for ($grau = 1; $grau <= $graus; $grau++) {
                $linhas[] = ['categoria' => $categoria, 'grau' => $grau, 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora];
            }
        }

        // Grava os 13 de uma vez.
        DB::table('niveis')->insert($linhas);
    }

    /**
     * Desfaz: apaga a tabela (e os níveis junto).
     */
    public function down(): void
    {
        Schema::dropIfExists('niveis');
    }
};
