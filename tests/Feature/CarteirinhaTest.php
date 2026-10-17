<?php

/*
 * Testes da CARTEIRINHA (#46): todo filiado novo ganha um código sorteado; a
 * validação pública mostra só o necessário (nome abreviado, situação, nível),
 * nunca CPF; nome completo, cidade e foto só com a autorização de imagem; código
 * inexistente não revela nada; anuidade pendente aparece; "Minha carteirinha"
 * só para filiados. Dados INVENTADOS (CPF de teste).
 */

use App\Actions\Filiados\CriarFiliado;
use App\Models\Anuidade;
use App\Models\Equipe;
use App\Models\Filiado;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->filiado = app(CriarFiliado::class)->executar([
        'nome' => 'Fulano de Tal Souza', 'email' => 'fulano@ibto.test', 'cpf' => '529.982.247-25',
        'nascimento' => '1990-01-01', 'municipio' => 'Recife', 'uf' => 'PE', 'equipe_id' => Equipe::factory()->create(['nome' => 'Equipe Teste Carteirinha'])->id,
    ]);
});

test('o filiado novo ganha um código sorteado no formato IBTO-XXXX-XXXX', function () {
    expect($this->filiado->codigo_validacao)->toMatch('/^IBTO-[A-Z2-9]{4}-[A-Z2-9]{4}$/');
});

test('a validação pública mostra só o necessário, sem CPF; nome completo e cidade só com autorização', function () {
    $resposta = $this->get(route('carteirinha.validar', strtolower($this->filiado->codigo_validacao)));
    $resposta->assertOk()->assertInertia(fn ($tela) => $tela
        ->where('carteirinha.nome_abreviado', 'Fulano T. S.')
        ->where('carteirinha.nome_completo', null)
        ->where('carteirinha.cidade', null)
        ->where('carteirinha.equipe', 'Equipe Teste Carteirinha'));
    expect($resposta->getContent())->not->toContain('52998224725')->not->toContain('529.982.247-25');

    // Com a autorização de imagem: nome completo e cidade aparecem.
    $this->filiado->forceFill(['autoriza_imagem_rede' => true])->save();
    $this->get(route('carteirinha.validar', $this->filiado->codigo_validacao))
        ->assertInertia(fn ($tela) => $tela->where('carteirinha.nome_completo', 'Fulano de Tal Souza')->where('carteirinha.cidade', 'Recife-PE'));
});

test('código inexistente não revela nada; a foto pública sem autorização dá 404', function () {
    $this->get(route('carteirinha.validar', 'IBTO-XXXX-XXXX'))->assertInertia(fn ($tela) => $tela->where('carteirinha', null));
    $this->get(route('carteirinha.foto', $this->filiado->codigo_validacao))->assertNotFound();
});

test('situação: ativa no ano isento; anuidade pendente quando há valor e não pagou; inativa', function () {
    expect($this->filiado->situacaoCarteirinha()['situacao'])->toBe('ativa');

    Anuidade::updateOrCreate(['ano' => today()->year], ['valor_centavos' => 9000, 'isenta' => false]);
    expect(Filiado::find($this->filiado->id)->situacaoCarteirinha()['situacao'])->toBe('pendente');

    $this->filiado->update(['status' => 'inativo']);
    expect(Filiado::find($this->filiado->id)->situacaoCarteirinha()['situacao'])->toBe('inativa');
});

test('Minha carteirinha: o filiado vê a dele com o QR; quem não é filiado recebe 404', function () {
    $usuario = $this->filiado->usuario;
    $usuario->markEmailAsVerified();

    $this->actingAs($usuario)->get(route('carteirinha.minha'))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela->where('carteirinha.nome', 'Fulano de Tal Souza')->where('carteirinha.qr_svg', fn ($svg) => str_starts_with($svg, '<svg')));

    $this->actingAs(User::factory()->create())->get(route('carteirinha.minha'))->assertNotFound();
});
