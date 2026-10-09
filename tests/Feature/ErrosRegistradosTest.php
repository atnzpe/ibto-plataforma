<?php

/*
 * Testes do "nenhum erro em silêncio" (#58): acesso negado (403) vai para o
 * canal de segurança, e todo erro gravado leva usuário, rota e método, sem
 * nenhum dado pessoal (e-mail, nome, IP).
 */

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Rotas só para o teste: uma que exige uma permissão que ninguém tem, e
    // uma que quebra de propósito (erro 500).
    Gate::define('ninguem-pode', fn () => false);
    Route::middleware('web')->get('/teste/proibida', fn () => 'nunca')
        ->middleware('can:ninguem-pode')->name('teste.proibida');
    Route::middleware('web')->get('/teste/quebrada', fn () => throw new RuntimeException('falha de teste'))
        ->name('teste.quebrada');
});

test('403 de usuário logado vai para o canal de segurança, sem e-mail', function () {
    $usuario = User::factory()->create(['email' => 'pessoa@ibto.test']);

    // "Espião" no log: deixa tudo passar e depois confere o que foi gravado.
    // channel() devolve o próprio espião, para o ->warning() seguinte também ser conferido.
    Log::spy()->shouldReceive('channel')->andReturnSelf();

    $this->actingAs($usuario)->get('/teste/proibida')->assertForbidden();

    Log::shouldHaveReceived('channel')->with('seguranca');
    Log::shouldHaveReceived('warning')->withArgs(function (string $codigo, array $contexto) use ($usuario) {
        return $codigo === 'PERMISSAO_NEGADA'
            && $contexto['usuario_id'] === $usuario->id
            && $contexto['rota'] === 'teste.proibida'
            && $contexto['metodo'] === 'GET'
            // LGPD: o e-mail não pode aparecer em nenhum campo.
            && ! str_contains(json_encode($contexto), 'pessoa@ibto.test');
    });
});

test('403 de visitante (abort) também é anotado', function () {
    Route::middleware('web')->get('/teste/abort', fn () => abort(403))->name('teste.abort');
    Log::spy()->shouldReceive('channel')->andReturnSelf();

    $this->get('/teste/abort')->assertForbidden();

    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $codigo, array $contexto) => $codigo === 'PERMISSAO_NEGADA' && $contexto['usuario_id'] === 'visitante',
    );
});

test('404 não vai para o canal de segurança', function () {
    Log::spy()->shouldReceive('channel')->andReturnSelf();

    $this->get('/pagina-que-nao-existe')->assertNotFound();

    Log::shouldNotHaveReceived('channel', ['seguranca']);
});

test('erro 500 é gravado com usuário, rota e método', function () {
    $usuario = User::factory()->create();
    // Faz a requisição "como no servidor": o erro vira resposta 500 em vez de estourar no teste.
    $this->withExceptionHandling();
    Log::spy()->shouldReceive('channel')->andReturnSelf();

    $this->actingAs($usuario)->get('/teste/quebrada')->assertStatus(500);

    Log::shouldHaveReceived('error')->withArgs(function (string $mensagem, array $contexto) use ($usuario) {
        return $mensagem === 'falha de teste'
            && $contexto['usuario_id'] === $usuario->id
            && $contexto['rota'] === 'teste.quebrada'
            && $contexto['metodo'] === 'GET';
    });
});

test('erro da tela é gravado no log com ERRO_TELA, sem a busca da página', function () {
    Log::spy();
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->postJson(route('api.erros-tela'), [
            'mensagem' => 'TypeError: x is undefined',
            'pagina' => '/cadastros/filiados?busca=fulano@exemplo.com',
            'origem' => 'tela',
        ])
        ->assertNoContent();

    Log::shouldHaveReceived('error')->withArgs(fn (string $codigo, array $contexto) => $codigo === 'ERRO_TELA'
        && $contexto['pagina'] === '/cadastros/filiados'
        && $contexto['origem'] === 'tela'
        // LGPD: a busca (que tinha um e-mail) não chega ao log.
        && ! str_contains(json_encode($contexto), 'exemplo.com'));
});

test('erro da tela com dados inválidos é recusado (422), e há limite de envios', function () {
    $this->postJson(route('api.erros-tela'), ['pagina' => '/'])->assertStatus(422);

    // 10 por minuto por IP: o pedido inválido acima também conta, então mais
    // 9 passam e o seguinte (o 11º do minuto) é barrado (429).
    for ($i = 0; $i < 9; $i++) {
        $this->postJson(route('api.erros-tela'), ['mensagem' => 'x', 'pagina' => '/'])->assertNoContent();
    }
    $this->postJson(route('api.erros-tela'), ['mensagem' => 'x', 'pagina' => '/'])->assertStatus(429);
});
