<?php

/*
 * PROVA DE UM CURSO (#43): múltipla escolha, com nota mínima e número de
 * tentativas. As perguntas e as alternativas ficam nos modelos ligados; o
 * gabarito (alternativa certa) nunca vai para a tela do aluno.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $curso_id
 * @property string $titulo Ex.: 'Prova final'.
 * @property string|null $instrucoes
 * @property int $nota_minima De 0 a 100. Ex.: 70.
 * @property int $tentativas_max Ex.: 3.
 * @property bool $ativa
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Curso $curso
 */
class CursoProva extends Model
{
    protected $table = 'curso_provas';

    /**
     * Os campos que o formulário do instrutor preenche.
     *
     * @var list<string>
     */
    protected $fillable = ['titulo', 'instrucoes', 'nota_minima', 'tentativas_max', 'ativa'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['nota_minima' => 'integer', 'tentativas_max' => 'integer', 'ativa' => 'boolean'];
    }

    /**
     * O curso da prova.
     *
     * @return BelongsTo<Curso, $this>
     */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /**
     * As perguntas, em ordem.
     *
     * @return HasMany<CursoProvaQuestao, $this>
     */
    public function questoes(): HasMany
    {
        return $this->hasMany(CursoProvaQuestao::class, 'prova_id')->orderBy('ordem')->orderBy('id');
    }

    /**
     * As tentativas dos alunos.
     *
     * @return HasMany<CursoProvaTentativa, $this>
     */
    public function tentativas(): HasMany
    {
        return $this->hasMany(CursoProvaTentativa::class, 'prova_id');
    }
}
