<?php

/*
 * Filiado do IBTO (#37): o registro da carteirinha. A conta do app (nome,
 * e-mail, senha) fica em User; aqui ficam o registro, os dados pessoais
 * criptografados, a equipe, o nível e as áreas.
 *
 * LGPD: CPF, nascimento e telefone são criptografados (cast "encrypted").
 * Para achar alguém pelo CPF sem decifrar todos, usa-se o cpf_hash
 * (ver Filiado::hashDoCpf).
 */

namespace App\Models;

use App\Enums\CategoriaFiliado;
use App\Support\Documento;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $registro
 * @property string|null $cpf
 * @property string|null $nascimento
 * @property string|null $telefone
 * @property string|null $cpf_hash
 * @property string|null $municipio
 * @property string|null $uf
 * @property int|null $equipe_id
 * @property CategoriaFiliado|null $categoria
 * @property int|null $nivel_id
 * @property string|null $abrangencia
 * @property bool $documentacao_emitida
 * @property string $status
 * @property string|null $foto Foto 3x4 na pasta PRIVADA (#78). Ex.: 'fotos/filiados/9f1c...webp'.
 * @property bool $autoriza_imagem_rede Autorizou a foto na Rede Nacional do site (#78)? Começa false.
 * @property Carbon|null $autoriza_imagem_em Quando a autorização foi marcada (#78).
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
// registro e cpf_hash ficam de fora: quem calcula é a própria plataforma (ver booted()).
#[Fillable(['cpf', 'nascimento', 'telefone', 'municipio', 'uf', 'equipe_id', 'categoria', 'nivel_id', 'abrangencia', 'documentacao_emitida', 'status'])]
class Filiado extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Gravados embaralhados com a APP_KEY; no PHP voltam ao texto normal.
            'cpf' => 'encrypted',
            'nascimento' => 'encrypted',
            'telefone' => 'encrypted',
            // O texto do banco vira uma das opções da lista (ex.: CategoriaFiliado::Instrutor).
            'categoria' => CategoriaFiliado::class,
            // 0/1 do banco vira false/true.
            'documentacao_emitida' => 'boolean',
            // Autorização de imagem (#78): 0/1 vira false/true; a data vira Carbon.
            'autoriza_imagem_rede' => 'boolean',
            'autoriza_imagem_em' => 'datetime',
        ];
    }

    /**
     * Regras automáticas ao gravar: o CPF é guardado só com números e o
     * cpf_hash é recalculado sempre que o CPF muda.
     */
    protected static function booted(): void
    {
        static::saving(function (Filiado $filiado): void {
            // Só mexe quando o CPF foi informado ou trocado.
            if ($filiado->isDirty('cpf')) {
                // Importado sem CPF ("importar mesmo assim"): os dois ficam vazios.
                // Vários vazios não contam como CPF repetido.
                if (blank($filiado->cpf)) {
                    $filiado->cpf = null;
                    $filiado->cpf_hash = null;

                    return;
                }
                $filiado->cpf = Documento::apenasDigitos($filiado->cpf);
                $filiado->cpf_hash = self::hashDoCpf($filiado->cpf);
            }
        });
    }

    /**
     * Impressão digital do CPF: sempre a mesma para o mesmo CPF, mas não dá
     * para voltar dela ao número (diferente da criptografia, que dá).
     * HMAC = hash com uma "senha" (a APP_KEY): sem ela, ninguém recalcula.
     *
     * @param  string  $cpf  Com ou sem pontuação. Ex.: '529.982.247-25'
     * @return string 64 caracteres. Ex.: 'a3f1...'
     */
    public static function hashDoCpf(string $cpf): string
    {
        return hash_hmac('sha256', Documento::apenasDigitos($cpf), (string) config('app.key'));
    }

    /**
     * Monta o número de registro do filiado.
     * Formato do IBTO: "BR" + ano (4 números) + sequência do ano (4 números) + UF.
     *
     * @param  int  $ano  Ex.: 2026
     * @param  int  $sequencia  Ex.: 7
     * @param  string  $uf  Ex.: 'pe'
     * @return string Ex.: 'BR20260007PE'
     */
    public static function montarRegistro(int $ano, int $sequencia, string $uf): string
    {
        // sprintf monta o texto a partir de um "molde": %d = o ano; %04d = a
        // sequência com 4 casas (7 vira 0007); %s = a UF, em maiúsculas (pe vira PE).
        return sprintf('BR%d%04d%s', $ano, $sequencia, strtoupper($uf));
    }

    /**
     * A conta do app desta pessoa (nome, e-mail, senha).
     *
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Cadastro INCOMPLETO: entrou pela importação com o "importar mesmo assim"
     * (Gleyson, 10/10/2026) e ainda falta CPF, nascimento, município, UF ou o
     * e-mail da conta. Mostra o selo "Incompleto" na lista; completar na edição.
     * Precisa da conta carregada (with('usuario')) para olhar o e-mail.
     *
     * @return bool true = falta algum dado.
     */
    public function incompleto(): bool
    {
        // getAttributes() lê o valor guardado SEM descriptografar (mais leve e sem mostrar o dado).
        $guardado = $this->getAttributes();

        return ($guardado['cpf_hash'] ?? null) === null
            || ($guardado['nascimento'] ?? null) === null
            || blank($this->municipio)
            || blank($this->uf)
            // Conta existe, mas sem e-mail (sem conta nenhuma não conta aqui).
            || ($this->usuario !== null && $this->usuario->email === null);
    }

    /**
     * @return BelongsTo<Equipe, $this>
     */
    public function equipe(): BelongsTo
    {
        return $this->belongsTo(Equipe::class);
    }

    /**
     * @return BelongsTo<Nivel, $this>
     */
    public function nivel(): BelongsTo
    {
        return $this->belongsTo(Nivel::class);
    }

    /**
     * Pagamentos de anuidade deste filiado, um por ano (#37).
     *
     * @return HasMany<AnuidadePagamento, $this>
     */
    public function pagamentosAnuidade(): HasMany
    {
        return $this->hasMany(AnuidadePagamento::class);
    }

    /**
     * A anuidade deste filiado está EM DIA? (#37, regras do Gleyson de 09/10/2026)
     * Vale o ano civil: em 2027, está em dia quem pagou a de 2027.
     *
     * @param  CarbonInterface|null  $hoje  A data de referência (vazio = hoje). Ex.: Carbon::parse('2027-03-15')
     * @return bool true = em dia; false = vencida.
     */
    public function anuidadeEmDia(?CarbonInterface $hoje = null): bool
    {
        // O ano que vale é o da data de referência. Ex.: 15/03/2027 → 2027.
        $ano = ($hoje ?? now())->year;

        // O valor cadastrado para esse ano (ou null, se ninguém cadastrou ainda).
        $anuidade = Anuidade::where('ano', $ano)->first();

        // Ano sem valor cadastrado, ou isento (ex.: 2026): ninguém deve nada.
        // Se não for o caso, segue para conferir o pagamento.
        if ($anuidade === null || $anuidade->isenta) {
            return true;
        }

        // Ano com valor cadastrado: está em dia se houver pagamento registrado
        // (linha do Gleyson) e VÁLIDO, isto é, cuja receita não foi estornada (#59).
        return $this->pagamentosAnuidade()
            ->validos()
            ->where('ano', $ano)
            ->exists();
    }

    /**
     * Áreas de atuação do filiado (várias).
     *
     * @return BelongsToMany<AreaAtuacao, $this>
     */
    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(AreaAtuacao::class, 'area_atuacao_filiado', 'filiado_id', 'area_atuacao_id');
    }

    /**
     * Endereço PROTEGIDO da foto 3x4 (#78), ou null sem foto.
     * O "?v=" muda quando o cadastro muda, para o navegador não mostrar a antiga.
     *
     * @return string|null Ex.: 'https://cursos.ib-to.org/fotos/filiados/7?v=1760630400'.
     */
    public function fotoUrl(): ?string
    {
        // Sem foto: null (a tela mostra um ícone).
        if ($this->foto === null) {
            return null;
        }

        return route('filiados.foto', ['filiado' => $this->id, 'v' => $this->updated_at?->getTimestamp()]);
    }
}
