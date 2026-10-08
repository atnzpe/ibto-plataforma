<?php

/*
 * Testes das TELAS da importação de filiados (#37, F4b): o caminho completo
 * (enviar → colunas → prévia → confirmar), quem pode importar, a importação
 * presa a quem começou e o arquivo apagado no fim. Planilha INVENTADA.
 */

use App\Models\Filiado;
use App\Models\Papel;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    config(['app.admin_emails' => []]);
    $this->admin = User::factory()->create();
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
});

/**
 * Um arquivo CSV inventado: 1 linha certa e 1 com nível que não existe.
 */
function csvInventado(): UploadedFile
{
    $conteudo = implode("\n", [
        'Nome completo;E-mail;CPF;Nascimento;Município;UF;Nível',
        'Aluno Um;um@ibto.test;529.982.247-25;20/05/1990;Recife;PE;Praticante',
        'Aluno Dois;dois@ibto.test;111.444.777-35;10/10/1985;Recife;PE;Nacional',
    ]);

    return UploadedFile::fake()->createWithContent('filiados.csv', $conteudo);
}

/**
 * Envia o arquivo e devolve o código da importação (tirado do endereço de destino).
 */
function enviarCsv($teste): string
{
    $resposta = $teste->actingAs($teste->admin)
        ->post(route('cadastros.filiados.importar.store'), ['planilha' => csvInventado()]);

    $resposta->assertRedirect();
    preg_match('#importar/([0-9a-f-]{36})/colunas#', (string) $resposta->headers->get('Location'), $achado);

    return $achado[1];
}

test('caminho completo: enviar, relacionar colunas, prévia e confirmar', function () {
    $codigo = enviarCsv($this);

    // Tela 2: as 7 colunas, com a sugestão marcada.
    $this->actingAs($this->admin)
        ->get(route('cadastros.filiados.importar.colunas', $codigo))
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('cadastros/filiados/importar-colunas')
            ->has('colunas', 7)
            ->where('sugestao.CPF', 'cpf')
            ->where('total_linhas', 2));

    // Tela 3: o mapa vai pela posição das colunas (0 = Nome completo, 1 = E-mail...).
    $mapa = ['nome', 'email', 'cpf', 'nascimento', 'municipio', 'uf', 'nivel'];
    $this->actingAs($this->admin)
        ->post(route('cadastros.filiados.importar.previa', $codigo), ['mapa' => $mapa])
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('cadastros/filiados/importar-previa')
            ->where('validas', 1)
            ->where('recusadas.0.linha', 3));

    // Confirmar: 1 entra, 1 recusada; o arquivo some da memória temporária.
    $this->actingAs($this->admin)
        ->post(route('cadastros.filiados.importar.confirmar', $codigo))
        ->assertRedirect(route('cadastros.filiados.index'))
        ->assertSessionHas('sucesso', 'Importação concluída: 1 filiado(s) cadastrado(s) e convidado(s); 1 linha(s) recusada(s).');

    expect(Filiado::count())->toBe(1);
    expect(Cache::has("importacao-filiados:{$codigo}"))->toBeFalse();
});

test('a importação é presa a quem começou: outro administrador recebe 404', function () {
    $codigo = enviarCsv($this);

    $outro = User::factory()->create();
    $outro->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));

    $this->actingAs($outro)
        ->get(route('cadastros.filiados.importar.colunas', $codigo))
        ->assertNotFound();
});

test('o mesmo campo em duas colunas é recusado', function () {
    $codigo = enviarCsv($this);

    $this->actingAs($this->admin)
        ->post(route('cadastros.filiados.importar.previa', $codigo), ['mapa' => ['nome', 'nome', 'cpf']])
        ->assertStatus(422);
});

test('Administrador de equipe não importa (403)', function () {
    $responsavel = User::factory()->create();
    $responsavel->papeis()->attach(Papel::where('slug', 'admin_equipe')->value('id'));

    $this->actingAs($responsavel)
        ->get(route('cadastros.filiados.importar.create'))
        ->assertForbidden();
});

test('arquivo que não é CSV é recusado com mensagem em português', function () {
    $this->actingAs($this->admin)
        ->post(route('cadastros.filiados.importar.store'), [
            'planilha' => UploadedFile::fake()->create('foto.png', 10, 'image/png'),
        ])
        ->assertSessionHasErrors(['planilha' => 'Envie um arquivo CSV (no Excel: Salvar como → CSV).']);
});
