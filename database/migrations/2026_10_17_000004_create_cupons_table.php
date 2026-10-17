<?php

/*
 * CUPOM PROMOCIONAL NOS CURSOS (#89).
 *
 * - cupons: o código (ex.: FILIADO10), o tipo (percentual ou valor fixo), o
 *   valor, a validade de/até, os limites (total e por pessoa), "só filiados",
 *   se vale em todos os cursos e se está ativo. Cupom usado nunca é apagado:
 *   só desativado (fica o histórico).
 * - cupom_curso: os cursos em que o cupom vale (quando não vale em todos).
 * - curso_matriculas: o cupom usado, o preço cheio e o desconto (o valor_centavos
 *   que já existe passa a ser o valor COM desconto, o que de fato se paga).
 * - permissão "cupons.gerenciar": criar, alterar e desativar cupons. Começa só
 *   no Administrador e na Tecnologia (o desconto mexe na parte do IBTO).
 * Roda no MySQL 5.7.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria as tabelas, as colunas da matrícula e a permissão.
     */
    public function up(): void
    {
        Schema::create('cupons', function (Blueprint $table) {
            $table->id();
            // O código que o aluno digita. Ex.: 'FILIADO10'. Sempre em maiúsculas, sem repetir.
            $table->string('codigo', 30)->unique();
            // 'percentual' (ex.: 10 = 10%) ou 'valor' (ex.: 5000 = R$ 50,00 de desconto).
            $table->string('tipo', 12);
            $table->unsignedInteger('valor');
            // Validade: de/até (data e hora). Vazio = sem limite naquele lado.
            $table->dateTime('valido_de')->nullable();
            $table->dateTime('valido_ate')->nullable();
            // Limite total de usos (vazio = sem limite) e por pessoa (padrão 1).
            $table->unsignedInteger('limite_total')->nullable();
            $table->unsignedInteger('limite_por_pessoa')->default(1);
            // Só para filiados? Vale em todos os cursos? Está ativo?
            $table->boolean('so_filiados')->default(false);
            $table->boolean('todos_cursos')->default(true);
            $table->boolean('ativo')->default(true);
            // Quem criou (para a auditoria e o filtro "meus cupons").
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Os cursos em que o cupom vale (só quando "todos_cursos" é falso).
        Schema::create('cupom_curso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cupom_id')->constrained('cupons')->cascadeOnDelete();
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->unique(['cupom_id', 'curso_id']);
        });

        // Na matrícula: o cupom usado, o preço cheio e o desconto.
        Schema::table('curso_matriculas', function (Blueprint $table) {
            $table->foreignId('cupom_id')->nullable()->constrained('cupons')->nullOnDelete();
            $table->unsignedInteger('valor_cheio_centavos')->nullable();
            $table->unsignedInteger('desconto_centavos')->default(0);
        });

        // Permissão de criar e cuidar dos cupons.
        $agora = now();
        $permissaoId = DB::table('permissoes')->insertGetId([
            'slug' => 'cupons.gerenciar', 'nome' => 'Criar e cuidar dos cupons de desconto',
            'grupo' => 'Cursos', 'ordem' => 86, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        foreach (['administrador', 'tecnologia'] as $papel) {
            $papelId = DB::table('papeis')->where('slug', $papel)->value('id');
            // Papel ainda não existe (banco de teste vazio): pula, sem erro.
            if ($papelId !== null) {
                DB::table('papel_permissao')->insert(['papel_id' => $papelId, 'permissao_id' => $permissaoId]);
            }
        }
    }

    /**
     * Desfaz: tira a permissão, as colunas e as tabelas.
     */
    public function down(): void
    {
        DB::table('permissoes')->where('slug', 'cupons.gerenciar')->delete();
        Schema::table('curso_matriculas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cupom_id');
            $table->dropColumn(['valor_cheio_centavos', 'desconto_centavos']);
        });
        Schema::dropIfExists('cupom_curso');
        Schema::dropIfExists('cupons');
    }
};
