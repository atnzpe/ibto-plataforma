<?php

/*
 * REGRAS DO CERTIFICADO DE GRADUAÇÃO (#46, parte 2): sugerir os textos, montar
 * o número de série, emitir e cancelar. Toda emissão e cancelamento vão para a
 * auditoria (só números, LGPD).
 *
 * NÚMERO DE SÉRIE (padrão do modelo do Mestre Eduardo; ajustável quando ele
 * confirmar o significado de cada parte):
 *   GR + ano da emissão + ordem do registro (4 dígitos) + letra do nível + via
 *   Ex.: registro BR20260034SP, nível Instrutor Expert, 1ª via em 2026 → GR20260034E0
 *   Letras: P = Praticante, M = Monitor, I = Instrutor, E = Instrutor Expert, X = sem nível.
 *   Via: 0 na primeira; 1, 2... nas seguintes do mesmo ano e nível (ex.: 2ª via).
 *   Se a ordem se repetir entre dois filiados (há casos na planilha), entra a UF
 *   no fim, para o número continuar único. Ex.: GR20260041E0RJ.
 */

namespace App\Actions\Filiados;

use App\Enums\CategoriaNivel;
use App\Exceptions\RegraFinanceira;
use App\Models\Auditoria;
use App\Models\CursoCertificado;
use App\Models\Filiado;
use App\Models\GraduacaoCertificado;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CertificadosGraduacao
{
    /** A letra de cada categoria de nível no número de série. */
    private const LETRAS = [
        'Praticante' => 'P',
        'Monitor' => 'M',
        'Instrutor' => 'I',
        'Instrutor Expert' => 'E',
    ];

    /**
     * Os textos sugeridos para o formulário de emissão (o Comando Geral pode ajustar).
     *
     * @return array{condicao: string, nivel: string, abrangencia: string, local: string}
     *                                                                                    Ex.: ['condicao' => 'Instrutor de Krav Maga', 'nivel' => 'Nível Expert II - [E]', 'abrangencia' => 'Nacional', 'local' => 'Rio de Janeiro - RJ']
     */
    public function sugestao(Filiado $filiado): array
    {
        $nivel = $filiado->nivel;
        $area = $filiado->areas()->orderBy('nome')->value('nome');
        // A função vem da categoria do nível (Instrutor Expert também é "Instrutor").
        $funcao = match ($nivel?->categoria) {
            CategoriaNivel::Praticante => 'Praticante',
            CategoriaNivel::Monitor => 'Monitor',
            CategoriaNivel::Instrutor, CategoriaNivel::InstrutorExpert => 'Instrutor',
            default => 'Membro',
        };
        // O nível no estilo do modelo. Ex.: 'Instrutor Expert II' → 'Nível Expert II - [E]'.
        $textoNivel = $nivel === null ? 'Membro do IBTO' : 'Nível '.trim(str_replace('Instrutor ', '', $nivel->nome())).' - ['.$this->letra($filiado).']';

        return [
            'condicao' => $area !== null ? "{$funcao} de {$area}" : $funcao,
            'nivel' => $textoNivel,
            'abrangencia' => $filiado->abrangencia ?: 'Nacional',
            'local' => (string) config('ibto.certificado_local', 'Rio de Janeiro - RJ'),
        ];
    }

    /**
     * Emite o certificado de graduação.
     *
     * @param  Filiado  $filiado  Quem recebe (precisa ter a conta, de onde sai o nome).
     * @param  array{condicao: string, nivel: string, abrangencia: string, local: string}  $dados  Os textos confirmados.
     * @return GraduacaoCertificado O certificado emitido.
     *
     * @throws RegraFinanceira Filiado sem conta (sem nome para imprimir).
     */
    public function emitir(Filiado $filiado, array $dados): GraduacaoCertificado
    {
        $nome = $filiado->usuario?->name;
        // Sem conta ligada não há nome para imprimir.
        if ($nome === null || trim($nome) === '') {
            throw new RegraFinanceira('GRADUACAO_SEM_NOME', 'Este filiado ainda não tem a conta ligada (sem o nome para o certificado).');
        }

        $certificado = DB::transaction(function () use ($filiado, $dados, $nome): GraduacaoCertificado {
            // Tranca o filiado: duas emissões ao mesmo tempo não pegam a mesma via.
            Filiado::query()->lockForUpdate()->findOrFail($filiado->id);
            $certificado = new GraduacaoCertificado;
            $certificado->forceFill([
                'filiado_id' => $filiado->id,
                'codigo' => $this->novoCodigo(),
                'numero_serie' => $this->numeroSerie($filiado),
                'nome' => trim($nome),
                'condicao' => trim($dados['condicao']),
                'nivel' => trim($dados['nivel']),
                'abrangencia' => trim($dados['abrangencia']),
                'local' => trim($dados['local']),
                'emitido_em' => today()->toDateString(),
                'emitido_por' => Auth::id(),
            ])->save();

            return $certificado;
        });

        Auditoria::registrar('GRADUACAO_EMITIDA', ['certificado_id' => $certificado->id, 'filiado_id' => $filiado->id]);

        return $certificado;
    }

    /**
     * Cancela com motivo (o certificado continua no histórico, marcado como cancelado).
     *
     * @param  string  $motivo  Ex.: 'Emitido com o nível errado'.
     */
    public function cancelar(GraduacaoCertificado $certificado, string $motivo): GraduacaoCertificado
    {
        // Já cancelado: não muda nada.
        if ($certificado->cancelado_em !== null) {
            return $certificado;
        }
        $certificado->forceFill(['cancelado_em' => now(), 'motivo_cancelamento' => trim($motivo)])->save();
        Auditoria::registrar('GRADUACAO_CANCELADA', ['certificado_id' => $certificado->id, 'filiado_id' => $certificado->filiado_id]);

        return $certificado;
    }

    /**
     * O número de série do próximo certificado deste filiado (ver o comentário do arquivo).
     *
     * @return string Ex.: 'GR20260034E0'
     */
    public function numeroSerie(Filiado $filiado): string
    {
        $ano = today()->year;
        // A ordem: os 4 dígitos depois do ano no registro (BR + 4 do ano + 4 da ordem + UF).
        $ordem = preg_match('/^BR\d{4}(\d{4})/', $filiado->registro, $partes) === 1 ? $partes[1] : str_pad((string) $filiado->id, 4, '0', STR_PAD_LEFT);
        $base = "GR{$ano}{$ordem}".$this->letra($filiado);
        // Via: quantos certificados DESTE filiado já começam com a mesma base (0 = primeira).
        $via = GraduacaoCertificado::where('filiado_id', $filiado->id)->where('numero_serie', 'like', $base.'%')->count();
        $numero = $base.$via;

        // A ordem se repete com outro filiado: entra a UF no fim para continuar único.
        if (GraduacaoCertificado::where('numero_serie', $numero)->exists()) {
            $numero .= strtoupper((string) $filiado->uf);
        }

        return $numero;
    }

    /**
     * A letra do nível do filiado. Ex.: Instrutor Expert → 'E'; sem nível → 'X'.
     */
    private function letra(Filiado $filiado): string
    {
        return self::LETRAS[$filiado->nivel->categoria->value ?? ''] ?? 'X';
    }

    /**
     * Um código sorteado ÚNICO entre os certificados de curso e de graduação
     * (a mesma página /validar atende os dois). Ex.: 'IBTO-7K2M-9QXA'.
     */
    private function novoCodigo(): string
    {
        do {
            $codigo = CursoCertificado::novoCodigo();
        } while (GraduacaoCertificado::where('codigo', $codigo)->exists());

        return $codigo;
    }
}
