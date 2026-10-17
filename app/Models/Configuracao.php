<?php

/*
 * CONFIGURAÇÕES LIGA/DESLIGA do IBTO (#90), mudadas pela tela do Comando Geral
 * (sem depender do .env nem da Tecnologia). Ex.: 'venda_fotos_ligada'.
 * Como o interruptor de um quadro de luz: cada chave liga ou desliga uma função.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $chave Ex.: 'venda_fotos_ligada'.
 * @property string|null $valor Ex.: '1' (ligada) ou '0' (desligada).
 */
#[Fillable(['chave', 'valor'])]
class Configuracao extends Model
{
    // Nome da tabela (sem isto, o Laravel tentaria "configuracaos").
    protected $table = 'configuracoes';

    /**
     * A chave está ligada? (não existe = desligada, o mais seguro)
     *
     * @param  string  $chave  Ex.: 'venda_fotos_ligada'.
     */
    public static function ligada(string $chave): bool
    {
        return self::query()->where('chave', $chave)->value('valor') === '1';
    }

    /**
     * Liga ou desliga uma chave (cria se não existir).
     *
     * @param  string  $chave  Ex.: 'venda_fotos_ligada'.
     * @param  bool  $ligada  true = liga.
     */
    public static function definir(string $chave, bool $ligada): void
    {
        self::query()->updateOrCreate(['chave' => $chave], ['valor' => $ligada ? '1' : '0']);
    }
}
