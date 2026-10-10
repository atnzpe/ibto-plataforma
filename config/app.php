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
    | PIX do IBTO (#41, matrícula com pagamento manual)
    |--------------------------------------------------------------------------
    |
    | A chave PIX e o nome do favorecido que aparecem para quem está com a
    | matrícula "aguardando pagamento". Ficam no .env (e não no código) para
    | trocar sem nova publicação. Chave vazia: a tela pede para falar com o
    | IBTO pelo WhatsApp do curso.
    |
    */

    'pix' => [
        // Ex.: 'financeiro@ib-to.org' ou o CNPJ.
        'chave' => env('IBTO_PIX_CHAVE', ''),
        // Quem recebe (o nome que aparece no app do banco).
        'favorecido' => env('IBTO_PIX_FAVORECIDO', 'Instituto Brasileiro de Treinamento Operacional'),
    ],

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
