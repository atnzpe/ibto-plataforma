<?php

/*
 * ÁLBUM DA GALERIA LIGADO A UM CURSO (17/10/2026).
 *
 * O Mestre Eduardo definiu que "o responsável pelas fotos é sempre o instrutor
 * do curso". Com o álbum ligado ao curso, o instrutor responsável consegue
 * enviar, descrever e desfocar as fotos do curso dele (quem publica continua
 * sendo o Comando Geral). null = álbum avulso (ex.: um evento sem curso).
 * Curso apagado: o álbum fica, só perde a ligação. Roda no MySQL 5.7.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cria a coluna curso_id.
     */
    public function up(): void
    {
        Schema::table('galeria_albuns', function (Blueprint $table) {
            $table->foreignId('curso_id')->nullable()->constrained('cursos')->nullOnDelete();
        });
    }

    /**
     * Desfaz: tira a coluna (e a ligação).
     */
    public function down(): void
    {
        Schema::table('galeria_albuns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('curso_id');
        });
    }
};
