<?php

/*
 * MATRÍCULAS no Comando Geral (#41, bloco C5c; decisão de 29/09/2026: quem
 * confirma o PIX é a secretaria/tesouraria, permissão "pagamentos.confirmar").
 *
 * - Lista com filtros: situação, curso, busca pelo nome e período "De / Até"
 *   pela data da matrícula (regra do Gleyson: filtro por período, nunca por mês);
 * - Confirmar o pagamento: dia em que o dinheiro entrou + forma (PIX...) →
 *   receita paga no livro-caixa e e-mail ao aluno (App\Actions\Cursos\Matriculas);
 * - Cancelar com motivo, sem apagar.
 */

namespace App\Http\Controllers\Cursos;

use App\Actions\Cursos\Matriculas;
use App\Enums\SituacaoMatricula;
use App\Exceptions\RegraCurso;
use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Curso;
use App\Models\CursoMatricula;
use App\Models\FinFormaPagamento;
use App\Support\Periodo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MatriculaPainelController extends Controller
{
    /**
     * A lista de matrículas, 20 por página, as mais novas primeiro.
     *
     * @param  Request  $request  Filtros. Ex.: ?situacao=aguardando_pagamento&curso=7&busca=ana&de=2026-10-01&ate=2026-10-31
     * @return Response A tela "cursos/matriculas".
     */
    public function index(Request $request): Response
    {
        $situacao = SituacaoMatricula::tryFrom((string) $request->query('situacao', ''));
        $cursoId = (int) $request->query('curso', 0);
        $busca = trim((string) $request->query('busca', ''));
        // Período só quando a pessoa escolhe alguma data (sem datas = todas).
        $periodo = ($request->filled('de') || $request->filled('ate')) ? Periodo::doPedido($request) : null;

        $consulta = CursoMatricula::query()
            // Só o REGISTRO do filiado e a equipe vão para a lista (LGPD; decisão de 10/10/2026).
            ->with(['curso:id,titulo', 'usuario.filiado:id,user_id,registro,equipe_id', 'usuario.filiado.equipe:id,nome'])
            ->orderByDesc('id');

        if ($situacao !== null) {
            $consulta->where('situacao', $situacao->value);
        }
        if ($cursoId > 0) {
            $consulta->where('curso_id', $cursoId);
        }
        if ($busca !== '') {
            // Pelo registro do filiado (ex.: BR20260046PE) ou pelo número da matrícula.
            $consulta->where(fn ($ou) => $ou
                ->whereHas('usuario.filiado', fn ($filiado) => $filiado->where('registro', 'like', "%{$busca}%"))
                ->orWhere('id', (int) ltrim($busca, '#')));
        }
        if ($periodo !== null) {
            $consulta->whereBetween('created_at', [$periodo->inicio, $periodo->fim]);
        }

        $pagina = $consulta->paginate(20)->withQueryString()->through(fn (CursoMatricula $matricula) => [
            'id' => $matricula->id,
            'curso' => $matricula->curso->titulo,
            'curso_id' => $matricula->curso_id,
            // Quem é, SEM o nome (LGPD): o registro do filiado; não filiado = só o nº da matrícula.
            'registro' => $matricula->usuario->filiado?->registro,
            'equipe' => $matricula->usuario->filiado !== null ? ($matricula->usuario->filiado->equipe->nome ?? 'Sem equipe') : 'Não filiado',
            'situacao' => $matricula->situacao->value,
            'situacao_rotulo' => $matricula->situacao->rotulo(),
            'valor_centavos' => $matricula->valor_centavos,
            'criada_em' => $matricula->created_at?->toDateString(),
            'confirmada_em' => $matricula->confirmada_em?->toDateString(),
            'motivo_cancelamento' => $matricula->motivo_cancelamento,
            // Link do comprovante (rota protegida), ou null.
            'comprovante_url' => $matricula->comprovante !== null ? route('matriculas.comprovante.ver', $matricula->id) : null,
            'lancamento_id' => $matricula->lancamento_id,
        ]);

        return Inertia::render('cursos/matriculas', [
            'matriculas' => $pagina,
            'filtros' => [
                'situacao' => $situacao->value ?? '',
                'curso' => $cursoId > 0 ? $cursoId : '',
                'busca' => $busca,
                'de' => $periodo?->inicio->toDateString() ?? '',
                'ate' => $periodo?->fim->toDateString() ?? '',
            ],
            // Quantas aguardam pagamento (o número no topo, para não esquecer ninguém).
            'aguardando' => CursoMatricula::where('situacao', SituacaoMatricula::AguardandoPagamento->value)->count(),
            'cursos' => Curso::query()->whereHas('matriculas')->orderBy('titulo')->get(['id', 'titulo']),
            'formas' => FinFormaPagamento::query()->where('ativa', true)->orderBy('nome')->get(['id', 'nome']),
            'hoje' => today()->toDateString(),
        ]);
    }

    /**
     * A FICHA da matrícula: aqui sim aparecem o nome e o e-mail (decisão de
     * 10/10/2026: na lista só o registro; o nome ao abrir a ficha). Cada
     * abertura fica na auditoria: quem viu os dados de quem e quando (LGPD).
     *
     * @param  CursoMatricula  $matricula  A do endereço. Ex.: /comando-geral/matriculas/12
     * @return Response A tela "cursos/matricula-ficha".
     */
    public function ficha(CursoMatricula $matricula): Response
    {
        $matricula->load(['curso:id,titulo,data_inicio,local', 'usuario.filiado.equipe:id,nome']);
        $filiado = $matricula->usuario->filiado;

        // Registro de acesso a dados pessoais (só números: o nome não vai para o log).
        Auditoria::registrar('MATRICULA_FICHA_ABERTA', ['matricula_id' => $matricula->id, 'usuario_id' => $matricula->user_id]);

        return Inertia::render('cursos/matricula-ficha', [
            'matricula' => [
                'id' => $matricula->id,
                'nome' => $matricula->usuario->name,
                'email' => $matricula->usuario->email,
                'registro' => $filiado?->registro,
                'equipe' => $filiado !== null ? ($filiado->equipe->nome ?? 'Sem equipe') : 'Não filiado',
                'curso' => $matricula->curso->titulo,
                'situacao' => $matricula->situacao->value,
                'situacao_rotulo' => $matricula->situacao->rotulo(),
                'valor_centavos' => $matricula->valor_centavos,
                'criada_em' => $matricula->created_at?->format('d/m/Y H:i'),
                'confirmada_em' => $matricula->confirmada_em?->format('d/m/Y H:i'),
                'cancelada_em' => $matricula->cancelada_em?->format('d/m/Y H:i'),
                'motivo_cancelamento' => $matricula->motivo_cancelamento,
                'comprovante_url' => $matricula->comprovante !== null ? route('matriculas.comprovante.ver', $matricula->id) : null,
                'lancamento_id' => $matricula->lancamento_id,
            ],
        ]);
    }

    /**
     * Confirma o pagamento (baixa manual).
     *
     * @param  Request  $request  Ex.: pago_em=2026-10-12, forma_pagamento_id=1
     * @param  CursoMatricula  $matricula  A do endereço.
     * @param  Matriculas  $matriculas  As regras.
     * @return RedirectResponse Volta para a lista com o resultado.
     */
    public function confirmar(Request $request, CursoMatricula $matricula, Matriculas $matriculas): RedirectResponse
    {
        $dados = $request->validate([
            // Não aceita data no futuro: o dinheiro já precisa ter entrado.
            'pago_em' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'forma_pagamento_id' => ['required', 'integer', Rule::exists('fin_formas_pagamento', 'id')->where('ativa', 1)],
        ], [
            'pago_em.before_or_equal' => 'O dia do pagamento não pode ser no futuro.',
        ]);

        try {
            $matriculas->confirmarPagamento($matricula, $dados['pago_em'], (int) $dados['forma_pagamento_id']);
        } catch (RegraCurso $erro) {
            return back()->withErrors(['matricula' => "{$erro->getMessage()} (código {$erro->codigo})"]);
        }

        return back()->with('sucesso', "Pagamento da matrícula nº {$matricula->id} confirmado. A receita entrou no financeiro e o aluno recebe um e-mail.");
    }

    /**
     * Cancela com motivo, sem apagar.
     *
     * @param  Request  $request  Ex.: motivo='Pagamento não identificado'
     * @param  CursoMatricula  $matricula  A do endereço.
     * @param  Matriculas  $matriculas  As regras.
     * @return RedirectResponse Volta para a lista com o resultado.
     */
    public function cancelar(Request $request, CursoMatricula $matricula, Matriculas $matriculas): RedirectResponse
    {
        $dados = $request->validate(['motivo' => ['required', 'string', 'max:500']], [
            'motivo.required' => 'Explique o motivo do cancelamento.',
        ]);

        try {
            $cancelada = $matriculas->cancelar($matricula, $dados['motivo']);
        } catch (RegraCurso $erro) {
            return back()->withErrors(['matricula' => "{$erro->getMessage()} (código {$erro->codigo})"]);
        }

        // Tinha pagamento: lembra que a devolução do dinheiro é um estorno no financeiro.
        $lembrete = $cancelada->lancamento_id !== null
            ? " Ela tinha pagamento (lançamento nº {$cancelada->lancamento_id}): se for devolver o dinheiro, faça o estorno no Financeiro."
            : '';

        return back()->with('sucesso', "Matrícula nº {$matricula->id} cancelada; a vaga foi liberada.{$lembrete}");
    }
}
