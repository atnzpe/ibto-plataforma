<?php

/*
 * DADOS PARA PAGAMENTO (#41; pedido do Gleyson em 10/10/2026: "deixar
 * configurado as duas opções e uma chave liga/desliga").
 *
 * - pagamento_config: UMA linha com os dados do IBTO (PIX, favorecido, banco,
 *   agência, conta, instruções) e a chave "por_curso";
 * - cursos.pag_*: os dados PRÓPRIOS de um curso (ex.: curso de equipe que
 *   recebe na conta da equipe). Só valem com a chave "por_curso" LIGADA e
 *   quando o curso tem a chave PIX preenchida; senão, valem os do IBTO;
 * - permissão "pagamentos.configurar": quem mexe nesses dados (Administrador
 *   e Tecnologia no começo). Quem propõe curso NÃO muda para onde vai o
 *   dinheiro (evita desvio de pagamento).
 *
 * A chave PIX começa VAZIA: preencher na tela "Dados para pagamento" do
 * Comando Geral (não fica mais no .env).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a tabela, as colunas do curso e a permissão.
     */
    public function up(): void
    {
        Schema::create('pagamento_config', function (Blueprint $table) {
            $table->id();
            // Ex.: 'financeiro@ib-to.org', um CNPJ ou um telefone.
            $table->string('pix_chave', 140)->nullable();
            // Quem recebe (o nome que aparece no app do banco).
            $table->string('favorecido', 150)->nullable();
            // Transferência: banco, agência e conta. Ex.: '001 - Banco do Brasil', '1234-5', '12345-6'.
            $table->string('banco', 100)->nullable();
            $table->string('agencia', 20)->nullable();
            $table->string('conta', 30)->nullable();
            // Recado para quem vai pagar. Ex.: 'Envie o comprovante pela plataforma.'
            $table->string('instrucoes', 500)->nullable();
            // A chave liga/desliga: true = cada curso pode ter os seus dados.
            $table->boolean('por_curso')->default(false);
            $table->timestamps();
        });

        // A linha única do IBTO (a chave PIX é preenchida depois, na tela).
        DB::table('pagamento_config')->insert([
            'favorecido' => 'Instituto Brasileiro de Treinamento Operacional',
            'por_curso' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Os dados próprios de cada curso (vazios = usa os do IBTO).
        Schema::table('cursos', function (Blueprint $table) {
            $table->string('pag_pix_chave', 140)->nullable()->after('contato_whatsapp');
            $table->string('pag_favorecido', 150)->nullable()->after('pag_pix_chave');
            $table->string('pag_banco', 100)->nullable()->after('pag_favorecido');
            $table->string('pag_agencia', 20)->nullable()->after('pag_banco');
            $table->string('pag_conta', 30)->nullable()->after('pag_agencia');
        });

        // Permissão de mexer nos dados para pagamento.
        $agora = now();
        $permissaoId = DB::table('permissoes')->insertGetId([
            'slug' => 'pagamentos.configurar', 'nome' => 'Configurar os dados para pagamento (PIX e conta)',
            'grupo' => 'Cursos', 'ordem' => 84, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        foreach (['administrador', 'tecnologia'] as $papel) {
            DB::table('papel_permissao')->insert([
                'papel_id' => DB::table('papeis')->where('slug', $papel)->value('id'),
                'permissao_id' => $permissaoId,
            ]);
        }
    }

    /**
     * Desfaz: tira a permissão, as colunas e a tabela.
     */
    public function down(): void
    {
        DB::table('permissoes')->where('slug', 'pagamentos.configurar')->delete();
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn(['pag_pix_chave', 'pag_favorecido', 'pag_banco', 'pag_agencia', 'pag_conta']);
        });
        Schema::dropIfExists('pagamento_config');
    }
};
