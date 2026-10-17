<?php

/*
 * VENDA DE FOTOS — LADO DO COMANDO GERAL (#90). Permissão "fotos.gerenciar".
 *
 * - Liga/desliga a venda (começa desligada; depende da aprovação do Mestre).
 * - Álbuns à venda: escolhe o curso, o fotógrafo credenciado, o preço por foto,
 *   a parte do fotógrafo e quando as fotos expiram.
 * - Pedidos: confirma o pagamento do Pix (lança a receita e o repasse) ou cancela.
 */

namespace App\Http\Controllers\Fotos;

use App\Actions\Fotos\VendaFotos;
use App\Exceptions\RegraCurso;
use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Configuracao;
use App\Models\Curso;
use App\Models\FinFormaPagamento;
use App\Models\FotoAlbumVenda;
use App\Models\Papel;
use App\Models\PedidoFoto;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AlbunsVendaController extends Controller
{
    /**
     * A tela da venda: o interruptor, os álbuns e os pedidos aguardando pagamento.
     */
    public function index(): Response
    {
        return Inertia::render('fotos/admin/index', [
            'ligada' => VendaFotos::ligada(),
            'albuns' => FotoAlbumVenda::query()->with(['curso:id,titulo', 'fotografo:id,name'])->withCount('fotos')->orderByDesc('id')->get()
                ->map(fn (FotoAlbumVenda $album) => [
                    'id' => $album->id,
                    'titulo' => $album->titulo,
                    'curso' => $album->curso->titulo,
                    'fotografo' => $album->fotografo->name,
                    'fotos' => (int) $album->getAttribute('fotos_count'),
                    'preco_centavos' => $album->preco_centavos,
                    'expira_em' => $album->expira_em->format('d/m/Y'),
                    'situacao' => $album->expirado ? 'Expirado (fotos apagadas)' : ($album->ativo ? 'Ativo' : 'Desativado'),
                ]),
            'pedidos' => PedidoFoto::query()->with('album:id,titulo')->withCount('fotos')->orderByRaw("situacao = 'aguardando_pagamento' desc")->orderByDesc('id')->limit(100)->get()
                ->map(fn (PedidoFoto $pedido) => [
                    'id' => $pedido->id,
                    // LGPD: sem o nome do aluno na lista; o identificador do Pix basta para conferir no extrato.
                    'txid' => self::txid($pedido->id),
                    'album' => $pedido->album->titulo,
                    'fotos' => (int) $pedido->getAttribute('fotos_count'),
                    'valor_centavos' => $pedido->valor_centavos,
                    'situacao' => $pedido->situacao,
                    'criado_em' => $pedido->created_at?->format('d/m/Y H:i'),
                ]),
            'formas' => FinFormaPagamento::where('ativa', true)->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    /**
     * Liga ou desliga a venda de fotos (auditado).
     */
    public function interruptor(Request $request): RedirectResponse
    {
        $ligar = (bool) $request->validate(['ligada' => ['required', 'boolean']])['ligada'];
        Configuracao::definir(VendaFotos::CHAVE, $ligar);
        Auditoria::registrar($ligar ? 'VENDA_FOTOS_LIGADA' : 'VENDA_FOTOS_DESLIGADA');

        return back()->with('sucesso', $ligar ? 'Venda de fotos ligada.' : 'Venda de fotos desligada: nada aparece para os alunos.');
    }

    /**
     * Formulário de álbum novo.
     */
    public function create(): Response
    {
        return Inertia::render('fotos/admin/formulario', ['album' => null, ...$this->opcoes()]);
    }

    /**
     * Grava o álbum novo.
     */
    public function store(Request $request): RedirectResponse
    {
        $album = FotoAlbumVenda::create($this->validar($request));
        Auditoria::registrar('ALBUM_VENDA_CRIADO', ['album_id' => $album->id]);

        return to_route('fotos.admin.index')->with('sucesso', "Álbum \"{$album->titulo}\" criado. O fotógrafo já pode enviar as fotos.");
    }

    /**
     * Formulário preenchido.
     */
    public function edit(FotoAlbumVenda $album): Response
    {
        return Inertia::render('fotos/admin/formulario', [
            'album' => [
                'id' => $album->id,
                'curso_id' => $album->curso_id,
                'fotografo_id' => $album->fotografo_id,
                'titulo' => $album->titulo,
                'preco' => number_format($album->preco_centavos / 100, 2, ',', ''),
                'percentual_fotografo' => $album->percentual_fotografo,
                'expira_em' => $album->expira_em->toDateString(),
                'ativo' => $album->ativo,
            ],
            ...$this->opcoes(),
        ]);
    }

    /**
     * Grava as mudanças (pedidos já feitos não mudam de preço).
     */
    public function update(Request $request, FotoAlbumVenda $album): RedirectResponse
    {
        $album->update($this->validar($request));
        Auditoria::registrar('ALBUM_VENDA_EDITADO', ['album_id' => $album->id]);

        return to_route('fotos.admin.index')->with('sucesso', "Álbum \"{$album->titulo}\" atualizado.");
    }

    /**
     * Confirma o pagamento de um pedido (receita, repasse e download liberado).
     */
    public function confirmar(Request $request, PedidoFoto $pedido, VendaFotos $venda): RedirectResponse
    {
        $dados = $request->validate([
            'pago_em' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'forma_pagamento_id' => ['required', 'integer', Rule::exists('fin_formas_pagamento', 'id')],
        ]);

        try {
            $venda->confirmarPagamento($pedido, $dados['pago_em'], (int) $dados['forma_pagamento_id']);
        } catch (RegraCurso $erro) {
            return back()->withErrors(['pedido' => "{$erro->getMessage()} (código {$erro->codigo})"]);
        }

        return back()->with('sucesso', "Pedido {$this->txid($pedido->id)} confirmado: o aluno já pode baixar as fotos.");
    }

    /**
     * Cancela um pedido aguardando pagamento.
     */
    public function cancelar(PedidoFoto $pedido, VendaFotos $venda): RedirectResponse
    {
        try {
            $venda->cancelar($pedido);
        } catch (RegraCurso $erro) {
            return back()->withErrors(['pedido' => "{$erro->getMessage()} (código {$erro->codigo})"]);
        }

        return back()->with('sucesso', "Pedido {$this->txid($pedido->id)} cancelado.");
    }

    /**
     * Identificador do Pix do pedido (o mesmo que vai no código e aparece no extrato).
     *
     * @param  int  $pedidoId  Ex.: 42.
     * @return string Ex.: 'IBTOFOT42'.
     */
    public static function txid(int $pedidoId): string
    {
        return 'IBTOFOT'.$pedidoId;
    }

    /**
     * Confere e converte os campos do álbum.
     *
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        $dados = $request->validate([
            'curso_id' => ['required', 'integer', Rule::exists('cursos', 'id')],
            // Só contas com o papel Fotógrafo.
            'fotografo_id' => ['required', 'integer', Rule::exists('papel_usuario', 'user_id')->where('papel_id', Papel::where('slug', 'fotografo')->value('id'))],
            'titulo' => ['required', 'string', 'max:150'],
            'preco' => ['required', 'string', 'max:20'],
            'percentual_fotografo' => ['required', 'integer', 'min:0', 'max:100'],
            'expira_em' => ['required', 'date_format:Y-m-d', 'after:today'],
            'ativo' => ['sometimes', 'boolean'],
        ], ['fotografo_id.exists' => 'Escolha uma conta com o papel Fotógrafo.']);

        // Preço em reais ("15,00") para centavos (1500).
        $preco = (int) round((float) str_replace(['.', ','], ['', '.'], $dados['preco']) * 100);
        if ($preco < 100) {
            throw ValidationException::withMessages(['preco' => 'O preço por foto precisa ser de pelo menos R$ 1,00.']);
        }

        return [
            'curso_id' => (int) $dados['curso_id'],
            'fotografo_id' => (int) $dados['fotografo_id'],
            'titulo' => $dados['titulo'],
            'preco_centavos' => $preco,
            'percentual_fotografo' => (int) $dados['percentual_fotografo'],
            'expira_em' => $dados['expira_em'],
            'ativo' => $request->boolean('ativo', true),
        ];
    }

    /**
     * As listas do formulário: cursos (não cancelados) e as contas com o papel Fotógrafo.
     *
     * @return array<string, mixed>
     */
    private function opcoes(): array
    {
        return [
            'cursos' => Curso::query()->where('situacao', '!=', 'cancelado')->orderByDesc('data_inicio')->get(['id', 'titulo']),
            'fotografos' => User::query()->whereHas('papeis', fn ($papel) => $papel->where('slug', 'fotografo'))->orderBy('name')->get(['id', 'name']),
            // Sugestão de expiração: 60 dias a partir de hoje.
            'expira_sugerida' => today()->addDays(60)->toDateString(),
        ];
    }
}
