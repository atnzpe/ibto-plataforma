<?php

/*
 * REGRAS DO CERTIFICADO (#44 e #72), num lugar só:
 * - apto(): matrícula CONFIRMADA e, se o curso teve chamada, presença mínima
 *   de FREQUENCIA_MINIMA % dos encontros com chamada; se o curso tem aulas
 *   GRAVADAS (aulas sem data), 100% delas marcadas como assistidas (#72,
 *   padrão de mercado). Curso misto exige as duas coisas;
 * - emitirSeConcluiu(): no curso SÓ gravado, emite sozinho quando a pessoa
 *   marca a última aula (#72); curso com encontros continua com o IBTO;
 * - emitir(): cria o certificado com a "foto" dos dados do dia e o código
 *   público (um por matrícula: não emite duas vezes);
 * - cancelar(): não apaga; marca, com motivo, e deixa de validar.
 * Tudo com auditoria, só com números (LGPD).
 */

namespace App\Actions\Cursos;

use App\Enums\SituacaoMatricula;
use App\Exceptions\RegraCurso;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\CursoAula;
use App\Models\CursoCertificado;
use App\Models\CursoMatricula;
use App\Models\CursoPresenca;
use App\Models\User;
use App\Services\SalaDeAula;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Certificados
{
    // Presença mínima (%) nos encontros com chamada para receber o certificado.
    // Fica num lugar só: mudar aqui muda a regra inteira.
    public const FREQUENCIA_MINIMA = 75;

    /**
     * @param  SalaDeAula  $sala  As regras da sala de aula (a sequência de aulas e as assistidas).
     *                            O Laravel entrega pronto (injeção de dependência).
     */
    public function __construct(private readonly SalaDeAula $sala) {}

    /**
     * Diz se a matrícula pode receber o certificado agora, e por quê não.
     *
     * @param  CursoMatricula  $matricula  A matrícula.
     * @return array{apto: bool, motivo: string|null, presencas: int, encontros: int, gravadas_assistidas: int, gravadas_total: int}
     *                                                                                                                               Ex.: ['apto' => false, 'motivo' => 'Presença de 50% (mínimo 75%).', ...]
     */
    public function apto(CursoMatricula $matricula): array
    {
        // Encontros do curso que já tiveram chamada.
        $encontros = CursoAula::query()
            ->whereNotNull('chamada_feita_em')
            ->whereHas('modulo', fn ($modulo) => $modulo->where('curso_id', $matricula->curso_id))
            ->pluck('id');
        $presencas = CursoPresenca::query()->whereIn('aula_id', $encontros)->where('matricula_id', $matricula->id)->where('presente', true)->count();
        // Aulas GRAVADAS (#72): as aulas sem data que a pessoa pode ver, e quantas ela já assistiu.
        [$gravadasAssistidas, $gravadasTotal] = $this->gravadas($matricula->curso, $matricula->usuario);
        $base = [
            'presencas' => $presencas,
            'encontros' => $encontros->count(),
            'gravadas_assistidas' => $gravadasAssistidas,
            'gravadas_total' => $gravadasTotal,
        ];

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

        // Com aulas gravadas (#72): precisa de TODAS assistidas. Sem gravadas: não se aplica.
        if ($gravadasTotal > 0 && $gravadasAssistidas < $gravadasTotal) {
            $faltam = $gravadasTotal - $gravadasAssistidas;

            return ['apto' => false, 'motivo' => "Faltam {$faltam} de {$gravadasTotal} aulas gravadas para assistir.", ...$base];
        }

        return ['apto' => true, 'motivo' => null, ...$base];
    }

    /**
     * Emite o certificado da matrícula.
     *
     * @param  CursoMatricula  $matricula  A matrícula apta.
     * @param  bool  $automatico  true = emitido sozinho pela plataforma (#72): fica sem "emitido por"
     *                            (não foi a pessoa logada quem emitiu) e com outra ação na auditoria.
     * @return CursoCertificado O certificado criado.
     *
     * @throws RegraCurso Não apta ou já emitido.
     */
    public function emitir(CursoMatricula $matricula, bool $automatico = false): CursoCertificado
    {
        return DB::transaction(function () use ($matricula, $automatico): CursoCertificado {
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
                // Emissão automática (#72): ninguém emitiu; a plataforma emitiu sozinha.
                'emitido_por' => $automatico ? null : Auth::id(),
                'emitido_em' => now(),
            ])->save();

            Auditoria::registrar($automatico ? 'CERTIFICADO_EMITIDO_AUTOMATICO' : 'CERTIFICADO_EMITIDO', [
                'certificado_id' => $certificado->id,
                'matricula_id' => $matricula->id,
                'curso_id' => $curso->id,
            ]);

            return $certificado;
        });
    }

    /**
     * Emite o certificado SOZINHO quando a pessoa conclui um curso SÓ gravado (#72).
     *
     * Chamado depois de "Marcar como assistida". Só age quando: o curso não tem
     * encontros (aulas com data), a pessoa tem matrícula confirmada, ainda não
     * tem certificado e está apta (100% das aulas gravadas). Curso com encontros
     * continua com a emissão pelo IBTO (a presença depende da chamada).
     *
     * @param  Curso  $curso  O curso. Ex.: "Metodologia de Instrução" (online, gravado).
     * @param  User  $usuario  Quem acabou de marcar a aula.
     * @return CursoCertificado|null O certificado emitido agora, ou null (não era a hora).
     */
    public function emitirSeConcluiu(Curso $curso, User $usuario): ?CursoCertificado
    {
        // Curso com encontros: a emissão continua com o IBTO.
        $temEncontros = CursoAula::query()
            ->whereNotNull('data')
            ->whereHas('modulo', fn ($modulo) => $modulo->where('curso_id', $curso->id))
            ->exists();
        if ($temEncontros) {
            return null;
        }

        // Só matrícula confirmada desta pessoa neste curso (quem só organiza o curso não tem).
        $matricula = CursoMatricula::query()
            ->where('curso_id', $curso->id)
            ->where('user_id', $usuario->id)
            ->where('situacao', SituacaoMatricula::Confirmada)
            ->first();
        // Sem matrícula confirmada, já com certificado ou ainda não apta: não é a hora.
        if ($matricula === null || $matricula->certificado()->exists() || ! $this->apto($matricula)['apto']) {
            return null;
        }

        try {
            return $this->emitir($matricula, automatico: true);
        } catch (RegraCurso) {
            // Dois cliques ao mesmo tempo: o outro já emitiu (a trava da transação segurou).
            return null;
        }
    }

    /**
     * As aulas GRAVADAS (sem data) que a pessoa pode ver no curso, e quantas ela já assistiu (#72).
     *
     * @param  Curso  $curso  O curso.
     * @param  User  $usuario  O aluno.
     * @return array{0: int, 1: int} [assistidas, total]. Ex.: [3, 5].
     */
    private function gravadas(Curso $curso, User $usuario): array
    {
        // A mesma sequência da sala de aula (respeita módulo desativado e nível mínimo).
        $gravadas = $this->sala->sequencia($curso, $usuario)
            ->filter(fn (CursoAula $aula) => $aula->data === null)
            ->values();
        // Sem aulas gravadas: [0, 0] (a regra das gravadas não se aplica).
        if ($gravadas->isEmpty()) {
            return [0, 0];
        }

        return [count($this->sala->assistidas($gravadas, $usuario)), $gravadas->count()];
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
