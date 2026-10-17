<?php

/*
 * PÁGINA AJUDA (#68, parte 2): o manual de instruções de cada perfil.
 *
 * O conteúdo fica nas telas (resources/js/lib/manuais.ts); aqui o servidor
 * decide QUAIS manuais a pessoa vê:
 * - todo mundo com login: o do aluno e filiado;
 * - instrutor: + o do instrutor;
 * - admin de equipe: + o do admin de equipe;
 * - Administrador e Tecnologia: TODOS (transparência: quem cuida da
 *   plataforma precisa saber como cada perfil trabalha e por quê).
 */

namespace App\Http\Controllers;

use App\Models\Papel;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AjudaController extends Controller
{
    /**
     * A página Ajuda.
     *
     * @param  Request  $request  Quem está logado.
     * @return Response A tela "ajuda/index", com a lista dos manuais liberados.
     */
    public function __invoke(Request $request): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        // Os papéis da pessoa, pelo nome técnico. Ex.: ['aluno', 'instrutor'].
        $papeis = $usuario->papeis->map(fn (Papel $papel): string => $papel->slug)->values()->all();

        return Inertia::render('ajuda/index', [
            'manuais' => self::manuaisDosPapeis($papeis),
        ]);
    }

    /**
     * Os manuais liberados para uma lista de papéis, na ordem da página.
     *
     * @param  array<int, string>  $papeis  Ex.: ['aluno', 'admin_equipe'] (só importa QUAIS papéis estão, não a ordem).
     * @return list<string> Ex.: ['aluno', 'admin-equipe']
     */
    public static function manuaisDosPapeis(array $papeis): array
    {
        // Administrador e Tecnologia veem todos os manuais.
        if (array_intersect($papeis, ['administrador', 'tecnologia']) !== []) {
            return ['aluno', 'instrutor', 'admin-equipe', 'fotografo', 'comando-geral', 'tecnologia'];
        }

        // Os outros: o do aluno sempre, mais o de cada papel que a pessoa tem.
        $manuais = ['aluno'];
        if (in_array('instrutor', $papeis, true)) {
            $manuais[] = 'instrutor';
        }
        if (in_array('admin_equipe', $papeis, true)) {
            $manuais[] = 'admin-equipe';
        }
        // Fotógrafo credenciado (#90): o manual de enviar as fotos e do financeiro dele.
        if (in_array('fotografo', $papeis, true)) {
            $manuais[] = 'fotografo';
        }

        return $manuais;
    }
}
