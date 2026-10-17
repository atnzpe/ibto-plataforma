<?php

/*
 * STAKEHOLDERS E CONTAS DE EXEMPLO DO PILOTO (pedido do Gleyson em 17/10/2026).
 *
 * 1) Os STAKEHOLDERS (lista no .env, PILOTO_STAKEHOLDERS): cada um ganha conta e
 *    ficha de filiado (com o convite por e-mail para criar a senha), os papéis
 *    da função dele e a matrícula no evento de teste com fotos à venda.
 *    Formato: "Nome|email|papel1,papel2;Nome 2|email2|papel". Ex.:
 *    "Fulana Teste|fulana@exemplo.com|tecnologia;Beltrano|b@exemplo.com|admin_equipe,instrutor"
 *    Quem é admin de equipe ganha uma equipe [TESTE] própria (ele só vê a dele).
 * 2) O EVENTO "[TESTE] Evento do piloto: Operação Avançada (fotos)": curso
 *    gratuito, aberto e sem data (para matricular qualquer conta do piloto a
 *    qualquer momento), com o álbum À VENDA ativo (o fotógrafo é a conta do
 *    PILOTO_CONTA_COMPLETA, que sobe as fotos pela tela de fotógrafo).
 * 3) As CONTAS DE EXEMPLO por papel (só com PILOTO_SENHA_DEMO no .env): aluno,
 *    filiado, instrutor, admin de equipe e fotógrafo, todas com essa senha.
 *    NÃO existe conta de exemplo de Comando Geral nem de Tecnologia: com a
 *    homologação aberta, uma senha compartilhada dessas abriria os dados dos
 *    filiados reais importados.
 * Nomes, e-mails e a senha ficam SÓ no .env do servidor (LGPD), nunca no código.
 * Pode rodar de novo: o que já existe é reaproveitado (pelo e-mail ou título).
 */

namespace App\Console\Commands;

use App\Actions\Cursos\Matriculas;
use App\Actions\Filiados\CriarFiliado;
use App\Enums\SituacaoCurso;
use App\Models\Curso;
use App\Models\CursoMatricula;
use App\Models\CursoModulo;
use App\Models\Equipe;
use App\Models\Filiado;
use App\Models\FotoAlbumVenda;
use App\Models\Papel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CarregarStakeholdersPiloto extends Command
{
    protected $signature = 'plataforma:piloto-stakeholders';

    protected $description = 'Cria as contas dos stakeholders do piloto (com convite), o evento com fotos à venda e as contas de exemplo por papel';

    /** O texto que marca o que é de teste. */
    private const TESTE = CarregarPiloto::PREFIXO_TESTE;

    /** Os papéis que podem vir no .env (os outros são ignorados, por segurança). */
    private const PAPEIS_ACEITOS = ['aluno', 'filiado', 'instrutor', 'admin_equipe', 'fotografo', 'administrador', 'tecnologia'];

    /**
     * As contas de exemplo: papel => [nome, e-mail, papéis].
     * O domínio demo.ib-to.org não recebe e-mail: as contas já nascem confirmadas.
     */
    private const DEMOS = [
        'aluno' => ['[DEMO] Aluno', 'aluno@demo.ib-to.org', ['aluno']],
        'filiado' => ['[DEMO] Filiado', 'filiado@demo.ib-to.org', ['aluno', 'filiado']],
        'instrutor' => ['[DEMO] Instrutor', 'instrutor@demo.ib-to.org', ['aluno', 'filiado', 'instrutor']],
        'admin_equipe' => ['[DEMO] Admin de equipe', 'admin-equipe@demo.ib-to.org', ['aluno', 'filiado', 'admin_equipe']],
        'fotografo' => ['[DEMO] Fotógrafo', 'fotografo@demo.ib-to.org', ['fotografo']],
    ];

    /**
     * Roda as três partes.
     *
     * @param  Matriculas  $matriculas  A matrícula (pelo IBTO).
     * @param  CriarFiliado  $criarFiliado  Cria conta + ficha + convite.
     * @return int 0 = deu certo.
     */
    public function handle(Matriculas $matriculas, CriarFiliado $criarFiliado): int
    {
        $evento = $this->evento();
        $this->album($evento);

        foreach ($this->stakeholders() as [$nome, $email, $papeis]) {
            $usuario = $this->stakeholder($criarFiliado, $nome, $email, $papeis);
            $this->matricular($matriculas, $evento, $usuario);
        }

        $senha = (string) config('app.piloto_senha_demo');
        // Sem a senha no .env: não cria contas de exemplo.
        if ($senha !== '') {
            foreach (self::DEMOS as [$nome, $email, $papeis]) {
                $this->matricular($matriculas, $evento, $this->demo($nome, $email, $papeis, $senha));
            }
        }

        $this->info('Stakeholders, evento e contas de exemplo prontos.');

        return self::SUCCESS;
    }

    /**
     * Lê a lista de stakeholders do .env.
     *
     * @return list<array{0: string, 1: string, 2: list<string>}> [nome, e-mail, papéis]
     */
    private function stakeholders(): array
    {
        $lista = [];
        foreach (explode(';', (string) config('app.piloto_stakeholders')) as $linha) {
            $partes = array_map('trim', explode('|', $linha));
            // Linha incompleta (sem nome ou sem e-mail válido): pula.
            if (count($partes) < 3 || $partes[0] === '' || filter_var($partes[1], FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }
            $papeis = array_values(array_intersect(array_map('trim', explode(',', strtolower($partes[2]))), self::PAPEIS_ACEITOS));
            $lista[] = [$partes[0], strtolower($partes[1]), $papeis];
        }

        return $lista;
    }

    /**
     * Cria (ou reaproveita) a conta do stakeholder, com a ficha, a equipe e os papéis.
     *
     * @param  list<string>  $papeis  Ex.: ['admin_equipe', 'instrutor'].
     */
    private function stakeholder(CriarFiliado $criarFiliado, string $nome, string $email, array $papeis): User
    {
        // Admin de equipe ganha uma equipe própria (ele só vê a dele); os outros, a do piloto.
        $equipe = in_array('admin_equipe', $papeis, true)
            ? Equipe::firstOrCreate(['nome' => self::TESTE.'Equipe de '.Str::of($nome)->explode(' ')->first()], ['cidade' => 'Recife', 'uf' => 'PE', 'status' => 'ativa'])
            : Equipe::firstOrCreate(['nome' => self::TESTE.'Equipe do piloto'], ['cidade' => 'Recife', 'uf' => 'PE', 'status' => 'ativa']);

        $usuario = User::where('email', $email)->first();
        // Conta nova: o CriarFiliado faz a conta, o registro e manda o convite (criar a senha).
        if ($usuario === null) {
            $filiado = $criarFiliado->executar(['nome' => $nome, 'email' => $email, 'cpf' => null, 'nascimento' => null, 'municipio' => null, 'uf' => 'PE', 'equipe_id' => $equipe->id]);
            $usuario = $filiado->usuario;
        } elseif ($usuario->filiado === null) {
            // Já tinha conta, mas não a ficha: cria só a ficha (sem dado pessoal inventado).
            $ficha = new Filiado;
            $ficha->forceFill(['user_id' => $usuario->id, 'registro' => 'IBTO-P'.str_pad((string) $usuario->id, 4, '0', STR_PAD_LEFT), 'equipe_id' => $equipe->id, 'status' => 'ativo', 'uf' => 'PE'])->save();
        }

        /** @var User $usuario */
        $usuario->papeis()->syncWithoutDetaching(Papel::whereIn('slug', [...$papeis, 'aluno', 'filiado'])->pluck('id'));

        return $usuario;
    }

    /**
     * Cria (ou reaproveita) uma conta de exemplo, já confirmada, com a senha do .env.
     *
     * @param  list<string>  $papeis
     */
    private function demo(string $nome, string $email, array $papeis, string $senha): User
    {
        $usuario = User::firstOrNew(['email' => $email]);
        // A senha é sempre a do .env (trocar lá e rodar de novo troca aqui).
        $usuario->forceFill(['name' => $nome, 'password' => Hash::make($senha), 'email_verified_at' => $usuario->email_verified_at ?? now()])->save();
        $usuario->papeis()->syncWithoutDetaching(Papel::whereIn('slug', $papeis)->pluck('id'));

        // Quem é filiado ganha a ficha na equipe do piloto (o admin de equipe de exemplo chefia ela).
        if (in_array('filiado', $papeis, true) && $usuario->filiado()->doesntExist()) {
            $equipe = Equipe::firstOrCreate(['nome' => self::TESTE.'Equipe do piloto'], ['cidade' => 'Recife', 'uf' => 'PE', 'status' => 'ativa']);
            $ficha = new Filiado;
            $ficha->forceFill(['user_id' => $usuario->id, 'registro' => 'IBTO-D'.str_pad((string) $usuario->id, 4, '0', STR_PAD_LEFT), 'equipe_id' => $equipe->id, 'status' => 'ativo', 'uf' => 'PE'])->save();
            $usuario->load('filiado');
        }

        return $usuario;
    }

    /**
     * O evento de teste (curso gratuito, aberto e sem data).
     */
    private function evento(): Curso
    {
        $titulo = self::TESTE.'Evento do piloto: Operação Avançada (fotos)';
        $existente = Curso::where('titulo', $titulo)->first();
        if ($existente !== null) {
            return $existente;
        }

        $curso = Curso::create([
            'titulo' => $titulo,
            'descricao' => 'Evento de teste do piloto: as fotos do estande ficam à venda só para quem está matriculado. Sai antes da produção.',
            'modalidade' => 'presencial',
            // Aberto e sem data: qualquer conta do piloto (filiada ou não) pode ser matriculada
            // a qualquer momento (com data, a regra recusa matrícula depois do início).
            'acesso' => 'aberto',
            'valor_centavos' => 0,
            'local' => 'Recife - PE',
            'instrutor_nome' => 'Instrutor do piloto',
        ]);
        $curso->forceFill(['situacao' => SituacaoCurso::Publicado, 'percentual_ibto' => 20])->save();
        $modulo = CursoModulo::create(['curso_id' => $curso->id, 'titulo' => 'Módulo 1', 'ordem' => 1, 'ativo' => true]);
        $modulo->aulas()->create(['titulo' => 'Encontro', 'ordem' => 1, 'ativa' => true]);

        return $curso;
    }

    /**
     * O álbum À VENDA do evento, ATIVO, com a conta completa como fotógrafa (ela sobe as fotos).
     * Sem PILOTO_CONTA_COMPLETA (ou sem essa conta): não cria o álbum.
     */
    private function album(Curso $evento): void
    {
        $fotografo = User::where('email', strtolower(trim((string) config('app.piloto_conta_completa'))))->first();
        if ($fotografo === null) {
            return;
        }

        FotoAlbumVenda::firstOrCreate(
            ['titulo' => self::TESTE.'Fotos do estande (Operação Avançada)'],
            ['curso_id' => $evento->id, 'fotografo_id' => $fotografo->id, 'preco_centavos' => 1500, 'percentual_fotografo' => 70, 'expira_em' => CarbonImmutable::today()->addDays(60)->toDateString(), 'ativo' => true],
        );
        $fotografo->papeis()->syncWithoutDetaching(Papel::where('slug', 'fotografo')->pluck('id'));
    }

    /**
     * Matricula a pessoa no evento (pelo IBTO), se ainda não estiver.
     */
    private function matricular(Matriculas $matriculas, Curso $evento, User $usuario): void
    {
        // Já matriculada: não repete.
        if (CursoMatricula::where('curso_id', $evento->id)->where('user_id', $usuario->id)->exists()) {
            return;
        }
        $usuario->load('filiado');
        $matriculas->matricular($evento, $usuario, peloIbto: true);
    }
}
