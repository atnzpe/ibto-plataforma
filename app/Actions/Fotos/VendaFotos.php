<?php

/*
 * REGRAS DA VENDA DE FOTOS (#90): criar o pedido, confirmar o pagamento e cancelar.
 *
 * - A venda precisa estar LIGADA (Comando Geral) e o álbum ativo e não expirado.
 * - Só compra quem tem matrícula CONFIRMADA no curso do álbum (as fotos são da
 *   turma; nada é público, pela segurança dos operadores).
 * - Confirmar o pagamento lança a receita "Venda de fotos" e a parte do fotógrafo
 *   como conta a pagar "Fotógrafos" (como o repasse ao instrutor nos cursos), e
 *   libera o download dos originais por 30 dias.
 * Cada recusa tem mensagem e código estável (RegraCurso).
 */

namespace App\Actions\Fotos;

use App\Enums\SituacaoMatricula;
use App\Exceptions\RegraCurso;
use App\Models\Auditoria;
use App\Models\Configuracao;
use App\Models\FinCategoria;
use App\Models\FotoAlbumVenda;
use App\Models\PedidoFoto;
use App\Models\User;
use App\Services\LivroCaixa;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VendaFotos
{
    // A chave liga/desliga da venda de fotos (tela do Comando Geral).
    public const CHAVE = 'venda_fotos_ligada';

    // Por quantos dias os links de download valem depois de pago.
    public const DIAS_DOWNLOAD = 30;

    public function __construct(private readonly LivroCaixa $caixa) {}

    /**
     * A venda de fotos está ligada?
     */
    public static function ligada(): bool
    {
        return Configuracao::ligada(self::CHAVE);
    }

    /**
     * Esta pessoa pode ver (e comprar) as fotos do álbum?
     * Comando Geral (fotos.gerenciar) e o fotógrafo do álbum sempre veem; o aluno, só
     * com a venda ligada e matrícula confirmada no curso.
     *
     * @param  FotoAlbumVenda  $album  O álbum.
     * @param  User|null  $usuario  Quem está vendo (null = visitante: nunca).
     */
    public static function podeVer(FotoAlbumVenda $album, ?User $usuario): bool
    {
        if ($usuario === null) {
            return false;
        }
        if ($usuario->temPermissao('fotos.gerenciar') || $album->fotografo_id === $usuario->id) {
            return true;
        }

        return self::ligada()
            && $album->ativo
            && ! $album->expirado
            && $album->curso->matriculas()->where('user_id', $usuario->id)->where('situacao', SituacaoMatricula::Confirmada->value)->exists();
    }

    /**
     * Cria o pedido com as fotos escolhidas (aguardando o Pix).
     *
     * @param  FotoAlbumVenda  $album  O álbum.
     * @param  User  $usuario  Quem compra.
     * @param  list<int>  $fotoIds  As fotos escolhidas. Ex.: [12, 15, 18].
     * @return PedidoFoto O pedido criado.
     *
     * @throws RegraCurso Quando a regra não deixa.
     */
    public function criarPedido(FotoAlbumVenda $album, User $usuario, array $fotoIds): PedidoFoto
    {
        // Desligada, álbum fora do ar, ou a pessoa não é da turma: não compra.
        if (! self::ligada()) {
            throw new RegraCurso('VENDA_FOTOS_DESLIGADA', 'A venda de fotos não está ativa no momento.');
        }
        // O fotógrafo do álbum não compra as próprias fotos (mensagem própria, para não confundir).
        if ($album->fotografo_id === $usuario->id) {
            throw new RegraCurso('FOTOS_DO_PROPRIO_FOTOGRAFO', 'Você é o fotógrafo deste álbum: não dá para comprar as próprias fotos.');
        }
        // Não é da turma (matrícula confirmada no curso) nem do Comando Geral: não compra.
        if (! self::podeVer($album, $usuario)) {
            throw new RegraCurso('FOTOS_SO_DA_TURMA', 'As fotos deste evento são só para quem fez o curso.');
        }

        // Só fotos deste álbum, sem repetir. Nenhuma válida: nada a comprar.
        $validas = $album->fotos()->whereIn('id', array_unique($fotoIds))->pluck('id')->all();
        if ($validas === []) {
            throw new RegraCurso('PEDIDO_SEM_FOTOS', 'Escolha pelo menos uma foto.');
        }

        return DB::transaction(function () use ($album, $usuario, $validas): PedidoFoto {
            $pedido = new PedidoFoto;
            $pedido->forceFill([
                'user_id' => $usuario->id,
                'album_id' => $album->id,
                'situacao' => 'aguardando_pagamento',
                // Preço do dia × quantidade (se o preço mudar depois, o pedido fica com este).
                'valor_centavos' => $album->preco_centavos * count($validas),
            ])->save();
            $pedido->fotos()->sync($validas);

            Auditoria::registrar('PEDIDO_FOTOS_CRIADO', ['pedido_id' => $pedido->id, 'album_id' => $album->id, 'fotos' => count($validas)]);

            return $pedido;
        });
    }

    /**
     * Confirma o pagamento: receita, parte do fotógrafo a pagar e download por 30 dias.
     *
     * @param  PedidoFoto  $pedido  O pedido aguardando pagamento.
     * @param  string  $pagoEm  Dia em que o dinheiro entrou. Ex.: '2026-10-20'.
     * @param  int  $formaPagamentoId  Como pagou (ex.: o id do 'PIX').
     *
     * @throws RegraCurso Se o pedido não está aguardando ou falta uma categoria.
     */
    public function confirmarPagamento(PedidoFoto $pedido, string $pagoEm, int $formaPagamentoId): PedidoFoto
    {
        $receitaId = FinCategoria::where('nome', 'Venda de fotos')->where('tipo', 'receita')->value('id');
        $repasseId = FinCategoria::where('nome', 'Fotógrafos')->where('tipo', 'despesa')->value('id');
        // As categorias nascem na migration; se alguém apagou, avisa em vez de lançar errado.
        if ($receitaId === null || $repasseId === null) {
            throw new RegraCurso('CATEGORIA_FOTOS_AUSENTE', 'As categorias "Venda de fotos" e "Fotógrafos" não existem no financeiro.');
        }

        return DB::transaction(function () use ($pedido, $pagoEm, $formaPagamentoId, $receitaId, $repasseId): PedidoFoto {
            // Tranca o pedido: dois cliques em "Confirmar" não lançam duas receitas.
            $pedido = PedidoFoto::query()->lockForUpdate()->findOrFail($pedido->id);
            if ($pedido->situacao !== 'aguardando_pagamento') {
                throw new RegraCurso('PEDIDO_NAO_AGUARDANDO', 'Este pedido não está aguardando pagamento.');
            }
            $album = $pedido->album;

            // 1) A receita (sem o nome do aluno: LGPD) e a baixa.
            $receita = $this->caixa->lancar([
                'tipo' => 'receita',
                'categoria_id' => (int) $receitaId,
                'descricao' => "Venda de fotos — {$album->titulo} — pedido nº {$pedido->id}",
                'valor_centavos' => $pedido->valor_centavos,
                'vencimento' => $pagoEm,
                // Quem pagou (o aluno) e o curso do evento.
                'pessoa_id' => $pedido->user_id,
                'curso_id' => $album->curso_id,
                'origem' => 'pedido_foto',
                'origem_id' => $pedido->id,
            ]);
            $this->caixa->pagar($receita, $pagoEm, $formaPagamentoId);

            // 2) A parte do fotógrafo vira conta A PAGAR (o IBTO dá a baixa quando repassar).
            $parte = $album->divisao($pedido->valor_centavos)['fotografo'];
            if ($parte > 0) {
                $this->caixa->lancar([
                    'tipo' => 'despesa',
                    'categoria_id' => (int) $repasseId,
                    'descricao' => "Repasse ao fotógrafo ({$album->fotografo->name}) — {$album->titulo} — pedido nº {$pedido->id}",
                    'valor_centavos' => $parte,
                    'vencimento' => $pagoEm,
                    // Quem recebe: o fotógrafo do álbum.
                    'pessoa_id' => $album->fotografo_id,
                    'curso_id' => $album->curso_id,
                    'origem' => 'repasse_foto',
                    'origem_id' => $pedido->id,
                ]);
            }

            // 3) Pago: libera o download por 30 dias.
            $pedido->forceFill([
                'situacao' => 'pago',
                'lancamento_id' => $receita->id,
                'confirmado_por' => Auth::id(),
                'confirmado_em' => now(),
                'download_ate' => now()->addDays(self::DIAS_DOWNLOAD),
            ])->save();

            Auditoria::registrar('PEDIDO_FOTOS_PAGO', ['pedido_id' => $pedido->id, 'valor_centavos' => $pedido->valor_centavos]);

            return $pedido;
        });
    }

    /**
     * Cancela um pedido que ainda aguarda pagamento (nunca apaga: fica o histórico).
     *
     * @throws RegraCurso Se o pedido não está aguardando.
     */
    public function cancelar(PedidoFoto $pedido): PedidoFoto
    {
        if ($pedido->situacao !== 'aguardando_pagamento') {
            throw new RegraCurso('PEDIDO_NAO_AGUARDANDO', 'Só dá para cancelar um pedido que ainda aguarda pagamento.');
        }
        $pedido->forceFill(['situacao' => 'cancelado'])->save();
        Auditoria::registrar('PEDIDO_FOTOS_CANCELADO', ['pedido_id' => $pedido->id]);

        return $pedido;
    }
}
