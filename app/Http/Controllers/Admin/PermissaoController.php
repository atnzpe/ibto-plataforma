<?php

/*
 * Tela de PAPÉIS × PERMISSÕES (#36), no molde do Painel da CBSA: uma grade
 * com os papéis nas colunas e as permissões nas linhas. Só quem tem
 * "permissoes.gerenciar" entra (a Tecnologia e a chave de emergência).
 *
 * Cada caixa que muda vira uma linha na auditoria. Trava de segurança: a
 * Tecnologia nunca perde "permissoes.gerenciar" (senão ninguém mais abriria
 * esta tela).
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Papel;
use App\Models\Permissao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PermissaoController extends Controller
{
    /**
     * A grade: papéis, permissões (em ordem) e quais caixas estão marcadas.
     *
     * @return Response A tela "admin/permissoes".
     */
    public function edit(): Response
    {
        $papeis = Papel::query()->with('permissoes:id')->orderBy('id')->get();

        return Inertia::render('admin/permissoes', [
            'papeis' => $papeis->map(fn (Papel $papel) => [
                'id' => $papel->id,
                'slug' => $papel->slug,
                'nome' => $papel->nome,
                // Números das permissões marcadas para este papel.
                'permissoes' => $papel->permissoes->pluck('id'),
            ]),
            'permissoes' => Permissao::query()->orderBy('ordem')->get(['id', 'slug', 'nome', 'grupo']),
            // Caixas que a tela mostra travadas (sempre marcadas, sem como desmarcar).
            'travadas' => [$this->caixaTravada()],
        ]);
    }

    /**
     * Grava a grade: compara com o que estava, aplica as mudanças e registra
     * cada uma na auditoria.
     *
     * @param  Request  $request  O formulário. Ex.: vinculos[2][] = 1, vinculos[2][] = 3
     * @return RedirectResponse Volta para a grade com a mensagem de sucesso.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'vinculos' => ['nullable', 'array'],
            'vinculos.*' => ['array'],
            'vinculos.*.*' => ['integer', 'exists:permissoes,id'],
        ]);

        // vinculos = { id do papel: [ids das permissões marcadas] }; papel sem nada marcado não vem.
        /** @var array<int|string, array<int, int|string>> $vinculos */
        $vinculos = $request->input('vinculos', []);
        $mudancas = 0;

        DB::transaction(function () use ($vinculos, &$mudancas) {
            $permissoes = Permissao::all()->keyBy('id');

            foreach (Papel::with('permissoes:id')->get() as $papel) {
                // O que veio marcado para este papel, só números.
                $marcadas = array_map('intval', $vinculos[$papel->id] ?? []);

                // A trava: a Tecnologia continua com permissoes.gerenciar, venha o que vier.
                $travada = $this->caixaTravada();
                if ($papel->slug === $travada['papel']) {
                    $marcadas[] = (int) $permissoes->firstWhere('slug', $travada['permissao'])?->id;
                }

                $antes = $papel->permissoes->pluck('id')->all();
                $depois = array_values(array_unique(array_filter($marcadas)));

                // O que entrou e o que saiu, para a auditoria.
                foreach (array_diff($depois, $antes) as $id) {
                    Auditoria::registrar('PERMISSAO_CONCEDIDA', ['papel' => $papel->slug, 'permissao' => $permissoes[$id]->slug]);
                    $mudancas++;
                }
                foreach (array_diff($antes, $depois) as $id) {
                    Auditoria::registrar('PERMISSAO_REMOVIDA', ['papel' => $papel->slug, 'permissao' => $permissoes[$id]->slug]);
                    $mudancas++;
                }

                // sync: deixa o papel exatamente com as permissões marcadas.
                $papel->permissoes()->sync($depois);
            }
        });

        return to_route('admin.permissoes.edit')
            ->with('sucesso', $mudancas === 0 ? 'Nada mudou.' : "Permissões salvas ({$mudancas} mudança(s)).");
    }

    /**
     * A caixa que nunca pode ser desmarcada: sem ela, ninguém mais abriria esta tela.
     *
     * @return array{papel: string, permissao: string}
     */
    private function caixaTravada(): array
    {
        // Papel do Time de Tecnologia × permissão que abre ESTA tela (slugs da
        // migration 2026_10_08_000001: o texto à esquerda do "=>").
        return ['papel' => 'tecnologia', 'permissao' => 'permissoes.gerenciar'];
    }
}
