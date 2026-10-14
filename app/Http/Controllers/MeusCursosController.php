<?php

/*
 * MEUS CURSOS (desenho aprovado pelo Gleyson em 10/10/2026, tela 1 do aluno):
 * a página inicial de quem NÃO é da equipe do IBTO (aluno, filiado,
 * instrutor, admin de equipe).
 * - Cabeçalho com o LOGO DA EQUIPE do filiado (sem equipe ou não filiado:
 *   o emblema do IBTO), o nome, o registro e a categoria;
 * - TRILHA DE FORMAÇÃO: os NÍVEIS do cadastro, em ordem (Monitor I, Monitor II,
 *   Instrutor I...; decisão de 10/10/2026), cada um "feito", "atual" ou "a seguir";
 * - os CURSOS da pessoa (matrículas aguardando pagamento ou confirmadas),
 *   com o próximo passo de cada um (pagar ou assistir).
 * Ficam para depois: a foto do filiado (upload com LGPD, #47) e o dossiê
 * (presenças e certificados, #44).
 */

namespace App\Http\Controllers;

use App\Enums\SituacaoMatricula;
use App\Models\CursoMatricula;
use App\Models\Nivel;
use App\Models\User;
use App\Services\SalaDeAula;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MeusCursosController extends Controller
{
    /**
     * A página "Meus cursos".
     *
     * @param  Request  $request  Quem está logado.
     * @param  SalaDeAula  $sala  As regras da sala de aula (progresso e "Continuar").
     * @return Response A tela "meus-cursos".
     */
    public function __invoke(Request $request, SalaDeAula $sala): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $filiado = $usuario->filiado()->with(['equipe', 'nivel'])->first();

        // As matrículas que valem (aguardando ou confirmadas), as mais novas primeiro.
        $matriculas = CursoMatricula::query()
            ->where('user_id', $usuario->id)
            ->ocupandoVaga()
            ->with(['curso.area:id,nome', 'certificado'])
            ->latest('id')
            ->get();

        return Inertia::render('meus-cursos', [
            'pessoa' => [
                'nome' => $usuario->name,
                // Só filiado tem registro, equipe e categoria.
                'filiado' => $filiado !== null,
                'registro' => $filiado?->registro,
                'categoria' => $filiado?->categoria?->rotulo(),
                'equipe' => $filiado?->equipe?->nome,
                // Logo da equipe; null = mostrar o emblema do IBTO.
                'logo_url' => $filiado?->equipe?->logoUrl(),
                // O nível atual escrito. Ex.: 'Monitor II' (null = sem nível).
                'nivel' => $filiado?->nivel?->nome(),
            ],
            // Trilha pelo NÍVEL do filiado (graduação), não pela categoria.
            'trilha' => $this->trilha($filiado?->nivel_id),
            'cursos' => $matriculas->map(fn (CursoMatricula $matricula) => [
                'matricula_id' => $matricula->id,
                'curso_id' => $matricula->curso_id,
                'titulo' => $matricula->curso->titulo,
                'imagem_url' => $matricula->curso->imagemUrl(),
                'modalidade' => $matricula->curso->modalidade->rotulo(),
                'area' => $matricula->curso->area?->nome,
                'data_inicio' => $matricula->curso->data_inicio?->toDateString(),
                'situacao' => $matricula->situacao->value,
                'situacao_rotulo' => $matricula->situacao->rotulo(),
                // Código do certificado VÁLIDO (#44), para "Ver certificado"; null = não tem.
                'certificado' => $matricula->certificado?->valido() ? $matricula->certificado->codigo : null,
                // PROGRESSO das aulas (14/10/2026), só na matrícula confirmada:
                // "3 de 10 (30%)" e a aula do botão "Continuar" (a 1ª não assistida).
                // Aguardando pagamento, ou curso sem aulas: null (o botão vira "Ver o curso").
                'progresso' => $matricula->situacao === SituacaoMatricula::Confirmada
                    ? $this->progressoComAulas($sala->progresso($matricula->curso, $usuario))
                    : null,
            ])->values(),
        ]);
    }

    /**
     * As etapas da trilha de formação: os NÍVEIS ativos do cadastro, na ordem
     * da hierarquia (decisão do Gleyson, 10/10/2026: "trilha por nível", como
     * no desenho aprovado: Monitor I, Monitor II, Instrutor I...).
     *
     * @param  int|null  $nivelAtual  O nível do filiado (null = sem nível ou não filiado).
     * @return list<array{nome: string, estado: string}> estado: 'feita', 'atual' ou 'a_seguir'.
     */
    private function trilha(?int $nivelAtual): array
    {
        // Os níveis ativos, já na ordem (Nivel::emOrdem: categoria e depois o grau).
        $niveis = Nivel::emOrdem(Nivel::query()->where('ativo', true)->get())->values();
        // Posição do nível do filiado na lista (false = sem nível: tudo "a seguir").
        $posicao = $nivelAtual !== null ? $niveis->search(fn (Nivel $nivel) => $nivel->id === $nivelAtual) : false;

        // array_values: devolve uma lista numerada 0, 1, 2... (o que a tela espera).
        return array_values($niveis->map(fn (Nivel $nivel, int $indice) => [
            'nome' => $nivel->nome(),
            'estado' => match (true) {
                $posicao === false => 'a_seguir',
                // Antes do nível atual: já passou por ele.
                $indice < $posicao => 'feita',
                $indice === $posicao => 'atual',
                default => 'a_seguir',
            },
        ])->all());
    }

    /**
     * O progresso, ou null quando o curso ainda não tem aula (nada para assistir).
     *
     * @param  array{assistidas: int, total: int, percentual: int, continuar_id: int|null}  $progresso  O que a SalaDeAula calculou.
     * @return array{assistidas: int, total: int, percentual: int, continuar_id: int|null}|null
     */
    private function progressoComAulas(array $progresso): ?array
    {
        return $progresso['total'] > 0 ? $progresso : null;
    }
}
