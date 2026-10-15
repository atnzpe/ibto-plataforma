<?php

/*
 * REGRAS DE PUBLICAÇÃO DA GALERIA (#61, bloco B2), no molde da galeria do
 * site da CBSA:
 *
 *   rascunho → publicado → arquivado → (voltar para) rascunho
 *
 * Para PUBLICAR o álbum precisa ter:
 * - pelo menos uma foto;
 * - TODAS as fotos descritas (texto alternativo com pelo menos 5 letras):
 *   a foto pode ser enviada sem descrição, mas nunca aparece no site sem ela;
 * - a AUTORIZAÇÃO DE USO DE IMAGEM marcada e a origem dela (fotos de evento
 *   mostram pessoas); com menores de idade, também a dos responsáveis
 *   (LGPD art. 14 e ECA art. 17).
 * Cada passo vai para a auditoria.
 */

namespace App\Actions\Blog;

use App\Enums\SituacaoAlbum;
use App\Exceptions\RegraBlog;
use App\Models\Auditoria;
use App\Models\GaleriaAlbum;

class PublicacaoAlbum
{
    /**
     * De que situação cada ação parte e para qual leva.
     *
     * @var array<string, array{de: list<SituacaoAlbum>, para: SituacaoAlbum}>
     */
    private const CAMINHOS = [
        'publicar' => ['de' => [SituacaoAlbum::Rascunho, SituacaoAlbum::Arquivado], 'para' => SituacaoAlbum::Publicado],
        'arquivar' => ['de' => [SituacaoAlbum::Publicado], 'para' => SituacaoAlbum::Arquivado],
        'voltar_rascunho' => ['de' => [SituacaoAlbum::Arquivado], 'para' => SituacaoAlbum::Rascunho],
    ];

    /**
     * Faz uma ação do caminho do álbum.
     *
     * @param  GaleriaAlbum  $album  O álbum. Ex.: "Estande de tiro (PE)".
     * @param  string  $acao  Ex.: 'publicar'
     * @return GaleriaAlbum O álbum com a situação nova.
     *
     * @throws RegraBlog Quando a regra não deixa (o código diz o motivo).
     */
    public function aplicar(GaleriaAlbum $album, string $acao): GaleriaAlbum
    {
        $caminho = self::CAMINHOS[$acao] ?? null;
        // Ação que não existe.
        if ($caminho === null) {
            throw new RegraBlog('ALBUM_ACAO_INVALIDA', 'Esta ação não existe.');
        }
        // A ação não parte da situação atual (ex.: arquivar um rascunho).
        if (! in_array($album->situacao, $caminho['de'], true)) {
            throw new RegraBlog('ALBUM_ACAO_NAO_PERMITIDA', "Um álbum \"{$album->situacao->rotulo()}\" não pode fazer esta ação.");
        }

        // Publicar exige o álbum completo (as regras abaixo, uma de cada vez).
        if ($acao === 'publicar') {
            $this->conferirParaPublicar($album);
        }

        $anterior = $album->situacao;
        $album->forceFill(['situacao' => $caminho['para']])->save();

        Auditoria::registrar('ALBUM_'.strtoupper($acao), [
            'album_id' => $album->id,
            'de' => $anterior->value,
            'para' => $album->situacao->value,
        ]);

        return $album;
    }

    /**
     * As ações que fazem sentido AGORA para o álbum (os botões da tela).
     *
     * @return list<string> Ex.: ['publicar']
     */
    public function acoesPara(GaleriaAlbum $album): array
    {
        // array_keys já devolve uma lista numerada com os nomes das ações.
        return array_keys(array_filter(
            self::CAMINHOS,
            fn (array $caminho) => in_array($album->situacao, $caminho['de'], true),
        ));
    }

    /**
     * Confere se o álbum está completo para ir ao site.
     *
     * @throws RegraBlog O primeiro item que falta, com o código.
     */
    private function conferirParaPublicar(GaleriaAlbum $album): void
    {
        $fotos = $album->fotos()->get();

        // Pelo menos uma foto.
        if ($fotos->isEmpty()) {
            throw new RegraBlog('ALBUM_SEM_FOTOS', 'Envie pelo menos uma foto antes de publicar.');
        }

        // Todas descritas: diz quantas ainda faltam.
        $semDescricao = $fotos->reject(fn ($foto) => $foto->descrita())->count();
        if ($semDescricao > 0) {
            throw new RegraBlog('ALBUM_FOTO_SEM_DESCRICAO', "Descreva todas as fotos antes de publicar ({$semDescricao} sem descrição; mínimo de 5 letras).");
        }

        // LGPD: autorização de uso de imagem e de onde ela veio.
        if (! $album->autorizacao_imagem || trim((string) $album->autorizacao_origem) === '') {
            throw new RegraBlog('ALBUM_SEM_AUTORIZACAO_IMAGEM', 'Marque a autorização de uso de imagem e diga como ela foi obtida.');
        }

        // Com menores, também a autorização dos responsáveis.
        if ($album->tem_menores && ! $album->autorizacao_responsaveis) {
            throw new RegraBlog('ALBUM_SEM_AUTORIZACAO_RESPONSAVEIS', 'Há menores de idade: marque a autorização dos pais ou responsáveis.');
        }
    }
}
