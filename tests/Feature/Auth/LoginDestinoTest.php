<?php

/*
 * Testes do DESTINO DEPOIS DO LOGIN e do "SAIR" (17/10/2026):
 * - página guardada que a conta NÃO pode abrir (ex.: Usuários) → vai para o início (sem 403);
 * - página guardada que a conta pode abrir → volta para ela;
 * - "Sair" pela tela (Inertia) com o site configurado → troca a página inteira (409 +
 *   X-Inertia-Location), em vez do redirecionamento que o navegador bloqueava (CORS).
 * Dados INVENTADOS.
 */

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Papel;
use App\Models\User;

test('página guardada sem permissão: o login leva ao início, não ao 403', function () {
    $aluno = User::factory()->create();

    $this->withSession(['url.intended' => url('/admin/usuarios')])
        ->post(route('login.store'), ['email' => $aluno->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
});

test('página guardada com permissão: o login volta para ela', function () {
    $tecnologia = User::factory()->create();
    $tecnologia->papeis()->attach(Papel::where('slug', 'tecnologia')->value('id'));

    $this->withSession(['url.intended' => url('/admin/usuarios')])
        ->post(route('login.store'), ['email' => $tecnologia->email, 'password' => 'password'])
        ->assertRedirect(url('/admin/usuarios'));
});

test('"Sair" pela tela troca a página inteira para o site (sem bloqueio de CORS)', function () {
    config(['app.site_url' => 'https://homolog.ib-to.org']);

    // A mesma versão do front que a tela mandaria (senão o Inertia pede recarga por versão).
    $versao = (string) app(HandleInertiaRequests::class)->version(request());
    $this->get('/', ['X-Inertia' => 'true', 'X-Inertia-Version' => $versao])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://homolog.ib-to.org');
    // Visita normal continua com o redirecionamento direto.
    $this->get('/')->assertRedirect('https://homolog.ib-to.org');
});
