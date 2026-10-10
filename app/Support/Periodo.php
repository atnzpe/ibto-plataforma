<?php

/*
 * PERÍODO dos filtros (pedido do Gleyson, 10/10/2026): todo filtro de data é
 * por DATA INICIAL e DATA FINAL ("de" e "até"), no lugar do mês fechado.
 * Um lugar só para a regra: padrão = mês atual; data inválida = padrão;
 * datas invertidas = trocadas.
 */

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class Periodo
{
    /**
     * @param  CarbonImmutable  $inicio  1º dia do período, às 00:00. Ex.: 2026-10-01 00:00
     * @param  CarbonImmutable  $fim  Último dia do período, às 23:59:59. Ex.: 2026-10-31 23:59:59
     */
    public function __construct(
        public readonly CarbonImmutable $inicio,
        public readonly CarbonImmutable $fim,
    ) {}

    /**
     * Lê "de" e "até" do endereço. Ex.: /financeiro?de=2026-09-15&ate=2026-10-15
     *
     * @param  Request  $request  O pedido com os filtros.
     * @return self O período pronto (com o padrão e a troca já aplicados).
     */
    public static function doPedido(Request $request): self
    {
        // Padrão: o mês atual inteiro.
        $hoje = CarbonImmutable::now();
        $inicio = self::data((string) $request->query('de', '')) ?? $hoje->startOfMonth();
        $fim = self::data((string) $request->query('ate', '')) ?? $hoje->endOfMonth();

        // Datas ao contrário (o início DEPOIS do fim; Gleyson): troca as duas de
        // lugar, em vez de mostrar erro. "gt" = greater than = "é depois de".
        if ($inicio->gt($fim)) {
            [$inicio, $fim] = [$fim, $inicio];
        }

        // O início começa à meia-noite e o fim termina às 23:59:59 (o dia inteiro conta).
        return new self($inicio->startOfDay(), $fim->endOfDay());
    }

    /**
     * Texto "2026-10-15" → data; qualquer outra coisa → null (usa o padrão).
     *
     * @param  string  $texto  Ex.: '2026-10-15'
     */
    private static function data(string $texto): ?CarbonImmutable
    {
        // Só aceita o formato do campo de data (AAAA-MM-DD) e datas que existem
        // (31/02 não passa: a volta para texto não bate).
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto) !== 1) {
            return null;
        }
        $data = CarbonImmutable::createFromFormat('Y-m-d', $texto);

        return $data !== null && $data->format('Y-m-d') === $texto ? $data : null;
    }

    /**
     * As duas datas no formato do campo, para a tela. Ex.: ['de' => '2026-10-01', 'ate' => '2026-10-31']
     *
     * @return array{de: string, ate: string}
     */
    public function paraTela(): array
    {
        return ['de' => $this->inicio->toDateString(), 'ate' => $this->fim->toDateString()];
    }
}
