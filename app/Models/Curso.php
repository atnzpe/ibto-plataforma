<?php

/*
 * CURSO do IBTO (#39 / #38): presencial ou online, aberto ou só para filiados,
 * gratuito ou pago. Quem cadastra é o Comando Geral (permissão cursos.gerenciar).
 * A imagem de capa é pública (aparece no catálogo) e é entregue por rota própria.
 */

namespace App\Models;

use App\Enums\AcessoCurso;
use App\Enums\ModalidadeCurso;
use App\Enums\SituacaoCurso;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $titulo
 * @property string|null $descricao
 * @property int|null $area_atuacao_id
 * @property ModalidadeCurso $modalidade
 * @property AcessoCurso $acesso
 * @property bool $fechado true = curso FECHADO: fora do site, só quem o IBTO matricular (ex.: Formação de instrutor).
 * @property int $valor_centavos
 * @property int $percentual_ibto Quanto do valor fica com o IBTO (0 a 100). Ex.: 20; a Formação de instrutor, 100.
 * @property int|null $vagas
 * @property Carbon|null $data_inicio
 * @property Carbon|null $data_fim
 * @property string|null $hora_inicio
 * @property string|null $hora_fim
 * @property string|null $local
 * @property int|null $nivel_minimo_id Pré-requisito (#88): nível mínimo para se inscrever. Vazio = qualquer.
 * @property int|null $meses_filiacao_minimo Pré-requisito (#88): meses mínimos de filiação. Ex.: 12.
 * @property int|null $carga_horaria
 * @property int|null $instrutor_id
 * @property string|null $imagem
 * @property string|null $link_pagamento
 * @property string|null $material_incluso
 * @property string|null $instrutor_nome
 * @property string|null $instrutor_especialidade
 * @property string|null $chamada
 * @property string|null $contato_whatsapp
 * @property string|null $pag_pix_chave Dados para pagamento PRÓPRIOS do curso (só com a chave "por curso" ligada; ver DadosPagamento).
 * @property string|null $pag_favorecido
 * @property string|null $pag_banco
 * @property string|null $pag_agencia
 * @property string|null $pag_conta
 * @property SituacaoCurso $situacao
 * @property string|null $motivo_situacao
 * @property int|null $responsavel_id
 * @property int|null $equipe_id
 * @property int|null $chancelado_por
 * @property Carbon|null $chancelado_em
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
// Ficam de fora: "imagem" (gravada pelo App\Support\ImagemPublica) e a SITUAÇÃO e a
// chancela (só o fluxo de chancela muda, pelo forceFill: App\Services\FluxoCurso, #40).
// "fechado" e "percentual_ibto" também ficam de fora: só o IBTO muda (forceFill no CursoController).
#[Fillable(['titulo', 'descricao', 'area_atuacao_id', 'modalidade', 'acesso', 'valor_centavos', 'vagas', 'data_inicio', 'data_fim', 'hora_inicio', 'hora_fim', 'local', 'carga_horaria', 'instrutor_id', 'link_pagamento', 'material_incluso', 'contato_whatsapp', 'instrutor_nome', 'instrutor_especialidade', 'chamada'])]
class Curso extends Model
{
    // Parte do IBTO quando ninguém escolheu outra (decisão de 14/10/2026: 20%).
    // Um nome em vez do número solto no código ("número mágico").
    public const PERCENTUAL_IBTO_PADRAO = 20;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Texto do banco vira uma das opções (ex.: ModalidadeCurso::Online).
            'modalidade' => ModalidadeCurso::class,
            'acesso' => AcessoCurso::class,
            'situacao' => SituacaoCurso::class,
            // 0/1 do banco vira false/true no PHP.
            'fechado' => 'boolean',
            'valor_centavos' => 'integer',
            'percentual_ibto' => 'integer',
            'vagas' => 'integer',
            'carga_horaria' => 'integer',
            'data_inicio' => 'date',
            'data_fim' => 'date',
            // Pré-requisito de tempo de filiado (#88), em meses.
            'meses_filiacao_minimo' => 'integer',
            // Dia e hora da chancela (texto do banco vira data e hora no PHP).
            'chancelado_em' => 'datetime',
        ];
    }

    /**
     * Filtro pronto ("escopo") dos cursos que podem aparecer no SITE: tira os
     * FECHADOS (ex.: Formação de instrutor). Uso: Curso::query()->noSite()->...
     * O catálogo e as trilhas usam este mesmo filtro, para a regra ficar num lugar só.
     *
     * @param  Builder<Curso>  $consulta  A consulta que está sendo montada.
     */
    public function scopeNoSite(Builder $consulta): void
    {
        $consulta->where('fechado', false);
    }

    /**
     * DIVIDE um valor pago entre o IBTO e o instrutor, pelo percentual do curso.
     * Tudo em CENTAVOS (número inteiro, sem vírgula): a parte do IBTO é
     * arredondada para baixo e o centavo que sobrar vai para o instrutor, assim
     * a soma das duas partes é SEMPRE igual ao valor pago.
     *
     * @param  int  $valorCentavos  O que a pessoa pagou. Ex.: 25000 (R$ 250,00).
     * @return array{ibto: int, instrutor: int} Ex.: com 20% → ['ibto' => 5000, 'instrutor' => 20000].
     */
    public function divisao(int $valorCentavos): array
    {
        // intdiv = divisão que joga fora a parte quebrada. Ex.: 999 × 20 ÷ 100 = 199 (e não 199,8).
        $ibto = intdiv($valorCentavos * $this->percentual_ibto, 100);

        // O instrutor fica com o resto (inclusive o centavo do arredondamento).
        return ['ibto' => $ibto, 'instrutor' => $valorCentavos - $ibto];
    }

    /**
     * true quando o curso não cobra nada.
     */
    public function gratuito(): bool
    {
        return $this->valor_centavos === 0;
    }

    /**
     * O "tipo" do curso como o IBTO fala. Ex.: "Pago · aberto ao público".
     *
     * @return string Ex.: "Gratuito · só filiados"
     */
    public function tipo(): string
    {
        return ($this->gratuito() ? 'Gratuito' : 'Pago').' · '.mb_strtolower($this->acesso->rotulo());
    }

    /**
     * Quem aparece como instrutor na vitrine: o nome do cartaz (se preenchido)
     * ou o da conta escolhida; vazio = "A definir".
     *
     * @return string|null Ex.: 'Caetano'
     */
    public function instrutorExibido(): ?string
    {
        return $this->instrutor_nome ?: $this->instrutor?->name;
    }

    /**
     * O material incluso como lista (um selo por item na tela). O texto vem
     * separado por vírgula. Ex.: 'Certificado, Camisa do evento' → ['Certificado', 'Camisa do evento']
     *
     * @return list<string>
     */
    public function materiais(): array
    {
        // Corta nas vírgulas, tira os espaços das pontas e joga fora os vazios.
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->material_incluso)), fn (string $item) => $item !== ''));
    }

    /**
     * Endereço da imagem de capa (com a versão, para o navegador pegar a nova), ou null.
     */
    public function imagemUrl(): ?string
    {
        if ($this->imagem === null) {
            return null;
        }

        return route('cursos.imagem', ['curso' => $this->id, 'v' => $this->updated_at?->getTimestamp()]);
    }

    /**
     * A equipe dona do curso (18/10/2026, para os relatórios); null = curso do IBTO.
     *
     * @return BelongsTo<Equipe, $this>
     */
    public function equipe(): BelongsTo
    {
        return $this->belongsTo(Equipe::class);
    }

    /**
     * @return BelongsTo<AreaAtuacao, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(AreaAtuacao::class, 'area_atuacao_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function instrutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instrutor_id');
    }

    /**
     * As matrículas do curso (#41), de todas as situações.
     *
     * @return HasMany<CursoMatricula, $this>
     */
    public function matriculas(): HasMany
    {
        return $this->hasMany(CursoMatricula::class);
    }

    /**
     * Os módulos do curso (#39), já na ordem.
     *
     * @return HasMany<CursoModulo, $this>
     */
    public function modulos(): HasMany
    {
        return $this->hasMany(CursoModulo::class)->orderBy('ordem')->orderBy('id');
    }

    /**
     * Pré-requisito (#88): os cursos ANTERIORES; basta ter concluído um deles.
     *
     * @return BelongsToMany<Curso, $this>
     */
    public function requisitosCursos(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'curso_requisitos', 'curso_id', 'requisito_curso_id')->withTimestamps();
    }

    /**
     * Pré-requisito (#88): o nível mínimo para se inscrever.
     *
     * @return BelongsTo<Nivel, $this>
     */
    public function nivelMinimo(): BelongsTo
    {
        return $this->belongsTo(Nivel::class, 'nivel_minimo_id');
    }

    /**
     * Trilhas de que este curso faz parte (com a posição em cada uma).
     *
     * @return BelongsToMany<Trilha, $this>
     */
    public function trilhas(): BelongsToMany
    {
        return $this->belongsToMany(Trilha::class, 'curso_trilha')->withPivot('ordem');
    }
}
