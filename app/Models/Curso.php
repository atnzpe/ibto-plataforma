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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $titulo
 * @property string|null $descricao
 * @property int|null $area_atuacao_id
 * @property ModalidadeCurso $modalidade
 * @property AcessoCurso $acesso
 * @property int $valor_centavos
 * @property int|null $vagas
 * @property Carbon|null $data_inicio
 * @property Carbon|null $data_fim
 * @property string|null $hora_inicio
 * @property string|null $hora_fim
 * @property string|null $local
 * @property int|null $carga_horaria
 * @property int|null $instrutor_id
 * @property string|null $imagem
 * @property string|null $link_pagamento
 * @property SituacaoCurso $situacao
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
// "imagem" fica de fora: quem grava é o App\Support\ImagemPublica.
#[Fillable(['titulo', 'descricao', 'area_atuacao_id', 'modalidade', 'acesso', 'valor_centavos', 'vagas', 'data_inicio', 'data_fim', 'hora_inicio', 'hora_fim', 'local', 'carga_horaria', 'instrutor_id', 'link_pagamento', 'situacao'])]
class Curso extends Model
{
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
            'valor_centavos' => 'integer',
            'vagas' => 'integer',
            'carga_horaria' => 'integer',
            'data_inicio' => 'date',
            'data_fim' => 'date',
        ];
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
     * Trilhas de que este curso faz parte (com a posição em cada uma).
     *
     * @return BelongsToMany<Trilha, $this>
     */
    public function trilhas(): BelongsToMany
    {
        return $this->belongsToMany(Trilha::class, 'curso_trilha')->withPivot('ordem');
    }
}
