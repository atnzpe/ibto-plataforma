<?php

/*
 * Filiado do IBTO (#37): o registro da carteirinha. A conta do app (nome,
 * e-mail, senha) fica em User; aqui ficam o registro, os dados pessoais
 * criptografados, a equipe, o nível e as áreas.
 *
 * LGPD: CPF, nascimento e telefone são criptografados (cast "encrypted").
 * Para achar alguém pelo CPF sem decifrar todos, usa-se o cpf_hash
 * (ver Filiado::hashDoCpf).
 */

namespace App\Models;

use App\Enums\CategoriaFiliado;
use App\Support\Documento;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $registro
 * @property string $cpf
 * @property string $nascimento
 * @property string|null $telefone
 * @property string $cpf_hash
 * @property string $municipio
 * @property string $uf
 * @property int|null $equipe_id
 * @property CategoriaFiliado|null $categoria
 * @property int|null $nivel_id
 * @property string|null $abrangencia
 * @property bool $documentacao_emitida
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
// registro e cpf_hash ficam de fora: quem calcula é a própria plataforma (ver booted()).
#[Fillable(['cpf', 'nascimento', 'telefone', 'municipio', 'uf', 'equipe_id', 'categoria', 'nivel_id', 'abrangencia', 'documentacao_emitida', 'status'])]
class Filiado extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Gravados embaralhados com a APP_KEY; no PHP voltam ao texto normal.
            'cpf' => 'encrypted',
            'nascimento' => 'encrypted',
            'telefone' => 'encrypted',
            // O texto do banco vira uma das opções da lista (ex.: CategoriaFiliado::Instrutor).
            'categoria' => CategoriaFiliado::class,
            // 0/1 do banco vira false/true.
            'documentacao_emitida' => 'boolean',
        ];
    }

    /**
     * Regras automáticas ao gravar: o CPF é guardado só com números e o
     * cpf_hash é recalculado sempre que o CPF muda.
     */
    protected static function booted(): void
    {
        static::saving(function (Filiado $filiado): void {
            // Só mexe quando o CPF foi informado ou trocado.
            if ($filiado->isDirty('cpf')) {
                $filiado->cpf = Documento::apenasDigitos($filiado->cpf);
                $filiado->cpf_hash = self::hashDoCpf($filiado->cpf);
            }
        });
    }

    /**
     * Impressão digital do CPF: sempre a mesma para o mesmo CPF, mas não dá
     * para voltar dela ao número (diferente da criptografia, que dá).
     * HMAC = hash com uma "senha" (a APP_KEY): sem ela, ninguém recalcula.
     *
     * @param  string  $cpf  Com ou sem pontuação. Ex.: '529.982.247-25'
     * @return string 64 caracteres. Ex.: 'a3f1...'
     */
    public static function hashDoCpf(string $cpf): string
    {
        return hash_hmac('sha256', Documento::apenasDigitos($cpf), (string) config('app.key'));
    }

    /**
     * Monta o número de registro do filiado.
     * Formato do IBTO: "BR" + ano (4 números) + sequência do ano (4 números) + UF.
     *
     * @param  int  $ano  Ex.: 2026
     * @param  int  $sequencia  Ex.: 7
     * @param  string  $uf  Ex.: 'pe'
     * @return string Ex.: 'BR20260007PE'
     */
    public static function montarRegistro(int $ano, int $sequencia, string $uf): string
    {
        // sprintf monta o texto a partir de um "molde": %d = o ano; %04d = a
        // sequência com 4 casas (7 vira 0007); %s = a UF, em maiúsculas (pe vira PE).
        return sprintf('BR%d%04d%s', $ano, $sequencia, strtoupper($uf));
    }

    /**
     * A conta do app desta pessoa (nome, e-mail, senha).
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Equipe, $this>
     */
    public function equipe(): BelongsTo
    {
        return $this->belongsTo(Equipe::class);
    }

    /**
     * @return BelongsTo<Nivel, $this>
     */
    public function nivel(): BelongsTo
    {
        return $this->belongsTo(Nivel::class);
    }

    /**
     * Áreas de atuação do filiado (várias).
     *
     * @return BelongsToMany<AreaAtuacao, $this>
     */
    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(AreaAtuacao::class, 'area_atuacao_filiado', 'filiado_id', 'area_atuacao_id');
    }
}
