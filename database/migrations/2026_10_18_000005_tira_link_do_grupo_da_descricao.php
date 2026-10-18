<?php

/*
 * LIMPEZA DE DADO (18/10/2026, preparação para o repositório público): a carga do
 * piloto gravou, na descrição de um curso, o link de convite de um grupo de WhatsApp.
 * Esta migração troca SÓ esse trecho por uma frase neutra, nos cursos que o têm
 * (a carga não altera curso que já existe, por isso a correção vem por aqui).
 * O "Redefinir link" do grupo no WhatsApp é o que invalida o convite antigo.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Troca o link de convite de grupo do WhatsApp pela frase neutra.
     */
    public function up(): void
    {
        foreach (DB::table('cursos')->where('descricao', 'like', '%chat.whatsapp.com/%')->get(['id', 'descricao']) as $curso) {
            // Qualquer convite de grupo (chat.whatsapp.com/<código>) sai do texto.
            $limpa = (string) preg_replace('#Inscrições e dúvidas pelo grupo do WhatsApp:\s*https?://chat\.whatsapp\.com/\S+#u', 'Inscrições e dúvidas pelo WhatsApp do curso (o link do grupo é passado pelo IBTO).', (string) $curso->descricao);
            $limpa = (string) preg_replace('#https?://chat\.whatsapp\.com/\S+#u', '(link do grupo passado pelo IBTO)', $limpa);
            DB::table('cursos')->where('id', $curso->id)->update(['descricao' => $limpa]);
        }
    }

    /**
     * Desfazer não traz o link de volta (de propósito).
     */
    public function down(): void {}
};
