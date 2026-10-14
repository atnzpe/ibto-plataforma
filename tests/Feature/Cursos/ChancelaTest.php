<?php

/*
 * Testes da CHANCELA dos cursos (#40, decisão do Gleyson em 10/10/2026):
 * instrutor e admin de equipe PROPÕEM (cursos.propor, configurável); o curso
 * só é PUBLICADO depois de CHANCELADO pelo IBTO (cursos.chancelar). Cada um
 * vê e mexe só no que pode; rejeitar e cancelar exigem motivo; tudo vai para
 * a auditoria. Dados INVENTADOS.
 */

use App\Enums\SituacaoCurso;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\Papel;
use App\Models\Permissao;
use App\Models\User;

/**
 * Cria uma conta com o papel indicado. Ex.: contaCom('instrutor')
 */
function contaCom(string $papel): User
{
    $usuario = User::factory()->create();
    $usuario->papeis()->attach(Papel::where('slug', $papel)->value('id'));

    return $usuario;
}

/**
 * Campos de um curso online válido (sem data nem local).
 *
 * @return array<string, mixed>
 */
function cursoOnline(string $titulo = 'APH Tático'): array
{
    return ['titulo' => $titulo, 'modalidade' => 'online', 'acesso' => 'restrito', 'valor' => ''];
}

beforeEach(function () {
    $this->instrutor = contaCom('instrutor');
    $this->ibto = contaCom('administrador');
});

test('instrutor propõe: o curso nasce rascunho, com ele como dono, e NÃO pode se publicar', function () {
    $this->actingAs($this->instrutor)->post(route('comando-geral.cursos.store'), cursoOnline() + ['situacao' => 'publicado']);

    $curso = Curso::first();
    // O "situacao" enviado no formulário é ignorado: todo curso nasce rascunho.
    expect($curso->situacao)->toBe(SituacaoCurso::Rascunho);
    expect($curso->responsavel_id)->toBe($this->instrutor->id);

    // O instrutor não tem o botão "Chancelar": a ação é recusada com código.
    $this->actingAs($this->instrutor)->post(route('comando-geral.cursos.situacao', $curso), ['acao' => 'chancelar'])
        ->assertSessionHasErrors('acao');
    expect(session('errors')->first('acao'))->toContain('CURSO_ACAO_NAO_PERMITIDA');
    expect($curso->fresh()->situacao)->toBe(SituacaoCurso::Rascunho);
});

test('caminho completo: enviar → rejeitar (com motivo) → corrigir → enviar → chancelar', function () {
    $this->actingAs($this->instrutor)->post(route('comando-geral.cursos.store'), cursoOnline());
    $curso = Curso::first();

    // O dono envia para a chancela; enquanto em análise, não edita mais.
    $this->actingAs($this->instrutor)->post(route('comando-geral.cursos.situacao', $curso), ['acao' => 'enviar']);
    expect($curso->fresh()->situacao)->toBe(SituacaoCurso::EmAnalise);
    $this->actingAs($this->instrutor)->put(route('comando-geral.cursos.update', $curso), cursoOnline('Mudei'))->assertForbidden();

    // O IBTO rejeita SEM motivo: recusado; COM motivo: devolvido para ajustes.
    $this->actingAs($this->ibto)->post(route('comando-geral.cursos.situacao', $curso), ['acao' => 'rejeitar'])
        ->assertSessionHasErrors('acao');
    $this->actingAs($this->ibto)->post(route('comando-geral.cursos.situacao', $curso), ['acao' => 'rejeitar', 'motivo' => 'Falta a carga horária.']);
    expect($curso->fresh()->situacao)->toBe(SituacaoCurso::Rejeitado);
    expect($curso->fresh()->motivo_situacao)->toBe('Falta a carga horária.');

    // Rejeitado: o dono volta a editar e reenvia.
    $this->actingAs($this->instrutor)->put(route('comando-geral.cursos.update', $curso), cursoOnline() + ['carga_horaria' => 8])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->instrutor)->post(route('comando-geral.cursos.situacao', $curso), ['acao' => 'enviar']);

    // O IBTO chancela: publicado, com quem e quando; o motivo antigo some.
    $this->actingAs($this->ibto)->post(route('comando-geral.cursos.situacao', $curso), ['acao' => 'chancelar']);
    $curso->refresh();
    expect($curso->situacao)->toBe(SituacaoCurso::Publicado);
    expect($curso->chancelado_por)->toBe($this->ibto->id);
    expect($curso->chancelado_em)->not->toBeNull();
    expect($curso->motivo_situacao)->toBeNull();

    // Auditoria de cada passo.
    expect(Auditoria::whereIn('acao', ['CURSO_ENVIAR', 'CURSO_REJEITAR', 'CURSO_CHANCELAR'])->count())->toBe(4);
});

test('cada um vê só o que pode: o instrutor vê os DELE; o IBTO vê todos', function () {
    $outro = contaCom('instrutor');
    $this->actingAs($this->instrutor)->post(route('comando-geral.cursos.store'), cursoOnline('Meu'));
    $this->actingAs($outro)->post(route('comando-geral.cursos.store'), cursoOnline('Do outro'));

    $this->actingAs($this->instrutor)->get(route('comando-geral.cursos.index'))
        ->assertInertia(fn ($tela) => $tela->has('cursos', 1)->where('cursos.0.titulo', 'Meu'));
    $this->actingAs($this->ibto)->get(route('comando-geral.cursos.index'))
        ->assertInertia(fn ($tela) => $tela->has('cursos', 2));

    // O instrutor não abre o curso do outro, nem mexe no conteúdo dele.
    $doOutro = Curso::where('titulo', 'Do outro')->first();
    $this->actingAs($this->instrutor)->get(route('comando-geral.cursos.edit', $doOutro))->assertForbidden();
    $this->actingAs($this->instrutor)->post(route('comando-geral.modulos.store', $doOutro), ['titulo' => 'M', 'ativo' => '1'])->assertForbidden();
});

test('a tela mostra só os botões permitidos e quantos esperam a chancela', function () {
    $this->actingAs($this->instrutor)->post(route('comando-geral.cursos.store'), cursoOnline());
    $curso = Curso::first();

    // Dono, em rascunho: "Enviar para chancela" e "Cancelar curso".
    $this->actingAs($this->instrutor)->get(route('comando-geral.cursos.edit', $curso))
        ->assertInertia(fn ($tela) => $tela->where('curso.acoes.0.acao', 'enviar')->where('curso.acoes.1.acao', 'cancelar'));

    $this->actingAs($this->instrutor)->post(route('comando-geral.cursos.situacao', $curso), ['acao' => 'enviar']);

    // IBTO, em análise: chancelar, rejeitar, cancelar; e o aviso "1 esperando".
    $this->actingAs($this->ibto)->get(route('comando-geral.cursos.edit', $curso))
        ->assertInertia(fn ($tela) => $tela->where('curso.acoes', fn ($acoes) => collect($acoes)->pluck('acao')->all() === ['chancelar', 'rejeitar', 'cancelar']));
    $this->actingAs($this->ibto)->get(route('comando-geral.cursos.index'))
        ->assertInertia(fn ($tela) => $tela->where('em_analise', 1));
});

test('aluno não entra em cursos (403); permissão de propor é configurável', function () {
    $aluno = contaCom('aluno');
    $this->actingAs($aluno)->get(route('comando-geral.cursos.index'))->assertForbidden();

    // Dá "cursos.propor" ao papel Aluno (como a tela de Permissões faria): agora entra.
    Papel::where('slug', 'aluno')->first()->permissoes()->attach(Permissao::where('slug', 'cursos.propor')->value('id'));
    // fresh(): a conta guarda as permissões uma vez; recarregada, como numa requisição nova.
    $this->actingAs($aluno->fresh())->get(route('comando-geral.cursos.index'))->assertOk();
});
