<?php

/*
 * QUEM VÊ AS FOTOS DOS EVENTOS (decisão do Gleyson, 16/10/2026).
 *
 * Muitos alunos e instrutores do IBTO são operadores de segurança (policiais,
 * militares): foto com rosto e distintivo na internet pode pôr essas pessoas em
 * risco. Por isso, no modo PRIVADO (o padrão), as fotos dos eventos só abrem
 * para quem tem registro de FILIADO e para os cargos acima (instrutor, admin de
 * equipe, Comando Geral, Tecnologia), sempre com login. O site público mostra só
 * o nome, a data e o local do evento.
 *
 * A diretoria pode decidir pelo modo público: GALERIA_PUBLICA=true no .env
 * (aí qualquer pessoa vê, como no #61). O blog continua público nos dois modos.
 */

namespace App\Support;

use App\Models\User;

class Galeria
{
    /**
     * As fotos dos eventos estão no modo público? (false = privado, o padrão)
     */
    public static function publica(): bool
    {
        return (bool) config('app.galeria_publica');
    }

    /**
     * Esta pessoa pode ver as fotos dos eventos?
     *
     * @param  User|null  $usuario  Quem está vendo (null = visitante sem login).
     * @return bool true no modo público; no privado, só filiado ou cargo acima.
     */
    public static function podeVer(?User $usuario): bool
    {
        // Modo público: todo mundo vê.
        if (self::publica()) {
            return true;
        }

        // Visitante sem login: não vê no modo privado.
        if ($usuario === null) {
            return false;
        }

        // Filiado (tem registro no IBTO) ou algum cargo acima do aluno: vê.
        return $usuario->filiado !== null
            || $usuario->temPermissao('cursos.propor')      // instrutor
            || $usuario->temPermissao('filiados.equipe')    // admin de equipe
            || $usuario->temPermissao('usuarios.gerenciar') // Comando Geral e Tecnologia
            || $usuario->temPermissao('blog.publicar');      // quem cuida do blog e da galeria
    }
}
