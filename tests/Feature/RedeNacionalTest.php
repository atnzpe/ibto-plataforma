<?php

/*
 * Testes da REDE NACIONAL (#11, pedido do Mestre Eduardo, 23/10/2026): a foto
 * personalizada dos monitores e instrutores, por estado, para o site.
 * Regra: só Monitor/Instrutor/Instrutor Expert, ativo, anuidade em dia, com a
 * autorização de imagem e a foto; NUNCA contato na resposta. Dados INVENTADOS.
 */

use App\Actions\Filiados\CriarFiliado;
use App\Models\Anuidade;
use App\Models\Equipe;
use App\Models\Filiado;
use App\Models\Nivel;
use App\Models\User;
use App\Support\FotoDePerfil;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(FotoDePerfil::DISCO);
    Notification::fake();
    $this->equipe = Equipe::factory()->create(['nome' => 'Equipe Teste']);
});

/**
 * Cria um filiado JÁ credenciado (ativo, com autorização e foto), no nível e na UF pedidos.
 *
 * @param  string  $nome  Nome da conta. Ex.: 'Ana Maria Souza'.
 * @param  string  $categoria  Categoria do nível. Ex.: 'Instrutor'.
 * @param  int|null  $grau  Grau do nível (null = Praticante). Ex.: 2.
 * @param  string  $uf  Sigla do estado. Ex.: 'PE'.
 * @param  string  $cpf  CPF de teste (único por filiado).
 * @return Filiado O filiado pronto.
 */
function credenciado(string $nome, string $categoria, ?int $grau, string $uf, string $cpf): Filiado
{
    $filiado = app(CriarFiliado::class)->executar([
        'nome' => $nome, 'email' => strtolower(str_replace(' ', '.', $nome)).'@ibto.test', 'cpf' => $cpf,
        'nascimento' => '1990-01-01', 'municipio' => 'Cidade Secreta', 'uf' => $uf, 'equipe_id' => test()->equipe->id,
    ]);
    $nivel = Nivel::where('categoria', $categoria)->when($grau === null, fn ($q) => $q->whereNull('grau'), fn ($q) => $q->where('grau', $grau))->firstOrFail();
    $filiado->forceFill([
        'nivel_id' => $nivel->id, 'status' => 'ativo', 'telefone' => '(81) 99999-0000',
        'autoriza_imagem_rede' => true, 'autoriza_imagem_em' => now(),
        'foto_rede' => FotoDePerfil::gravar(UploadedFile::fake()->image('rede.jpg', 500, 500), 'fotos/rede-nacional'),
    ])->save();

    return $filiado->fresh();
}

test('separa instrutores e monitores, por estado e pela hierarquia, sem contato', function () {
    credenciado('Bruno Lima', 'Instrutor', 1, 'PE', '529.982.247-25');
    credenciado('Ana Maria Souza', 'Instrutor Expert', 2, 'PE', '111.444.777-35');
    credenciado('Carlos Dias', 'Instrutor', 3, 'BA', '390.533.447-05');
    credenciado('Davi Rocha', 'Monitor', 1, 'SP', '123.456.789-09');

    $resposta = $this->getJson(route('api.publico.rede-nacional'))->assertOk();

    // Estados em ordem de sigla (BA antes de PE); no PE, o Instrutor Expert vem primeiro.
    expect(collect($resposta->json('instrutores'))->pluck('uf')->all())->toBe(['BA', 'PE'])
        ->and($resposta->json('instrutores.1.estado'))->toBe('Pernambuco')
        ->and(collect($resposta->json('instrutores.1.pessoas'))->pluck('nome')->all())->toBe(['Ana M. S.', 'Bruno L.'])
        ->and($resposta->json('monitores.0.uf'))->toBe('SP')
        ->and($resposta->json('total'))->toBe(4)
        ->and($resposta->json('estados.PE'))->toBe('Pernambuco');

    // O cartão: só nome, nível, equipe, UF, áreas e foto. Nada de telefone, cidade ou e-mail.
    $cartao = $resposta->json('instrutores.1.pessoas.0');
    expect(array_keys($cartao))->toBe(['nome', 'nivel', 'equipe', 'uf', 'areas', 'foto_url'])
        ->and($cartao['nivel'])->toBe('Instrutor Expert II')
        ->and($cartao['equipe'])->toBe('Equipe Teste');
    expect($resposta->getContent())->not->toContain('Cidade Secreta')->not->toContain('99999')->not->toContain('@ibto.test');
});

test('fica de fora: Praticante, sem autorização, sem foto, inativo e sem UF válida', function () {
    $praticante = credenciado('Praticante Teste', 'Praticante', null, 'PE', '529.982.247-25');
    $semAutorizacao = credenciado('Sem Autorizacao', 'Instrutor', 1, 'PE', '111.444.777-35');
    $semAutorizacao->forceFill(['autoriza_imagem_rede' => false])->save();
    $semFoto = credenciado('Sem Foto', 'Instrutor', 2, 'PE', '390.533.447-05');
    $semFoto->forceFill(['foto_rede' => null])->save();
    $inativo = credenciado('Inativo Teste', 'Monitor', 1, 'PE', '123.456.789-09');
    $inativo->forceFill(['status' => 'inativo'])->save();
    $ufErrada = credenciado('Uf Errada', 'Monitor', 2, 'XX', '987.654.321-00');

    $resposta = $this->getJson(route('api.publico.rede-nacional'))->assertOk();

    expect($resposta->json('total'))->toBe(0)
        ->and($resposta->json('instrutores'))->toBe([])
        ->and($resposta->json('monitores'))->toBe([]);

    // A foto pública também não sai de nenhum deles.
    foreach ([$praticante, $semAutorizacao, $semFoto, $inativo, $ufErrada] as $filiado) {
        $this->get(route('rede-nacional.foto', $filiado->id))->assertNotFound();
    }
});

test('anuidade vencida tira da lista; paga ou ano isento, aparece', function () {
    $filiado = credenciado('Ana Souza', 'Instrutor', 1, 'PE', '529.982.247-25');
    // Ano de hoje com valor cobrado e sem pagamento: vencida.
    Anuidade::updateOrCreate(['ano' => now()->year], ['valor_centavos' => 9000, 'isenta' => false]);

    expect($this->getJson(route('api.publico.rede-nacional'))->json('total'))->toBe(0);
    $this->get(route('rede-nacional.foto', $filiado->id))->assertNotFound();

    // Ano isento: todos estão em dia.
    Anuidade::where('ano', now()->year)->update(['isenta' => true]);
    expect($this->getJson(route('api.publico.rede-nacional'))->json('total'))->toBe(1);
});

test('a foto pública sai de quem está na lista, com cache', function () {
    $filiado = credenciado('Ana Souza', 'Instrutor', 1, 'PE', '529.982.247-25');

    $this->get(route('rede-nacional.foto', $filiado->id))
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=3600, public');
});

test('enviar e tirar a foto da Rede Nacional: a própria pessoa sim, outra pessoa não', function () {
    $filiado = credenciado('Ana Souza', 'Instrutor', 1, 'PE', '529.982.247-25');
    $dono = $filiado->usuario;
    $dono->markEmailAsVerified();
    $antiga = $filiado->foto_rede;

    // Troca: grava a nova e apaga a antiga.
    $this->actingAs($dono)
        ->post(route('filiados.foto-rede.update', $filiado->id), ['foto' => UploadedFile::fake()->image('nova.png', 600, 800)])
        ->assertRedirect()->assertSessionHasNoErrors();
    $nova = $filiado->fresh()->foto_rede;
    expect($nova)->toStartWith('fotos/rede-nacional/')->not->toBe($antiga);
    Storage::disk(FotoDePerfil::DISCO)->assertMissing($antiga);
    $this->actingAs($dono)->get(route('filiados.foto-rede', $filiado->id))->assertOk();

    // Outra pessoa: não envia (403) nem vê a prévia protegida (404).
    $outro = User::factory()->create();
    $this->actingAs($outro)
        ->post(route('filiados.foto-rede.update', $filiado->id), ['foto' => UploadedFile::fake()->image('x.jpg')])
        ->assertForbidden();
    $this->actingAs($outro)->get(route('filiados.foto-rede', $filiado->id))->assertNotFound();

    // Tirar: apaga o arquivo e a pessoa sai da Rede Nacional.
    $this->actingAs($dono)->delete(route('filiados.foto-rede.destroy', $filiado->id))->assertRedirect();
    expect($filiado->fresh()->foto_rede)->toBeNull();
    Storage::disk(FotoDePerfil::DISCO)->assertMissing($nova);
    expect($this->getJson(route('api.publico.rede-nacional'))->json('total'))->toBe(0);
});

test('quadro do Comando Geral: todos os monitores e instrutores, com o que falta', function () {
    config(['app.admin_emails' => ['admin@ibto.test']]);
    $admin = User::factory()->create(['email' => 'admin@ibto.test']);
    credenciado('Ana Souza', 'Instrutor', 1, 'PE', '529.982.247-25');
    $semFoto = credenciado('Bruno Lima', 'Monitor', 1, 'BA', '111.444.777-35');
    $semFoto->forceFill(['foto_rede' => null])->save();
    // Praticante não entra no quadro.
    credenciado('Praticante Teste', 'Praticante', null, 'PE', '390.533.447-05');

    $this->actingAs($admin)
        ->get(route('cadastros.rede-nacional'))
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('cadastros/rede-nacional')
            ->has('linhas', 2)
            // BA antes de PE; o da BA está sem foto e não aparece.
            ->where('linhas.0.nome', 'Bruno Lima')
            ->where('linhas.0.lista', 'Monitores')
            ->where('linhas.0.tem_foto', false)
            ->where('linhas.0.aparece', false)
            ->where('linhas.1.aparece', true)
            ->where('resumo', ['total' => 2, 'aparecem' => 1]));

    // "Só pendentes" + estado: só o Bruno; o resumo continua contando todos do estado.
    $this->actingAs($admin)
        ->get(route('cadastros.rede-nacional', ['pendentes' => 1, 'uf' => 'ba']))
        ->assertInertia(fn ($pagina) => $pagina
            ->has('linhas', 1)
            ->where('filtros', ['uf' => 'BA', 'pendentes' => true])
            ->where('resumo', ['total' => 1, 'aparecem' => 0]));

    // Quem não cuida de todos os filiados: 403.
    $this->actingAs(User::factory()->create())
        ->get(route('cadastros.rede-nacional'))
        ->assertForbidden();
});
