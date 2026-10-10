<?php

/*
 * CRONOGRAMA CLICÁVEL (#38, bloco C4d; conversa de 23/09/2026 com a Aline e o
 * Eduardo): no cronograma da página do curso, cada encontro abre uma janela
 * com o horário, o local, quem aplica e uma INTRODUÇÃO.
 *
 * A introdução é PÚBLICA (aparece para quem ainda não se inscreveu), por isso
 * é um campo próprio e curto: o texto da aula ("conteudo") é material do
 * curso e continua só para quem tem acesso.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a coluna "introducao" (vazia nas aulas que já existem).
     */
    public function up(): void
    {
        Schema::table('curso_aulas', function (Blueprint $table) {
            // Até 500 letras (conferido no AulaRequest). Ex.: "Postura, guarda e deslocamento básico."
            $table->text('introducao')->nullable()->after('quem_aplica');
        });
    }

    /**
     * Desfaz: tira a coluna.
     */
    public function down(): void
    {
        Schema::table('curso_aulas', function (Blueprint $table) {
            $table->dropColumn('introducao');
        });
    }
};
