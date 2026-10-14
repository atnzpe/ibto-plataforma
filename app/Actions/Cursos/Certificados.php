<?php

/*
 * REGRAS DO CERTIFICADO (#44), num lugar só:
 * - apto(): matrícula CONFIRMADA e, se o curso teve chamada, presença mínima
 *   de FREQUENCIA_MINIMA % dos encontros com chamada;
 * - emitir(): cria o certificado com a "foto" dos dados do dia e o código
 *   público (um por matrícula: não emite duas vezes);
 * - cancelar(): não apaga; marca, com motivo, e deixa de validar.
 * Tudo com auditoria, só com números (LGPD).
 */

namespace App\Actions\Cursos;

use App\Enums\SituacaoMatricula;
use App\Exceptions\RegraCurso;
use App\Models\Auditoria;
use App\Models\CursoAula;
use App\Models\CursoCertificado;
use App\Models\CursoMatricula;
use App\Models\CursoPresenca;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Certificados
{
    // Presença mínima (%) nos encontros com chamada para receber o certificado.
    // Fica num lugar só: mudar aqui muda a regra inteira.
    public const FREQUENCIA_MINIMA = 75;

    /**
     * Diz se a matrícula pode receber o certificado agora, e por quê não.
     *
     * @param  CursoMatricula  $matricula  A matrícula.
     * @return array{apto: bool, motivo: string|null, presencas: int, encontros: int}
     *                                                                                Ex.: ['apto' => false, 'motivo' => 'Presença de 50% (mínimo 75%).', ...]
     */
    public function apto(CursoMatricula $matricula): array
    {
        // Encontros do curso que já tiveram chamada.
        $encontros = CursoAula::query()
            ->whereNotNull('chamada_feita_em')
            ->whereHas('modulo', fn ($modulo) => $modulo->where('curso_id', $matricula->curso_id))
            ->pluck('id');
        $presencas = CursoPresenca::query()->whereIn('aula_id', $encontros)->where('matricula_id', $matricula->id)->where('presente', true)->count();
        $base = ['presencas' => $presencas, 'encontros' => $encontros->count()];

        // Só matrícula confirmada (paga ou gratuita).
        if ($matricula->situacao !== SituacaoMatricula::Confirmada) {
            return ['apto' => false, 'motivo' => 'A matrícula não está confirmada.', ...$base];
        }

        // Com chamada feita: precisa da frequência mínima. Sem chamada (ex.: online): a decisão é do IBTO.
        if ($encontros->isNotEmpty()) {
            $percentual = intdiv($presencas * 100, $encontros->count());
            if ($percentual < self::FREQUENCIA_MINIMA) {
                return ['apto' => false, 'motivo' => "Presença de {$percentual}% (mínimo ".self::FREQUENCIA_MINIMA.'%).', ...$base];
            }
        }

        return ['apto' => true, 'motivo' => null, ...$base];
    }

    /**
     * Emite o certificado da matrícula.
     *
     * @param  CursoMatricula  $matricula  A matrícula apta.
     * @return CursoCertificado O certificado criado.
     *
     * @throws RegraCurso Não apta ou já emitido.
     */
    public function emitir(CursoMatricula $matricula): CursoCertificado
    {
        return DB::transaction(function () use ($matricula): CursoCertificado {
            // Tranca a matrícula: dois cliques em "Emitir" não criam dois certificados.
            $matricula = CursoMatricula::query()->lockForUpdate()->findOrFail($matricula->id);

            if (CursoCertificado::where('matricula_id', $matricula->id)->exists()) {
                throw new RegraCurso('CERTIFICADO_JA_EMITIDO', 'O certificado desta matrícula já foi emitido.');
            }

            $situacao = $this->apto($matricula);
            if (! $situacao['apto']) {
                throw new RegraCurso('CERTIFICADO_NAO_APTO', (string) $situacao['motivo']);
            }

            $curso = $matricula->curso;
            // Data de conclusão: o fim do curso, senão o último encontro, senão hoje.
            $ultimoEncontro = CursoAula::query()
                ->whereNotNull('data')
                ->whereHas('modulo', fn ($modulo) => $modulo->where('curso_id', $curso->id))
                ->max('data');
            $concluidoEm = $curso->data_fim?->toDateString() ?? $ultimoEncontro ?? today()->toDateString();

            $certificado = new CursoCertificado;
            $certificado->forceFill([
                'matricula_id' => $matricula->id,
                'codigo' => CursoCertificado::novoCodigo(),
                // A "foto" do dia da emissão.
                'nome' => $matricula->usuario->name,
                'curso' => $curso->titulo,
                'carga_horaria' => $curso->carga_horaria,
                'instrutor' => $curso->instrutorExibido(),
                'concluido_em' => $concluidoEm,
                'emitido_por' => Auth::id(),
                'emitido_em' => now(),
            ])->save();

            Auditoria::registrar('CERTIFICADO_EMITIDO', [
                'certificado_id' => $certificado->id,
                'matricula_id' => $matricula->id,
                'curso_id' => $curso->id,
            ]);

            return $certificado;
        });
    }

    /**
     * Cancela o certificado: não apaga; deixa de validar.
     *
     * @param  CursoCertificado  $certificado  O certificado.
     * @param  string  $motivo  Ex.: 'Emitido por engano'.
     * @return CursoCertificado O certificado cancelado.
     *
     * @throws RegraCurso Sem motivo ou já cancelado.
     */
    public function cancelar(CursoCertificado $certificado, string $motivo): CursoCertificado
    {
        if (trim($motivo) === '') {
            throw new RegraCurso('MOTIVO_OBRIGATORIO', 'Explique o motivo do cancelamento.');
        }
        if (! $certificado->valido()) {
            throw new RegraCurso('CERTIFICADO_JA_CANCELADO', 'Este certificado já está cancelado.');
        }

        $certificado->forceFill(['cancelado_em' => now(), 'motivo_cancelamento' => trim($motivo)])->save();
        Auditoria::registrar('CERTIFICADO_CANCELADO', ['certificado_id' => $certificado->id, 'matricula_id' => $certificado->matricula_id]);

        return $certificado;
    }
}
