<?php

/*
 * DADOS PARA PAGAMENTO do IBTO (#41; pedido do Gleyson em 10/10/2026). Uma
 * linha só (criada pela migration), editada na tela "Dados para pagamento"
 * do Comando Geral. A chave "por_curso" liga/desliga os dados próprios de
 * cada curso (ver App\Support\DadosPagamento).
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $pix_chave
 * @property string|null $favorecido
 * @property string|null $banco
 * @property string|null $agencia
 * @property string|null $conta
 * @property string|null $instrucoes
 * @property bool $por_curso
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['pix_chave', 'favorecido', 'banco', 'agencia', 'conta', 'instrucoes', 'por_curso'])]
class PagamentoConfig extends Model
{
    // Nome da tabela (no singular: é uma linha só).
    protected $table = 'pagamento_config';

    /**
     * Converte os campos do banco para os tipos certos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // 0/1 do banco vira false/true.
            'por_curso' => 'boolean',
        ];
    }

    /**
     * A linha do IBTO. Se, por algum motivo, ela não existir, cria vazia
     * (a tela nunca quebra).
     *
     * @return self Os dados atuais.
     */
    public static function atual(): self
    {
        return self::query()->firstOrCreate([], ['favorecido' => 'Instituto Brasileiro de Treinamento Operacional']);
    }
}
