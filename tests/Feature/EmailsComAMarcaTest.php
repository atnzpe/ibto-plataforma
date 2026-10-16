<?php

/*
 * Testes dos E-MAILS COM A MARCA DO IBTO (17/10/2026): o e-mail de confirmação
 * sai com o texto do IBTO, o endereço oficial e o prazo do link; e TODO e-mail
 * da plataforma traz o emblema e o aviso de segurança contra golpes.
 * Dados INVENTADOS.
 */

use App\Models\User;
use App\Notifications\CadastroAnalisado;
use App\Notifications\ConfirmarEmail;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    config(['app.url' => 'https://cursos.ib-to.org', 'auth.verification.expire' => 60]);
    // Os links (como o de confirmação) passam a ser gerados com o endereço oficial.
    URL::forceRootUrl('https://cursos.ib-to.org');
    // O mesmo que a plataforma faz quando o endereço oficial é https (AppServiceProvider).
    URL::forceScheme('https');
    $this->usuario = User::factory()->unverified()->create(['name' => 'Fulano de Tal']);
});

test('confirmação: texto do IBTO, endereço oficial e prazo do link', function () {
    $html = (string) (new ConfirmarEmail)->toMail($this->usuario)->render();

    expect($html)
        ->toContain('Bem-vindo ao IBTO.')
        ->toContain('Confirmar meu e-mail')
        ->toContain('Este link vale por 60 minutos')
        ->toContain('cursos.ib-to.org')
        // O botão leva para o endereço oficial (link assinado do próprio domínio).
        ->toContain('https://cursos.ib-to.org/email/verify/');
});

test('todo e-mail traz o emblema e o aviso de segurança', function () {
    foreach ([(new ConfirmarEmail)->toMail($this->usuario), (new CadastroAnalisado(true))->toMail($this->usuario)] as $mensagem) {
        $html = (string) $mensagem->render();

        expect($html)
            ->toContain('https://cursos.ib-to.org/imagens/email/ibto-emblema.png')
            ->toContain('o IBTO nunca pede a sua senha')
            ->toContain('Formação real. Resultado operacional.')
            // Nada do padrão do Laravel sobrou.
            ->not->toContain('laravel.com/img');
    }
});

test('o emblema do e-mail existe na pasta liberada da senha da homologação', function () {
    expect(file_exists(public_path('imagens/email/ibto-emblema.png')))->toBeTrue();
});
