<?php

/*
 * "IMPORTAR MESMO ASSIM" (#37; pedido do Gleyson em 10/10/2026): na importação
 * da planilha, a linha que tem um dado FALTANDO (e-mail, CPF, nascimento,
 * município ou UF) pode entrar mesmo assim, com o cadastro marcado como
 * incompleto, para ser completado depois na tela de edição.
 *
 * Para isso, estes campos passam a aceitar "vazio" (NULL) no banco:
 * - users.email: a conta sem e-mail existe (guarda o nome), mas não entra no
 *   app nem recebe convite até alguém completar o e-mail. O "unique" continua:
 *   no MySQL, vários NULL não contam como repetidos;
 * - filiados.cpf, cpf_hash, nascimento, municipio e uf.
 *
 * O NOME continua obrigatório: sem nome não dá para saber quem é a pessoa.
 * Quem cadastra pela TELA continua obrigado a preencher tudo (FiliadoRequest).
 *
 * Roda no MySQL 5.7 da HostGator ("change" só altera a coluna, sem apagar dados).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Libera o vazio (NULL) nas colunas da lista acima.
     */
    public function up(): void
    {
        // E-mail da conta: pode ficar vazio (filiado importado sem e-mail).
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });

        // Dados do filiado que a planilha pode não trazer.
        Schema::table('filiados', function (Blueprint $table) {
            $table->text('cpf')->nullable()->change();
            $table->text('nascimento')->nullable()->change();
            $table->char('cpf_hash', 64)->nullable()->change();
            $table->string('municipio', 100)->nullable()->change();
            $table->char('uf', 2)->nullable()->change();
        });
    }

    /**
     * Volta a exigir os campos. Só funciona se nenhum filiado estiver com eles
     * vazios (o banco recusa; nesse caso, complete os cadastros antes).
     */
    public function down(): void
    {
        Schema::table('filiados', function (Blueprint $table) {
            $table->text('cpf')->nullable(false)->change();
            $table->text('nascimento')->nullable(false)->change();
            $table->char('cpf_hash', 64)->nullable(false)->change();
            $table->string('municipio', 100)->nullable(false)->change();
            $table->char('uf', 2)->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
