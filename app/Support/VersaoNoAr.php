<?php

/*
 * VERSÃO NO AR — FRONT, BACK E BANCO (#75, pedido do Gleyson: "no log mostre
 * build, versão e release do front, do back e do banco").
 *
 * - Back: a versão do arquivo VERSION e o commit do deploy.
 * - Front: a data e hora do build (o arquivo public/build/manifest.json nasce no build).
 * - Banco: a versão do servidor de banco e a última migration aplicada.
 * Vai para a rota /api/saude e para o console do navegador (F12) em toda tela.
 * Os dados do banco ficam guardados por 10 minutos (não consulta a cada clique);
 * o pós-deploy limpa esse guardado quando aplica uma versão nova.
 */

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class VersaoNoAr
{
    /**
     * Tudo junto, para a tela e para a rota de saúde.
     *
     * @return array{numero: string, commit: string|null, front_build: string|null, banco: array{versao: string|null, migracao: string|null}}
     */
    public static function tudo(): array
    {
        return [
            'numero' => (string) config('app.versao'),
            'commit' => config('app.commit'),
            'front_build' => self::frontBuild(),
            'banco' => self::banco(),
        ];
    }

    /**
     * Data e hora do build do front (quando o manifest do Vite foi gerado), ou null sem build.
     *
     * @return string|null Ex.: '17/10/2026 03:12'.
     */
    public static function frontBuild(): ?string
    {
        $manifest = public_path('build/manifest.json');

        return is_file($manifest) ? date('d/m/Y H:i', (int) filemtime($manifest)) : null;
    }

    /**
     * Versão do servidor de banco e a última migration aplicada (guardadas por 10 minutos).
     *
     * @return array{versao: string|null, migracao: string|null} Ex.: ['versao' => '5.7.44-48', 'migracao' => '2026_10_17_000006_create_permissoes_relatorios'].
     */
    public static function banco(): array
    {
        return Cache::remember('ibto_versao_banco', 600, function (): array {
            try {
                // SQLite (computador do desenvolvedor e testes) tem outra função para a versão.
                $versao = DB::connection()->getDriverName() === 'sqlite'
                    ? DB::selectOne('select sqlite_version() as v')?->v
                    : DB::selectOne('select version() as v')?->v;

                return [
                    'versao' => $versao !== null ? (string) $versao : null,
                    'migracao' => DB::table('migrations')->orderByDesc('id')->value('migration'),
                ];
            } catch (Throwable) {
                // Banco fora do ar: sem os dados (a rota de saúde já avisa o erro).
                return ['versao' => null, 'migracao' => null];
            }
        });
    }
}
