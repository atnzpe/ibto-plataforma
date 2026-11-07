<?php

/*
 * BIBLIOTECA DE TÉCNICAS no Comando Geral (#107): a lista de TODOS os materiais
 * (ativos e retirados), o cadastro e a edição. Quem entra: "biblioteca.gerenciar"
 * (na rota). Não existe "apagar": retirar = desmarcar "ativo"; o material some
 * da lista de quem estuda, mas continua guardado e na auditoria (dá para voltar).
 * Os arquivos (vídeo e apostila) ficam no disco PRIVADO, como nas aulas (#39).
 */

namespace App\Http\Controllers\Biblioteca;

use App\Http\Controllers\Controller;
use App\Http\Requests\Biblioteca\MaterialRequest;
use App\Models\AreaAtuacao;
use App\Models\Auditoria;
use App\Models\BibliotecaMaterial;
use App\Models\CursoAula;
use App\Models\Nivel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MaterialAdminController extends Controller
{
    /**
     * Lista com busca pelo nome da técnica (ativos e retirados, os mais novos primeiro).
     *
     * @param  Request  $request  Ex.: /comando-geral/biblioteca?busca=faca
     * @return Response A tela "biblioteca-admin/index".
     */
    public function index(Request $request): Response
    {
        // O que a pessoa digitou na busca, sem espaços nas pontas.
        $busca = trim((string) $request->query('busca', ''));

        $consulta = BibliotecaMaterial::query()->with(['area', 'nivelMinimo'])->latest('id');
        // Com busca: só os que têm o texto no nome. Sem busca: todos.
        if ($busca !== '') {
            $consulta->where('titulo', 'like', '%'.$busca.'%');
        }

        return Inertia::render('biblioteca-admin/index', [
            'filtros' => ['busca' => $busca],
            // Até 300 por vez (a biblioteca do piloto é pequena; com mais, entra a paginação).
            'materiais' => $consulta->limit(300)->get()->map(fn (BibliotecaMaterial $material) => [
                'id' => $material->id,
                'titulo' => $material->titulo,
                'area' => $material->area?->nome,
                'nivel' => $material->nivelMinimo?->nome(),
                'tem_video' => $material->video() !== null,
                'tem_apostila' => $material->apostila !== null,
                'ativo' => $material->ativo,
                'atualizado_em' => $material->updated_at?->format('d/m/Y'),
            ])->values(),
        ]);
    }

    /**
     * Formulário vazio.
     *
     * @return Response A tela "biblioteca-admin/formulario".
     */
    public function create(): Response
    {
        return Inertia::render('biblioteca-admin/formulario', ['material' => null, ...$this->opcoes()]);
    }

    /**
     * Grava o material novo (com o vídeo, a apostila, o autor e a auditoria).
     *
     * @param  MaterialRequest  $request  O formulário, já conferido.
     * @return RedirectResponse A lista, com a mensagem de sucesso.
     */
    public function store(MaterialRequest $request): RedirectResponse
    {
        $material = DB::transaction(function () use ($request): BibliotecaMaterial {
            $material = new BibliotecaMaterial($request->paraGravar());
            // Quem enviou (não vem do formulário: ninguém consegue se passar por outra pessoa).
            $material->forceFill(['autor_id' => $request->user()?->id])->save();
            $this->aplicarArquivos($material, $request);
            Auditoria::registrar('BIBLIOTECA_MATERIAL_CRIADO', ['material_id' => $material->id]);

            return $material;
        });

        return to_route('comando-geral.biblioteca.index')->with('sucesso', "Material \"{$material->titulo}\" salvo na biblioteca.");
    }

    /**
     * Edição: o formulário já preenchido.
     *
     * @param  BibliotecaMaterial  $biblioteca  O material do endereço (ex.: /comando-geral/biblioteca/3/edit).
     * @return Response A tela "biblioteca-admin/formulario".
     */
    public function edit(BibliotecaMaterial $biblioteca): Response
    {
        return Inertia::render('biblioteca-admin/formulario', [
            'material' => [
                'id' => $biblioteca->id,
                'titulo' => $biblioteca->titulo,
                'descricao' => $biblioteca->descricao,
                'area_atuacao_id' => $biblioteca->area_atuacao_id,
                'nivel_minimo_id' => $biblioteca->nivel_minimo_id,
                // Qual opção do vídeo abre marcada: a do arquivo, se já tem um enviado.
                'fonte_video' => $biblioteca->video_arquivo !== null ? 'arquivo' : 'link',
                'video_url' => $biblioteca->video_url,
                'tem_video_arquivo' => $biblioteca->video_arquivo !== null,
                'tem_apostila' => $biblioteca->apostila !== null,
                'ativo' => $biblioteca->ativo,
            ],
            ...$this->opcoes(),
        ]);
    }

    /**
     * Grava as mudanças (e troca ou tira os arquivos, se pedido), com auditoria.
     * Retirar e voltar a mostrar também passam por aqui (o campo "ativo").
     *
     * @param  MaterialRequest  $request  O formulário, já conferido.
     * @param  BibliotecaMaterial  $biblioteca  O material que está sendo editado.
     * @return RedirectResponse De volta à edição, com a mensagem.
     */
    public function update(MaterialRequest $request, BibliotecaMaterial $biblioteca): RedirectResponse
    {
        DB::transaction(function () use ($request, $biblioteca): void {
            // Guarda se estava ativo, para a auditoria dizer "retirado" ou "voltou".
            $estavaAtivo = $biblioteca->ativo;
            $biblioteca->update($request->paraGravar());
            $this->aplicarArquivos($biblioteca, $request);

            // Mudou o "ativo": registra com nome próprio (fica fácil achar quem retirou).
            $acao = match (true) {
                $estavaAtivo && ! $biblioteca->ativo => 'BIBLIOTECA_MATERIAL_RETIRADO',
                ! $estavaAtivo && $biblioteca->ativo => 'BIBLIOTECA_MATERIAL_REATIVADO',
                default => 'BIBLIOTECA_MATERIAL_EDITADO',
            };
            Auditoria::registrar($acao, ['material_id' => $biblioteca->id]);
        });

        return back()->with('sucesso', "Material \"{$biblioteca->titulo}\" salvo.");
    }

    /**
     * As listas de escolha do formulário: as áreas ativas e os níveis ativos
     * (na ordem da hierarquia), e o limite do vídeo para a tela avisar.
     *
     * @return array<string, mixed>
     */
    private function opcoes(): array
    {
        return [
            'areas' => AreaAtuacao::query()->where('ativa', true)->orderBy('nome')->get(['id', 'nome']),
            'niveis' => Nivel::emOrdem(Nivel::ativos()->get())->map(fn (Nivel $nivel) => ['id' => $nivel->id, 'nome' => $nivel->nome()])->values(),
            // Em MB, para a tela. Ex.: 200.
            'video_maximo_mb' => intdiv(CursoAula::VIDEO_MAXIMO_KB, 1024),
        ];
    }

    /**
     * Aplica o vídeo e a apostila do formulário:
     * - vídeo por link: apaga o arquivo enviado antes (se havia);
     * - vídeo por arquivo: guarda o novo (se veio) e apaga o antigo;
     * - apostila nova: guarda e apaga a antiga; "remover apostila": apaga.
     * Os arquivos antigos só saem DEPOIS que o novo foi gravado e salvo no banco
     * (se algo der errado no meio, o material não fica sem nada).
     *
     * @param  BibliotecaMaterial  $material  O material já salvo.
     * @param  MaterialRequest  $request  O formulário (fonte_video, os arquivos e remover_apostila).
     */
    private function aplicarArquivos(BibliotecaMaterial $material, MaterialRequest $request): void
    {
        // Escolheu link: o vídeo enviado antes (se havia) sai do servidor.
        if ($request->input('fonte_video') === 'link') {
            $this->trocar($material, 'video_arquivo', null);
        } elseif ($request->file('video_arquivo') !== null) {
            // Escolheu arquivo e enviou um novo: troca. (Sem arquivo novo: mantém o que existe.)
            $this->trocar($material, 'video_arquivo', $request->file('video_arquivo'));
        }

        // Apostila: uma nova vale mais que o "remover" (a pessoa trocou o PDF).
        if ($request->file('apostila') !== null) {
            $this->trocar($material, 'apostila', $request->file('apostila'));
        } elseif ($request->boolean('remover_apostila')) {
            $this->trocar($material, 'apostila', null);
        }
    }

    /**
     * Troca (ou tira) um arquivo do material no disco PRIVADO.
     *
     * @param  BibliotecaMaterial  $material  O material.
     * @param  'video_arquivo'|'apostila'  $coluna  Qual arquivo. Ex.: 'apostila'.
     * @param  UploadedFile|null  $arquivo  O arquivo novo; null = só tirar o que existe.
     */
    private function trocar(BibliotecaMaterial $material, string $coluna, ?UploadedFile $arquivo): void
    {
        $disco = Storage::disk('local');
        // O caminho que estava gravado antes (pode ser vazio).
        $antigo = $material->getAttribute($coluna);

        // Nada para tirar e nada para pôr: não faz nada.
        if ($arquivo === null && $antigo === null) {
            return;
        }

        $caminho = null;
        // Veio arquivo novo: guarda com nome aleatório (o nome original pode ter
        // acentos ou dados pessoais) dentro da pasta da biblioteca.
        if ($arquivo !== null) {
            $caminho = $disco->putFile(BibliotecaMaterial::PASTA, $arquivo);
            // O disco recusou (ex.: cheio): para tudo, e a transação desfaz o banco.
            if ($caminho === false) {
                throw new \RuntimeException('BIBLIOTECA_ARQUIVO_NAO_GRAVADO');
            }
        }

        $material->forceFill([$coluna => $caminho])->save();
        // Só apaga o antigo depois que o novo caminho foi salvo no banco.
        if ($antigo !== null) {
            $disco->delete($antigo);
        }
    }
}
