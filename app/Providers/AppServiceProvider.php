<?php

namespace App\Providers;

use App\Enums\SituacaoCurso;
use App\Enums\SituacaoMatricula;
use App\Models\Curso;
use App\Models\Filiado;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Só no computador de desenvolvimento (Windows), 08/10/2026: o "php artisan
        // serve" inicia o servidor SEM repassar TEMP e TMP (onde fica a pasta
        // temporária). Sem elas, o PHP não consegue guardar os arquivos enviados
        // (planilha, imagens, logos) e todo upload falha com "unable to create a
        // temporary file". Aqui a lista do que é repassado ganha as duas.
        // No servidor (HostGator) o serve não é usado: isto não muda nada lá.
        ServeCommand::$passthroughVariables = array_values(array_unique([
            ...ServeCommand::$passthroughVariables,
            'TEMP',
            'TMP',
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurarPermissoes();
    }

    /**
     * Regras de permissão ("Gates": portões que dizem quem pode passar).
     *
     * Gate::before roda ANTES de qualquer conferência (#36): é o "porteiro geral".
     * - Chave de emergência (e-mail do ADMIN_EMAILS, confirmado): passa sempre.
     * - Quem tem a permissão pedida por algum dos seus papéis: passa.
     * - Senão, devolve null: "não decido aqui", e o Laravel segue para as
     *   outras regras (se nenhuma liberar, a resposta é 403 Proibido).
     * Uso numa rota: ->middleware('can:cadastros.equipes').
     */
    protected function configurarPermissoes(): void
    {
        Gate::before(function (User $usuario, string $habilidade): ?bool {
            // Chave de emergência: ninguém fica trancado do lado de fora.
            if ($usuario->ehChaveDeEmergencia()) {
                return true;
            }

            // Tem a permissão: libera (true). Não tem: "não decido aqui" (null),
            // para o Laravel continuar procurando (como o porteiro que liga para o apartamento).
            return $usuario->temPermissao($habilidade) ? true : null;
        });

        // "filiados.acessar" (#37, F3b): abrir o cadastro de filiados. Passa quem
        // cuida de TODOS (filiados.gerenciar) ou só da PRÓPRIA equipe (filiados.equipe).
        Gate::define('filiados.acessar', fn (User $usuario): bool => $usuario->temPermissao('filiados.gerenciar')
            || $usuario->temPermissao('filiados.equipe'));

        // "filiados.editar" (#37, F3b): mexer num filiado específico.
        Gate::define('filiados.editar', function (User $usuario, Filiado $filiado): bool {
            // Quem cuida de todos os filiados pode mexer em qualquer um.
            if ($usuario->temPermissao('filiados.gerenciar')) {
                return true;
            }

            // Sem a permissão da própria equipe: não pode.
            if (! $usuario->temPermissao('filiados.equipe')) {
                return false;
            }

            // Equipe de quem está logado (vem do registro de filiado dele); sem equipe, não pode.
            $minhaEquipe = $usuario->filiado?->equipe_id;
            if ($minhaEquipe === null) {
                return false;
            }

            // Pode mexer só se o filiado é da MESMA equipe (como o gerente da
            // unidade que só abre a ficha dos alunos da própria unidade).
            return $filiado->equipe_id === $minhaEquipe;
        });

        // "cursos.acessar" (#40): abrir a lista de cursos. Passa quem cuida de TODOS
        // (cursos.gerenciar) ou quem PROPÕE os próprios (cursos.propor: instrutor,
        // admin de equipe — configurável na tela de Permissões).
        Gate::define('cursos.acessar', fn (User $usuario): bool => $usuario->temPermissao('cursos.gerenciar')
            || $usuario->temPermissao('cursos.propor'));

        // "curso.editar" (#40): mexer nos dados e no conteúdo de um curso.
        Gate::define('curso.editar', function (User $usuario, Curso $curso): bool {
            // O Comando Geral mexe em qualquer curso.
            if ($usuario->temPermissao('cursos.gerenciar')) {
                return true;
            }

            // Quem propôs mexe só no DELE, e só enquanto não foi para a chancela
            // (rascunho ou rejeitado): o que o IBTO chancelou não muda sem nova análise.
            return $usuario->temPermissao('cursos.propor')
                && $curso->responsavel_id === $usuario->id
                && in_array($curso->situacao, [SituacaoCurso::Rascunho, SituacaoCurso::Rejeitado], true);
        });

        // "curso.inscritos" (#41, detalhes do curso): ver o resumo e a lista dos inscritos
        // (só REGISTRO e equipe, sem nome: LGPD). Passam o Comando Geral, quem chancela,
        // quem confirma pagamentos e o DONO do curso (o instrutor faz a chamada, #44).
        Gate::define('curso.inscritos', fn (User $usuario, Curso $curso): bool => $usuario->temPermissao('cursos.gerenciar')
            || $usuario->temPermissao('cursos.chancelar')
            || $usuario->temPermissao('pagamentos.confirmar')
            || ($usuario->temPermissao('cursos.propor') && $curso->responsavel_id === $usuario->id));

        // "curso.chamada" (#44, desenho de 10/10/2026: "o admin de equipe ou o instrutor marca
        // quem veio"): fazer a chamada de um encontro. Passam o Comando Geral, o DONO do
        // curso e o administrador da EQUIPE DONA do curso (só da própria equipe).
        Gate::define('curso.chamada', fn (User $usuario, Curso $curso): bool => $usuario->temPermissao('cursos.gerenciar')
            || ($usuario->temPermissao('cursos.propor') && $curso->responsavel_id === $usuario->id)
            || ($usuario->temPermissao('filiados.equipe') && $curso->equipe_id !== null && $usuario->filiado?->equipe_id === $curso->equipe_id));

        // "curso.conteudo" (#39 / #41): ver as aulas, o vídeo enviado e baixar a apostila.
        // Passam: o Comando Geral, quem chancela, quem propôs o curso e quem tem
        // MATRÍCULA CONFIRMADA (aguardando pagamento ainda não). Sem isso: 403, mesmo com o link.
        Gate::define('curso.conteudo', fn (User $usuario, Curso $curso): bool => $usuario->temPermissao('cursos.gerenciar')
            || $usuario->temPermissao('cursos.chancelar')
            || ($usuario->temPermissao('cursos.propor') && $curso->responsavel_id === $usuario->id)
            || $curso->matriculas()->where('user_id', $usuario->id)->where('situacao', SituacaoMatricula::Confirmada->value)->exists());
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
