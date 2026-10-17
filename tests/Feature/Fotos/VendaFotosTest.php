<?php

/*
 * Testes da VENDA DE FOTOS DOS EVENTOS (#90): desligada não aparece; o fotógrafo
 * envia (original privado + prévia com marca d'água); só a turma vê e compra; o
 * Pix sai com o identificador IBTOFOT…; confirmar lança a receita e a parte do
 * fotógrafo; o original só baixa depois de pago; a limpeza apaga as fotos
 * vencidas e guarda o pedido. Dados INVENTADOS.
 */

use App\Actions\Cursos\Matriculas;
use App\Actions\Fotos\VendaFotos;
use App\Enums\SituacaoCurso;
use App\Exceptions\RegraCurso;
use App\Models\Configuracao;
use App\Models\Curso;
use App\Models\FinFormaPagamento;
use App\Models\FinLancamento;
use App\Models\FotoAlbumVenda;
use App\Models\FotoVenda;
use App\Models\PagamentoConfig;
use App\Models\Papel;
use App\Models\PedidoFoto;
use App\Models\User;
use App\Support\FotoComMarcaDagua;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(FotoComMarcaDagua::DISCO);
    Notification::fake();
    PagamentoConfig::atual()->update(['pix_chave' => 'financeiro@ibto.test', 'favorecido' => 'IBTO Teste']);

    // O curso do evento, gratuito para os filiados (a matrícula já nasce confirmada).
    $this->curso = Curso::create(['titulo' => 'Combate com Faca', 'modalidade' => 'presencial', 'acesso' => 'aberto', 'valor_centavos' => 0, 'data_inicio' => today()->addDay()->toDateString(), 'local' => 'Recife']);
    $this->curso->forceFill(['situacao' => SituacaoCurso::Publicado])->save();

    // O aluno da turma (matrícula confirmada) e alguém de fora.
    $this->aluno = User::factory()->create();
    app(Matriculas::class)->matricular($this->curso, $this->aluno, peloIbto: true);
    $this->deFora = User::factory()->create();

    // O fotógrafo credenciado e o álbum (R$ 15,00 por foto, 70% para ele).
    $this->fotografo = User::factory()->create(['name' => 'Fulano Fotos']);
    $this->fotografo->papeis()->attach(Papel::where('slug', 'fotografo')->value('id'));
    $this->album = FotoAlbumVenda::create([
        'curso_id' => $this->curso->id, 'fotografo_id' => $this->fotografo->id, 'titulo' => 'Combate com Faca — turma',
        'preco_centavos' => 1500, 'percentual_fotografo' => 70, 'expira_em' => today()->addDays(60)->toDateString(), 'ativo' => true,
    ]);

    $this->admin = User::factory()->create();
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
});

/**
 * O fotógrafo envia N fotos pelo formulário; devolve as fotos gravadas.
 *
 * @return list<FotoVenda>
 */
function fotografoEnvia($teste, int $quantas = 2): array
{
    $arquivos = array_map(fn ($n) => UploadedFile::fake()->image("foto{$n}.jpg", 1200, 800), range(1, $quantas));
    $teste->actingAs($teste->fotografo)->post(route('fotos.fotografo.enviar', $teste->album), ['fotos' => $arquivos])->assertSessionHasNoErrors();

    return FotoVenda::where('album_id', $teste->album->id)->orderBy('id')->get()->all();
}

test('começa ligada (decisão de 17/10); desligada, a turma não vê a vitrine e não consegue comprar', function () {
    $fotos = fotografoEnvia($this);

    // A loja vem ligada desde a migration de 17/10/2026.
    expect(VendaFotos::ligada())->toBeTrue();

    // Desligada pelo interruptor: nada de vitrine nem de compra.
    Configuracao::definir(VendaFotos::CHAVE, false);
    expect(VendaFotos::ligada())->toBeFalse();
    $this->actingAs($this->aluno)->get(route('fotos.vitrine', $this->album))->assertNotFound();
    expect(fn () => app(VendaFotos::class)->criarPedido($this->album, $this->aluno, [$fotos[0]->id]))
        ->toThrow(fn (RegraCurso $erro) => expect($erro->codigo)->toBe('VENDA_FOTOS_DESLIGADA'));
});

test('o envio guarda o original privado e gera a prévia (1000 px, com a marca)', function () {
    $foto = fotografoEnvia($this, 1)[0];

    Storage::disk(FotoComMarcaDagua::DISCO)->assertExists($foto->original);
    Storage::disk(FotoComMarcaDagua::DISCO)->assertExists($foto->previa);
    [$largura] = getimagesizefromstring(Storage::disk(FotoComMarcaDagua::DISCO)->get($foto->previa));
    expect($largura)->toBe(1000);
});

test('ligada: só a turma vê e compra; o Pix sai com IBTOFOT; confirmar divide 70/30 e libera o original', function () {
    Configuracao::definir(VendaFotos::CHAVE, true);
    $fotos = fotografoEnvia($this, 3);

    // Quem não fez o curso: 404 na vitrine e na prévia.
    $this->actingAs($this->deFora)->get(route('fotos.vitrine', $this->album))->assertNotFound();
    $this->actingAs($this->deFora)->get(route('fotos.previa', $fotos[0]))->assertNotFound();

    // A turma: vê e compra 2 fotos (R$ 30,00).
    $this->actingAs($this->aluno)->get(route('fotos.vitrine', $this->album))->assertOk();
    $this->actingAs($this->aluno)->post(route('fotos.comprar', $this->album), ['fotos' => [$fotos[0]->id, $fotos[1]->id]])->assertRedirect();
    $pedido = PedidoFoto::firstOrFail();
    expect($pedido->valor_centavos)->toBe(3000);

    $this->actingAs($this->aluno)->get(route('fotos.pedido', $pedido))
        ->assertInertia(fn ($tela) => $tela->where('pix.txid', 'IBTOFOT'.$pedido->id)->where('pix.copia_e_cola', fn ($codigo) => str_contains($codigo, '540530.00')));

    // Antes de pagar, o original não sai.
    $this->actingAs($this->aluno)->get(route('fotos.original', [$pedido, $fotos[0]]))->assertNotFound();

    // O Comando Geral confirma: receita de R$ 30,00 e repasse de 70% (R$ 21,00) ao fotógrafo.
    $this->actingAs($this->admin)->post(route('fotos.admin.pedidos.confirmar', $pedido), [
        'pago_em' => today()->toDateString(), 'forma_pagamento_id' => FinFormaPagamento::where('nome', 'PIX')->value('id'),
    ])->assertSessionHasNoErrors();
    expect(FinLancamento::where('origem', 'pedido_foto')->value('valor_centavos'))->toBe(3000)
        ->and(FinLancamento::where('origem', 'repasse_foto')->value('valor_centavos'))->toBe(2100);

    // Pago: baixa o original (só as fotos do pedido).
    $this->actingAs($this->aluno)->get(route('fotos.original', [$pedido, $fotos[0]]))->assertOk();
    $this->actingAs($this->aluno)->get(route('fotos.original', [$pedido, $fotos[2]]))->assertNotFound();
    // Outra pessoa não baixa o pedido de ninguém.
    $this->actingAs($this->deFora)->get(route('fotos.original', [$pedido, $fotos[0]]))->assertNotFound();
});

test('o fotógrafo vê só os álbuns dele; o aluno não entra na área do fotógrafo', function () {
    $outro = User::factory()->create();
    $outro->papeis()->attach(Papel::where('slug', 'fotografo')->value('id'));

    $this->actingAs($outro->fresh())->get(route('fotos.fotografo.album', $this->album))->assertNotFound();
    $this->actingAs($this->aluno)->get(route('fotos.fotografo.index'))->assertForbidden();
    $this->actingAs($this->aluno)->get(route('fotos.admin.index'))->assertForbidden();
});

test('a limpeza apaga as fotos vencidas e guarda o pedido (sem a foto)', function () {
    Configuracao::definir(VendaFotos::CHAVE, true);
    $foto = fotografoEnvia($this, 1)[0];
    $pedido = app(VendaFotos::class)->criarPedido($this->album, $this->aluno, [$foto->id]);

    $this->album->forceFill(['expira_em' => today()->subDay()])->save();
    $this->artisan('fotos:expirar')->assertSuccessful();

    Storage::disk(FotoComMarcaDagua::DISCO)->assertMissing($foto->original);
    Storage::disk(FotoComMarcaDagua::DISCO)->assertMissing($foto->previa);
    expect($this->album->fresh()->expirado)->toBeTrue()
        ->and(FotoVenda::count())->toBe(0)
        ->and(PedidoFoto::whereKey($pedido->id)->exists())->toBeTrue();
});

test('o interruptor: só o Comando Geral liga e desliga', function () {
    $this->actingAs($this->admin)->post(route('fotos.admin.interruptor'), ['ligada' => true])->assertRedirect();
    expect(VendaFotos::ligada())->toBeTrue();

    $this->actingAs($this->aluno)->post(route('fotos.admin.interruptor'), ['ligada' => false])->assertForbidden();
    expect(VendaFotos::ligada())->toBeTrue();
});
