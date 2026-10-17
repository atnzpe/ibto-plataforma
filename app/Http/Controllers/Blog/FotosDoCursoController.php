<?php

/*
 * FOTOS DO CURSO PELO INSTRUTOR (Mestre Eduardo, 17/10/2026: "o responsável pelas
 * fotos é sempre o instrutor do curso").
 *
 * O instrutor responsável pelo curso (ou o Comando Geral) abre, com um botão:
 * - o ÁLBUM DA GALERIA do curso: fotos gratuitas para os filiados. Ele envia,
 *   descreve e desfoca; o álbum fica em RASCUNHO até o Comando Geral publicar;
 * - o ÁLBUM À VENDA do curso: ele é o fotógrafo; o álbum nasce DESATIVADO, e o
 *   Comando Geral confere o preço e ativa (só então a turma vê e compra).
 * Um álbum de cada por curso: clicar de novo abre o que já existe.
 */

namespace App\Http\Controllers\Blog;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\FotoAlbumVenda;
use App\Models\GaleriaAlbum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FotosDoCursoController extends Controller
{
    /** Preço padrão por foto do álbum à venda, em centavos (o Comando Geral ajusta). R$ 15,00. */
    private const PRECO_PADRAO_CENTAVOS = 1500;

    /** Parte padrão do fotógrafo (%), a sugestão de 17/10/2026 (70% fotógrafo, 30% IBTO). */
    private const PERCENTUAL_FOTOGRAFO_PADRAO = 70;

    /** Por quantos dias as fotos à venda ficam disponíveis (depois são apagadas). */
    private const DIAS_DISPONIVEL = 60;

    /**
     * Abre (ou cria) o álbum da galeria do curso e leva à tela de enviar as fotos.
     *
     * @param  Request  $request  O pedido do instrutor.
     * @param  Curso  $curso  O curso do endereço (ex.: /cursos/7/album-fotos).
     * @return RedirectResponse Para a edição do álbum.
     */
    public function galeria(Request $request, Curso $curso): RedirectResponse
    {
        // Só o responsável pelo curso ou o Comando Geral; os outros: 403.
        Gate::authorize('curso.instrutor', $curso);

        $album = GaleriaAlbum::where('curso_id', $curso->id)->first();
        // Ainda não existe: cria em rascunho, já com os dados do curso.
        if ($album === null) {
            $album = new GaleriaAlbum([
                'titulo' => 'Fotos: '.$curso->titulo,
                'data_evento' => $curso->data_inicio?->toDateString(),
                'local_evento' => $curso->local,
                'credito_padrao' => $curso->instrutor_nome ?: $request->user()->name,
            ]);
            $album->forceFill(['curso_id' => $curso->id, 'autor_id' => $request->user()->id])->save();
            Auditoria::registrar('ALBUM_CURSO_CRIADO', ['album_id' => $album->id, 'curso_id' => $curso->id]);
        }

        return to_route('comando-geral.galeria.edit', $album)
            ->with('sucesso', 'Envie as fotos do curso, descreva cada uma e desfoque rostos e distintivos. O Comando Geral confere e publica.');
    }

    /**
     * Abre (ou cria) o álbum À VENDA do curso, com o instrutor como fotógrafo.
     *
     * @param  Request  $request  O pedido do instrutor.
     * @param  Curso  $curso  O curso do endereço (ex.: /cursos/7/fotos-venda).
     * @return RedirectResponse Para a tela de enviar as fotos do álbum (fotógrafo).
     */
    public function venda(Request $request, Curso $curso): RedirectResponse
    {
        // Só o responsável pelo curso ou o Comando Geral; os outros: 403.
        Gate::authorize('curso.instrutor', $curso);

        $album = FotoAlbumVenda::where('curso_id', $curso->id)->where('fotografo_id', $request->user()->id)->first();
        // Ainda não existe: cria DESATIVADO (o Comando Geral confere o preço e ativa).
        if ($album === null) {
            $album = FotoAlbumVenda::create([
                'curso_id' => $curso->id,
                'fotografo_id' => $request->user()->id,
                'titulo' => 'Fotos à venda: '.$curso->titulo,
                'preco_centavos' => self::PRECO_PADRAO_CENTAVOS,
                'percentual_fotografo' => self::PERCENTUAL_FOTOGRAFO_PADRAO,
                'expira_em' => today()->addDays(self::DIAS_DISPONIVEL)->toDateString(),
                'ativo' => false,
            ]);
            Auditoria::registrar('ALBUM_VENDA_CRIADO', ['album_id' => $album->id, 'curso_id' => $curso->id, 'pelo_instrutor' => true]);
        }

        return to_route('fotos.fotografo.album', $album)
            ->with('sucesso', 'Envie as fotos. O álbum fica desativado até o Comando Geral conferir o preço e ativar; só então a turma vê.');
    }
}
