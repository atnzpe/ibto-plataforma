<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Indexação por buscadores (IBTO)
    |--------------------------------------------------------------------------
    |
    | Desligada por padrão: enquanto for false, todas as páginas saem com
    | <meta name="robots" content="noindex, nofollow">. Só ligar em produção,
    | quando a plataforma for lançada.
    |
    */

    'indexavel' => (bool) env('APP_INDEXAVEL', false),

    /*
    |--------------------------------------------------------------------------
    | Administradores provisórios (IBTO, #37)
    |--------------------------------------------------------------------------
    |
    | Até a #36 (papéis de usuário), quem abre os cadastros (áreas, níveis,
    | equipes) são os e-mails desta lista, separados por vírgula no .env.
    | Ex.: ADMIN_EMAILS=gleyson@exemplo.com,eduardo@exemplo.com
    | Vazio = ninguém é administrador (o padrão mais seguro).
    | A regra completa está no Gate "administrar" (AppServiceProvider).
    |
    */

    'admin_emails' => array_values(array_filter(array_map(
        // Para cada e-mail: tira espaços e passa para minúsculas
        // (ex.: " Gleyson@Exemplo.com" vira "gleyson@exemplo.com").
        fn (string $email): string => strtolower(trim($email)),
        // Quebra o texto do .env em pedaços, um por vírgula.
        explode(',', (string) env('ADMIN_EMAILS', '')),
    ))), // array_filter tira os pedaços vazios (ex.: vírgula sobrando no fim).

    /*
    | SUPORTE TÉCNICO pelo WhatsApp (pedido do Gleyson, 15/10/2026): o número
    | que o botão "Suporte técnico" abre, com o código do país e o DDD, só
    | números (os outros sinais são tirados aqui). Ex.: SUPORTE_WHATSAPP=5581999990000
    | Fica no .env (dado pessoal fora do código). Vazio = o botão não aparece.
    */

    'suporte_whatsapp' => preg_replace('/\D/', '', (string) env('SUPORTE_WHATSAPP', '')),

    /*
    | ENDEREÇO DO SITE PÚBLICO do mesmo ambiente (#61, bloco B4): o blog e os
    | eventos passaram a ser mostrados no site; as páginas públicas da
    | plataforma (/blog, /eventos) levam a pessoa para lá.
    | Ex.: SITE_URL=https://homolog.ib-to.org
    | Sem SITE_URL no .env, o endereço é DEDUZIDO do APP_URL, para não depender
    | de configuração manual: cursos-homolog.ib-to.org → homolog.ib-to.org;
    | cursos.ib-to.org → ib-to.org. Qualquer outro (ex.: o computador do
    | desenvolvedor) fica vazio, e aí as páginas da plataforma continuam abrindo
    | normalmente (plano B: nada some se o site estiver fora do ar).
    */

    'site_url' => (static function (): string {
        // Endereço escrito no .env: vale ele (sem a barra do fim).
        $doEnv = rtrim((string) env('SITE_URL', ''), '/');
        if ($doEnv !== '') {
            return $doEnv;
        }

        // O "host" da plataforma. Ex.: 'cursos-homolog.ib-to.org'.
        $host = (string) parse_url((string) env('APP_URL', ''), PHP_URL_HOST);

        // Plataforma de testes ou oficial: tira o "cursos-" ou o "cursos." da frente.
        // Ex.: 'cursos-homolog.ib-to.org' → 'https://homolog.ib-to.org'.
        if (preg_match('/^cursos[.-](.+\.ib-to\.org)$/', $host, $partes) === 1) {
            return 'https://'.$partes[1];
        }
        if ($host === 'cursos.ib-to.org') {
            return 'https://ib-to.org';
        }

        // Outro endereço (ex.: localhost): sem site, sem redirecionar.
        return '';
    })(),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'America/Sao_Paulo',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', '')),
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache", "array"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
