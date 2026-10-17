<?php

/*
 * RELATÓRIO: AUDITORIA (#73). Quem fez o quê e quando, no período (as 1.000
 * ações mais recentes). Só a Tecnologia. O nome sai abreviado (LGPD: o primeiro
 * nome e as iniciais) e os detalhes já são só códigos e números.
 */

namespace App\Relatorios;

use App\Models\Auditoria;
use App\Models\User;
use App\Support\Periodo;

class AuditoriaRelatorio extends Relatorio
{
    // No máximo quantas linhas (as mais recentes do período).
    private const LIMITE = 1000;

    public function chave(): string
    {
        return 'auditoria';
    }

    public function titulo(): string
    {
        return 'Auditoria: quem fez o quê';
    }

    public function descricao(): string
    {
        return 'As ações registradas no período (as 1.000 mais recentes), com quem fez e os detalhes em códigos.';
    }

    public function permissao(): string
    {
        return 'relatorio.auditoria';
    }

    public function colunas(): array
    {
        return [
            ['chave' => 'quando', 'rotulo' => 'Quando', 'tipo' => 'texto'],
            ['chave' => 'quem', 'rotulo' => 'Quem', 'tipo' => 'texto'],
            ['chave' => 'acao', 'rotulo' => 'Ação', 'tipo' => 'texto'],
            ['chave' => 'detalhes', 'rotulo' => 'Detalhes', 'tipo' => 'texto'],
        ];
    }

    public function linhas(Periodo $periodo, User $usuario): array
    {
        return array_values(Auditoria::query()
            ->with('usuario:id,name')
            ->whereBetween('created_at', [$periodo->inicio, $periodo->fim])
            ->orderByDesc('id')->limit(self::LIMITE)->get()
            ->map(fn (Auditoria $linha) => [
                'quando' => $linha->created_at?->format('d/m/Y H:i:s'),
                // Sem usuário (ex.: o Cron): "sistema".
                'quem' => $linha->usuario !== null ? self::abreviado($linha->usuario->name).' (nº '.$linha->usuario->id.')' : 'sistema',
                'acao' => $linha->acao,
                'detalhes' => json_encode($linha->detalhes, JSON_UNESCAPED_UNICODE) ?: '',
            ])->all());
    }

    /**
     * O primeiro nome inteiro e as iniciais dos outros. Ex.: 'Gleyson Atanazio da Silva' → 'Gleyson A. S.'
     */
    public static function abreviado(string $nome): string
    {
        $partes = preg_split('/\s+/', trim($nome)) ?: [];
        $primeiro = array_shift($partes) ?? '';
        // Ignora "da", "de", "do", "dos", "das" nas iniciais.
        $iniciais = array_map(fn (string $parte) => mb_strtoupper(mb_substr($parte, 0, 1)).'.', array_filter($partes, fn (string $parte) => ! in_array(mb_strtolower($parte), ['da', 'de', 'do', 'dos', 'das', 'e'], true)));

        return trim($primeiro.' '.implode(' ', $iniciais));
    }
}
