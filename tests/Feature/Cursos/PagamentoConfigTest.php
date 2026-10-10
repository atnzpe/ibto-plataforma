<?php

/*
 * Testes dos DADOS PARA PAGAMENTO (#41; pedidos do Gleyson em 10/10/2026:
 * "as duas opções e uma chave liga/desliga" e "tela bem segura"):
 * - a chave liga/desliga decide se valem os dados do curso ou os do IBTO;
 * - a tela pede a SENHA DE NOVO; sem a permissão: 403;
 * - cada troca anota o antes e o depois e alerta todos os responsáveis;
 * - quem propõe o curso NÃO muda os dados de pagamento dele;
 * - visitante nunca vê a chave PIX.
 * Dados INVENTADOS.
 */

use App\Actions\Cursos\Matriculas;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\PagamentoConfig;
use App\Models\Papel;
use App\Models\User;
use App\Notifications\PagamentoAlterado;
use App\Support\DadosPagamento;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->create();
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));

    PagamentoConfig::atual()->update(['pix_chave' => 'ibto@pix.test', 'favorecido' => 'IBTO']);
    $this->curso = Curso::create(['titulo' => 'Curso da Equipe', 'modalidade' => 'online', 'acesso' => 'aberto', 'valor_centavos' => 10000]);
    // A situação e os dados de pagamento não entram pelo create: forceFill.
    $this->curso->forceFill(['situacao' => 'publicado', 'pag_pix_chave' => 'equipe@pix.test', 'pag_favorecido' => 'Equipe Vulcan'])->save();
});

/**
 * Entra como a administradora COM a senha já confirmada (como se tivesse
 * acabado de digitar a senha na tela "Confirme a sua senha").
 */
function adminComSenhaConfirmada($teste)
{
    return $teste->actingAs($teste->admin)->withSession(['auth.password_confirmed_at' => time()]);
}

test('chave DESLIGADA: vale o PIX do IBTO; LIGADA: vale o do curso; curso sem dados: o do IBTO', function () {
    expect(DadosPagamento::doCurso($this->curso))->toMatchArray(['origem' => 'ibto', 'pix_chave' => 'ibto@pix.test']);

    PagamentoConfig::atual()->update(['por_curso' => true]);
    expect(DadosPagamento::doCurso($this->curso))->toMatchArray(['origem' => 'curso', 'pix_chave' => 'equipe@pix.test', 'favorecido' => 'Equipe Vulcan']);

    $semDados = Curso::create(['titulo' => 'Outro', 'modalidade' => 'online', 'acesso' => 'aberto', 'valor_centavos' => 10000]);
    expect(DadosPagamento::doCurso($semDados)['origem'])->toBe('ibto');
});

test('a tela pede a senha de novo; sem a permissão: 403', function () {
    // Logada, mas sem ter confirmado a senha agora: vai para "Confirme a sua senha".
    $this->actingAs($this->admin)->get(route('comando-geral.pagamento.edit'))
        ->assertRedirect(route('password.confirm'));

    adminComSenhaConfirmada($this)->get(route('comando-geral.pagamento.edit'))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela->component('cursos/pagamento')->where('config.pix_chave', 'ibto@pix.test'));

    $this->actingAs(User::factory()->create())->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('comando-geral.pagamento.edit'))
        ->assertForbidden();
});

test('trocar o PIX: antes e depois na auditoria e alerta para TODOS os responsáveis', function () {
    $tecnologia = User::factory()->create();
    $tecnologia->papeis()->attach(Papel::where('slug', 'tecnologia')->value('id'));

    adminComSenhaConfirmada($this)
        ->put(route('comando-geral.pagamento.update'), ['pix_chave' => 'novo@pix.test', 'favorecido' => 'IBTO', 'por_curso' => '0'])
        ->assertRedirect(route('comando-geral.pagamento.edit'));

    $linha = Auditoria::where('acao', 'PAGAMENTO_CONFIG_ALTERADO')->latest('id')->firstOrFail();
    expect($linha->detalhes['mudancas']['pix_chave'])->toEqual(['antes' => 'ibto@pix.test', 'depois' => 'novo@pix.test']);
    // Só o campo que mudou (o favorecido continuou igual).
    expect($linha->detalhes['mudancas'])->not->toHaveKey('favorecido');

    Notification::assertSentTo([$this->admin, $tecnologia], PagamentoAlterado::class);
});

test('salvar sem mudar nada não anota nem alerta', function () {
    adminComSenhaConfirmada($this)
        ->put(route('comando-geral.pagamento.update'), ['pix_chave' => 'ibto@pix.test', 'favorecido' => 'IBTO', 'por_curso' => '0']);

    expect(Auditoria::where('acao', 'PAGAMENTO_CONFIG_ALTERADO')->count())->toBe(0);
    Notification::assertNothingSent();
});

test('dados do curso: só com a permissão; quem propôs o curso não muda (403) nem pelo formulário comum', function () {
    adminComSenhaConfirmada($this)
        ->put(route('comando-geral.cursos.pagamento', $this->curso), ['pix_chave' => 'outra@pix.test'])
        ->assertRedirect(route('comando-geral.cursos.edit', $this->curso));
    expect($this->curso->fresh()->pag_pix_chave)->toBe('outra@pix.test');

    // Instrutor (propõe cursos) é dono do curso, mas não tem pagamentos.configurar.
    $instrutor = User::factory()->create();
    $instrutor->papeis()->attach(Papel::where('slug', 'instrutor')->value('id'));
    $this->curso->forceFill(['responsavel_id' => $instrutor->id])->save();
    $this->actingAs($instrutor)->withSession(['auth.password_confirmed_at' => time()])
        ->put(route('comando-geral.cursos.pagamento', $this->curso), ['pix_chave' => 'golpe@pix.test'])
        ->assertForbidden();

    // O campo não é preenchível pelo formulário comum do curso (fora do Fillable).
    expect((new Curso)->isFillable('pag_pix_chave'))->toBeFalse();
    expect($this->curso->fresh()->pag_pix_chave)->toBe('outra@pix.test');
});

test('visitante nunca vê a chave PIX; o aluno com matrícula aguardando vê', function () {
    $this->get(route('catalogo.curso', $this->curso))
        ->assertInertia(fn ($tela) => $tela->where('matricula', null))
        ->assertDontSee('ibto@pix.test');

    $aluno = User::factory()->create();
    app(Matriculas::class)->matricular($this->curso, $aluno);
    $this->actingAs($aluno)->get(route('catalogo.curso', $this->curso))
        ->assertInertia(fn ($tela) => $tela->where('matricula.pagamento.pix_chave', 'ibto@pix.test'));
});
