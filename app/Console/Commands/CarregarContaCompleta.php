<?php

/*
 * CONTA COMPLETA PARA O TESTE DO PILOTO (pedido do Gleyson em 17/10/2026).
 *
 * Deixa UMA conta (que já existe) com todos os papéis e com dados em todos os
 * módulos, para testar cada perfil sem trocar de usuário:
 * - papéis: aluno, filiado, instrutor, admin de equipe e fotógrafo (os que a
 *   pessoa já tem continuam);
 * - filiado da equipe "[TESTE] Equipe do piloto" (SEM CPF, telefone ou
 *   nascimento: nenhum dado pessoal inventado);
 * - aluno: matrícula paga (receita no livro-caixa), aguardando o Pix, gratuita e
 *   um curso concluído com presença e certificado;
 * - instrutor: um curso dela com 3 alunos de teste, chamada e a parte dela no
 *   livro-caixa (aparece no Meu painel);
 * - admin de equipe: filiados de teste na equipe e uma solicitação de cadastro
 *   esperando a aprovação dela;
 * - fotos: um álbum em que ela é a fotógrafa (com uma venda paga) e outro em que
 *   ela compra (um pedido pago, com download, e um aguardando o Pix).
 * O e-mail NÃO fica no código (LGPD): vem do argumento ou do .env
 * (PILOTO_CONTA_COMPLETA). As outras contas são de teste (e-mail @exemplo.invalid,
 * que não existe) e tudo começa com "[TESTE]". Pode rodar de novo: não duplica.
 * Uso local: php artisan plataforma:conta-completa email@exemplo.com
 */

namespace App\Console\Commands;

use App\Actions\Cursos\Certificados;
use App\Actions\Cursos\Matriculas;
use App\Actions\Fotos\VendaFotos;
use App\Enums\CategoriaFiliado;
use App\Enums\SituacaoCurso;
use App\Enums\SituacaoMatricula;
use App\Models\Curso;
use App\Models\CursoAula;
use App\Models\CursoMatricula;
use App\Models\CursoModulo;
use App\Models\CursoPresenca;
use App\Models\Equipe;
use App\Models\Filiado;
use App\Models\FinFormaPagamento;
use App\Models\FotoAlbumVenda;
use App\Models\FotoVenda;
use App\Models\Nivel;
use App\Models\Papel;
use App\Models\PedidoFoto;
use App\Models\User;
use App\Support\FotoComMarcaDagua;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CarregarContaCompleta extends Command
{
    protected $signature = 'plataforma:conta-completa {email : O e-mail da conta que já existe}';

    protected $description = 'Dá todos os papéis a uma conta e cria dados de teste em todos os módulos (sem duplicar)';

    /** O texto que marca o que é de teste (sai antes da produção). */
    private const TESTE = CarregarPiloto::PREFIXO_TESTE;

    /**
     * Monta a conta completa.
     *
     * @param  Matriculas  $matriculas  Matrícula e confirmação de pagamento (com o livro-caixa).
     * @param  Certificados  $certificados  Emissão do certificado.
     * @param  VendaFotos  $vendaFotos  Pedidos e confirmação da venda de fotos.
     * @return int 0 = deu certo; 1 = a conta não existe.
     */
    public function handle(Matriculas $matriculas, Certificados $certificados, VendaFotos $vendaFotos): int
    {
        $usuario = User::where('email', strtolower(trim((string) $this->argument('email'))))->first();
        // A conta precisa existir (a pessoa cria pelo convite ou pelo cadastro); não criamos conta real aqui.
        if ($usuario === null) {
            $this->error('CONTA_COMPLETA_SEM_CONTA: esta conta não existe na plataforma.');

            return self::FAILURE;
        }

        $equipe = $this->papeisEFiliado($usuario);
        $pix = (int) FinFormaPagamento::where('nome', 'PIX')->value('id');
        $alunos = $this->alunosDeTeste($equipe);

        $this->comoAluno($usuario, $alunos[0], $matriculas, $certificados, $pix);
        $this->comoInstrutor($usuario, $alunos, $matriculas, $pix);
        $this->comoChefeDeEquipe($equipe);
        $this->comoFotografaECompradora($usuario, $alunos, $vendaFotos, $pix);

        $this->info('Conta completa pronta.');

        return self::SUCCESS;
    }

    /**
     * Dá os papéis e cria (ou reaproveita) a ficha de filiado na equipe de teste.
     *
     * @return Equipe A equipe de teste (a que a pessoa chefia).
     */
    private function papeisEFiliado(User $usuario): Equipe
    {
        // syncWithoutDetaching: acrescenta os papéis sem tirar os que a pessoa já tem.
        $usuario->papeis()->syncWithoutDetaching(Papel::whereIn('slug', ['aluno', 'filiado', 'instrutor', 'admin_equipe', 'fotografo'])->pluck('id'));

        $equipe = Equipe::firstOrCreate(['nome' => self::TESTE.'Equipe do piloto'], ['cidade' => 'Recife', 'uf' => 'PE', 'status' => 'ativa']);

        // A ficha de filiado: só registro, equipe, categoria e nível (sem dado pessoal inventado).
        if ($usuario->filiado === null) {
            $filiado = new Filiado;
            $filiado->forceFill([
                'user_id' => $usuario->id,
                'registro' => 'IBTO-T'.str_pad((string) $usuario->id, 4, '0', STR_PAD_LEFT),
                'equipe_id' => $equipe->id,
                'categoria' => CategoriaFiliado::cases()[0],
                'nivel_id' => Nivel::query()->orderBy('id')->value('id'),
                'status' => 'ativo',
                'municipio' => 'Recife',
                'uf' => 'PE',
                'data_filiacao' => CarbonImmutable::today()->subYear()->toDateString(),
            ])->save();
            // Recarrega: o Laravel tinha guardado "sem ficha" na memória (as travas de filiado leem daqui).
            $usuario->load('filiado');
        }

        return $equipe;
    }

    /**
     * Três alunos de teste (contas que não recebem e-mail: domínio .invalid), filiados da equipe.
     *
     * @return list<User>
     */
    private function alunosDeTeste(Equipe $equipe): array
    {
        $alunos = [];
        foreach ([1, 2, 3] as $n) {
            $aluno = User::firstOrNew(['email' => "aluno{$n}.piloto@exemplo.invalid"]);
            if (! $aluno->exists) {
                $aluno->forceFill(['name' => self::TESTE."Aluno {$n}", 'password' => Hash::make(Str::random(40)), 'email_verified_at' => now()])->save();
                $aluno->papeis()->syncWithoutDetaching(Papel::whereIn('slug', ['aluno', 'filiado'])->pluck('id'));
                $ficha = new Filiado;
                $ficha->forceFill(['user_id' => $aluno->id, 'registro' => 'IBTO-TA'.$n.str_pad((string) $aluno->id, 3, '0', STR_PAD_LEFT), 'equipe_id' => $equipe->id, 'status' => 'ativo', 'municipio' => 'Recife', 'uf' => 'PE'])->save();
            }
            $alunos[] = $aluno;
        }

        return $alunos;
    }

    /**
     * Como ALUNO: paga, aguardando o Pix, gratuita e um curso concluído com certificado.
     */
    private function comoAluno(User $usuario, User $alunoDaTurma, Matriculas $matriculas, Certificados $certificados, int $pix): void
    {
        $futuro = CarbonImmutable::today()->addDays(20)->toDateString();

        // Paga e confirmada (gera a receita e a parte do instrutor no livro-caixa).
        $paga = $this->matricula($matriculas, $this->curso('Curso pago (você já pagou)', 15000, $futuro), $usuario);
        if ($paga->situacao === SituacaoMatricula::AguardandoPagamento) {
            $matriculas->confirmarPagamento($paga, CarbonImmutable::today()->toDateString(), $pix);
        }

        // Aguardando o Pix (aparece o Pix copia e cola em Meus cursos).
        $this->matricula($matriculas, $this->curso('Curso pago (aguardando o seu Pix)', 9000, $futuro), $usuario);

        // Gratuita (confirma na hora).
        $this->matricula($matriculas, $this->curso('Curso gratuito para filiados', 0, $futuro), $usuario);

        // Concluído: encontro no passado, presença e certificado (aparece no Meu dossiê).
        // A ordem da vida real: publicado → matrícula → presença → certificado → encerrado.
        $concluido = $this->curso('Curso concluído (com certificado)', 0, CarbonImmutable::today()->subDays(10)->toDateString());
        $matricula = $this->matricula($matriculas, $concluido, $usuario);
        $this->presenca($concluido, $matricula);
        if ($matricula->certificado()->doesntExist()) {
            $certificados->emitir($matricula);
        }
        // O aluno de teste 1 também foi dessa turma (ele compra as fotos do evento que ela tirou).
        $this->matricula($matriculas, $concluido, $alunoDaTurma);
        $concluido->forceFill(['situacao' => SituacaoCurso::Encerrado])->save();
    }

    /**
     * Como INSTRUTOR: um curso dela, pago, com 3 alunos (2 pagos, 1 aguardando) e a chamada feita.
     *
     * @param  list<User>  $alunos
     */
    private function comoInstrutor(User $usuario, array $alunos, Matriculas $matriculas, int $pix): void
    {
        $curso = $this->curso('Curso em que você é o instrutor', 20000, CarbonImmutable::today()->subDays(2)->toDateString());
        // Responsável = ela (Meu painel, chamada, conteúdo); 20% do IBTO, 80% do instrutor.
        $curso->forceFill(['responsavel_id' => $usuario->id, 'percentual_ibto' => 20, 'instrutor_nome' => $usuario->name])->save();

        foreach ($alunos as $i => $aluno) {
            $matricula = $this->matricula($matriculas, $curso, $aluno);
            // Os dois primeiros pagaram (e estiveram presentes); o terceiro ainda deve o Pix.
            if ($i < 2 && $matricula->situacao === SituacaoMatricula::AguardandoPagamento) {
                $matricula = $matriculas->confirmarPagamento($matricula, CarbonImmutable::today()->toDateString(), $pix);
            }
            if ($i < 2) {
                $this->presenca($curso, $matricula);
            }
        }
    }

    /**
     * Como CHEFE DE EQUIPE: uma solicitação de cadastro esperando a aprovação.
     */
    private function comoChefeDeEquipe(Equipe $equipe): void
    {
        $pedido = User::firstOrNew(['email' => 'solicitacao.piloto@exemplo.invalid']);
        if (! $pedido->exists) {
            $pedido->forceFill([
                'name' => self::TESTE.'Pessoa pedindo cadastro',
                'password' => Hash::make(Str::random(40)),
                'email_verified_at' => now(),
                'situacao_cadastro' => 'em_analise',
                'equipe_solicitada_id' => $equipe->id,
                'indicado_por' => 'Instrutor da equipe (teste)',
            ])->save();
        }
    }

    /**
     * FOTOS: um álbum em que ela é a fotógrafa (o aluno 1 comprou e pagou) e outro de
     * um fotógrafo de teste em que ela compra (um pedido pago e um aguardando o Pix).
     *
     * @param  list<User>  $alunos
     */
    private function comoFotografaECompradora(User $usuario, array $alunos, VendaFotos $vendaFotos, int $pix): void
    {
        // O curso do evento: ela e o aluno 1 têm matrícula confirmada nele (feito em comoAluno).
        $evento = Curso::where('titulo', self::TESTE.'Curso concluído (com certificado)')->firstOrFail();

        // 1) Ela é a fotógrafa: o aluno 1 compra 2 fotos e o IBTO confirma.
        $dela = $this->albumVenda($evento, $usuario, 'Fotos que você tirou (você é o fotógrafo)');
        if (PedidoFoto::where('album_id', $dela->id)->doesntExist()) {
            // As 2 primeiras fotos (lista de números inteiros, como o pedido espera).
            $fotos = array_values(array_map('intval', $dela->fotos()->orderBy('id')->limit(2)->pluck('id')->all()));
            $vendaFotos->confirmarPagamento($vendaFotos->criarPedido($dela, $alunos[0], $fotos), CarbonImmutable::today()->toDateString(), $pix);
        }

        // 2) Ela compra de um fotógrafo de teste: um pedido pago (com download) e um aguardando.
        $fotografo = User::firstOrNew(['email' => 'fotografo.piloto@exemplo.invalid']);
        if (! $fotografo->exists) {
            $fotografo->forceFill(['name' => self::TESTE.'Fotógrafo credenciado', 'password' => Hash::make(Str::random(40)), 'email_verified_at' => now()])->save();
            $fotografo->papeis()->syncWithoutDetaching(Papel::where('slug', 'fotografo')->pluck('id'));
        }
        $outro = $this->albumVenda($evento, $fotografo, 'Fotos do evento (você compra)');
        if (PedidoFoto::where('album_id', $outro->id)->where('user_id', $usuario->id)->doesntExist()) {
            // As fotos do álbum, como números inteiros (o pedido espera uma lista de int).
            $ids = array_values(array_map('intval', $outro->fotos()->orderBy('id')->pluck('id')->all()));
            $vendaFotos->confirmarPagamento($vendaFotos->criarPedido($outro, $usuario, [$ids[0], $ids[1]]), CarbonImmutable::today()->toDateString(), $pix);
            $vendaFotos->criarPedido($outro, $usuario, [$ids[2]]);
        }
    }

    /**
     * Cria (ou reaproveita, pelo título) um curso de teste publicado, com um encontro.
     *
     * @param  string  $titulo  Sem o "[TESTE] ". Ex.: 'Curso gratuito para filiados'.
     * @param  int  $centavos  O valor. Ex.: 15000 = R$ 150,00.
     * @param  string  $data  A data do encontro. Ex.: '2026-11-06'.
     */
    private function curso(string $titulo, int $centavos, string $data, SituacaoCurso $situacao = SituacaoCurso::Publicado): Curso
    {
        $existente = Curso::where('titulo', self::TESTE.$titulo)->first();
        if ($existente !== null) {
            return $existente;
        }

        $curso = Curso::create([
            'titulo' => self::TESTE.$titulo,
            'descricao' => 'Curso de teste da conta completa do piloto. Sai antes da produção.',
            'modalidade' => 'presencial',
            // Regra do IBTO: gratuito é só para filiados; pago é aberto.
            'acesso' => $centavos > 0 ? 'aberto' : 'restrito',
            'valor_centavos' => $centavos,
            'vagas' => 20,
            'data_inicio' => $data,
            'data_fim' => $data,
            'local' => 'Recife - PE',
            'instrutor_nome' => 'Instrutor de teste',
        ]);
        $curso->forceFill(['situacao' => $situacao, 'percentual_ibto' => 20])->save();
        $modulo = CursoModulo::create(['curso_id' => $curso->id, 'titulo' => 'Módulo 1', 'ordem' => 1, 'ativo' => true]);
        $modulo->aulas()->create(['titulo' => 'Encontro', 'ordem' => 1, 'ativa' => true, 'data' => $data]);

        return $curso;
    }

    /**
     * A matrícula da pessoa no curso (a que já existe, ou uma nova pelo IBTO, que ignora as travas de data).
     */
    private function matricula(Matriculas $matriculas, Curso $curso, User $usuario): CursoMatricula
    {
        $existente = CursoMatricula::where('curso_id', $curso->id)->where('user_id', $usuario->id)->first();
        // Já matriculada: reaproveita (não duplica).
        if ($existente !== null) {
            return $existente;
        }

        // Turma que já aconteceu (data no passado): a regra recusa matrícula depois do início.
        // Para montar o histórico, tira a data só durante a matrícula e devolve em seguida.
        $inicio = $curso->data_inicio;
        if ($inicio !== null && $inicio->lt(today())) {
            $curso->forceFill(['data_inicio' => null])->save();
            try {
                return $matriculas->matricular($curso, $usuario, peloIbto: true);
            } finally {
                $curso->forceFill(['data_inicio' => $inicio])->save();
            }
        }

        return $matriculas->matricular($curso, $usuario, peloIbto: true);
    }

    /**
     * Marca presença da matrícula em todos os encontros do curso.
     */
    private function presenca(Curso $curso, CursoMatricula $matricula): void
    {
        $aulas = CursoAula::query()->whereHas('modulo', fn ($m) => $m->where('curso_id', $curso->id))->pluck('id');
        foreach ($aulas as $aulaId) {
            // forceFill: aula e matrícula não entram por formulário (o modelo protege), só a carga grava.
            $presenca = CursoPresenca::where('aula_id', $aulaId)->where('matricula_id', $matricula->id)->first() ?? new CursoPresenca;
            $presenca->forceFill(['aula_id' => $aulaId, 'matricula_id' => $matricula->id, 'presente' => true])->save();
        }
    }

    /**
     * Cria (ou reaproveita) um álbum à venda com 4 fotos geradas (com a marca d'água de verdade).
     *
     * @param  string  $titulo  Sem o "[TESTE] ".
     */
    private function albumVenda(Curso $curso, User $fotografo, string $titulo): FotoAlbumVenda
    {
        $album = FotoAlbumVenda::firstOrCreate(
            ['titulo' => self::TESTE.$titulo],
            ['curso_id' => $curso->id, 'fotografo_id' => $fotografo->id, 'preco_centavos' => 1500, 'percentual_fotografo' => 70, 'expira_em' => CarbonImmutable::today()->addDays(60)->toDateString(), 'ativo' => true],
        );

        // Já tem fotos (rodou antes): não envia de novo.
        if ($album->fotos()->exists()) {
            return $album;
        }
        for ($n = 1; $n <= 4; $n++) {
            $caminhos = FotoComMarcaDagua::gravar($this->fotoGerada($n), $fotografo->name);
            $foto = new FotoVenda;
            $foto->forceFill(['album_id' => $album->id, 'original' => $caminhos['original'], 'previa' => $caminhos['previa'], 'ordem' => $n])->save();
        }

        return $album;
    }

    /**
     * Gera uma foto de exemplo (sem pessoas) como se fosse um arquivo enviado.
     *
     * @param  int  $n  O número da foto (muda a cor). Ex.: 2.
     */
    private function fotoGerada(int $n): UploadedFile
    {
        $imagem = imagecreatetruecolor(1600, 1067);
        // Uma cor de fundo diferente por foto (sempre dentro de 0 a 255, o limite de cada cor).
        $vermelho = min(255, max(0, 20 + $n * 25));
        $verde = min(255, max(0, 22 + $n * 10));
        imagefill($imagem, 0, 0, (int) imagecolorallocate($imagem, $vermelho, $verde, 26));
        imagestring($imagem, 5, 60, 60, "IBTO - foto de exemplo {$n} (sem pessoas)", (int) imagecolorallocate($imagem, 201, 189, 148));
        $arquivo = tempnam(sys_get_temp_dir(), 'ibto').'.jpg';
        imagejpeg($imagem, $arquivo, 85);

        // true = "modo de teste": aceita o arquivo mesmo sem ter vindo de um formulário.
        return new UploadedFile($arquivo, "exemplo-{$n}.jpg", 'image/jpeg', null, true);
    }
}
