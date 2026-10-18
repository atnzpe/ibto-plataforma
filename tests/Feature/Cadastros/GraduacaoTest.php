<?php

/*
 * Testes do CERTIFICADO DE GRADUAÇÃO (#46, parte 2): número de série no padrão
 * do modelo (GR + ano + ordem + letra do nível + via), sugestão dos textos,
 * validação pública (nome abreviado; cancelado aparece cancelado), só quem
 * gerencia os filiados emite, o dono imprime e outra pessoa recebe 403.
 * Dados INVENTADOS.
 */

use App\Actions\Filiados\CertificadosGraduacao;
use App\Actions\Filiados\CriarFiliado;
use App\Enums\CategoriaNivel;
use App\Models\GraduacaoCertificado;
use App\Models\Nivel;
use App\Models\Papel;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $this->filiado = app(CriarFiliado::class)->executar([
        'nome' => 'Fulano Teste da Silva', 'email' => 'fulano@ibto.test', 'cpf' => '529.982.247-25',
        'nascimento' => '1990-05-20', 'municipio' => 'Recife', 'uf' => 'PE',
    ]);
    // A conta do filiado já confirmou o e-mail (as telas com login pedem isso).
    $this->filiado->usuario->forceFill(['email_verified_at' => now()])->save();
    // Instrutor Expert II (letra E no número de série).
    $this->filiado->forceFill(['nivel_id' => Nivel::where('categoria', CategoriaNivel::InstrutorExpert->value)->where('grau', 2)->value('id')])->save();
});

test('o número de série segue o modelo e a sugestão vem do nível', function () {
    $graduacoes = app(CertificadosGraduacao::class);
    $ordem = substr($this->filiado->registro, 6, 4);

    expect($graduacoes->numeroSerie($this->filiado->fresh()))->toBe('GR'.today()->year.$ordem.'E0');
    expect($graduacoes->sugestao($this->filiado->fresh())['nivel'])->toBe('Nível Expert II - [E]');
});

test('emitir pela ficha: segunda via ganha o número seguinte; a validação pública mostra o nome abreviado', function () {
    $dados = ['condicao' => 'Instrutor de Krav Maga', 'nivel' => 'Nível Expert II - [E]', 'abrangencia' => 'Nacional', 'local' => 'Rio de Janeiro - RJ'];
    $this->actingAs($this->admin)->post(route('cadastros.filiados.graduacao', $this->filiado), $dados)->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post(route('cadastros.filiados.graduacao', $this->filiado), $dados)->assertSessionHasNoErrors();

    $series = GraduacaoCertificado::orderBy('id')->pluck('numero_serie')->all();
    expect($series[0])->toEndWith('E0')->and($series[1])->toEndWith('E1');

    $primeiro = GraduacaoCertificado::orderBy('id')->first();
    $this->get(route('certificados.validar', $primeiro->codigo))
        ->assertInertia(fn ($tela) => $tela
            ->where('certificado', null)
            ->where('graduacao.valido', true)
            ->where('graduacao.nome', 'Fulano T. S.')
            ->where('graduacao.condicao', 'Instrutor de Krav Maga'));
});

test('cancelado aparece como cancelado; só quem gerencia emite; o dono imprime e outra pessoa não', function () {
    $certificado = app(CertificadosGraduacao::class)->emitir($this->filiado->fresh(), ['condicao' => 'Instrutor de Krav Maga', 'nivel' => 'Nível Expert II - [E]', 'abrangencia' => 'Nacional', 'local' => 'Recife - PE']);

    // O próprio filiado vê o certificado para imprimir; outra pessoa, não.
    $this->actingAs($this->filiado->usuario)->get(route('graduacoes.ver', $certificado->codigo))->assertOk()
        ->assertInertia(fn ($tela) => $tela->component('graduacoes/ver')->where('certificado.numero_serie', $certificado->numero_serie));
    $this->actingAs(User::factory()->create())->get(route('graduacoes.ver', $certificado->codigo))->assertForbidden();
    // Quem não gerencia os filiados não emite.
    $this->actingAs($this->filiado->usuario)->post(route('cadastros.filiados.graduacao', $this->filiado), ['condicao' => 'x', 'nivel' => 'x', 'abrangencia' => 'x', 'local' => 'x'])->assertForbidden();

    $this->actingAs($this->admin)->post(route('cadastros.graduacoes.cancelar', $certificado), ['motivo' => 'Nível errado'])->assertSessionHasNoErrors();
    $this->get(route('certificados.validar', $certificado->codigo))->assertInertia(fn ($tela) => $tela->where('graduacao.valido', false));
    // O dossiê do filiado lista o certificado (cancelado).
    $this->actingAs($this->filiado->usuario)->get(route('meu-dossie'))->assertInertia(fn ($tela) => $tela->where('graduacoes.0.valido', false));
});
