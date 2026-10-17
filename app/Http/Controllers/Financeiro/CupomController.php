<?php

/*
 * TELA DE CUPONS DE DESCONTO (#89), no Financeiro: lista (com usos e total de
 * desconto), novo e edição. Não existe "apagar": cupom usado só é desativado
 * (fica o histórico). Permissão "cupons.gerenciar" (routes/web.php); começa só
 * com o Comando Geral e a Tecnologia. Cada mudança vai para a auditoria.
 */

namespace App\Http\Controllers\Financeiro;

use App\Enums\SituacaoCurso;
use App\Enums\SituacaoMatricula;
use App\Http\Controllers\Controller;
use App\Http\Requests\Financeiro\CupomRequest;
use App\Models\Auditoria;
use App\Models\Cupom;
use App\Models\Curso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CupomController extends Controller
{
    /**
     * A lista, com a situação de cada cupom, quantas vezes foi usado e o total de desconto.
     *
     * @param  Request  $request  Com o filtro "situacao" (opcional). Ex.: ?situacao=Ativo
     * @return Response A tela "financeiro/cupons/index".
     */
    public function index(Request $request): Response
    {
        // Usos e desconto somados numa consulta só (matrículas não canceladas contam).
        $cupons = Cupom::query()
            ->withCount(['matriculas as usos' => fn ($matricula) => $matricula->where('situacao', '!=', SituacaoMatricula::Cancelada->value)])
            ->withSum(['matriculas as total_desconto' => fn ($matricula) => $matricula->where('situacao', '!=', SituacaoMatricula::Cancelada->value)], 'desconto_centavos')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Cupom $cupom) => [
                'id' => $cupom->id,
                'codigo' => $cupom->codigo,
                'desconto' => $cupom->rotuloDesconto(),
                // Validade escrita. Ex.: 'até 31/12/2026 23:59'; null = sem prazo.
                'validade' => $this->validade($cupom),
                'usos' => (int) $cupom->getAttribute('usos'),
                'limite_total' => $cupom->limite_total,
                'total_desconto_centavos' => (int) $cupom->getAttribute('total_desconto'),
                'situacao' => $cupom->situacao((int) $cupom->getAttribute('usos')),
            ]);

        // Filtro por situação (Ativo, Vencido, Esgotado, Desativado, Ainda não começou).
        $situacao = (string) $request->query('situacao', '');
        if ($situacao !== '') {
            $cupons = $cupons->where('situacao', $situacao);
        }

        return Inertia::render('financeiro/cupons/index', [
            'cupons' => $cupons->values(),
            'filtro_situacao' => $situacao,
        ]);
    }

    /**
     * Formulário vazio.
     */
    public function create(): Response
    {
        return Inertia::render('financeiro/cupons/formulario', ['cupom' => null, 'cursos' => $this->cursos()]);
    }

    /**
     * Grava o cupom novo (com quem criou e a auditoria) e volta para a lista.
     *
     * @param  CupomRequest  $request  O formulário, já conferido.
     */
    public function store(CupomRequest $request): RedirectResponse
    {
        $cupom = DB::transaction(function () use ($request): Cupom {
            $cupom = new Cupom($this->dados($request));
            $cupom->forceFill(['criado_por' => $request->user()?->id])->save();
            $cupom->cursos()->sync($cupom->todos_cursos ? [] : array_map('intval', (array) $request->input('cursos', [])));
            Auditoria::registrar('CUPOM_CRIADO', ['cupom_id' => $cupom->id]);

            return $cupom;
        });

        return to_route('financeiro.cupons.index')->with('sucesso', "Cupom {$cupom->codigo} criado.");
    }

    /**
     * Formulário preenchido.
     *
     * @param  Cupom  $cupom  O do endereço (ex.: /financeiro/cupons/3/edit).
     */
    public function edit(Cupom $cupom): Response
    {
        return Inertia::render('financeiro/cupons/formulario', [
            'cupom' => [
                'id' => $cupom->id,
                'codigo' => $cupom->codigo,
                'tipo' => $cupom->tipo,
                // No formato do campo: percentual inteiro ou reais com vírgula. Ex.: '10' ou '50,00'.
                'valor' => $cupom->tipo === 'percentual' ? (string) $cupom->valor : number_format($cupom->valor / 100, 2, ',', ''),
                'valido_de' => $cupom->valido_de?->format('Y-m-d\TH:i'),
                'valido_ate' => $cupom->valido_ate?->format('Y-m-d\TH:i'),
                'limite_total' => $cupom->limite_total,
                'limite_por_pessoa' => $cupom->limite_por_pessoa,
                'so_filiados' => $cupom->so_filiados,
                'todos_cursos' => $cupom->todos_cursos,
                'ativo' => $cupom->ativo,
                'cursos' => $cupom->cursos()->pluck('cursos.id'),
                // Já foi usado? (a tela avisa que mudar o desconto não muda as matrículas antigas).
                'usado' => $cupom->matriculas()->exists(),
            ],
            'cursos' => $this->cursos(),
        ]);
    }

    /**
     * Grava as mudanças (inclusive desativar), com auditoria. Matrículas já feitas não mudam.
     *
     * @param  CupomRequest  $request  O formulário, já conferido.
     * @param  Cupom  $cupom  O que está sendo editado.
     */
    public function update(CupomRequest $request, Cupom $cupom): RedirectResponse
    {
        DB::transaction(function () use ($request, $cupom): void {
            $antes = $cupom->ativo;
            $cupom->update($this->dados($request));
            $cupom->cursos()->sync($cupom->todos_cursos ? [] : array_map('intval', (array) $request->input('cursos', [])));
            Auditoria::registrar('CUPOM_EDITADO', ['cupom_id' => $cupom->id, 'ativo_antes' => $antes, 'ativo_depois' => $cupom->ativo]);
        });

        return to_route('financeiro.cupons.index')->with('sucesso', "Cupom {$cupom->codigo} atualizado.");
    }

    /**
     * Os campos para gravar, já convertidos (código normalizado, valor em unidades).
     *
     * @param  CupomRequest  $request  O formulário conferido.
     * @return array<string, mixed>
     */
    private function dados(CupomRequest $request): array
    {
        return [
            'codigo' => $request->string('codigo')->toString(),
            'tipo' => $request->string('tipo')->toString(),
            'valor' => $request->valorEmUnidades(),
            'valido_de' => $request->input('valido_de') ?: null,
            'valido_ate' => $request->input('valido_ate') ?: null,
            'limite_total' => $request->filled('limite_total') ? $request->integer('limite_total') : null,
            'limite_por_pessoa' => $request->integer('limite_por_pessoa', 1),
            'so_filiados' => $request->boolean('so_filiados'),
            'todos_cursos' => $request->boolean('todos_cursos'),
            'ativo' => $request->boolean('ativo', true),
        ];
    }

    /**
     * Os cursos que podem receber cupom: pagos e não cancelados nem encerrados.
     *
     * @return list<array{id: int, titulo: string}>
     */
    private function cursos(): array
    {
        // array_values: garante uma lista (posições 0, 1, 2…), como a tela espera.
        return array_values(Curso::query()
            ->where('valor_centavos', '>', 0)
            ->whereNotIn('situacao', [SituacaoCurso::Cancelado->value, SituacaoCurso::Encerrado->value])
            ->orderBy('titulo')
            ->get(['id', 'titulo'])
            ->map(fn (Curso $curso) => ['id' => $curso->id, 'titulo' => $curso->titulo])
            ->all());
    }

    /**
     * A validade escrita para a lista.
     *
     * @return string|null Ex.: '01/11/2026 00:00 a 30/11/2026 23:59', 'até 30/11/2026 23:59', ou null (sem prazo).
     */
    private function validade(Cupom $cupom): ?string
    {
        $de = $cupom->valido_de?->format('d/m/Y H:i');
        $ate = $cupom->valido_ate?->format('d/m/Y H:i');

        return match (true) {
            $de !== null && $ate !== null => "{$de} a {$ate}",
            $ate !== null => "até {$ate}",
            $de !== null => "a partir de {$de}",
            default => null,
        };
    }
}
