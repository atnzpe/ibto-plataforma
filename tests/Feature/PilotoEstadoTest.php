<?php

/*
 * Testes do ESTADO DO PILOTO (17/10/2026): o pós-deploy grava um arquivo com
 * sim/não e números (sem e-mail nem nome) que o diagnóstico mostra.
 */

use App\Models\User;
use Illuminate\Support\Facades\File;

afterEach(function () {
    File::delete([base_path('VERSAO_DEPLOY'), storage_path('app/piloto-estado')]);
});

test('o estado diz se a conta completa foi encontrada, sem gravar o e-mail', function () {
    File::put(base_path('VERSAO_DEPLOY'), 'abc123');
    File::put(storage_path('app/versao-aplicada'), 'abc123');
    User::factory()->create(['email' => 'conta.estado@ibto.test']);
    config(['app.piloto_conta_completa' => 'conta.estado@ibto.test', 'app.piloto_senha_demo' => '']);

    $this->artisan('plataforma:pos-deploy')->assertSuccessful();

    $estado = File::get(storage_path('app/piloto-estado'));
    expect($estado)->toContain('conta_completa_encontrada: sim')
        ->toContain('senha_demo_configurada: nao')
        ->not->toContain('conta.estado@ibto.test');
});
