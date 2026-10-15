<?php

/*
 * Testes das REGRAS DO BLOG (#61): o endereço (slug) feito do título; o
 * caminho rascunho → revisão → publicado → arquivado; publicar exige resumo,
 * capa com descrição e, com pessoas nas fotos, a autorização de imagem (e a
 * dos responsáveis, com menores); o agendado só aparece no dia.
 * Dados INVENTADOS.
 */

use App\Actions\Blog\PublicacaoPost;
use App\Enums\SituacaoPost;
use App\Exceptions\RegraBlog;
use App\Models\Post;

/**
 * Um post completo (pronto para publicar), sem pessoas nas fotos.
 *
 * @param  array<string, mixed>  $troca  Ex.: ['tem_pessoas' => true]
 */
function postCompleto(array $troca = []): Post
{
    $post = Post::create([
        'titulo' => 'Formação de Instrutores no estande!', 'categoria' => 'evento',
        'resumo' => 'Um fim de semana de treino no estande.', 'texto' => 'Texto do post.',
        'capa_descricao' => 'Turma no estande de tiro', ...$troca,
    ]);
    // A capa é gravada à parte (ImagemPublica); aqui basta o caminho.
    $post->forceFill(['capa' => 'blog/capa-teste.jpg'])->save();

    return $post;
}

/**
 * O código do erro de regra, ou null se deu certo.
 */
function codigoDoBlog(callable $acao): ?string
{
    try {
        $acao();
    } catch (RegraBlog $erro) {
        return $erro->codigo;
    }

    return null;
}

test('o endereço (slug) sai do título, sem acentos, e não repete', function () {
    expect(postCompleto()->slug)->toBe('formacao-de-instrutores-no-estande')
        ->and(postCompleto()->slug)->toBe('formacao-de-instrutores-no-estande-2');
});

test('o caminho: rascunho → revisão → publicado → arquivado → rascunho', function () {
    $post = postCompleto();
    $regras = app(PublicacaoPost::class);

    expect($regras->acoesPara($post))->toBe(['enviar_revisao', 'publicar']);
    $regras->aplicar($post, 'enviar_revisao');
    expect($post->situacao)->toBe(SituacaoPost::EmRevisao);

    $regras->aplicar($post, 'publicar');
    expect($post->situacao)->toBe(SituacaoPost::Publicado)
        ->and($post->publicar_em)->not->toBeNull();

    // Publicado não volta direto para rascunho: precisa arquivar antes.
    expect(codigoDoBlog(fn () => $regras->aplicar($post, 'voltar_rascunho')))->toBe('POST_ACAO_NAO_PERMITIDA');

    $regras->aplicar($post, 'arquivar');
    $regras->aplicar($post, 'voltar_rascunho');
    expect($post->situacao)->toBe(SituacaoPost::Rascunho);
});

test('publicar exige resumo, capa e descrição da capa', function () {
    $regras = app(PublicacaoPost::class);

    expect(codigoDoBlog(fn () => $regras->aplicar(postCompleto(['resumo' => '']), 'publicar')))->toBe('POST_SEM_RESUMO');
    expect(codigoDoBlog(fn () => $regras->aplicar(postCompleto(['capa_descricao' => '']), 'publicar')))->toBe('POST_CAPA_SEM_DESCRICAO');

    $semCapa = postCompleto();
    $semCapa->forceFill(['capa' => null])->save();
    expect(codigoDoBlog(fn () => $regras->aplicar($semCapa, 'publicar')))->toBe('POST_SEM_CAPA');
});

test('LGPD: com pessoas, só publica com a autorização e a origem; com menores, também a dos responsáveis', function () {
    $regras = app(PublicacaoPost::class);

    $comPessoas = postCompleto(['tem_pessoas' => true]);
    expect(codigoDoBlog(fn () => $regras->aplicar($comPessoas, 'publicar')))->toBe('POST_SEM_AUTORIZACAO_IMAGEM');

    // Autorização marcada, mas sem dizer como foi obtida: ainda não.
    $comPessoas->update(['autorizacao_imagem' => true]);
    expect(codigoDoBlog(fn () => $regras->aplicar($comPessoas, 'publicar')))->toBe('POST_SEM_AUTORIZACAO_IMAGEM');

    $comPessoas->update(['autorizacao_origem' => 'Termo assinado na inscrição do curso', 'tem_menores' => true]);
    expect(codigoDoBlog(fn () => $regras->aplicar($comPessoas, 'publicar')))->toBe('POST_SEM_AUTORIZACAO_RESPONSAVEIS');

    $comPessoas->update(['autorizacao_responsaveis' => true]);
    expect(codigoDoBlog(fn () => $regras->aplicar($comPessoas, 'publicar')))->toBeNull();
});

test('agendado: publicado com data no futuro só aparece no site no dia', function () {
    $regras = app(PublicacaoPost::class);
    $agendado = postCompleto();
    $regras->aplicar($agendado, 'publicar', now()->addDays(2)->format('Y-m-d\TH:i'));
    $hoje = postCompleto(['titulo' => 'Notícia de hoje']);
    $regras->aplicar($hoje, 'publicar');

    // Só o de hoje aparece; o agendado ainda não.
    expect(Post::query()->noSite()->pluck('id')->all())->toBe([$hoje->id])
        ->and($agendado->visivelNoSite())->toBeFalse();

    // Dois dias depois, o agendado aparece sozinho.
    $this->travel(3)->days();
    expect($agendado->fresh()->visivelNoSite())->toBeTrue();
});
