<?php

/*
 * Testes dos papéis e permissões (#36): os 5 papéis e as 5 permissões
 * iniciais, quem passa no porteiro geral (Gate::before) e a chave de emergência.
 */

use App\Models\Papel;
use App\Models\Permissao;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    config(['app.admin_emails' => ['emergencia@ibto.test']]);
});

/**
 * Dá um papel (pelo slug) a uma pessoa.
 */
function darPapel(User $usuario, string $slug): void
{
    $usuario->papeis()->attach(Papel::where('slug', $slug)->value('id'));
}

test('as migrations trazem os 6 papéis e as 9 permissões (com as de filiados e as do financeiro, #59)', function () {
    expect(Papel::pluck('slug')->sort()->values()->all())
        ->toBe(['admin_equipe', 'administrador', 'aluno', 'filiado', 'instrutor', 'tecnologia']);
    expect(Permissao::count())->toBe(9);
});

test('só a Tecnologia começa com "permissoes.gerenciar"', function () {
    $papeisComPermissao = Permissao::where('slug', 'permissoes.gerenciar')->firstOrFail()
        ->papeis()->pluck('slug')->all();

    expect($papeisComPermissao)->toBe(['tecnologia']);
});

test('administrador pode os cadastros, mas não as permissões', function () {
    $usuario = User::factory()->create();
    darPapel($usuario, 'administrador');

    expect(Gate::forUser($usuario)->allows('cadastros.equipes'))->toBeTrue();
    expect(Gate::forUser($usuario)->allows('cadastros.niveis'))->toBeTrue();
    expect(Gate::forUser($usuario)->allows('permissoes.gerenciar'))->toBeFalse();
});

test('aluno sem nenhuma permissão é barrado', function () {
    $usuario = User::factory()->create();
    darPapel($usuario, 'aluno');

    expect(Gate::forUser($usuario)->allows('cadastros.equipes'))->toBeFalse();
});

test('pessoa sem papel nenhum é barrada', function () {
    expect(Gate::forUser(User::factory()->create())->allows('cadastros.areas'))->toBeFalse();
});

test('chave de emergência (ADMIN_EMAILS confirmado) pode tudo, até sem papel', function () {
    $usuario = User::factory()->create(['email' => 'emergencia@ibto.test']);

    expect(Gate::forUser($usuario)->allows('permissoes.gerenciar'))->toBeTrue();
    expect(Gate::forUser($usuario)->allows('cadastros.equipes'))->toBeTrue();
});

test('e-mail da lista NÃO confirmado não é chave de emergência', function () {
    $impostor = User::factory()->unverified()->create(['email' => 'emergencia@ibto.test']);

    expect(Gate::forUser($impostor)->allows('cadastros.equipes'))->toBeFalse();
});

test('várias perguntas de permissão fazem uma consulta só ao banco', function () {
    $usuario = User::factory()->create();
    darPapel($usuario, 'administrador');

    // Conta as consultas feitas a partir daqui.
    DB::enableQueryLog();
    $usuario->temPermissao('cadastros.areas');
    $usuario->temPermissao('cadastros.niveis');
    $usuario->temPermissao('cadastros.equipes');

    expect(DB::getQueryLog())->toHaveCount(1);
});
