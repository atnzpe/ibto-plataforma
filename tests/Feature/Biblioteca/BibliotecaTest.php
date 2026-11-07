<?php

/*
 * Testes da BIBLIOTECA DE TÉCNICAS (#107):
 *   - quem entra: instrutor (papel), monitor graduado (pelo nível) e o Comando
 *     Geral; o aluno, o Praticante e o graduado com a anuidade vencida, não;
 *   - o nível: material de Instrutor não aparece para o Monitor, nem pelo link;
 *   - retirado some para quem estuda, mas o Comando Geral ainda vê;
 *   - o Comando Geral envia vídeo e apostila (disco PRIVADO), edita, retira e
 *     tudo vai para a auditoria; quem não é do Comando Geral não envia;
 *   - link de vídeo de um site qualquer é recusado (VIDEO_INVALIDO).
 * Dados INVENTADOS (CPF de teste).
 */

use App\Actions\Filiados\CriarFiliado;
use App\Models\Anuidade;
use App\Models\AreaAtuacao;
use App\Models\Auditoria;
use App\Models\BibliotecaMaterial;
use App\Models\Equipe;
use App\Models\Nivel;
use App\Models\Papel;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Nenhum e-mail sai de verdade, e os arquivos vão para um disco de mentira.
    Notification::fake();
    Storage::fake('local');

    // Os níveis que a migration já cadastra (Praticante, Monitor I, Instrutor I...).
    $this->praticante = Nivel::where('categoria', 'Praticante')->firstOrFail();
    $this->monitor = Nivel::where('categoria', 'Monitor')->where('grau', 1)->firstOrFail();
    $this->instrutor = Nivel::where('categoria', 'Instrutor')->where('grau', 1)->firstOrFail();

    // Comando Geral inventado (papel administrador, que recebe "biblioteca.gerenciar").
    $this->comando = User::factory()->create();
    $this->comando->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));

    // Dois materiais: um sem nível (todos veem) e um só a partir de Instrutor I.
    $this->basico = BibliotecaMaterial::create(['titulo' => 'Postura de guarda', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'ativo' => true]);
    $this->avancado = BibliotecaMaterial::create(['titulo' => 'Defesa contra faca avançada', 'nivel_minimo_id' => $this->instrutor->id, 'ativo' => true]);
});

/**
 * Cria um filiado inventado com o nível pedido e devolve a conta dele.
 *
 * @param  Nivel  $nivel  O nível do filiado. Ex.: $this->monitor.
 * @param  string  $cpf  Um CPF de teste diferente para cada filiado. Ex.: '529.982.247-25'.
 * @return User A conta (login) do filiado.
 */
function filiadoComNivel(Nivel $nivel, string $cpf): User
{
    $filiado = app(CriarFiliado::class)->executar([
        'nome' => 'Filiado Teste '.$nivel->id, 'email' => 'filiado'.$nivel->id.'@ibto.test', 'cpf' => $cpf,
        'nascimento' => '1990-01-01', 'municipio' => 'Recife', 'uf' => 'PE',
        'equipe_id' => Equipe::factory()->create()->id, 'nivel_id' => $nivel->id,
    ]);

    $usuario = User::findOrFail($filiado->user_id);
    // E-mail confirmado: sem isso, a plataforma manda primeiro confirmar o e-mail.
    $usuario->markEmailAsVerified();

    return $usuario;
}

test('o monitor graduado entra e vê só o material liberado para o nível dele', function () {
    $monitor = filiadoComNivel($this->monitor, '529.982.247-25');

    $this->actingAs($monitor)->get(route('biblioteca.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $tela) => $tela
            ->component('biblioteca/index')
            ->has('materiais', 1)
            ->where('materiais.0.titulo', 'Postura de guarda'));
});

test('o monitor não abre o material de Instrutor nem com o link copiado', function () {
    $monitor = filiadoComNivel($this->monitor, '529.982.247-25');

    $this->actingAs($monitor)->get(route('biblioteca.show', $this->avancado))->assertForbidden();
    $this->actingAs($monitor)->get(route('biblioteca.apostila', $this->avancado))->assertForbidden();
    $this->actingAs($monitor)->get(route('biblioteca.video', $this->avancado))->assertForbidden();
});

test('o instrutor graduado vê os dois materiais', function () {
    $instrutor = filiadoComNivel($this->instrutor, '111.444.777-35');

    $this->actingAs($instrutor)->get(route('biblioteca.index'))
        ->assertInertia(fn (Assert $tela) => $tela->has('materiais', 2));
    $this->actingAs($instrutor)->get(route('biblioteca.show', $this->avancado))->assertOk();
});

test('o Praticante e o aluno sem ficha de filiado não entram', function () {
    $praticante = filiadoComNivel($this->praticante, '529.982.247-25');

    $this->actingAs($praticante)->get(route('biblioteca.index'))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('biblioteca.index'))->assertForbidden();
});

test('o graduado com a anuidade vencida não entra até pagar', function () {
    $monitor = filiadoComNivel($this->monitor, '529.982.247-25');
    // Ano com anuidade cobrada e não paga: a carteirinha fica "pendente".
    Anuidade::updateOrCreate(['ano' => today()->year], ['valor_centavos' => 9000, 'isenta' => false]);

    $this->actingAs($monitor)->get(route('biblioteca.index'))->assertForbidden();
});

test('quem tem o papel de instrutor entra mesmo sem ficha, mas só vê o material sem nível', function () {
    $conta = User::factory()->create();
    $conta->papeis()->attach(Papel::where('slug', 'instrutor')->value('id'));

    $this->actingAs($conta)->get(route('biblioteca.index'))
        ->assertInertia(fn (Assert $tela) => $tela->has('materiais', 1));
});

test('material retirado some para quem estuda, mas o Comando Geral ainda abre', function () {
    $instrutor = filiadoComNivel($this->instrutor, '111.444.777-35');
    $this->basico->update(['ativo' => false]);

    $this->actingAs($instrutor)->get(route('biblioteca.index'))
        ->assertInertia(fn (Assert $tela) => $tela->has('materiais', 1));
    $this->actingAs($instrutor)->get(route('biblioteca.show', $this->basico))->assertForbidden();
    $this->actingAs($this->comando)->get(route('biblioteca.show', $this->basico))->assertOk();
});

test('filtro por área traz a área escolhida e os materiais de todas as áreas; a busca acha pelo nome', function () {
    $instrutor = filiadoComNivel($this->instrutor, '111.444.777-35');
    $area = AreaAtuacao::factory()->create(['nome' => 'Área Teste Biblioteca A']);
    $outra = AreaAtuacao::factory()->create(['nome' => 'Área Teste Biblioteca B']);
    $this->avancado->update(['area_atuacao_id' => $outra->id]);

    // Área A: só o básico (vale para todas); o avançado é da área B.
    $this->actingAs($instrutor)->get(route('biblioteca.index', ['area' => $area->id]))
        ->assertInertia(fn (Assert $tela) => $tela->has('materiais', 1)->where('materiais.0.titulo', 'Postura de guarda'));
    // Busca "faca": só o avançado.
    $this->actingAs($instrutor)->get(route('biblioteca.index', ['busca' => 'faca']))
        ->assertInertia(fn (Assert $tela) => $tela->has('materiais', 1)->where('materiais.0.titulo', 'Defesa contra faca avançada'));
});

test('o Comando Geral envia vídeo e apostila para a pasta privada, com auditoria', function () {
    $this->actingAs($this->comando)->post(route('comando-geral.biblioteca.store'), [
        'titulo' => 'Queda para trás',
        'descricao' => 'Proteja a cabeça.',
        'nivel_minimo_id' => $this->monitor->id,
        'fonte_video' => 'arquivo',
        'video_arquivo' => UploadedFile::fake()->create('queda.mp4', 500, 'video/mp4'),
        'apostila' => UploadedFile::fake()->create('queda.pdf', 100, 'application/pdf'),
        'ativo' => true,
    ])->assertRedirect(route('comando-geral.biblioteca.index'));

    $material = BibliotecaMaterial::where('titulo', 'Queda para trás')->firstOrFail();
    expect($material->autor_id)->toBe($this->comando->id)
        ->and($material->video_arquivo)->toStartWith('biblioteca/')
        ->and($material->apostila)->toStartWith('biblioteca/');
    Storage::disk('local')->assertExists([$material->video_arquivo, $material->apostila]);
    expect(Auditoria::where('acao', 'BIBLIOTECA_MATERIAL_CRIADO')->exists())->toBeTrue();

    // O monitor (nível exigido) baixa a apostila e toca o vídeo.
    $monitor = filiadoComNivel($this->monitor, '529.982.247-25');
    $this->actingAs($monitor)->get(route('biblioteca.apostila', $material))->assertOk();
    $this->actingAs($monitor)->get(route('biblioteca.video', $material))->assertOk();
});

test('retirar pela edição fica na auditoria e trocar para link apaga o vídeo enviado', function () {
    Storage::disk('local')->put('biblioteca/antigo.mp4', 'video');
    $this->basico->forceFill(['video_arquivo' => 'biblioteca/antigo.mp4', 'video_url' => null])->save();

    $this->actingAs($this->comando)->put(route('comando-geral.biblioteca.update', $this->basico), [
        'titulo' => 'Postura de guarda',
        'fonte_video' => 'link',
        'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'ativo' => false,
    ])->assertRedirect();

    expect($this->basico->fresh()->ativo)->toBeFalse()
        ->and($this->basico->fresh()->video_arquivo)->toBeNull();
    Storage::disk('local')->assertMissing('biblioteca/antigo.mp4');
    expect(Auditoria::where('acao', 'BIBLIOTECA_MATERIAL_RETIRADO')->exists())->toBeTrue();
});

test('link de vídeo de um site qualquer é recusado', function () {
    $this->actingAs($this->comando)->post(route('comando-geral.biblioteca.store'), [
        'titulo' => 'Técnica X',
        'fonte_video' => 'link',
        'video_url' => 'https://site-estranho.example/video',
        'ativo' => true,
    ])->assertSessionHasErrors('video_url');
});

test('quem não é do Comando Geral não abre o cadastro nem envia material', function () {
    $instrutor = filiadoComNivel($this->instrutor, '111.444.777-35');

    $this->actingAs($instrutor)->get(route('comando-geral.biblioteca.index'))->assertForbidden();
    $this->actingAs($instrutor)->post(route('comando-geral.biblioteca.store'), [
        'titulo' => 'Tentativa', 'fonte_video' => 'link', 'ativo' => true,
    ])->assertForbidden();
    expect(BibliotecaMaterial::where('titulo', 'Tentativa')->exists())->toBeFalse();
});

test('a tela do Comando Geral lista ativos e retirados', function () {
    $this->basico->update(['ativo' => false]);

    $this->actingAs($this->comando)->get(route('comando-geral.biblioteca.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $tela) => $tela->component('biblioteca-admin/index')->has('materiais', 2));
});
