<?php

/*
 * REDE NACIONAL (#11, pedido do Mestre Eduardo em 23/10/2026): quem aparece na
 * página Rede Nacional do site e como a lista sai.
 *
 * Modelo pedido: o das páginas de instrutores e monitores da WKMF, com a cara do
 * IBTO. Duas listas (INSTRUTORES e MONITORES), cada uma organizada por ESTADO.
 *
 * Regra para aparecer (TODAS precisam valer):
 *   1. nível de Monitor, Instrutor ou Instrutor Expert (Praticante não entra);
 *   2. filiado ATIVO;
 *   3. anuidade EM DIA (em ano isento, todos estão em dia);
 *   4. AUTORIZAÇÃO de imagem da Rede Nacional marcada (LGPD; começa desmarcada);
 *   5. com a FOTO da Rede Nacional enviada;
 *   6. com a UF preenchida (uma das 27 siglas) e com uma conta (de onde vem o nome).
 *
 * O que sai de cada pessoa: nome ABREVIADO (ex.: 'Gleyson A. S.'), nível, equipe,
 * UF e áreas de atuação, mais o endereço público da foto. NUNCA telefone, cidade,
 * e-mail, CPF ou nascimento: muitos são operadores de segurança, e o contato com
 * eles passa pela página de Contato do IBTO (decisão do Eduardo, 26/09/2026).
 *
 * Fica num lugar só: a API pública e a rota da foto usam esta mesma regra, então
 * a foto nunca sai de quem não está na lista.
 */

namespace App\Actions\Filiados;

use App\Enums\CategoriaNivel;
use App\Models\Anuidade;
use App\Models\AreaAtuacao;
use App\Models\Filiado;
use App\Models\Nivel;
use App\Relatorios\AuditoriaRelatorio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RedeNacional
{
    /**
     * Os 27 estados (sigla => nome), na ordem alfabética das siglas. Uma lista
     * fechada: uma UF digitada errado na ficha simplesmente não aparece (em vez
     * de criar um "estado" repetido, como acontece em listas escritas à mão).
     *
     * @var array<string, string>
     */
    public const ESTADOS = [
        'AC' => 'Acre', 'AL' => 'Alagoas', 'AM' => 'Amazonas', 'AP' => 'Amapá',
        'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
        'GO' => 'Goiás', 'MA' => 'Maranhão', 'MG' => 'Minas Gerais', 'MS' => 'Mato Grosso do Sul',
        'MT' => 'Mato Grosso', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PE' => 'Pernambuco',
        'PI' => 'Piauí', 'PR' => 'Paraná', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
        'RO' => 'Rondônia', 'RR' => 'Roraima', 'RS' => 'Rio Grande do Sul', 'SC' => 'Santa Catarina',
        'SE' => 'Sergipe', 'SP' => 'São Paulo', 'TO' => 'Tocantins',
    ];

    // Categorias de nível que vão para a lista de INSTRUTORES.
    private const CATEGORIAS_INSTRUTOR = [CategoriaNivel::Instrutor, CategoriaNivel::InstrutorExpert];

    // Categoria de nível que vai para a lista de MONITORES.
    private const CATEGORIAS_MONITOR = [CategoriaNivel::Monitor];

    /**
     * As duas listas prontas para a API pública.
     *
     * @return array{instrutores: list<array<string, mixed>>, monitores: list<array<string, mixed>>, total: int}
     *                                                                                                           Ex.: ['instrutores' => [['uf' => 'PE', 'estado' => 'Pernambuco', 'pessoas' => [...]]], ...]
     */
    public function listas(): array
    {
        // Todos que cumprem a regra, já com nível, equipe, áreas e conta carregados de uma vez
        // (evita uma consulta por pessoa).
        $credenciados = $this->consulta()
            ->with(['nivel', 'equipe:id,nome', 'areas:id,nome', 'usuario:id,name'])
            ->get()
            // Sem conta não há nome para mostrar: fica de fora.
            ->filter(fn (Filiado $filiado) => $filiado->usuario !== null);

        $instrutores = $this->porEstado($credenciados->filter(fn (Filiado $filiado) => in_array($filiado->nivel?->categoria, self::CATEGORIAS_INSTRUTOR, true)));
        $monitores = $this->porEstado($credenciados->filter(fn (Filiado $filiado) => in_array($filiado->nivel?->categoria, self::CATEGORIAS_MONITOR, true)));

        return [
            'instrutores' => $instrutores,
            'monitores' => $monitores,
            'total' => $credenciados->count(),
        ];
    }

    /**
     * Este filiado aparece na Rede Nacional? Usado pela rota pública da foto: a foto
     * só sai de quem está na lista.
     *
     * @param  Filiado  $filiado  O filiado. Ex.: o do endereço /imagens/rede-nacional/7.
     * @return bool true = aparece (a foto pode sair); false = não aparece (404).
     */
    public function aparece(Filiado $filiado): bool
    {
        // A mesma consulta da lista, filtrada por este filiado (uma regra só, sem cópia).
        return $this->consulta()->whereKey($filiado->id)->whereHas('usuario')->exists();
    }

    /**
     * A consulta com as regras 1 a 6 (ver o topo do arquivo).
     *
     * @return Builder<Filiado>
     */
    private function consulta(): Builder
    {
        // Os níveis de monitor e instrutor (inclusive os níveis livres dessas categorias).
        $categorias = array_map(fn (CategoriaNivel $categoria) => $categoria->value, [...self::CATEGORIAS_INSTRUTOR, ...self::CATEGORIAS_MONITOR]);
        $niveis = Nivel::query()->whereIn('categoria', $categorias)->pluck('id');

        $consulta = Filiado::query()
            ->where('status', 'ativo')
            ->where('autoriza_imagem_rede', true)
            ->whereNotNull('foto_rede')
            ->whereIn('nivel_id', $niveis)
            ->whereIn('uf', array_keys(self::ESTADOS));

        // Anuidade: o ano de hoje. Sem valor cadastrado ou isento (ex.: 2026), todos estão
        // em dia (a mesma regra de Filiado::anuidadeEmDia). Se não, precisa de um pagamento
        // VÁLIDO (não estornado) do ano.
        $ano = now()->year;
        $anuidade = Anuidade::where('ano', $ano)->first();
        if ($anuidade !== null && ! $anuidade->isenta) {
            $consulta->whereHas('pagamentosAnuidade', fn ($pagamentos) => $pagamentos->validos()->where('ano', $ano));
        }

        return $consulta;
    }

    /**
     * Agrupa por estado (A→Z pela sigla) e, dentro do estado, pela hierarquia (o nível
     * mais alto primeiro; empate = ordem alfabética do nome).
     *
     * @param  Collection<int, Filiado>  $filiados  Os credenciados de uma das listas.
     * @return list<array{uf: string, estado: string, pessoas: list<array{nome: string, nivel: string|null, equipe: string|null, uf: string, areas: list<string>, foto_url: string}>}>
     */
    private function porEstado(Collection $filiados): array
    {
        // array_values: garante uma LISTA (0, 1, 2...), que vira [] no JSON (e não um objeto).
        return array_values($filiados
            ->groupBy('uf')
            ->sortKeys()
            ->map(fn (Collection $doEstado, string $uf) => [
                'uf' => $uf,
                'estado' => self::ESTADOS[$uf],
                'pessoas' => array_values($doEstado
                    ->sortBy([
                        // Posição maior = nível mais alto: vem primeiro.
                        fn (Filiado $a, Filiado $b) => ($b->nivel->posicao ?? 0) <=> ($a->nivel->posicao ?? 0),
                        fn (Filiado $a, Filiado $b) => strcoll($a->usuario->name ?? '', $b->usuario->name ?? ''),
                    ])
                    ->map(fn (Filiado $filiado) => $this->cartao($filiado))
                    ->all()),
            ])
            ->all());
    }

    /**
     * O que vai no cartão de UMA pessoa (só o necessário; nada de contato).
     *
     * @param  Filiado  $filiado  O credenciado, com nível, equipe, áreas e conta carregados.
     * @return array{nome: string, nivel: string|null, equipe: string|null, uf: string, areas: list<string>, foto_url: string}
     */
    private function cartao(Filiado $filiado): array
    {
        return [
            // Primeiro nome e iniciais (ex.: 'Gleyson A. S.'), como na validação pública.
            'nome' => AuditoriaRelatorio::abreviado($filiado->usuario->name ?? ''),
            'nivel' => $filiado->nivel?->nome(),
            'equipe' => $filiado->equipe?->nome,
            'uf' => (string) $filiado->uf,
            // As áreas em ordem alfabética. Ex.: ['Defesa Pessoal', 'Krav Maga'].
            'areas' => array_values($filiado->areas->map(fn (AreaAtuacao $area) => $area->nome)->sort()->all()),
            // Endereço COMPLETO (o site fica em outro domínio). O "?v=" muda quando a ficha
            // muda, para o navegador não mostrar a foto antiga.
            'foto_url' => route('rede-nacional.foto', ['filiado' => $filiado->id, 'v' => $filiado->updated_at?->getTimestamp()]),
        ];
    }
}
