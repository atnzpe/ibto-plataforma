<?php

/*
 * CATÁLOGO PÚBLICO de cursos (#38, bloco C4a), no desenho aprovado pelo
 * Gleyson (10/10/2026) e nos cartazes de referência do IBTO:
 * - qualquer pessoa (mesmo sem login) vê os cursos PUBLICADOS;
 * - curso "só filiados" aparece com o selo "Exclusivo para filiados";
 * - rascunho, em análise, rejeitado e cancelado NÃO aparecem para o público;
 *   o Comando Geral e quem propôs veem uma PRÉVIA;
 * - o público vê só a LISTA de módulos e aulas (nada de vídeo ou apostila).
 */

namespace App\Http\Controllers;

use App\Enums\AcessoCurso;
use App\Enums\ModalidadeCurso;
use App\Enums\SituacaoCurso;
use App\Models\AreaAtuacao;
use App\Models\Curso;
use App\Models\CursoAula;
use App\Models\CursoModulo;
use App\Models\Trilha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CatalogoController extends Controller
{
    /**
     * O catálogo: os cursos publicados, com filtros por área, modalidade,
     * acesso e busca pelo nome.
     *
     * @param  Request  $request  Ex.: /cursos?area=3&modalidade=presencial&acesso=aberto&busca=tiro
     * @return Response A tela "catalogo/index".
     */
    public function index(Request $request): Response
    {
        $area = (int) $request->query('area', 0);
        $modalidade = ModalidadeCurso::tryFrom((string) $request->query('modalidade', ''));
        $acesso = AcessoCurso::tryFrom((string) $request->query('acesso', ''));
        $busca = trim((string) $request->query('busca', ''));

        $consulta = Curso::query()
            ->where('situacao', SituacaoCurso::Publicado)
            ->with(['area:id,nome', 'instrutor:id,name'])
            // Os com data primeiro, do mais próximo ao mais distante; os sem data no fim.
            ->orderByRaw('data_inicio is null')->orderBy('data_inicio')->orderBy('titulo');

        if ($area > 0) {
            $consulta->where('area_atuacao_id', $area);
        }
        if ($modalidade !== null) {
            $consulta->where('modalidade', $modalidade);
        }
        if ($acesso !== null) {
            $consulta->where('acesso', $acesso);
        }
        if ($busca !== '') {
            $consulta->where('titulo', 'like', '%'.$busca.'%');
        }

        return Inertia::render('catalogo/index', [
            'filtros' => ['area' => $area > 0 ? $area : null, 'modalidade' => $modalidade?->value, 'acesso' => $acesso?->value, 'busca' => $busca],
            'cursos' => $consulta->limit(200)->get()->map(fn (Curso $curso) => $this->cartao($curso))->values(),
            // Só as áreas que têm curso publicado (filtro sem opções vazias).
            'areas' => AreaAtuacao::query()
                ->whereIn('id', Curso::where('situacao', SituacaoCurso::Publicado)->whereNotNull('area_atuacao_id')->select('area_atuacao_id'))
                ->orderBy('nome')->get(['id', 'nome']),
            // Trilhas ativas, para o atalho "Trilhas de formação".
            'trilhas' => Trilha::query()->where('ativa', true)->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    /**
     * A página do curso (desenho aprovado: capa, tipo, chamada, instrutor,
     * quando e onde, investimento, material incluso, conteúdo e inscrição).
     *
     * @param  Request  $request  Quem está vendo (pode ser visitante).
     * @param  Curso  $curso  O do endereço (ex.: /cursos/7).
     * @return Response A tela "catalogo/curso".
     */
    public function show(Request $request, Curso $curso): Response
    {
        // Não publicado: só o Comando Geral, quem chancela ou o dono veem (prévia); os outros, 404.
        $previa = $curso->situacao !== SituacaoCurso::Publicado;
        abort_if($previa && ! Gate::allows('curso.conteudo', $curso), 404);

        $curso->load(['area:id,nome', 'instrutor:id,name', 'modulos' => fn ($modulos) => $modulos->where('ativo', true), 'modulos.aulas' => fn ($aulas) => $aulas->where('ativa', true)]);

        return Inertia::render('catalogo/curso', [
            'curso' => [
                ...$this->cartao($curso),
                'descricao' => $curso->descricao,
                'data_fim' => $curso->data_fim?->toDateString(),
                'hora_inicio' => $curso->hora_inicio !== null ? substr($curso->hora_inicio, 0, 5) : null,
                'hora_fim' => $curso->hora_fim !== null ? substr($curso->hora_fim, 0, 5) : null,
                'instrutor_especialidade' => $curso->instrutor_especialidade,
                'materiais' => $curso->materiais(),
                'contato_whatsapp' => $curso->contato_whatsapp,
                // Link do WhatsApp (só os números, com o 55 do Brasil). Ex.: 'https://wa.me/5581999990000'.
                'whatsapp_url' => $this->linkWhatsapp($curso->contato_whatsapp),
                'situacao_rotulo' => $curso->situacao->rotulo(),
                'previa' => $previa,
                // Módulos e aulas: só os títulos, a duração e o encontro (sem vídeo nem apostila).
                'modulos' => $curso->modulos->map(fn (CursoModulo $modulo) => [
                    'id' => $modulo->id,
                    'titulo' => $modulo->titulo,
                    'aulas' => $modulo->aulas->map(fn (CursoAula $aula) => [
                        'id' => $aula->id,
                        'titulo' => $aula->titulo,
                        'duracao_minutos' => $aula->duracao_minutos,
                        'data' => $aula->data?->toDateString(),
                        'hora_inicio' => $aula->hora_inicio !== null ? substr($aula->hora_inicio, 0, 5) : null,
                        'hora_fim' => $aula->hora_fim !== null ? substr($aula->hora_fim, 0, 5) : null,
                        'quem_aplica' => $aula->quem_aplica,
                        'tem_video' => $aula->video() !== null,
                    ])->values(),
                ])->values(),
                // Trilhas de que este curso faz parte (com a etapa em cada uma).
                'trilhas' => $curso->trilhas()->where('ativa', true)->get()->map(fn (Trilha $trilha) => [
                    'id' => $trilha->id,
                    'nome' => $trilha->nome,
                    'etapa' => (int) $trilha->getRelationValue('pivot')?->getAttribute('ordem'),
                ])->values(),
            ],
            // Quem está logado (para o botão: "Entrar para se inscrever" ou a inscrição, no bloco C5).
            'logado' => $request->user() !== null,
        ]);
    }

    /**
     * Os dados do cartão do curso no catálogo (e o topo da página do curso).
     *
     * @return array<string, mixed>
     */
    private function cartao(Curso $curso): array
    {
        return [
            'id' => $curso->id,
            'titulo' => $curso->titulo,
            'chamada' => $curso->chamada,
            'imagem_url' => $curso->imagemUrl(),
            'area' => $curso->area?->nome,
            'modalidade' => $curso->modalidade->rotulo(),
            'presencial' => $curso->modalidade === ModalidadeCurso::Presencial,
            // true = "Exclusivo para filiados".
            'restrito' => $curso->acesso === AcessoCurso::Restrito,
            'tipo' => $curso->tipo(),
            'valor_centavos' => $curso->valor_centavos,
            'data_inicio' => $curso->data_inicio?->toDateString(),
            'local' => $curso->local,
            'carga_horaria' => $curso->carga_horaria,
            'vagas' => $curso->vagas,
            'instrutor' => $curso->instrutorExibido(),
        ];
    }

    /**
     * Link do WhatsApp a partir do telefone digitado, ou null.
     *
     * @param  string|null  $telefone  Ex.: '(81) 99999-0000'
     * @return string|null Ex.: 'https://wa.me/5581999990000'
     */
    private function linkWhatsapp(?string $telefone): ?string
    {
        // Só os números. Ex.: '(81) 99999-0000' → '81999990000'.
        $numeros = preg_replace('/\D/', '', (string) $telefone) ?? '';
        if (strlen($numeros) < 10) {
            return null;
        }
        // Sem o código do país: acrescenta o 55 do Brasil.
        if (! str_starts_with($numeros, '55')) {
            $numeros = '55'.$numeros;
        }

        return 'https://wa.me/'.$numeros;
    }
}
