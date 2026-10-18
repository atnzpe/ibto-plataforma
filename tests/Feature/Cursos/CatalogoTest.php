<?php

/*
 * Testes do CATÁLOGO PÚBLICO de cursos (#38, bloco C4a): visitante sem login
 * vê os publicados; os outros não aparecem (prévia só para a equipe e o dono);
 * filtros; material incluso em lista; WhatsApp em link; o público vê só a
 * lista de aulas (sem vídeo nem apostila); trilha em fluxograma (C4b). Dados INVENTADOS.
 */

use App\Enums\SituacaoCurso;
use App\Models\Curso;
use App\Models\CursoModulo;
use App\Models\Papel;
use App\Models\Trilha;
use App\Models\User;

/**
 * Cria um curso direto no banco, na situação indicada.
 *
 * @param  array<string, mixed>  $troca  Ex.: ['titulo' => 'Tiro']
 */
function cursoNoBanco(SituacaoCurso $situacao, array $troca = []): Curso
{
    $curso = new Curso([
        'titulo' => 'Combate Corpo a Corpo', 'modalidade' => 'presencial', 'acesso' => 'aberto',
        'valor_centavos' => 25000, 'data_inicio' => '2026-10-10', 'local' => 'Recife',
        'material_incluso' => 'Certificado, Camisa do evento , ,E muito mais',
        'contato_whatsapp' => '(81) 99999-0000', 'instrutor_nome' => 'Trinca',
        'instrutor_especialidade' => 'Especialista em artes marciais', 'chamada' => 'Treinamento para a vida real.',
        ...$troca,
    ]);
    $curso->forceFill(['situacao' => $situacao])->save();

    return $curso;
}

test('visitante sem login vê só os cursos PUBLICADOS', function () {
    cursoNoBanco(SituacaoCurso::Publicado, ['titulo' => 'Publicado']);
    cursoNoBanco(SituacaoCurso::Rascunho, ['titulo' => 'Rascunho']);
    cursoNoBanco(SituacaoCurso::EmAnalise, ['titulo' => 'Em análise']);
    cursoNoBanco(SituacaoCurso::Cancelado, ['titulo' => 'Cancelado']);

    $this->get(route('catalogo.index'))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela->component('catalogo/index')->has('cursos', 1)->where('cursos.0.titulo', 'Publicado'));
});

test('página de curso não publicado: 404 para o público; prévia para a equipe', function () {
    $rascunho = cursoNoBanco(SituacaoCurso::Rascunho);

    $this->get(route('catalogo.curso', $rascunho))->assertNotFound();

    $admin = User::factory()->create();
    $admin->papeis()->attach(Papel::where('slug', 'administrador')->value('id'));
    $this->actingAs($admin)->get(route('catalogo.curso', $rascunho))
        ->assertInertia(fn ($tela) => $tela->where('curso.previa', true));
});

test('página do curso: instrutor do cartaz, materiais em lista, WhatsApp em link', function () {
    $curso = cursoNoBanco(SituacaoCurso::Publicado);

    $this->get(route('catalogo.curso', $curso))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela
            ->component('catalogo/curso')
            ->where('curso.instrutor', 'Trinca')
            ->where('curso.instrutor_especialidade', 'Especialista em artes marciais')
            // Vírgulas e espaços sobrando não viram selos vazios.
            ->where('curso.materiais', ['Certificado', 'Camisa do evento', 'E muito mais'])
            ->where('curso.whatsapp_url', 'https://wa.me/5581999990000')
            ->where('curso.previa', false)
            ->where('logado', false));
});

test('o público vê só a lista de aulas: sem vídeo, sem apostila, sem endereço', function () {
    $curso = cursoNoBanco(SituacaoCurso::Publicado);
    $modulo = CursoModulo::create(['curso_id' => $curso->id, 'titulo' => 'Fundamentos', 'ordem' => 1, 'ativo' => true]);
    $modulo->aulas()->create(['titulo' => 'Aula 1', 'ordem' => 1, 'video_url' => 'https://youtu.be/dQw4w9WgXcQ', 'ativa' => true]);
    $modulo->aulas()->create(['titulo' => 'Aula escondida', 'ordem' => 2, 'ativa' => false]);

    $this->get(route('catalogo.curso', $curso))
        ->assertInertia(fn ($tela) => $tela
            ->has('curso.modulos.0.aulas', 1)
            ->where('curso.modulos.0.aulas.0.tem_video', true)
            ->missing('curso.modulos.0.aulas.0.video_url'));
});

test('filtros do catálogo: modalidade, acesso e busca', function () {
    cursoNoBanco(SituacaoCurso::Publicado, ['titulo' => 'Tiro', 'acesso' => 'aberto']);
    cursoNoBanco(SituacaoCurso::Publicado, ['titulo' => 'APH', 'modalidade' => 'online', 'acesso' => 'restrito', 'valor_centavos' => 0, 'data_inicio' => null, 'local' => null]);

    $this->get(route('catalogo.index', ['modalidade' => 'online']))
        ->assertInertia(fn ($tela) => $tela->has('cursos', 1)->where('cursos.0.titulo', 'APH')->where('cursos.0.restrito', true));
    $this->get(route('catalogo.index', ['acesso' => 'aberto']))
        ->assertInertia(fn ($tela) => $tela->has('cursos', 1)->where('cursos.0.titulo', 'Tiro'));
    $this->get(route('catalogo.index', ['busca' => 'tir']))
        ->assertInertia(fn ($tela) => $tela->has('cursos', 1));
});

test('trilha em fluxograma: agrupa por etapa, "Em breve" sem capa e sem cancelados', function () {
    $basico = cursoNoBanco(SituacaoCurso::Publicado, ['titulo' => 'Básico']);
    $tiro = cursoNoBanco(SituacaoCurso::Publicado, ['titulo' => 'Tiro']);
    $aph = cursoNoBanco(SituacaoCurso::Rascunho, ['titulo' => 'APH']);
    $cancelado = cursoNoBanco(SituacaoCurso::Cancelado, ['titulo' => 'Cancelado']);
    // Uma aula ativa no Básico (conta no cartão).
    CursoModulo::create(['curso_id' => $basico->id, 'titulo' => 'Módulo 1', 'ordem' => 1, 'ativo' => true])
        ->aulas()->create(['titulo' => 'Aula 1', 'ordem' => 1, 'ativa' => true]);

    $trilha = Trilha::create(['nome' => 'Formação', 'ativa' => true]);
    $trilha->cursos()->sync([$basico->id => ['ordem' => 1], $tiro->id => ['ordem' => 2], $aph->id => ['ordem' => 2], $cancelado->id => ['ordem' => 3]]);

    $this->get(route('catalogo.trilha', $trilha))
        ->assertOk()
        ->assertInertia(fn ($tela) => $tela->component('catalogo/trilha')
            ->has('etapas', 2)
            ->where('etapas.0.etapa', 1)
            ->where('etapas.0.cursos.0.aulas', 1)
            ->has('etapas.1.cursos', 2)
            ->where('etapas.1.cursos.1.titulo', 'APH')
            ->where('etapas.1.cursos.1.disponivel', false)
            ->where('etapas.1.cursos.1.imagem_url', null));
});

test('trilha desativada: 404 para o público', function () {
    $trilha = Trilha::create(['nome' => 'Antiga', 'ativa' => false]);

    $this->get(route('catalogo.trilha', $trilha))->assertNotFound();
});
