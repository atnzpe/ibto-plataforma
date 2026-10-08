<?php

/*
 * Permissão nova "filiados.gerenciar" (#37): abrir o cadastro de filiados.
 * Começa com o Administrador e a Tecnologia (depois, muda pela tela de
 * permissões). O Administrador de equipe ganha a sua versão LIMITADA à
 * própria equipe no bloco F3.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $agora = now();

        $permissaoId = DB::table('permissoes')->insertGetId([
            'slug' => 'filiados.gerenciar',
            'nome' => 'Cadastro de filiados',
            'grupo' => 'Cadastros',
            // Logo depois de "Cadastro de equipes" (30) na tela.
            'ordem' => 35,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        // Liga aos papéis Administrador e Tecnologia.
        foreach (['administrador', 'tecnologia'] as $papel) {
            DB::table('papel_permissao')->insert([
                'papel_id' => DB::table('papeis')->where('slug', $papel)->value('id'),
                'permissao_id' => $permissaoId,
            ]);
        }
    }

    public function down(): void
    {
        // Apagar a permissão apaga também os vínculos (cascade na tabela papel_permissao).
        DB::table('permissoes')->where('slug', 'filiados.gerenciar')->delete();
    }
};
