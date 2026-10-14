<?php

/*
 * "MATRICULAR PESSOA" no Comando Geral (decisão do Gleyson, 14/10/2026): o
 * IBTO matricula alguém num curso. É o ÚNICO jeito de entrar num curso
 * FECHADO (ex.: Formação de instrutor) e também serve para quem pagou fora do
 * site (ex.: em dinheiro, no balcão do evento).
 *
 * A pessoa é encontrada pelo REGISTRO do filiado (o mesmo número que a lista
 * de inscritos mostra, por causa da LGPD) ou pelo E-MAIL da conta.
 * As regras da matrícula (vagas, só filiados, data...) são as mesmas da
 * inscrição pelo site: ficam em App\Actions\Cursos\Matriculas.
 * Quem pode: só o IBTO (permissão "cursos.gerenciar").
 */

namespace App\Http\Controllers\Cursos;

use App\Actions\Cursos\Matriculas;
use App\Exceptions\RegraCurso;
use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Models\Filiado;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MatricularPeloIbtoController extends Controller
{
    /**
     * Matricula a pessoa informada no curso.
     *
     * @param  Request  $request  Com "pessoa": o registro ou o e-mail. Ex.: 'BR20260007PE' ou 'aluno@exemplo.com'
     * @param  Curso  $curso  O curso do endereço. Ex.: /comando-geral/cursos/7/matricular
     * @param  Matriculas  $matriculas  As regras da matrícula.
     * @return RedirectResponse Volta para os detalhes do curso, com a mensagem ou o erro (com código).
     */
    public function __invoke(Request $request, Curso $curso, Matriculas $matriculas): RedirectResponse
    {
        // Só o IBTO. Sem a permissão: 403 (acesso negado).
        Gate::authorize('cursos.gerenciar');

        // O campo é obrigatório e curto (um registro ou um e-mail cabem em 150 letras).
        $dados = $request->validate(
            ['pessoa' => ['required', 'string', 'max:150']],
            ['pessoa.required' => 'Informe o registro do filiado ou o e-mail da conta.'],
        );
        // Tira os espaços das pontas (como a pessoa digitar).
        $pessoa = trim($dados['pessoa']);

        // Tem "@"? É e-mail: procura a conta. Senão, é registro: procura o
        // filiado e pega a conta dele (filiado sem conta = não encontrado).
        $usuario = str_contains($pessoa, '@')
            ? User::where('email', mb_strtolower($pessoa))->first()
            : Filiado::where('registro', mb_strtoupper($pessoa))->first()?->usuario;

        // Ninguém com esse registro/e-mail (ou o filiado ainda não tem conta): avisa e não grava nada.
        if (! $usuario instanceof User) {
            return back()->withErrors(['matricular' => 'Nenhuma conta com esse registro ou e-mail. Confira o número; filiado sem conta precisa do convite primeiro. (PESSOA_NAO_ENCONTRADA)']);
        }

        try {
            // peloIbto: true = aceita também curso FECHADO.
            $matricula = $matriculas->matricular($curso, $usuario, peloIbto: true);
        } catch (RegraCurso $erro) {
            // A regra recusou (ex.: vagas esgotadas, já matriculado): mostra o motivo com o código.
            return back()->withErrors(['matricular' => "{$erro->getMessage()} (código {$erro->codigo})"]);
        }

        // Mensagem sem nome (LGPD): o nº da matrícula e a situação. Ex.: "Matrícula nº 31 feita: Aguardando pagamento."
        return back()->with('sucesso', "Matrícula nº {$matricula->id} feita: {$matricula->situacao->rotulo()}.");
    }
}
