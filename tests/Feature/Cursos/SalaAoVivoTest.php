<?php

/*
 * Testes da SALA AO VIVO (#71): quais links são aceitos e quando a sala abre.
 * Horários fixos (sem depender do relógio de quem roda o teste).
 */

use App\Actions\Cursos\Matriculas;
use App\Models\Curso;
use App\Models\CursoAula;
use App\Models\CursoModulo;
use App\Models\Papel;
use App\Models\User;
use App\Support\SalaAoVivo;
use Illuminate\Support\Carbon;

test('aceita só Meet, Zoom, Teams e YouTube por https', function (string $link, bool $aceito) {
    expect(SalaAoVivo::linkValido($link))->toBe($aceito);
})->with([
    'Google Meet' => ['https://meet.google.com/abc-defg-hij', true],
    'Zoom da empresa' => ['https://us02web.zoom.us/j/123456789', true],
    'Teams' => ['https://teams.microsoft.com/l/meetup-join/abc', true],
    'live do YouTube' => ['https://www.youtube.com/live/abc123', true],
    'sem https' => ['http://meet.google.com/abc', false],
    'site estranho' => ['https://meet-google.com.golpe.io/abc', false],
    'parecido com zoom' => ['https://falsozoom.us/j/1', false],
    'usuário no endereço (golpe)' => ['https://meet.google.com@golpe.io/abc', false],
    'texto qualquer' => ['clique aqui', false],
]);

/**
 * Um encontro de teste em 10/11/2026, das 19h às 21h (sem gravar no banco).
 */
function encontroDeTeste(?string $fim = '21:00'): CursoAula
{
    return (new CursoAula)->forceFill(['data' => '2026-11-10', 'hora_inicio' => '19:00', 'hora_fim' => $fim]);
}

test('a sala abre 30 minutos antes e fecha no horário de fim', function () {
    $aula = encontroDeTeste();

    expect(SalaAoVivo::situacao($aula, Carbon::parse('2026-11-10 18:29')))->toBe('antes')
        ->and(SalaAoVivo::situacao($aula, Carbon::parse('2026-11-10 18:30')))->toBe('aberta')
        ->and(SalaAoVivo::situacao($aula, Carbon::parse('2026-11-10 21:00')))->toBe('aberta')
        ->and(SalaAoVivo::situacao($aula, Carbon::parse('2026-11-10 21:01')))->toBe('encerrada');
});

test('sem horário de fim, a sala fica aberta por 4 horas depois do início', function () {
    $aula = encontroDeTeste(null);

    expect(SalaAoVivo::situacao($aula, Carbon::parse('2026-11-10 22:59')))->toBe('aberta')
        ->and(SalaAoVivo::situacao($aula, Carbon::parse('2026-11-10 23:01')))->toBe('encerrada');
});

test('aula sem dia ou sem horário não tem janela', function () {
    expect(SalaAoVivo::situacao(new CursoAula))->toBe('sem_horario');
});

/**
 * Um curso online gratuito e publicado, com um encontro ao vivo em 10/11/2026
 * das 19h às 21h, e um aluno com a matrícula confirmada.
 *
 * @return array{0: Curso, 1: CursoAula, 2: User}
 */
function cursoComSalaAoVivo(): array
{
    $curso = Curso::create(['titulo' => 'Defesa Pessoal ao vivo', 'modalidade' => 'online', 'acesso' => 'aberto', 'valor_centavos' => 0]);
    $curso->forceFill(['situacao' => 'publicado'])->save();
    $modulo = CursoModulo::create(['curso_id' => $curso->id, 'titulo' => 'Encontros', 'ordem' => 1, 'ativo' => true]);
    $aula = $modulo->aulas()->create([
        'titulo' => 'Encontro 1', 'ordem' => 1, 'ativa' => true,
        'data' => '2026-11-10', 'hora_inicio' => '19:00', 'hora_fim' => '21:00',
        'link_ao_vivo' => 'https://meet.google.com/abc-defg-hij',
    ]);
    $aluno = User::factory()->create();
    app(Matriculas::class)->matricular($curso, $aluno);

    return [$curso, $aula, $aluno];
}

test('aluno confirmado só recebe o link com a sala aberta', function () {
    [$curso, $aula, $aluno] = cursoComSalaAoVivo();
    $endereco = route('cursos.assistir', ['curso' => $curso->id, 'aula' => $aula->id]);

    // Uma hora antes: sem link, com o horário de abertura.
    $this->travelTo(Carbon::parse('2026-11-10 18:00'));
    $this->actingAs($aluno)->get($endereco)
        ->assertInertia(fn ($tela) => $tela->where('aula.sala_ao_vivo.situacao', 'antes')
            ->where('aula.sala_ao_vivo.abre_as', '18:30')
            ->where('aula.sala_ao_vivo.link', null));

    // Dentro da janela: o link aparece.
    $this->travelTo(Carbon::parse('2026-11-10 18:45'));
    $this->actingAs($aluno)->get($endereco)
        ->assertInertia(fn ($tela) => $tela->where('aula.sala_ao_vivo.link', 'https://meet.google.com/abc-defg-hij'));

    // Depois do fim: sem link de novo.
    $this->travelTo(Carbon::parse('2026-11-10 21:30'));
    $this->actingAs($aluno)->get($endereco)
        ->assertInertia(fn ($tela) => $tela->where('aula.sala_ao_vivo.situacao', 'encerrada')
            ->where('aula.sala_ao_vivo.link', null));
});

test('quem organiza o curso vê o link sempre (é quem abre a reunião)', function () {
    [$curso, $aula] = cursoComSalaAoVivo();
    $admin = User::factory()->create();
    $admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));

    $this->travelTo(Carbon::parse('2026-11-09 10:00'));
    $this->actingAs($admin)->get(route('cursos.assistir', ['curso' => $curso->id, 'aula' => $aula->id]))
        ->assertInertia(fn ($tela) => $tela->where('aula.sala_ao_vivo.link', 'https://meet.google.com/abc-defg-hij'));
});

test('o link nunca aparece no catálogo público nem na API', function () {
    [$curso] = cursoComSalaAoVivo();
    auth()->logout();

    $this->get(route('catalogo.curso', $curso->id))->assertDontSee('meet.google.com', false);
    $this->getJson(route('api.publico.cursos'))->assertDontSee('meet.google.com', false);
});

test('o formulário recusa sala de site desconhecido e sala sem horário', function () {
    [$curso, $aula] = cursoComSalaAoVivo();
    $admin = User::factory()->create();
    $admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $base = ['titulo' => 'Encontro 1', 'fonte_video' => 'link', 'ativa' => '1'];

    $this->actingAs($admin)->put(route('comando-geral.aulas.update', $aula->id), [...$base, 'data' => '2026-11-10', 'hora_inicio' => '19:00', 'link_ao_vivo' => 'https://golpe.io/sala'])
        ->assertSessionHasErrors('link_ao_vivo');
    $this->put(route('comando-geral.aulas.update', $aula->id), [...$base, 'link_ao_vivo' => 'https://meet.google.com/abc'])
        ->assertSessionHasErrors('link_ao_vivo');
    $this->put(route('comando-geral.aulas.update', $aula->id), [...$base, 'data' => '2026-11-10', 'hora_inicio' => '19:00', 'link_ao_vivo' => 'https://us02web.zoom.us/j/123'])
        ->assertSessionHasNoErrors();
    expect($aula->fresh()->link_ao_vivo)->toBe('https://us02web.zoom.us/j/123');
});
