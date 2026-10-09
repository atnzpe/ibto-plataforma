<?php

/*
 * ABRANGÊNCIA do instrutor (#37): "nacional" ou "internacional" (Gleyson,
 * 08/10/2026). Na planilha do Canva ela vinha misturada na coluna "Nível";
 * agora tem campo próprio. Vazia (null) para quem não tem (ex.: praticante).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('filiados', function (Blueprint $table) {
            // nacional ou internacional; null = não se aplica.
            $table->string('abrangencia', 20)->nullable()->after('nivel_id');
        });
    }

    public function down(): void
    {
        Schema::table('filiados', function (Blueprint $table) {
            $table->dropColumn('abrangencia');
        });
    }
};
