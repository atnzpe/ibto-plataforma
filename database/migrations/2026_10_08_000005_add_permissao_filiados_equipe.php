<?php

/*
 * Permissão "filiados.equipe" (#37, F3b): cuidar SÓ dos filiados da própria
 * equipe. Começa com o papel Administrador de equipe (Gleyson, 07/10/2026).
 * Quem tem "filiados.gerenciar" (Administrador do IBTO) continua vendo todos.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $agora = now();

        $permissaoId = DB::table('permissoes')->insertGetId([
            'slug' => 'filiados.equipe',
            'nome' => 'Filiados da própria equipe',
            'grupo' => 'Cadastros',
            // Logo depois de "Cadastro de filiados" (35).
            'ordem' => 36,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        DB::table('papel_permissao')->insert([
            'papel_id' => DB::table('papeis')->where('slug', 'admin_equipe')->value('id'),
            'permissao_id' => $permissaoId,
        ]);
    }

    public function down(): void
    {
        DB::table('permissoes')->where('slug', 'filiados.equipe')->delete();
    }
};
