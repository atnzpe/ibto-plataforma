<?php

/*
 * PROGRAMAS DE AULAS REGULARES NO COMANDO GERAL (#80): lista, novo, editar e as
 * turmas de cada programa (dias, horário, local ou link da sala). Quem entra:
 * cursos.gerenciar (o mesmo de quem cuida dos cursos).
 * Desativar (em vez de apagar) mantém o histórico das assinaturas e da frequência.
 */

namespace App\Http\Controllers\AulasRegulares;

use App\Http\Controllers\Controller;
use App\Models\AreaAtuacao;
use App\Models\Auditoria;
use App\Models\Programa;
use App\Models\ProgramaTurma;
use App\Models\User;
use App\Support\Dinheiro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProgramaController extends Controller
{
    /**
     * A lista dos programas, com assinantes e mensalidades aguardando o Pix.
     */
    public function index(): Response
    {
        return Inertia::render('aulas-regulares/admin/index', [
            'programas' => Programa::query()->withCount([
                'assinaturas as ativas' => fn ($q) => $q->where('situacao', 'ativa'),
                'assinaturas as aguardando' => fn ($q) => $q->where('situacao', 'aguardando_pagamento'),
            ])->orderBy('titulo')->get()->map(fn (Programa $p) => [
                'id' => $p->id,
                'titulo' => $p->titulo,
                'modalidade' => Programa::MODALIDADES[$p->modalidade] ?? $p->modalidade,
                'valor_mensal_centavos' => $p->valor_mensal_centavos,
                'ativas' => (int) $p->getAttribute('ativas'),
                'aguardando' => (int) $p->getAttribute('aguardando'),
                'vagas' => $p->vagas,
                'ativo' => $p->ativo,
            ])->values()->all(),
        ]);
    }

    /**
     * Formulário de programa novo.
     */
    public function create(): Response
    {
        return Inertia::render('aulas-regulares/admin/formulario', ['programa' => null, ...$this->opcoes()]);
    }

    /**
     * Grava o programa novo.
     */
    public function store(Request $request): RedirectResponse
    {
        $programa = Programa::create($this->validar($request));
        Auditoria::registrar('PROGRAMA_CRIADO', ['programa_id' => $programa->id]);

        return to_route('comando-geral.programas.edit', $programa)->with('sucesso', 'Programa criado. Agora cadastre as turmas (dias e horários).');
    }

    /**
     * Formulário preenchido, com as turmas.
     */
    public function edit(Programa $programa): Response
    {
        return Inertia::render('aulas-regulares/admin/formulario', [
            'programa' => [
                ...$programa->only(['id', 'titulo', 'descricao', 'area_atuacao_id', 'modalidade', 'instrutor_id', 'instrutor_nome', 'valor_mensal_centavos', 'percentual_ibto', 'vagas', 'so_filiados', 'dia_vencimento', 'ativo']),
                'turmas' => $programa->turmas()->get()->map(fn (ProgramaTurma $t) => [
                    'id' => $t->id,
                    'nome' => $t->nome,
                    'dias_semana' => $t->dias_semana,
                    'hora_inicio' => substr($t->hora_inicio, 0, 5),
                    'hora_fim' => $t->hora_fim !== null ? substr($t->hora_fim, 0, 5) : null,
                    'local' => $t->local,
                    'link_sala' => $t->link_sala,
                    'ativa' => $t->ativa,
                    'horario' => $t->horarioPorExtenso(),
                ])->values()->all(),
            ],
            ...$this->opcoes(),
        ]);
    }

    /**
     * Grava as mudanças do programa.
     */
    public function update(Request $request, Programa $programa): RedirectResponse
    {
        $programa->update($this->validar($request));
        Auditoria::registrar('PROGRAMA_EDITADO', ['programa_id' => $programa->id]);

        return back()->with('sucesso', 'Programa salvo.');
    }

    /**
     * Cria uma turma no programa.
     */
    public function salvarTurma(Request $request, Programa $programa): RedirectResponse
    {
        $turma = new ProgramaTurma($this->validarTurma($request));
        $turma->forceFill(['programa_id' => $programa->id])->save();
        Auditoria::registrar('PROGRAMA_TURMA_CRIADA', ['programa_id' => $programa->id, 'turma_id' => $turma->id]);

        return back()->with('sucesso', 'Turma cadastrada.');
    }

    /**
     * Edita uma turma (inclusive desativar).
     */
    public function atualizarTurma(Request $request, ProgramaTurma $turma): RedirectResponse
    {
        $turma->update($this->validarTurma($request));

        return back()->with('sucesso', 'Turma salva.');
    }

    /**
     * Confere os campos do programa.
     *
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        // A mensalidade vem em reais, como a pessoa digita (ex.: "120,00"); vira centavos (12000).
        $request->merge(['valor_mensal_centavos' => (int) Dinheiro::paraCentavos((string) $request->input('valor', ''))]);

        return $request->validate([
            'titulo' => ['required', 'string', 'max:150'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'area_atuacao_id' => ['nullable', 'exists:areas_atuacao,id'],
            'modalidade' => ['required', Rule::in(array_keys(Programa::MODALIDADES))],
            'instrutor_id' => ['nullable', 'exists:users,id'],
            'instrutor_nome' => ['nullable', 'string', 'max:150'],
            // Valor em centavos (a tela converte "120,00" → 12000). Mínimo R$ 1,00.
            'valor_mensal_centavos' => ['required', 'integer', 'min:100', 'max:10000000'],
            'percentual_ibto' => ['required', 'integer', 'min:0', 'max:100'],
            'vagas' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'so_filiados' => ['boolean'],
            // 1 a 28: existe em todos os meses (fevereiro inclusive).
            'dia_vencimento' => ['required', 'integer', 'min:1', 'max:28'],
            'ativo' => ['boolean'],
        ], ['valor_mensal_centavos.min' => 'A mensalidade precisa ser de pelo menos R$ 1,00.', 'dia_vencimento.max' => 'Use um dia de 1 a 28 (existe em todos os meses).']);
    }

    /**
     * Confere os campos da turma. Link da sala: só https de Meet, Zoom, Teams ou YouTube
     * (a mesma lista fechada das aulas ao vivo dos cursos: um link qualquer poderia levar a um site falso).
     *
     * @return array<string, mixed>
     */
    private function validarTurma(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:100'],
            'dias_semana' => ['required', 'string', 'max:40', 'regex:/^(seg|ter|qua|qui|sex|sab|dom)(,(seg|ter|qua|qui|sex|sab|dom))*$/'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fim' => ['nullable', 'date_format:H:i', 'after:hora_inicio'],
            'local' => ['nullable', 'string', 'max:200'],
            'link_sala' => ['nullable', 'url:https', 'max:500', 'regex:#^https://([a-z0-9-]+\.)*(meet\.google\.com|zoom\.us|teams\.microsoft\.com|teams\.live\.com|youtube\.com|youtu\.be)(/|$)#i'],
            'ativa' => ['boolean'],
        ], ['dias_semana.regex' => 'Escolha pelo menos um dia da semana.', 'link_sala.regex' => 'O link da sala precisa ser do Meet, Zoom, Teams ou YouTube.']);
    }

    /**
     * As opções dos campos de escolha.
     *
     * @return array<string, mixed>
     */
    private function opcoes(): array
    {
        return [
            'areas' => AreaAtuacao::query()->where('ativa', true)->orderBy('nome')->get(['id', 'nome']),
            // Instrutores: quem tem o papel Instrutor (só o nome; nada de dado pessoal).
            'instrutores' => User::query()->whereHas('papeis', fn ($q) => $q->where('slug', 'instrutor'))->orderBy('name')->get(['id', 'name']),
            'modalidades' => collect(Programa::MODALIDADES)->map(fn (string $rotulo, string $valor) => ['valor' => $valor, 'rotulo' => $rotulo])->values(),
        ];
    }
}
