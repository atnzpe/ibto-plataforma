<?php

/*
 * Testes dos STAKEHOLDERS DO PILOTO (17/10/2026): cada um da lista do .env
 * ganha conta com convite, ficha, os papéis da função e a matrícula no evento;
 * admin de equipe ganha equipe própria; papel desconhecido é ignorado; as
 * contas de exemplo só existem com a senha no .env e nunca são de Comando
 * Geral; o álbum à venda do evento nasce ativo; rodar de novo não duplica.
 * Dados INVENTADOS.
 */

use App\Models\Curso;
use App\Models\CursoMatricula;
use App\Models\FotoAlbumVenda;
use App\Models\User;
use App\Notifications\ConviteFiliado;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->fotografa = User::factory()->create(['email' => 'conta.completa@ibto.test']);
    config([
        'app.piloto_conta_completa' => 'conta.completa@ibto.test',
        'app.piloto_stakeholders' => 'Fulana Tecnologia|fulana@ibto.test|tecnologia;Beltrano Equipe|beltrano@ibto.test|admin_equipe,instrutor,papel_inventado;linha quebrada',
    ]);
});

test('cada stakeholder ganha conta com convite, papéis e matrícula no evento; sem duplicar', function () {
    $this->artisan('plataforma:piloto-stakeholders')->assertSuccessful();
    $this->artisan('plataforma:piloto-stakeholders')->assertSuccessful();

    $fulana = User::where('email', 'fulana@ibto.test')->sole();
    $beltrano = User::where('email', 'beltrano@ibto.test')->sole();
    Notification::assertSentTo($fulana, ConviteFiliado::class);

    expect($fulana->papeis()->pluck('slug')->sort()->values()->all())->toBe(['aluno', 'filiado', 'tecnologia'])
        // O papel inventado foi ignorado.
        ->and($beltrano->papeis()->pluck('slug')->sort()->values()->all())->toBe(['admin_equipe', 'aluno', 'filiado', 'instrutor'])
        // Admin de equipe com equipe própria.
        ->and($beltrano->filiado->equipe->nome)->toBe('[TESTE] Equipe de Beltrano');

    $evento = Curso::where('titulo', 'like', '[TESTE] Evento do piloto%')->sole();
    expect(CursoMatricula::where('curso_id', $evento->id)->count())->toBe(2)
        ->and(FotoAlbumVenda::where('curso_id', $evento->id)->sole()->ativo)->toBeTrue();
});

test('contas de exemplo só com a senha no .env, com a senha certa e nunca de Comando Geral', function () {
    $this->artisan('plataforma:piloto-stakeholders');
    expect(User::where('email', 'like', '%@demo.ib-to.org')->count())->toBe(0);

    config(['app.piloto_senha_demo' => 'senha-de-exemplo-123']);
    $this->artisan('plataforma:piloto-stakeholders')->assertSuccessful();

    $demos = User::where('email', 'like', '%@demo.ib-to.org')->get();
    expect($demos)->toHaveCount(5)
        ->and(Hash::check('senha-de-exemplo-123', $demos->firstWhere('email', 'aluno@demo.ib-to.org')->password))->toBeTrue()
        ->and($demos->flatMap(fn (User $u) => $u->papeis()->pluck('slug'))->intersect(['administrador', 'tecnologia'])->all())->toBe([]);
});
