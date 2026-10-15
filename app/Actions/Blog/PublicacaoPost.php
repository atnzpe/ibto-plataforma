<?php

/*
 * REGRAS DE PUBLICAÇÃO DO BLOG (#61), num lugar só (as telas chamam daqui),
 * no molde das notícias do site da CBSA:
 *
 *   rascunho → em revisão → publicado → arquivado
 *
 * - enviar_revisao: rascunho → em revisão (outra pessoa confere antes);
 * - publicar: rascunho ou em revisão (ou arquivado, para republicar) →
 *   publicado. Data no futuro = AGENDADO (aparece sozinho no dia);
 * - arquivar: publicado → arquivado (sai do site, não é apagado);
 * - voltar_rascunho: em revisão ou arquivado → rascunho (para ajustar).
 *
 * Para PUBLICAR o post precisa estar completo: resumo, capa com descrição
 * (acessibilidade) e, se as fotos ou o vídeo mostram pessoas, a AUTORIZAÇÃO
 * DE USO DE IMAGEM com a origem; com menores de idade, também a dos
 * responsáveis (LGPD art. 14 e ECA art. 17). Rascunho pode ficar incompleto.
 * Cada passo vai para a auditoria.
 */

namespace App\Actions\Blog;

use App\Enums\SituacaoPost;
use App\Exceptions\RegraBlog;
use App\Models\Auditoria;
use App\Models\Post;
use Illuminate\Support\Carbon;

class PublicacaoPost
{
    /**
     * De que situação cada ação pode partir, e para qual ela leva.
     * Ex.: 'arquivar' só parte de "publicado" e leva a "arquivado".
     *
     * @var array<string, array{de: list<SituacaoPost>, para: SituacaoPost}>
     */
    private const CAMINHOS = [
        'enviar_revisao' => ['de' => [SituacaoPost::Rascunho], 'para' => SituacaoPost::EmRevisao],
        'publicar' => ['de' => [SituacaoPost::Rascunho, SituacaoPost::EmRevisao, SituacaoPost::Arquivado], 'para' => SituacaoPost::Publicado],
        'arquivar' => ['de' => [SituacaoPost::Publicado], 'para' => SituacaoPost::Arquivado],
        'voltar_rascunho' => ['de' => [SituacaoPost::EmRevisao, SituacaoPost::Arquivado], 'para' => SituacaoPost::Rascunho],
    ];

    /**
     * Faz uma ação do caminho do post.
     *
     * @param  Post  $post  O post. Ex.: "Formação de Instrutores em PE".
     * @param  string  $acao  Ex.: 'publicar'
     * @param  string|null  $publicarEm  Só no "publicar": data e hora para aparecer no site
     *                                   (formato '2026-10-20T08:00'); vazio = agora.
     * @return Post O post com a situação nova.
     *
     * @throws RegraBlog Quando a regra não deixa (o código diz o motivo).
     */
    public function aplicar(Post $post, string $acao, ?string $publicarEm = null): Post
    {
        $caminho = self::CAMINHOS[$acao] ?? null;
        // Ação que não existe (ex.: endereço mexido à mão).
        if ($caminho === null) {
            throw new RegraBlog('POST_ACAO_INVALIDA', 'Esta ação não existe.');
        }

        // A ação não parte da situação atual (ex.: arquivar um rascunho).
        if (! in_array($post->situacao, $caminho['de'], true)) {
            throw new RegraBlog('POST_ACAO_NAO_PERMITIDA', "Um post \"{$post->situacao->rotulo()}\" não pode fazer esta ação.");
        }

        $dados = ['situacao' => $caminho['para']];

        if ($acao === 'publicar') {
            // Publicar exige o post completo (as regras abaixo, uma de cada vez).
            $this->conferirParaPublicar($post);
            // A data de aparecer no site: a escolhida (agendamento) ou agora.
            $dados['publicar_em'] = $publicarEm !== null && $publicarEm !== ''
                ? Carbon::parse($publicarEm)
                : now();
        }

        $anterior = $post->situacao;
        $post->forceFill($dados)->save();

        Auditoria::registrar('POST_'.strtoupper($acao), [
            'post_id' => $post->id,
            'de' => $anterior->value,
            'para' => $post->situacao->value,
            'publicar_em' => $post->publicar_em?->toDateTimeString(),
        ]);

        return $post;
    }

    /**
     * As ações que fazem sentido AGORA para o post (os botões da tela).
     *
     * @param  Post  $post  O post.
     * @return list<string> Ex.: ['enviar_revisao', 'publicar']
     */
    public function acoesPara(Post $post): array
    {
        // array_keys já devolve uma lista numerada 0, 1, 2... com os nomes das ações.
        return array_keys(array_filter(
            self::CAMINHOS,
            fn (array $caminho) => in_array($post->situacao, $caminho['de'], true),
        ));
    }

    /**
     * Confere se o post está completo para ir ao site.
     *
     * @throws RegraBlog O primeiro item que falta, com o código.
     */
    private function conferirParaPublicar(Post $post): void
    {
        // O resumo aparece no cartão da lista.
        if (trim((string) $post->resumo) === '') {
            throw new RegraBlog('POST_SEM_RESUMO', 'Escreva o resumo antes de publicar (ele aparece no cartão da lista).');
        }

        // A capa e a descrição dela (quem usa leitor de tela "ouve" a descrição).
        if ($post->capa === null) {
            throw new RegraBlog('POST_SEM_CAPA', 'Envie a imagem de capa antes de publicar.');
        }
        if (trim((string) $post->capa_descricao) === '') {
            throw new RegraBlog('POST_CAPA_SEM_DESCRICAO', 'Descreva a capa (ex.: "Turma no estande de tiro") antes de publicar.');
        }

        // LGPD: fotos ou vídeo com pessoas precisam da autorização, e de onde ela veio.
        if ($post->tem_pessoas && (! $post->autorizacao_imagem || trim((string) $post->autorizacao_origem) === '')) {
            throw new RegraBlog('POST_SEM_AUTORIZACAO_IMAGEM', 'As fotos mostram pessoas: marque a autorização de uso de imagem e diga como ela foi obtida.');
        }

        // Com menores de idade, também a autorização dos responsáveis.
        if ($post->tem_menores && ! $post->autorizacao_responsaveis) {
            throw new RegraBlog('POST_SEM_AUTORIZACAO_RESPONSAVEIS', 'Há menores de idade: marque a autorização dos pais ou responsáveis.');
        }
    }
}
