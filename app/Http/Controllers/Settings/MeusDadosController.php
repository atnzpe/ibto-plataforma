<?php

/*
 * MEUS DADOS (LGPD, #47): em Configurações → Meus dados, a pessoa vê o que a
 * plataforma guarda sobre ela (com os dados sensíveis mascarados na tela), a
 * autorização de imagem e QUEM ABRIU a ficha dela com CPF, nascimento e
 * telefone, e quando. É o direito de acesso e de transparência do titular
 * (LGPD, art. 18). Pedidos de correção ou exclusão vão para o suporte.
 * Como o extrato do banco: você vê cada movimentação na sua conta.
 */

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Relatorios\AuditoriaRelatorio;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MeusDadosController extends Controller
{
    /** Código estável da auditoria: alguém abriu a ficha com os dados sensíveis. */
    public const ACESSO_DADOS_SENSIVEIS = 'DADOS_SENSIVEIS_ACESSADOS';

    /**
     * A tela "Meus dados".
     *
     * @param  Request  $request  O pedido da própria pessoa (logada).
     * @return Response A tela "settings/meus-dados".
     */
    public function show(Request $request): Response
    {
        $usuario = $request->user();
        $filiado = $usuario->filiado?->load(['equipe:id,nome', 'nivel']);

        return Inertia::render('settings/meus-dados', [
            'conta' => ['nome' => $usuario->name, 'email' => $usuario->email, 'criada_em' => $usuario->created_at?->format('d/m/Y')],
            // Quem não é filiado (ex.: só aluno de curso aberto) não tem ficha: null.
            'filiado' => $filiado === null ? null : [
                'registro' => $filiado->registro,
                'equipe' => $filiado->equipe?->nome,
                'categoria' => $filiado->categoria?->rotulo(),
                'nivel' => $filiado->nivel?->nome(),
                'cidade' => $filiado->local(),
                // Mascarados na tela (alguém pode estar olhando por cima do ombro): só o final.
                'cpf' => $this->mascarar($filiado->cpf, 2),
                'telefone' => $this->mascarar($filiado->telefone, 4),
                'nascimento' => $filiado->nascimento !== null ? 'cadastrado' : null,
                'autoriza_imagem' => (bool) $filiado->autoriza_imagem_rede,
                'autoriza_imagem_em' => $filiado->autoriza_imagem_em?->format('d/m/Y H:i'),
            ],
            // Os últimos 50 acessos à ficha com os dados sensíveis: quando e quem (nome abreviado).
            'acessos' => $filiado === null ? [] : Auditoria::query()
                ->with('usuario:id,name')
                ->where('acao', self::ACESSO_DADOS_SENSIVEIS)
                ->where('detalhes->filiado_id', $filiado->id)
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn (Auditoria $linha) => [
                    'quando' => $linha->created_at?->format('d/m/Y H:i'),
                    // Conta apagada: "conta removida".
                    'quem' => $linha->usuario !== null ? AuditoriaRelatorio::abreviado($linha->usuario->name) : 'conta removida',
                ])
                ->all(),
            'suporte_email' => 'suporte@ib-to.org',
        ]);
    }

    /**
     * Mostra só o final de um dado e esconde o resto com "•".
     *
     * @param  string|null  $valor  Ex.: '52998224725'.
     * @param  int  $final  Quantos caracteres do fim aparecem. Ex.: 2.
     * @return string|null Ex.: '•••••••••25'; null se não houver o dado.
     */
    private function mascarar(?string $valor, int $final): ?string
    {
        // Sem o dado: null (a tela mostra "não informado").
        if ($valor === null || $valor === '') {
            return null;
        }
        $digitos = (string) preg_replace('/\D/', '', $valor);

        return str_repeat('•', max(0, strlen($digitos) - $final)).substr($digitos, -$final);
    }
}
