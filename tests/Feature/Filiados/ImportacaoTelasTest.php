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
use Illuminate\Support\Facades\Log;
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

test('caminho completo: enviar, relacionar colunas e valores, prévia e confirmar', function () {
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

    // Salva as colunas: o mapa vai pela posição (0 = Nome completo, 1 = E-mail...).
    // O nível "Nacional" não bate com o cadastro, então segue para os VALORES.
    $mapa = ['nome', 'email', 'cpf', 'nascimento', 'municipio', 'uf', 'nivel'];
    $this->actingAs($this->admin)
        ->post(route('cadastros.filiados.importar.colunas.salvar', $codigo), ['mapa' => $mapa])
        ->assertRedirect(route('cadastros.filiados.importar.valores', $codigo));

    // Tela 3: só o "Nacional" aparece, já com a sugestão de abrangência.
    $this->actingAs($this->admin)
        ->get(route('cadastros.filiados.importar.valores', $codigo))
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('cadastros/filiados/importar-valores')
            ->has('valores', 1)
            ->where('valores.0.valor', 'Nacional')
            ->where('valores.0.sugestao', 'abrangencia:nacional'));

    // Aceita a sugestão e vai para a prévia.
    $this->actingAs($this->admin)
        ->post(route('cadastros.filiados.importar.valores.salvar', $codigo), ['escolhas' => ['abrangencia:nacional']])
        ->assertRedirect(route('cadastros.filiados.importar.previa', $codigo));

    // Tela 4: as 2 linhas entram.
    $this->actingAs($this->admin)
        ->get(route('cadastros.filiados.importar.previa', $codigo))
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('cadastros/filiados/importar-previa')
            ->where('validas', 2)
            ->where('teve_valores', true));

    // Confirmar: 2 entram; o arquivo some da memória temporária.
    $this->actingAs($this->admin)
        ->post(route('cadastros.filiados.importar.confirmar', $codigo))
        ->assertRedirect(route('cadastros.filiados.index'))
        ->assertSessionHas('sucesso', 'Importação concluída: 2 filiado(s) cadastrado(s); 0 linha(s) não importada(s).');

    expect(Filiado::count())->toBe(2);
    expect(Filiado::whereNotNull('abrangencia')->value('abrangencia'))->toBe('nacional');
    expect(Cache::has("importacao-filiados:{$codigo}"))->toBeFalse();
});

test('escolha que não está entre as opções é recusada (422)', function () {
    $codigo = enviarCsv($this);
    $this->actingAs($this->admin)->post(route('cadastros.filiados.importar.colunas.salvar', $codigo), [
        'mapa' => ['nome', 'email', 'cpf', 'nascimento', 'municipio', 'uf', 'nivel'],
    ]);

    $this->actingAs($this->admin)
        ->post(route('cadastros.filiados.importar.valores.salvar', $codigo), ['escolhas' => ['nivel:99999']])
        ->assertStatus(422);
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
        ->post(route('cadastros.filiados.importar.colunas.salvar', $codigo), ['mapa' => ['nome', 'nome', 'cpf']])
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

test('erro transparente: envio que não chegou ao servidor explica o motivo e anota no log', function () {
    Log::spy();

    // Simula o PHP sem conseguir gravar o arquivo temporário (código 7, UPLOAD_ERR_CANT_WRITE),
    // o mesmo erro do computador de desenvolvimento no Windows em 08/10/2026.
    $arquivo = new UploadedFile(__FILE__, 'filiados.csv', 'text/csv', UPLOAD_ERR_CANT_WRITE, true);

    $this->actingAs($this->admin)
        ->post(route('cadastros.filiados.importar.store'), ['planilha' => $arquivo])
        ->assertSessionHasErrors([
            'planilha' => 'O arquivo não chegou ao servidor: o servidor não conseguiu gravar o arquivo temporário (código IMPORTACAO_UPLOAD_FALHOU, detalhes no log).',
        ]);

    Log::shouldHaveReceived('error')->withArgs(
        fn (string $codigo, array $contexto) => $codigo === 'IMPORTACAO_UPLOAD_FALHOU' && $contexto['codigo_php'] === UPLOAD_ERR_CANT_WRITE,
    );
});
