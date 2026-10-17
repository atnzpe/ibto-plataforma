<?php

/*
 * LIGA A LOJA DE FOTOS (#90), por decisão do Gleyson em 17/10/2026 ("pode deixar
 * a loja de fotos ligada").
 *
 * A venda de fotos nasceu DESLIGADA (migration 2026_10_17_000005). Aqui ela é
 * ligada uma vez, ao publicar. O interruptor do Comando Geral continua valendo:
 * quem desligar lá depois não é desfeito (esta migration roda uma vez só).
 * Mesmo ligada, só a turma do curso (matrícula confirmada) vê e compra.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Liga a venda de fotos (cria a chave, se por algum motivo ela não existir).
     */
    public function up(): void
    {
        DB::table('configuracoes')->updateOrInsert(
            ['chave' => 'venda_fotos_ligada'],
            ['valor' => '1', 'updated_at' => now()],
        );
    }

    /**
     * Desfaz: volta a deixar desligada.
     */
    public function down(): void
    {
        DB::table('configuracoes')->where('chave', 'venda_fotos_ligada')->update(['valor' => '0', 'updated_at' => now()]);
    }
};
