<?php

/*
 * CARTEIRINHA DO FILIADO COM VALIDAÇÃO PÚBLICA (#46).
 *
 * - Validação pública (/carteirinha/{código}): quem lê o QR code vê só o
 *   necessário: registro, situação e validade, categoria, nível, equipe e o nome
 *   ABREVIADO. Foto, nome completo e cidade só se o filiado autorizou o uso da
 *   imagem (#78). Código inexistente não revela nada. Limite de consultas por IP.
 * - Minha carteirinha (/minha-carteirinha): o cartão da própria pessoa para
 *   imprimir, com a foto 3x4, o QR code e a validade.
 * Como a carteirinha de sócio de um clube: o porteiro confere, mas não vê o
 * cadastro inteiro.
 */

namespace App\Http\Controllers;

use App\Models\Filiado;
use App\Relatorios\AuditoriaRelatorio;
use App\Support\FotoDePerfil;
use App\Support\QrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CarteirinhaController extends Controller
{
    /**
     * A página pública de validação (aberta pelo QR code).
     *
     * @param  string  $codigo  O código da carteirinha. Ex.: 'IBTO-7K2M-9QXA' (aceita minúsculas e espaços).
     */
    public function validar(string $codigo): Response
    {
        $codigo = strtoupper(trim($codigo));
        $filiado = Filiado::query()->with(['usuario:id,name', 'nivel', 'equipe:id,nome'])->where('codigo_validacao', $codigo)->first();

        return Inertia::render('carteirinha/validar', [
            'codigo' => $codigo,
            // null = não encontrado (não revela se o código já existiu).
            'carteirinha' => $filiado === null ? null : $this->dadosPublicos($filiado),
            // noindex (#74): a validação tem o nome abreviado do filiado e nunca deve ir para o Google.
        ])->withViewData(['noindex' => true]);
    }

    /**
     * A foto da validação pública: só se o filiado autorizou o uso da imagem; senão, 404.
     */
    public function foto(string $codigo): StreamedResponse
    {
        $filiado = Filiado::query()->where('codigo_validacao', strtoupper(trim($codigo)))->first();
        $disco = Storage::disk(FotoDePerfil::DISCO);

        abort_unless($filiado !== null && $filiado->autoriza_imagem_rede && $filiado->foto !== null && $disco->exists($filiado->foto), 404);

        return $disco->response($filiado->foto, null, ['Cache-Control' => 'public, max-age=3600']);
    }

    /**
     * A carteirinha da própria pessoa, para imprimir.
     */
    public function minha(Request $request): Response
    {
        $filiado = $request->user()->filiado;
        // Quem não é filiado não tem carteirinha.
        abort_if($filiado === null, 404);
        $filiado->load(['nivel', 'equipe:id,nome']);
        $enderecoValidacao = route('carteirinha.validar', $filiado->codigo_validacao);

        return Inertia::render('carteirinha/minha', [
            'carteirinha' => [
                ...$this->dadosPublicos($filiado),
                // Na carteirinha da própria pessoa: o nome completo e a foto 3x4 (dela mesma).
                'nome' => $request->user()->name,
                'foto_url' => $filiado->fotoUrl(),
                'codigo' => $filiado->codigo_validacao,
                'endereco_validacao' => $enderecoValidacao,
                'qr_svg' => QrCode::svg($enderecoValidacao, 140),
            ],
        ]);
    }

    /**
     * O que a validação pública mostra (nunca CPF, nascimento, endereço ou telefone).
     *
     * @return array<string, mixed>
     */
    private function dadosPublicos(Filiado $filiado): array
    {
        $situacao = $filiado->situacaoCarteirinha();
        $autorizou = $filiado->autoriza_imagem_rede;
        $nome = (string) $filiado->usuario?->name;

        return [
            'registro' => $filiado->registro,
            'situacao' => $situacao['situacao'],
            'situacao_rotulo' => $situacao['rotulo'],
            'validade' => $situacao['validade'],
            'categoria' => $filiado->categoria?->rotulo(),
            'nivel' => $filiado->nivel?->nome(),
            'equipe' => $filiado->equipe?->nome,
            // Nome abreviado sempre; completo e cidade só com a autorização de imagem (#78).
            'nome_abreviado' => AuditoriaRelatorio::abreviado($nome),
            'nome_completo' => $autorizou ? $nome : null,
            'cidade' => $autorizou ? trim($filiado->municipio.'-'.$filiado->uf, '-') : null,
            'foto_publica_url' => $autorizou && $filiado->foto !== null ? route('carteirinha.foto', $filiado->codigo_validacao) : null,
        ];
    }
}
