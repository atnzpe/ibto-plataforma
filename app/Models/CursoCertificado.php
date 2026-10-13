<?php

/*
 * CERTIFICADO de uma matrícula (#44). Ver a migration 2026_10_11_000009:
 * guarda a "foto" dos dados do dia da emissão e o código público único.
 * Emissão e cancelamento passam por App\Actions\Cursos\Certificados.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $matricula_id
 * @property string $codigo
 * @property string $nome
 * @property string $curso
 * @property int|null $carga_horaria
 * @property string|null $instrutor
 * @property Carbon $concluido_em
 * @property int|null $emitido_por
 * @property Carbon $emitido_em
 * @property Carbon|null $cancelado_em
 * @property string|null $motivo_cancelamento
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CursoCertificado extends Model
{
    // Nada vem de formulário: tudo passa pela classe Certificados (forceFill).
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'carga_horaria' => 'integer',
            'concluido_em' => 'date',
            'emitido_em' => 'datetime',
            'cancelado_em' => 'datetime',
        ];
    }

    /**
     * true = vale (não foi cancelado).
     */
    public function valido(): bool
    {
        return $this->cancelado_em === null;
    }

    /**
     * O nome ABREVIADO para a página pública de validação (LGPD: "nome abreviado
     * por padrão"): o primeiro nome inteiro e as iniciais dos outros.
     *
     * @return string Ex.: 'Gleyson Atanazio da Silva' → 'Gleyson A. S.'
     */
    public function nomeAbreviado(): string
    {
        $partes = preg_split('/\s+/', trim($this->nome)) ?: [];
        $primeiro = array_shift($partes) ?? '';
        // Ignora as partículas ("da", "de", "dos"...): ficam fora das iniciais.
        $iniciais = array_map(
            fn (string $parte) => Str::upper(mb_substr($parte, 0, 1)).'.',
            array_filter($partes, fn (string $parte) => ! in_array(Str::lower($parte), ['da', 'de', 'do', 'das', 'dos', 'e'], true)),
        );

        return trim($primeiro.' '.implode(' ', $iniciais));
    }

    /**
     * Um código público novo e único. Letras e números SEM os parecidos
     * (0/O, 1/I/L), para ninguém errar ao digitar. Ex.: 'IBTO-7K2M-9QXA'.
     */
    public static function novoCodigo(): string
    {
        $letras = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $sorteio = '';
            for ($i = 0; $i < 8; $i++) {
                // random_int: sorteio seguro (não dá para adivinhar o próximo código).
                $sorteio .= $letras[random_int(0, strlen($letras) - 1)];
            }
            $codigo = 'IBTO-'.substr($sorteio, 0, 4).'-'.substr($sorteio, 4, 4);
        } while (self::where('codigo', $codigo)->exists());

        return $codigo;
    }

    /**
     * @return BelongsTo<CursoMatricula, $this>
     */
    public function matricula(): BelongsTo
    {
        return $this->belongsTo(CursoMatricula::class, 'matricula_id');
    }
}
