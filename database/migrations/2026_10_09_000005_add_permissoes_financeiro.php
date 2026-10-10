<?php

/*
 * Permissões do FINANCEIRO (#59), uma por ação, como nas outras telas (#36):
 * - financeiro.ver: abrir o livro-caixa (lista, filtros e totais);
 * - financeiro.lancar: criar lançamentos, dar baixa, cancelar e estornar.
 * Começam com os papéis Administrador e Tecnologia; outros podem receber pela
 * tela de Permissões.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Permissões novas: slug => [nome na tela, ordem na grade].
     *
     * @var array<string, array{0: string, 1: int}>
     */
    private const PERMISSOES = [
        'financeiro.ver' => ['Ver o livro-caixa', 60],
        'financeiro.lancar' => ['Lançar, dar baixa, cancelar e estornar', 70],
    ];

    /**
     * Cria as permissões e liga cada uma aos papéis Administrador e Tecnologia.
     */
    public function up(): void
    {
        $agora = now();

        foreach (self::PERMISSOES as $slug => [$nome, $ordem]) {
            $permissaoId = DB::table('permissoes')->insertGetId([
                'slug' => $slug,
                'nome' => $nome,
                // Grupo novo na grade de Permissões.
                'grupo' => 'Financeiro',
                'ordem' => $ordem,
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
    }

    /**
     * Desfaz: apaga as permissões (as ligações com os papéis vão junto, pelo banco).
     */
    public function down(): void
    {
        DB::table('permissoes')->whereIn('slug', array_keys(self::PERMISSOES))->delete();
    }
};
