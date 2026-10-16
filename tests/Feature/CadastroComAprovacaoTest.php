<?php

/*
 * Testes do CADASTRO COM APROVAÇÃO (#84; o IBTO é um grupo fechado):
 * - o pedido nasce "em análise", sem papel, e a trava fica no servidor;
 * - porta fechada (CADASTRO_PUBLICO=fechado): a tela e o envio dão 404;
 * - aprovar dá o papel Aluno e manda o e-mail; recusar exige motivo e desloga;
 * - o admin de equipe só analisa pedidos da própria equipe;
 * - contas que já existiam continuam aprovadas; e-mail repetido é recusado.
 * Dados INVENTADOS.
 */

use App\Actions\Filiados\CriarFiliado;
use App\Models\Auditoria;
use App\Models\Equipe;
use App\Models\Papel;
use App\Models\User;
use App\Notifications\CadastroAnalisado;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    config(['app.cadastro_publico' => 'aprovacao']);
    $this->equipe = Equipe::factory()->create(['nome' => 'Equipe Teste Recife', 'status' => 'ativa']);
    $this->outraEquipe = Equipe::factory()->create(['nome' => 'Equipe Teste Campinas', 'status' => 'ativa']);
});

/**
 * Os dados de um pedido de acesso válido.
 *
 * @param  array<string, mixed>  $troca  Ex.: ['email' => 'outro@exemplo.com']
 * @return array<string, mixed>
 */
function pedidoDeAcesso(array $troca = []): array
{
    return [
        'name' => 'Fulano de Tal',
        'email' => 'fulano@exemplo.com',
        'password' => 'Senha-Forte-123!',
        'password_confirmation' => 'Senha-Forte-123!',
        'equipe_id' => test()->equipe->id,
        'indicado_por' => 'Mestre Trinca',
        ...$troca,
    ];
}

/**
 * Uma conta com o papel pedido e, se houver, o registro de filiado numa equipe.
 */
function contaDoPapel(string $papel, ?Equipe $equipe = null): User
{
    // Com equipe: cria pelo cadastro de filiado de verdade (que também cria a conta ligada).
    // CPF de teste com dígitos válidos (não é de ninguém).
    $usuario = $equipe !== null
        ? app(CriarFiliado::class)->executar([
            'nome' => 'Admin da Equipe', 'email' => 'admin.equipe@ibto.test', 'cpf' => '529.982.247-25',
            'nascimento' => '1985-01-01', 'municipio' => 'Recife', 'uf' => 'PE', 'equipe_id' => $equipe->id,
        ])->usuario
        : User::factory()->create();
    $usuario->markEmailAsVerified();
    $usuario->papeis()->attach(Papel::where('slug', $papel)->value('id'));

    return $usuario->fresh();
}

test('o pedido nasce em análise, sem papel, e só abre a tela de espera', function () {
    $this->post(route('register.store'), pedidoDeAcesso())->assertRedirect();

    $usuario = User::where('email', 'fulano@exemplo.com')->firstOrFail();
    expect($usuario->situacao_cadastro)->toBe('em_analise')
        ->and($usuario->equipe_solicitada_id)->toBe($this->equipe->id)
        ->and($usuario->indicado_por)->toBe('Mestre Trinca')
        ->and($usuario->papeis()->count())->toBe(0)
        ->and(Auditoria::where('acao', 'CADASTRO_SOLICITADO')->exists())->toBeTrue();

    // A trava é no servidor: qualquer tela leva para a espera, mesmo pelo endereço.
    $usuario->markEmailAsVerified();
    $this->actingAs($usuario)->get(route('meus-cursos'))->assertRedirect(route('cadastro.em-analise'));
    $this->get(route('cadastro.em-analise'))->assertOk()
        ->assertInertia(fn ($tela) => $tela->component('auth/cadastro-em-analise')->where('equipe', 'Equipe Teste Recife'));
});

test('o pedido exige equipe válida e quem indicou; e-mail repetido é recusado', function () {
    $this->post(route('register.store'), pedidoDeAcesso(['equipe_id' => '', 'indicado_por' => '']))
        ->assertSessionHasErrors(['equipe_id', 'indicado_por']);

    $inativa = Equipe::factory()->create(['status' => 'inativa']);
    $this->post(route('register.store'), pedidoDeAcesso(['equipe_id' => $inativa->id]))->assertSessionHasErrors('equipe_id');

    User::factory()->create(['email' => 'fulano@exemplo.com']);
    $this->post(route('register.store'), pedidoDeAcesso())->assertSessionHasErrors('email');
});

test('fechado (o padrão) e sem chave de partida: a tela e o envio não existem (404)', function () {
    config(['app.cadastro_publico' => 'fechado', 'app.admin_emails' => []]);

    $this->get(route('register'))->assertNotFound();
    $this->post(route('register.store'), pedidoDeAcesso())->assertNotFound();
    $this->get(route('login'))->assertInertia(fn ($tela) => $tela->where('canRegister', false));
});

test('fechado com chave de partida: só aquele e-mail entra, aprovado e sem precisar de equipe', function () {
    config(['app.cadastro_publico' => 'fechado', 'app.admin_emails' => ['tecnologia@ib-to.test']]);

    // Outro e-mail: 404 (o cadastro continua fechado para todos os outros).
    $this->post(route('register.store'), pedidoDeAcesso())->assertNotFound();

    // A chave de partida: entra sem equipe nem indicação (banco novo) e já nasce aprovada.
    $this->post(route('register.store'), pedidoDeAcesso(['email' => 'tecnologia@ib-to.test', 'equipe_id' => '', 'indicado_por' => '']))->assertRedirect();
    expect(User::where('email', 'tecnologia@ib-to.test')->firstOrFail()->situacao_cadastro)->toBe('aprovado');
});

test('o Comando Geral aprova: vira Aluno, recebe o e-mail e entra', function () {
    $this->post(route('register.store'), pedidoDeAcesso());
    $pedido = User::where('email', 'fulano@exemplo.com')->firstOrFail();
    $admin = contaDoPapel('administrador');

    $this->actingAs($admin)->get(route('admin.solicitacoes.index'))
        ->assertInertia(fn ($tela) => $tela->has('solicitacoes', 1)->where('solicitacoes.0.indicado_por', 'Mestre Trinca'));
    $this->post(route('admin.solicitacoes.aprovar', $pedido))->assertSessionHas('sucesso');

    $pedido->refresh();
    expect($pedido->situacao_cadastro)->toBe('aprovado')
        ->and($pedido->papeis()->where('slug', 'aluno')->exists())->toBeTrue()
        ->and($pedido->cadastro_analisado_por)->toBe($admin->id);
    Notification::assertSentTo($pedido, CadastroAnalisado::class, fn ($aviso) => $aviso->aprovado === true);

    $pedido->markEmailAsVerified();
    $this->actingAs($pedido)->get(route('meus-cursos'))->assertOk();
});

test('recusar exige motivo, avisa por e-mail e a pessoa não entra mais', function () {
    $this->post(route('register.store'), pedidoDeAcesso());
    $pedido = User::where('email', 'fulano@exemplo.com')->firstOrFail();
    $admin = contaDoPapel('administrador');

    $this->actingAs($admin)->post(route('admin.solicitacoes.recusar', $pedido), ['motivo' => ''])->assertSessionHasErrors('motivo');
    $this->post(route('admin.solicitacoes.recusar', $pedido), ['motivo' => 'A equipe não confirmou a indicação.'])->assertSessionHas('sucesso');

    expect($pedido->refresh()->situacao_cadastro)->toBe('recusado')
        ->and($pedido->cadastro_motivo)->toBe('A equipe não confirmou a indicação.');
    Notification::assertSentTo($pedido, CadastroAnalisado::class, fn ($aviso) => $aviso->aprovado === false);

    // Recusado que tenta entrar: deslogado, de volta à tela de entrar com o aviso.
    $this->actingAs($pedido)->get(route('meus-cursos'))->assertRedirect(route('login'));
});

test('o admin de equipe só vê e analisa os pedidos da própria equipe', function () {
    $this->post(route('register.store'), pedidoDeAcesso());
    $daMinha = User::where('email', 'fulano@exemplo.com')->firstOrFail();
    auth()->logout();
    $this->post(route('register.store'), pedidoDeAcesso(['email' => 'beltrano@exemplo.com', 'equipe_id' => $this->outraEquipe->id]));
    $daOutra = User::where('email', 'beltrano@exemplo.com')->firstOrFail();
    auth()->logout();

    $adminEquipe = contaDoPapel('admin_equipe', $this->equipe);

    $this->actingAs($adminEquipe)->get(route('admin.solicitacoes.index'))
        ->assertInertia(fn ($tela) => $tela->has('solicitacoes', 1)->where('solicitacoes.0.email', 'fulano@exemplo.com'));
    $this->post(route('admin.solicitacoes.aprovar', $daOutra))->assertForbidden();
    $this->post(route('admin.solicitacoes.aprovar', $daMinha))->assertSessionHas('sucesso');
});

test('o e-mail da chave de emergência (ADMIN_EMAILS) nasce aprovado e não fica trancado', function () {
    config(['app.admin_emails' => ['suporte@ib-to.test']]);

    // O cadastro já exige o e-mail em minúsculas (regra "lowercase" do kit).
    $this->post(route('register.store'), pedidoDeAcesso(['email' => 'suporte@ib-to.test']));

    expect(User::where('email', 'suporte@ib-to.test')->firstOrFail()->situacao_cadastro)->toBe('aprovado');
});

test('aluno comum não abre as solicitações; conta antiga continua aprovada', function () {
    $aluno = contaDoPapel('aluno');

    expect($aluno->refresh()->situacao_cadastro)->toBe('aprovado');
    $this->actingAs($aluno)->get(route('admin.solicitacoes.index'))->assertForbidden();
});
