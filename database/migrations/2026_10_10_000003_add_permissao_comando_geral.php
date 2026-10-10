<?php

/*
 * Permissão do painel "COMANDO GERAL" (#59 bloco 4 / #45; nome dado pelo
 * Gleyson em 10/10/2026): abrir o painel de indicadores do IBTO (financeiro,
 * anuidade, filiados). Começa com Administrador e Tecnologia.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Cria a permissão e liga aos papéis Administrador e Tecnologia.
     */
    public function up(): void
    {
        $agora = now();

        $permissaoId = DB::table('permissoes')->insertGetId([
            'slug' => 'comando_geral.ver',
            'nome' => 'Ver o painel Comando Geral',
            'grupo' => 'Comando Geral',
            // Antes do financeiro (60) na grade de Permissões.
            'ordem' => 55,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        foreach (['administrador', 'tecnologia'] as $papel) {
            DB::table('papel_permissao')->insert([
                'papel_id' => DB::table('papeis')->where('slug', $papel)->value('id'),
                'permissao_id' => $permissaoId,
            ]);
        }
    }

    /**
     * Desfaz: apaga a permissão (as ligações com os papéis vão junto, pelo banco).
     */
    public function down(): void
    {
        DB::table('permissoes')->where('slug', 'comando_geral.ver')->delete();
    }
};
