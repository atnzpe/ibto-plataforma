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

use App\Actions\Fotos\VendaFotos;
use App\Enums\AcessoCurso;
use App\Enums\ModalidadeCurso;
use App\Enums\SituacaoCurso;
use App\Enums\SituacaoMatricula;
use App\Models\AreaAtuacao;
use App\Models\Curso;
use App\Models\CursoAula;
use App\Models\CursoAulaAssistida;
use App\Models\CursoModulo;
use App\Models\CursoPresenca;
use App\Models\FotoAlbumVenda;
use App\Models\Trilha;
use App\Support\Calendario;
use App\Support\DadosPagamento;
use App\Support\PixCopiaECola;
use App\Support\QrCode;
use App\Support\Requisitos;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
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
            // Curso FECHADO (ex.: Formação de instrutor) não aparece no site.
            ->noSite()
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
                ->whereIn('id', Curso::where('situacao', SituacaoCurso::Publicado)->noSite()->whereNotNull('area_atuacao_id')->select('area_atuacao_id'))
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

        // Curso FECHADO: a página só abre para quem cuida do curso (Gate
        // curso.conteudo) ou para quem foi matriculado nele. Os outros recebem
        // 404 ("não existe"), para nem saber que o curso existe.
        abort_if(
            $curso->fechado
                && ! Gate::allows('curso.conteudo', $curso)
                && ! $curso->matriculas()->where('user_id', $request->user()?->id)->exists(),
            404,
        );

        $curso->load(['area:id,nome', 'instrutor:id,name', 'modulos' => fn ($modulos) => $modulos->where('ativo', true), 'modulos.aulas' => fn ($aulas) => $aulas->where('ativa', true)]);

        // A situação de cada aula PARA ESTA PESSOA (desenho de 10/10/2026:
        // "Presença confirmada / Aguardando / ✓"). Vazio para visitante ou
        // quem não tem matrícula confirmada. Ex.: [31 => 'presente', 32 => 'aguardando'].
        $minhaSituacao = $this->minhaSituacaoPorAula($request, $curso);

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
                        // Janela do cronograma (C4d): a introdução PÚBLICA e os links de lembrete.
                        'introducao' => $aula->introducao,
                        'lembrete_url' => $aula->data !== null ? route('catalogo.lembrete', ['curso' => $curso->id, 'aula' => $aula->id]) : null,
                        'google_agenda_url' => $this->linkGoogleAgenda($curso, $aula),
                        // 'presente' | 'falta' | 'aguardando' (encontro) | 'assistida' (aula online) | null.
                        'minha_situacao' => $minhaSituacao[$aula->id] ?? null,
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
            // Vagas que ainda restam (#41): null = sem limite. Ex.: 3.
            // Vagas já ocupadas (aguardando ou confirmadas), para a barra "14 de 20 preenchidas".
            'vagas_preenchidas' => $curso->matriculas()->ocupandoVaga()->count(),
            'vagas_restantes' => $curso->vagas !== null
                ? max(0, $curso->vagas - $curso->matriculas()->ocupandoVaga()->count())
                : null,
            // A matrícula da pessoa logada que ainda vale (aguardando ou confirmada), ou null.
            'matricula' => $this->matriculaDe($request, $curso),
            // Venda de fotos (#90): os álbuns deste curso que a pessoa pode ver (fez o curso e a venda está ligada).
            'albuns_fotos' => FotoAlbumVenda::query()->where('curso_id', $curso->id)->where('ativo', true)->where('expirado', false)->get()
                ->filter(fn (FotoAlbumVenda $album) => VendaFotos::podeVer($album, $request->user()))
                ->map(fn (FotoAlbumVenda $album) => ['id' => $album->id, 'titulo' => $album->titulo])->values(),
            // Pré-requisitos (#88): cada item com cumprido true/false (visitante: null, só a lista).
            'requisitos' => Requisitos::avaliar($curso, $request->user()),
            // Pode entrar na sala de aula (#39, C4c)? A equipe do curso agora; os inscritos no C5.
            // Visitante sem login: false (o Gate recusa quem não está logado).
            'pode_assistir' => Gate::allows('curso.conteudo', $curso),
        ]);
    }

    /**
     * A TRILHA em FLUXOGRAMA (#38, bloco C4b; referência: o fluxograma da Solyd
     * que o Gleyson mandou): as etapas de cima para baixo e, em cada etapa, os
     * cursos lado a lado, com as horas e o número de aulas. Curso ainda não
     * publicado aparece como "Em breve", sem link.
     *
     * @param  Trilha  $trilha  A do endereço (ex.: /trilhas/2).
     * @return Response A tela "catalogo/trilha".
     */
    public function trilha(Trilha $trilha): Response
    {
        // Trilha desativada: só o Comando Geral vê (prévia); os outros, 404.
        abort_if(! $trilha->ativa && ! Gate::allows('cursos.gerenciar'), 404);

        // Os cursos da trilha (menos os cancelados), já na ordem das etapas.
        // Os FECHADOS também ficam de fora: a trilha é pública.
        $cursos = $trilha->cursos()->where('situacao', '!=', SituacaoCurso::Cancelado->value)->noSite()->get();

        // Agrupa por etapa: [1 => [cursos], 2 => [cursos, cursos], ...].
        $etapas = $cursos->groupBy(fn (Curso $curso) => (int) $curso->getRelationValue('pivot')?->getAttribute('ordem'))
            ->map(fn ($daEtapa, $etapa) => [
                'etapa' => $etapa,
                'cursos' => $daEtapa->map(fn (Curso $curso) => [
                    'id' => $curso->id,
                    'titulo' => $curso->titulo,
                    'imagem_url' => $curso->situacao === SituacaoCurso::Publicado ? $curso->imagemUrl() : null,
                    'carga_horaria' => $curso->carga_horaria,
                    // Número de aulas ativas, em módulos ativos (o "📖 6" do exemplo).
                    'aulas' => CursoAula::query()->where('ativa', true)
                        ->whereHas('modulo', fn ($modulo) => $modulo->where('curso_id', $curso->id)->where('ativo', true))
                        ->count(),
                    'modalidade' => $curso->modalidade->rotulo(),
                    // false = "Em breve" (ainda não publicado): aparece, mas sem link.
                    'disponivel' => $curso->situacao === SituacaoCurso::Publicado,
                ])->values(),
            ])->sortKeys()->values();

        return Inertia::render('catalogo/trilha', [
            'trilha' => ['id' => $trilha->id, 'nome' => $trilha->nome, 'descricao' => $trilha->descricao],
            'etapas' => $etapas,
            // As outras trilhas ativas, para trocar pelo topo ("Todas as trilhas").
            'trilhas' => Trilha::query()->where('ativa', true)->orderBy('nome')->get(['id', 'nome']),
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

    /**
     * LEMBRETE do encontro (#38, bloco C4d): baixa o arquivo .ics para a
     * agenda do celular ou do computador, com aviso 1 dia e 1 hora antes.
     * Público para curso publicado (como o cronograma); prévia para a equipe.
     *
     * @param  Curso  $curso  Ex.: /cursos/7/aulas/31/lembrete → curso 7.
     * @param  CursoAula  $aula  Ex.: aula 31.
     * @return HttpResponse O arquivo "encontro-ibto-31.ics".
     */
    public function lembrete(Curso $curso, CursoAula $aula): HttpResponse
    {
        // Curso não publicado: só quem pode ver a prévia; os outros, 404.
        abort_if($curso->situacao !== SituacaoCurso::Publicado && ! Gate::allows('curso.conteudo', $curso), 404);

        $quando = $this->momentos($aula);
        // Aula de outro curso, desativada, de módulo desativado ou sem data: 404.
        abort_if(
            $quando === null
                || $aula->modulo->curso_id !== $curso->id
                || ! $aula->ativa
                || ! $aula->modulo->ativo,
            404,
        );

        // A descrição: a introdução, quem aplica e o link da página do curso.
        $descricao = implode("\n", array_filter([
            $aula->introducao,
            $aula->quem_aplica !== null ? "Com: {$aula->quem_aplica}" : null,
            'Detalhes: '.route('catalogo.curso', $curso->id),
        ]));

        $ics = Calendario::evento(
            uid: "aula-{$aula->id}@".request()->getHost(),
            titulo: "{$curso->titulo} — {$aula->titulo}",
            inicio: $quando['inicio'],
            fim: $quando['fim'],
            diaInteiro: $quando['dia_inteiro'],
            local: $curso->local,
            descricao: $descricao,
            url: route('catalogo.curso', $curso->id),
        );

        // text/calendar: o celular reconhece e oferece "Adicionar à agenda".
        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"encontro-ibto-{$aula->id}.ics\"",
        ]);
    }

    /**
     * A situação de cada aula para a pessoa logada (só com matrícula CONFIRMADA):
     * - encontro (aula com data): 'presente' ou 'falta' depois da chamada;
     *   'aguardando' enquanto a chamada não foi feita;
     * - aula sem data (online): 'assistida' quando a pessoa marcou.
     *
     * @param  Request  $request  Quem está vendo (pode ser visitante).
     * @param  Curso  $curso  O curso da página, com os módulos e aulas já carregados.
     * @return array<int, string> [id da aula => situação]. Ex.: [31 => 'presente']. Vazio = nada a mostrar.
     */
    private function minhaSituacaoPorAula(Request $request, Curso $curso): array
    {
        $usuario = $request->user();
        // Visitante: nada a mostrar.
        if ($usuario === null) {
            return [];
        }

        // Só quem tem a matrícula confirmada tem situação nas aulas.
        $matricula = $curso->matriculas()
            ->where('user_id', $usuario->id)
            ->where('situacao', SituacaoMatricula::Confirmada)
            ->latest('id')
            ->first();
        if ($matricula === null) {
            return [];
        }

        // Todas as aulas ativas do curso, numa lista só.
        $aulas = $curso->modulos->flatMap(fn (CursoModulo $modulo) => $modulo->aulas);
        // Presenças desta matrícula: [aula_id => true/false].
        $presencas = CursoPresenca::query()->where('matricula_id', $matricula->id)->pluck('presente', 'aula_id');
        // Aulas que a pessoa marcou como assistidas. Ex.: [33, 34].
        $assistidas = CursoAulaAssistida::query()
            ->where('user_id', $usuario->id)
            ->whereIn('aula_id', $aulas->pluck('id'))
            ->pluck('aula_id')
            ->all();

        $situacoes = [];
        foreach ($aulas as $aula) {
            if ($aula->data !== null) {
                // Encontro: sem chamada ainda = aguardando; com chamada = presente ou falta.
                $situacoes[$aula->id] = $aula->chamada_feita_em === null
                    ? 'aguardando'
                    : (($presencas[$aula->id] ?? false) ? 'presente' : 'falta');
            } elseif (in_array($aula->id, $assistidas)) {
                // Aula online marcada como assistida. Não marcada: sem situação (null).
                $situacoes[$aula->id] = 'assistida';
            }
        }

        return $situacoes;
    }

    /**
     * A matrícula da pessoa logada neste curso, para o quadro da inscrição (#41).
     * Leva o PIX só quando ela está aguardando pagamento.
     *
     * @param  Request  $request  Quem está vendo (pode ser visitante).
     * @param  Curso  $curso  O curso da página.
     * @return array<string, mixed>|null null = visitante ou sem matrícula valendo.
     */
    private function matriculaDe(Request $request, Curso $curso): ?array
    {
        $usuario = $request->user();
        // Visitante: sem matrícula.
        if ($usuario === null) {
            return null;
        }

        // A mais recente que ainda ocupa vaga (as canceladas não contam).
        $matricula = $curso->matriculas()->ocupandoVaga()->where('user_id', $usuario->id)->latest('id')->first();
        if ($matricula === null) {
            return null;
        }

        $aguardando = $matricula->situacao === SituacaoMatricula::AguardandoPagamento;
        // Dados para pagamento (do curso ou do IBTO, conforme a chave liga/desliga),
        // só para quem precisa pagar. Ver App\Support\DadosPagamento.
        $pagamento = $aguardando ? DadosPagamento::doCurso($curso) : null;

        return [
            'id' => $matricula->id,
            'situacao' => $matricula->situacao->value,
            'situacao_rotulo' => $matricula->situacao->rotulo(),
            'valor_centavos' => $matricula->valor_centavos,
            // Cupom (#89): o código, o preço cheio e o desconto (null/0 sem cupom).
            'cupom' => $matricula->cupom?->codigo,
            'valor_cheio_centavos' => $matricula->valor_cheio_centavos,
            'desconto_centavos' => $matricula->desconto_centavos,
            'tem_comprovante' => $matricula->comprovante !== null,
            'pagamento' => $pagamento,
            // Pix copia e cola + QR code (#83), com o valor exato e o identificador da
            // matrícula; só quando há chave Pix cadastrada e algo a pagar.
            'pix' => $pagamento !== null && $pagamento['pix_chave'] !== '' && $matricula->valor_centavos > 0
                ? $this->pixDaMatricula($pagamento, $matricula->id, $matricula->valor_centavos)
                : null,
        ];
    }

    /**
     * Monta o Pix copia e cola e o QR code de uma matrícula (#83).
     *
     * @param  array{pix_chave: string, favorecido: string}  $pagamento  Chave e favorecido (do curso ou do IBTO).
     * @param  int  $matriculaId  O número da matrícula (vira o identificador). Ex.: 1234.
     * @param  int  $valorCentavos  O valor exato. Ex.: 15000 (R$ 150,00).
     * @return array{copia_e_cola: string, qr_svg: string, txid: string}
     */
    private function pixDaMatricula(array $pagamento, int $matriculaId, int $valorCentavos): array
    {
        // Identificador que aparece no extrato e na tela de matrículas. Ex.: 'IBTOMAT1234'.
        $txid = PixCopiaECola::txidDaMatricula($matriculaId);
        // Sem favorecido cadastrado: usa a sigla (o padrão exige um nome).
        $favorecido = $pagamento['favorecido'] !== '' ? $pagamento['favorecido'] : 'IBTO';
        $codigo = PixCopiaECola::gerar($pagamento['pix_chave'], $favorecido, (string) config('app.pix_cidade'), $valorCentavos, $txid);

        return [
            'copia_e_cola' => $codigo,
            // 220 px: acima do mínimo de 200 px para a câmera do celular ler com folga.
            'qr_svg' => QrCode::svg($codigo, 220),
            'txid' => $txid,
        ];
    }

    /**
     * Link "Adicionar ao Google Agenda" (abre o Google com o encontro preenchido),
     * ou null quando a aula não tem data.
     *
     * @param  Curso  $curso  Para o título e o local.
     * @param  CursoAula  $aula  Para a data, o horário e a introdução.
     * @return string|null Ex.: 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=...'
     */
    private function linkGoogleAgenda(Curso $curso, CursoAula $aula): ?string
    {
        $quando = $this->momentos($aula);
        // Sem data: sem link.
        if ($quando === null) {
            return null;
        }

        // Dia inteiro: só as datas, sem fuso (o fim é o dia seguinte). Com hora: em UTC ("Z").
        $datas = $quando['dia_inteiro']
            ? $quando['inicio']->format('Ymd').'/'.$quando['fim']->format('Ymd')
            : $quando['inicio']->copy()->utc()->format('Ymd\THis\Z').'/'.$quando['fim']->copy()->utc()->format('Ymd\THis\Z');

        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => "{$curso->titulo} — {$aula->titulo}",
            'dates' => $datas,
            'details' => trim(($aula->introducao ?? '')."\n".route('catalogo.curso', $curso->id)),
            'location' => $curso->local ?? '',
        ]);
    }

    /**
     * Começo e fim do encontro, no horário de Brasília (fuso da plataforma).
     * Sem hora de início: o dia inteiro. Sem hora de fim: a duração da aula ou 1 hora.
     *
     * @param  CursoAula  $aula  A aula. Ex.: data 10/10/2026, das 09:00 às 12:00.
     * @return array{inicio: CarbonInterface, fim: CarbonInterface, dia_inteiro: bool}|null null = aula sem data.
     */
    private function momentos(CursoAula $aula): ?array
    {
        // Sem data, não há encontro para lembrar.
        if ($aula->data === null) {
            return null;
        }

        $dia = $aula->data->toDateString();

        // Sem hora de início: o evento ocupa o dia todo (fim = dia seguinte, regra do formato).
        if ($aula->hora_inicio === null) {
            $inicio = Carbon::parse($dia);

            return ['inicio' => $inicio, 'fim' => $inicio->copy()->addDay(), 'dia_inteiro' => true];
        }

        $inicio = Carbon::parse("{$dia} {$aula->hora_inicio}");
        // Fim: o horário digitado; senão, a duração; senão, 1 hora.
        $fim = $aula->hora_fim !== null
            ? Carbon::parse("{$dia} {$aula->hora_fim}")
            : $inicio->copy()->addMinutes($aula->duracao_minutos ?? 60);

        return ['inicio' => $inicio, 'fim' => $fim, 'dia_inteiro' => false];
    }
}
