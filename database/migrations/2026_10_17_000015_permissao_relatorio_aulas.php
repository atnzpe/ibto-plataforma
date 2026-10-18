<?php

/*
 * PERMISSÃO DO PAINEL FINANCEIRO DAS AULAS REGULARES (17/10/2026).
 * "relatorio.aulas" abre /relatorios/aulas-regulares. Começa com:
 * - Administrador e Tecnologia: veem todos os programas;
 * - Instrutor: vê só os programas dele (quem treina, quanto entra, quanto é dele).
 * Dá para mudar na tela de Permissões.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Os papéis que começam com a permissão. */
    private const PAPEIS = ['administrador', 'tecnologia', 'instrutor'];

    /**
     * Cria a permissão e liga aos papéis.
     */
    public function up(): void
    {
        $agora = now();
        $id = DB::table('permissoes')->insertGetId(['slug' => 'relatorio.aulas', 'nome' => 'Painel financeiro das aulas regulares', 'grupo' => 'Relatórios', 'ordem' => 105, 'created_at' => $agora, 'updated_at' => $agora]);
        foreach (self::PAPEIS as $papel) {
            $papelId = DB::table('papeis')->where('slug', $papel)->value('id');
            // Papel ainda não existe (banco de teste vazio): pula, sem erro.
            if ($papelId !== null) {
                DB::table('papel_permissao')->insert(['papel_id' => $papelId, 'permissao_id' => $id]);
            }
        }
    }

    /**
     * Desfaz: tira a permissão (as ligações saem junto, pela chave estrangeira).
     */
    public function down(): void
    {
        DB::table('permissoes')->where('slug', 'relatorio.aulas')->delete();
    }
};
