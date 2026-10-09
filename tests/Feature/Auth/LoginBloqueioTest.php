<?php

/*
 * Testes do LOGIN REFORÇADO (#36): as duas travas de tentativas de login
 * (por e-mail e por computador), a mensagem com código LOGIN_BLOQUEADO e o
 * registro no log de segurança, sem e-mail (LGPD).
 */

use App\Models\User;
use App\Providers\FortifyServiceProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;

/**
 * Faz uma tentativa de login com senha errada, como a pessoa faria pela tela.
 *
 * @param  string  $email  O e-mail digitado. Ex.: 'ana@ibto.test'
 * @return TestResponse A resposta do servidor.
 */
function tentarLogin(string $email)
{
    // from(): finge que a pessoa estava na tela de login, para o "voltar" saber para onde ir.
    return test()->from(route('login'))->post(route('login.store'), [
        'email' => $email,
        'password' => 'senha-errada',
    ]);
}

test('trava 1: a 6ª tentativa no mesmo e-mail é barrada com o código LOGIN_BLOQUEADO', function () {
    User::factory()->create(['email' => 'ana@ibto.test']);

    // As 5 primeiras passam pela trava (e erram a senha normalmente).
    for ($i = 1; $i <= 5; $i++) {
        tentarLogin('ana@ibto.test')->assertSessionHasErrors('email');
    }

    // A 6ª nem chega a conferir a senha: volta para o login com o código.
    tentarLogin('ana@ibto.test')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('LOGIN_BLOQUEADO');
});

test('trava 2: muitos e-mails diferentes no mesmo computador também travam', function () {
    // Pega o limite escolhido pelo Gleyson direto do código (constante privada).
    $limite = (new ReflectionClassConstant(FortifyServiceProvider::class, 'LOGIN_TENTATIVAS_POR_IP'))->getValue();

    // Regra combinada: maior que a trava por e-mail (5), senão a trava 2 não faria sentido.
    expect($limite)->toBeGreaterThan(5);

    // Uma tentativa por e-mail diferente (a trava 1 nunca dispara), até o limite.
    for ($i = 1; $i <= $limite; $i++) {
        tentarLogin("pessoa{$i}@ibto.test");
    }

    // A próxima, com mais um e-mail novo, é barrada pela trava 2.
    tentarLogin('mais-uma@ibto.test')->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('LOGIN_BLOQUEADO');
});

test('o bloqueio é anotado no log de segurança, sem o e-mail (LGPD)', function () {
    // "Espião" no log: deixa tudo passar e depois confere o que foi gravado.
    Log::spy()->shouldReceive('channel')->andReturnSelf();

    for ($i = 1; $i <= 6; $i++) {
        tentarLogin('bia@ibto.test');
    }

    Log::shouldHaveReceived('channel')->with('seguranca');
    Log::shouldHaveReceived('info')->withArgs(function (string $codigo, array $contexto) {
        return $codigo === 'LOGIN_BLOQUEADO'
            && $contexto['trava'] === 'email'
            && $contexto['espera_segundos'] > 0
            // LGPD: o e-mail não pode aparecer em nenhum campo.
            && ! str_contains(json_encode($contexto), 'bia@ibto.test');
    });
});
