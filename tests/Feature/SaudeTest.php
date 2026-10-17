<?php

use App\Http\Controllers\SaudeController;
use Illuminate\Support\Facades\Log;

test('responde ok quando o banco está conectado', function () {
    $this->getJson('/api/saude')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('banco', 'ok')
        // A versão no ar (#75): back, front e banco.
        ->assertJsonPath('versao.numero', config('app.versao'))
        ->assertJsonStructure(['versao' => ['numero', 'commit', 'front_build', 'banco' => ['versao', 'migracao']]]);
});

test('limita a 30 consultas por minuto por IP', function () {
    for ($i = 0; $i < 30; $i++) {
        $this->getJson('/api/saude')->assertOk();
    }

    $this->getJson('/api/saude')->assertStatus(429);
});

test('responde 503 com código estável quando o banco está fora do ar', function () {
    // Troca o banco padrão por um arquivo que não existe: a conexão falha.
    $bancoPadrao = config('database.default');
    config([
        'database.connections.quebrada' => [
            'driver' => 'sqlite',
            'database' => storage_path('nao-existe/banco.sqlite'),
        ],
        'database.default' => 'quebrada',
    ]);
    Log::spy();

    $this->getJson('/api/saude')
        ->assertStatus(503)
        ->assertExactJson([
            'status' => 'erro',
            'codigo' => SaudeController::CODIGO_BANCO_INDISPONIVEL,
        ]);

    Log::shouldHaveReceived('error')
        ->withArgs(fn ($mensagem) => $mensagem === SaudeController::CODIGO_BANCO_INDISPONIVEL)
        ->once();

    // Devolve o banco padrão para o RefreshDatabase desfazer a transação.
    config(['database.default' => $bancoPadrao]);
});
