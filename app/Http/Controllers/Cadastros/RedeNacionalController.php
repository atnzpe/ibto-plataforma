<?php

/*
 * TELA "REDE NACIONAL" do Comando Geral (#11): a "lista de chamada" das fotos
 * que o Mestre Eduardo pediu. Mostra TODOS os monitores e instrutores e, para
 * cada um, o que já cumpre para aparecer na página Rede Nacional do site
 * (ativo, anuidade, autorização, foto e UF) e se já aparece.
 * Só quem cuida de todos os filiados (permissão "filiados.gerenciar").
 */

namespace App\Http\Controllers\Cadastros;

use App\Actions\Filiados\RedeNacional;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RedeNacionalController extends Controller
{
    /**
     * O quadro, com os filtros de estado e "só pendentes".
     *
     * @param  Request  $request  Pode trazer ?uf=PE e ?pendentes=1.
     * @param  RedeNacional  $rede  A regra de quem aparece.
     * @return Response A tela cadastros/rede-nacional.
     */
    public function index(Request $request, RedeNacional $rede): Response
    {
        // Estado do filtro: só aceita uma das 27 siglas; qualquer outra coisa = todos.
        $uf = strtoupper($request->string('uf')->toString());
        $uf = array_key_exists($uf, RedeNacional::ESTADOS) ? $uf : null;
        // "Só pendentes" ligado ou não.
        $pendentes = $request->boolean('pendentes');

        $linhas = $rede->acompanhamento($uf, $pendentes);
        // Os números do topo valem para TODOS do filtro de estado (sem o "só pendentes").
        $todos = $pendentes ? $rede->acompanhamento($uf) : $linhas;

        return Inertia::render('cadastros/rede-nacional', [
            'linhas' => $linhas,
            'resumo' => [
                'total' => count($todos),
                'aparecem' => count(array_filter($todos, fn (array $linha) => $linha['aparece'])),
            ],
            'filtros' => ['uf' => $uf, 'pendentes' => $pendentes],
            'estados' => RedeNacional::ESTADOS,
            // O endereço da página pública do site (para conferir), do mesmo ambiente.
            'pagina_publica' => rtrim((string) config('app.site_url', 'https://homolog.ib-to.org'), '/').'/rede-nacional',
        ]);
    }
}
