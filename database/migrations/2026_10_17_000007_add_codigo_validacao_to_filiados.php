<?php

/*
 * CÓDIGO DE VALIDAÇÃO DA CARTEIRINHA (#46).
 *
 * Cada filiado ganha um código SORTEADO (ex.: 'IBTO-7K2M-9QXA'), o mesmo padrão do
 * certificado de curso (#44): vai no QR code da carteirinha e abre a página
 * pública de validação. Sorteado (e não o registro) para ninguém montar a lista
 * dos filiados digitando números em sequência. Os filiados que já existem
 * recebem o código aqui mesmo; os novos, ao serem criados (Filiado::booted).
 * Roda no MySQL 5.7.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a coluna e sorteia o código dos filiados que já existem.
     */
    public function up(): void
    {
        Schema::table('filiados', function (Blueprint $table) {
            $table->string('codigo_validacao', 20)->nullable()->unique();
        });

        // Letras e números sem os parecidos (0/O, 1/I/L), para ninguém errar ao digitar.
        $letras = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $usados = [];
        foreach (DB::table('filiados')->whereNull('codigo_validacao')->pluck('id') as $id) {
            do {
                $sorteio = '';
                for ($i = 0; $i < 8; $i++) {
                    $sorteio .= $letras[random_int(0, strlen($letras) - 1)];
                }
                $codigo = 'IBTO-'.substr($sorteio, 0, 4).'-'.substr($sorteio, 4, 4);
            } while (isset($usados[$codigo]));
            $usados[$codigo] = true;
            DB::table('filiados')->where('id', $id)->update(['codigo_validacao' => $codigo]);
        }
    }

    /**
     * Desfaz: tira a coluna.
     */
    public function down(): void
    {
        Schema::table('filiados', function (Blueprint $table) {
            $table->dropUnique(['codigo_validacao']);
            $table->dropColumn('codigo_validacao');
        });
    }
};
