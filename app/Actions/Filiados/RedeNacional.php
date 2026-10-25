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
 *   6. com uma conta (de onde vem o nome) e com o LOCAL válido: no Brasil, uma das
 *      27 UFs; fora dele, um país da lista (decisão de 24/10/2026: quem está no
 *      exterior aparece num bloco do país, depois dos estados).
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
use App\Support\Paises;
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
     * Os estados do Brasil ficam em "instrutores" e "monitores" (como antes); quem está
     * fora do Brasil fica em "instrutores_exterior" e "monitores_exterior", por país.
     *
     * @return array{instrutores: list<array<string, mixed>>, monitores: list<array<string, mixed>>, instrutores_exterior: list<array<string, mixed>>, monitores_exterior: list<array<string, mixed>>, total: int}
     *                                                                                                                                                                                                             Ex.: ['instrutores' => [['uf' => 'PE', 'estado' => 'Pernambuco', 'pessoas' => [...]]], ...]
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

        // Separa por lista (instrutores ou monitores) e por lugar (Brasil ou exterior).
        $instrutores = $credenciados->filter(fn (Filiado $filiado) => in_array($filiado->nivel?->categoria, self::CATEGORIAS_INSTRUTOR, true));
        $monitores = $credenciados->filter(fn (Filiado $filiado) => in_array($filiado->nivel?->categoria, self::CATEGORIAS_MONITOR, true));
        $noBrasil = fn (Filiado $filiado) => $filiado->pais === Paises::BRASIL;

        return [
            'instrutores' => $this->porEstado($instrutores->filter($noBrasil)),
            'monitores' => $this->porEstado($monitores->filter($noBrasil)),
            'instrutores_exterior' => $this->porPais($instrutores->reject($noBrasil)),
            'monitores_exterior' => $this->porPais($monitores->reject($noBrasil)),
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
     * QUADRO DE ACOMPANHAMENTO para o Comando Geral (tela "Rede Nacional"): TODOS os
     * monitores e instrutores (inclusive os que ainda não aparecem), com o que cada um
     * já cumpre. Serve de "lista de chamada" para recolher as fotos que o Eduardo pediu.
     * Aqui o nome vai COMPLETO: a tela é interna, só para quem cuida de todos os filiados.
     *
     * @param  string|null  $uf  Só um estado (ex.: 'PE'), 'EX' para só o exterior, ou null para todos.
     * @param  bool  $soPendentes  true = só quem ainda NÃO aparece no site.
     * @return list<array{id: int, nome: string, nivel: string|null, equipe: string|null, uf: string|null, exterior: bool, lista: string, ativo: bool, anuidade_em_dia: bool, autorizou: bool, tem_foto: bool, uf_valida: bool, aparece: bool}>
     */
    public function acompanhamento(?string $uf = null, bool $soPendentes = false): array
    {
        $categorias = array_map(fn (CategoriaNivel $categoria) => $categoria->value, [...self::CATEGORIAS_INSTRUTOR, ...self::CATEGORIAS_MONITOR]);
        // Quem aparece hoje no site (a MESMA regra da página pública), numa consulta só.
        $aparecem = $this->consulta()->whereHas('usuario')->pluck('id')->flip();
        // Quem está com a anuidade em dia: null = todos (ano isento ou sem valor cadastrado).
        $emDia = $this->idsComAnuidadeEmDia();

        $linhas = Filiado::query()
            ->with(['nivel', 'equipe:id,nome', 'usuario:id,name'])
            ->whereHas('nivel', fn ($nivel) => $nivel->whereIn('categoria', $categorias))
            // 'EX' = só quem está fora do Brasil; uma UF = só aquele estado (no Brasil).
            ->when($uf === 'EX', fn ($consulta) => $consulta->where('pais', '!=', Paises::BRASIL))
            ->when($uf !== null && $uf !== 'EX', fn ($consulta) => $consulta->where('pais', Paises::BRASIL)->where('uf', $uf))
            ->get()
            ->map(fn (Filiado $filiado) => [
                'id' => $filiado->id,
                // Sem conta (raro): mostra o registro, para o Comando Geral achar a ficha.
                'nome' => $filiado->usuario->name ?? $filiado->registro,
                'nivel' => $filiado->nivel?->nome(),
                'equipe' => $filiado->equipe?->nome,
                // No Brasil, a UF; fora dele, o nome do país. Ex.: 'PE' ou 'Portugal'.
                'uf' => $filiado->pais === Paises::BRASIL ? $filiado->uf : (Paises::nome($filiado->pais) ?? $filiado->pais),
                // true = fora do Brasil (vem depois dos estados no quadro).
                'exterior' => $filiado->pais !== Paises::BRASIL,
                // Em qual aba do site a pessoa entra. Ex.: 'Instrutores'.
                'lista' => in_array($filiado->nivel?->categoria, self::CATEGORIAS_MONITOR, true) ? 'Monitores' : 'Instrutores',
                'ativo' => $filiado->status === 'ativo',
                'anuidade_em_dia' => $emDia === null || $emDia->has($filiado->id),
                'autorizou' => $filiado->autoriza_imagem_rede,
                'tem_foto' => $filiado->foto_rede !== null,
                // Local válido: UF da lista no Brasil; país da lista fora dele.
                'uf_valida' => $filiado->pais === Paises::BRASIL
                    ? array_key_exists((string) $filiado->uf, self::ESTADOS)
                    : Paises::nome($filiado->pais) !== null,
                'aparece' => $aparecem->has($filiado->id),
                // Para ordenar: o nível mais alto primeiro.
                'posicao' => $filiado->nivel->posicao ?? 0,
            ])
            // Filtro "só pendentes": fica só quem ainda NÃO aparece (desligado = todos ficam).
            ->filter(fn (array $linha) => ! $soPendentes || ! $linha['aparece'])
            // Brasil primeiro, por estado (sem UF no fim); depois o exterior, por país; dentro
            // de cada um, pela hierarquia e pelo nome.
            ->sortBy([
                fn (array $a, array $b) => [$a['exterior'], $a['uf'] === null, $a['uf']] <=> [$b['exterior'], $b['uf'] === null, $b['uf']],
                fn (array $a, array $b) => $b['posicao'] <=> $a['posicao'],
                fn (array $a, array $b) => strcoll($a['nome'], $b['nome']),
            ])
            // A posição só servia para ordenar: sai antes de ir para a tela.
            ->map(function (array $linha) {
                unset($linha['posicao']);

                return $linha;
            })
            ->all();

        return array_values($linhas);
    }

    /**
     * Os filiados com a anuidade do ano EM DIA, numa consulta só.
     *
     * @return Collection<int, int>|null Os números dos filiados (como chaves), ou null quando
     *                                   todos estão em dia (ano isento ou sem valor cadastrado).
     */
    private function idsComAnuidadeEmDia(): ?Collection
    {
        $ano = now()->year;
        $anuidade = Anuidade::where('ano', $ano)->first();

        // A mesma regra de Filiado::anuidadeEmDia: sem valor ou isento = todos em dia.
        if ($anuidade === null || $anuidade->isenta) {
            return null;
        }

        return Filiado::query()
            ->whereHas('pagamentosAnuidade', fn ($pagamentos) => $pagamentos->validos()->where('ano', $ano))
            ->pluck('id')
            ->flip();
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
            // Local válido: no Brasil, uma das 27 UFs; fora dele, um país da lista.
            ->where(fn ($local) => $local
                ->where(fn ($brasil) => $brasil->where('pais', Paises::BRASIL)->whereIn('uf', array_keys(self::ESTADOS)))
                ->orWhere(fn ($exterior) => $exterior->where('pais', '!=', Paises::BRASIL)->whereIn('pais', array_keys(Paises::LISTA))));

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
     * @return list<array{uf: string, estado: string, pessoas: list<array{nome: string, nivel: string|null, equipe: string|null, uf: string, pais: string|null, areas: list<string>, foto_url: string}>}>
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
     * Agrupa quem está FORA do Brasil por país (A→Z pelo nome do país) e, dentro dele,
     * pela hierarquia e pelo nome (a mesma ordem dos estados).
     *
     * @param  Collection<int, Filiado>  $filiados  Os credenciados do exterior de uma das listas.
     * @return list<array{pais: string, nome: string, pessoas: list<array{nome: string, nivel: string|null, equipe: string|null, uf: string, pais: string|null, areas: list<string>, foto_url: string}>}>
     */
    private function porPais(Collection $filiados): array
    {
        return array_values($filiados
            ->groupBy('pais')
            ->map(fn (Collection $doPais, string $pais) => [
                'pais' => $pais,
                'nome' => Paises::nome($pais) ?? $pais,
                'pessoas' => array_values($doPais
                    ->sortBy([
                        fn (Filiado $a, Filiado $b) => ($b->nivel->posicao ?? 0) <=> ($a->nivel->posicao ?? 0),
                        fn (Filiado $a, Filiado $b) => strcoll($a->usuario->name ?? '', $b->usuario->name ?? ''),
                    ])
                    ->map(fn (Filiado $filiado) => $this->cartao($filiado))
                    ->all()),
            ])
            // Em ordem alfabética do nome do país (ex.: Argentina antes de Portugal).
            ->sortBy(fn (array $grupo) => $grupo['nome'])
            ->all());
    }

    /**
     * O que vai no cartão de UMA pessoa (só o necessário; nada de contato).
     *
     * @param  Filiado  $filiado  O credenciado, com nível, equipe, áreas e conta carregados.
     * @return array{nome: string, nivel: string|null, equipe: string|null, uf: string, pais: string|null, areas: list<string>, foto_url: string}
     */
    private function cartao(Filiado $filiado): array
    {
        return [
            // Primeiro nome e iniciais (ex.: 'Gleyson A. S.'), como na validação pública.
            'nome' => AuditoriaRelatorio::abreviado($filiado->usuario->name ?? ''),
            'nivel' => $filiado->nivel?->nome(),
            'equipe' => $filiado->equipe?->nome,
            // No Brasil, a UF; fora dele, a sigla do país. Ex.: 'PE' ou 'PT'.
            'uf' => $filiado->pais === Paises::BRASIL ? (string) $filiado->uf : $filiado->pais,
            // O nome do país só para quem está fora do Brasil. Ex.: 'Portugal'; null no Brasil.
            'pais' => $filiado->pais === Paises::BRASIL ? null : Paises::nome($filiado->pais),
            // As áreas em ordem alfabética. Ex.: ['Defesa Pessoal', 'Krav Maga'].
            'areas' => array_values($filiado->areas->map(fn (AreaAtuacao $area) => $area->nome)->sort()->all()),
            // Endereço COMPLETO (o site fica em outro domínio). O "?v=" muda quando a ficha
            // muda, para o navegador não mostrar a foto antiga.
            'foto_url' => route('rede-nacional.foto', ['filiado' => $filiado->id, 'v' => $filiado->updated_at?->getTimestamp()]),
        ];
    }
}
