<?php

/*
 * Testes da CHAMADA DO ENCONTRO (#44): só os confirmados são chamados, pelo
 * registro (LGPD); marcados = presentes, os demais = falta; refazer corrige
 * a mesma linha; número de fora é ignorado; quem pode fazer (Comando Geral,
 * dono, admin da equipe dona) e quem não pode; aula sem data não tem chamada;
 * o resumo e o "x de y" nos detalhes do curso. Dados INVENTADOS.
 */

use App\Actions\Cursos\Matriculas;
use App\Actions\Filiados\CriarFiliado;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\CursoModulo;
use App\Models\CursoPresenca;
use App\Models\Equipe;
use App\Models\Papel;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->create();
    $this->admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));

    // Curso gratuito: as matrículas já nascem confirmadas.
    $this->curso = Curso::create(['titulo' => 'Combate', 'modalidade' => 'presencial', 'acesso' => 'aberto', 'valor_centavos' => 0, 'data_inicio' => today()->toDateString(), 'local' => 'Recife']);
    $this->curso->forceFill(['situacao' => 'publicado'])->save();
    $modulo = CursoModulo::create(['curso_id' => $this->curso->id, 'titulo' => 'Módulo 1', 'ordem' => 1, 'ativo' => true]);
    $this->encontro = $modulo->aulas()->create(['titulo' => 'Encontro 1', 'ordem' => 1, 'ativa' => true, 'data' => today()->toDateString(), 'hora_inicio' => '09:00']);
    $this->online = $modulo->aulas()->create(['titulo' => 'Vídeo', 'ordem' => 2, 'ativa' => true]);

    $matriculas = app(Matriculas::class);
    $this->m1 = $matriculas->matricular($this->curso, User::factory()->create(['name' => 'Aluno Um']));
    $this->m2 = $matriculas->matricular($this->curso, User::factory()->create());
});

test('a chamada lista os confirmados só pelo registro; salvar grava presentes e faltas', function () {
    $this->actingAs($this->admin)->get(route('comando-geral.cursos.chamada', ['curso' => $this->curso->id, 'aula' => $this->encontro->id]))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela->component('cursos/chamada')->has('inscritos', 2)->missing('inscritos.0.nome'))
        ->assertDontSee('Aluno Um');

    $this->actingAs($this->admin)
        ->put(route('comando-geral.cursos.chamada.update', ['curso' => $this->curso->id, 'aula' => $this->encontro->id]), [
            // 999999 não é deste curso: é ignorado.
            'presentes' => [$this->m1->id, 999999],
            'registro_encontro' => 'Guarda e base.',
        ])
        ->assertRedirect(route('comando-geral.cursos.inscritos', $this->curso));

    expect(CursoPresenca::where('matricula_id', $this->m1->id)->value('presente'))->toBeTrue();
    expect(CursoPresenca::where('matricula_id', $this->m2->id)->value('presente'))->toBeFalse();
    expect(CursoPresenca::count())->toBe(2);
    expect($this->encontro->fresh()->registro_encontro)->toBe('Guarda e base.');
    expect(Auditoria::where('acao', 'CHAMADA_REGISTRADA')->count())->toBe(1);
});

test('refazer a chamada corrige a mesma linha (não duplica)', function () {
    $endereco = route('comando-geral.cursos.chamada.update', ['curso' => $this->curso->id, 'aula' => $this->encontro->id]);
    $this->actingAs($this->admin)->put($endereco, ['presentes' => [$this->m1->id], 'registro_encontro' => 'Guarda.']);
    $this->actingAs($this->admin)->put($endereco, ['presentes' => [$this->m2->id], 'registro_encontro' => 'Base.']);

    expect(CursoPresenca::count())->toBe(2);
    expect(CursoPresenca::where('matricula_id', $this->m1->id)->value('presente'))->toBeFalse();
    expect(CursoPresenca::where('matricula_id', $this->m2->id)->value('presente'))->toBeTrue();
});

test('quem faz a chamada: dono do curso e admin da equipe dona sim; outro instrutor e aluno não', function () {
    $endereco = route('comando-geral.cursos.chamada', ['curso' => $this->curso->id, 'aula' => $this->encontro->id]);

    $dono = User::factory()->create();
    $dono->papeis()->attach(Papel::where('slug', 'instrutor')->value('id'));
    $this->curso->forceFill(['responsavel_id' => $dono->id])->save();
    $this->actingAs($dono)->get($endereco)->assertOk();

    $outro = User::factory()->create();
    $outro->papeis()->attach(Papel::where('slug', 'instrutor')->value('id'));
    $this->actingAs($outro)->get($endereco)->assertForbidden();

    $this->actingAs(User::factory()->create())->get($endereco)->assertForbidden();

    // Admin da equipe DONA do curso: entra; admin de OUTRA equipe: não.
    $equipe = Equipe::factory()->create();
    $this->curso->forceFill(['equipe_id' => $equipe->id])->save();
    $adminDaEquipe = app(CriarFiliado::class)->executar([
        'nome' => 'Admin Equipe', 'email' => 'admeq@ibto.test', 'cpf' => '529.982.247-25',
        'nascimento' => '1985-01-01', 'municipio' => 'Recife', 'uf' => 'PE', 'equipe_id' => $equipe->id,
    ])->usuario;
    $adminDaEquipe->markEmailAsVerified();
    $adminDaEquipe->papeis()->attach(Papel::where('slug', 'admin_equipe')->value('id'));
    $this->actingAs($adminDaEquipe->fresh())->get($endereco)->assertOk();

    $adminDeOutra = app(CriarFiliado::class)->executar([
        'nome' => 'Admin Outra', 'email' => 'admout@ibto.test', 'cpf' => '111.444.777-35',
        'nascimento' => '1985-01-01', 'municipio' => 'Recife', 'uf' => 'PE', 'equipe_id' => Equipe::factory()->create()->id,
    ])->usuario;
    $adminDeOutra->markEmailAsVerified();
    $adminDeOutra->papeis()->attach(Papel::where('slug', 'admin_equipe')->value('id'));
    $this->actingAs($adminDeOutra->fresh())->get($endereco)->assertForbidden();
});

test('aula sem data (online) não tem chamada: 404', function () {
    $this->actingAs($this->admin)
        ->get(route('comando-geral.cursos.chamada', ['curso' => $this->curso->id, 'aula' => $this->online->id]))
        ->assertNotFound();
});

test('detalhes do curso: encontros, presentes na última chamada e o "x de y" de cada inscrito', function () {
    $this->actingAs($this->admin)->put(route('comando-geral.cursos.chamada.update', ['curso' => $this->curso->id, 'aula' => $this->encontro->id]), ['presentes' => [$this->m1->id], 'registro_encontro' => 'Guarda.']);

    $this->actingAs($this->admin)->get(route('comando-geral.cursos.inscritos', $this->curso))
        ->assertInertia(fn ($tela) => $tela
            ->has('encontros', 1)
            ->where('encontros.0.chamada_feita', true)
            ->where('resumo.presentes_ultima', 1)
            ->where('pode_chamada', true)
            ->where('inscritos.1.presencas', 1)
            ->where('inscritos.1.encontros_com_chamada', 1));
});
