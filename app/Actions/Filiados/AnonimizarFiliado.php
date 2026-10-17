<?php

/*
 * ANONIMIZAR UM FILIADO A PEDIDO DELE (LGPD, art. 18, #47).
 *
 * Apaga o que identifica a pessoa e mantém só o que a lei ou a contabilidade
 * exigem guardar, sem nome:
 * - SOME: nome (vira "Filiado anonimizado (registro)"), e-mail, senha, CPF,
 *   nascimento, telefone, cidade, fotos (perfil e 3x4, os arquivos também),
 *   autorização de imagem, quem indicou e o nome nos certificados;
 * - FICA: o número de registro, a equipe, o nível, as matrículas, as presenças
 *   e os lançamentos do financeiro (números, sem nome), para os relatórios e a
 *   prestação de contas continuarem fechando;
 * - a conta é desativada e o QR da carteirinha deixa de valer (código novo).
 * NÃO TEM VOLTA. Fica na auditoria (só números e o motivo).
 * Como tirar a etiqueta com o nome de uma pasta e deixar só o número do arquivo.
 */

namespace App\Actions\Filiados;

use App\Models\Auditoria;
use App\Models\CursoCertificado;
use App\Models\Filiado;
use App\Support\FotoDePerfil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AnonimizarFiliado
{
    /**
     * Anonimiza o filiado e a conta dele.
     *
     * @param  Filiado  $filiado  Quem pediu a anonimização.
     * @param  string  $motivo  Por quê. Ex.: 'Pedido do titular por e-mail em 17/10/2026'.
     */
    public function executar(Filiado $filiado, string $motivo): void
    {
        // Rótulo que fica no lugar do nome. Ex.: 'Filiado anonimizado (IBTO-0042)'.
        $rotulo = 'Filiado anonimizado ('.$filiado->registro.')';
        $usuario = $filiado->usuario;
        // Os arquivos de foto são apagados só DEPOIS de gravar no banco (se o banco falhar, nada se perde pela metade).
        $fotos = [$filiado->foto, $usuario?->foto];

        DB::transaction(function () use ($filiado, $usuario, $rotulo, $motivo): void {
            $filiado->forceFill([
                'cpf' => null, 'cpf_hash' => null, 'nascimento' => null, 'telefone' => null,
                'municipio' => null, 'uf' => null, 'foto' => null,
                'autoriza_imagem_rede' => false, 'autoriza_imagem_em' => null,
                'status' => 'inativo',
                // Código novo: o QR da carteirinha antiga não abre mais nada.
                'codigo_validacao' => Filiado::novoCodigoValidacao(),
            ])->save();

            // A conta: sem nome, sem e-mail e com uma senha sorteada que ninguém sabe; desativada.
            if ($usuario !== null) {
                $usuario->forceFill([
                    'name' => $rotulo, 'email' => null, 'email_verified_at' => null,
                    'password' => Hash::make(Str::random(64)), 'remember_token' => null,
                    'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null,
                    'foto' => null, 'indicado_por' => null, 'cadastro_motivo' => null,
                    'desativada_em' => now(),
                ])->save();

                // O nome impresso nos certificados dele também sai.
                CursoCertificado::query()
                    ->whereHas('matricula', fn ($matricula) => $matricula->where('user_id', $usuario->id))
                    ->update(['nome' => $rotulo]);
            }

            Auditoria::registrar('FILIADO_ANONIMIZADO', ['filiado_id' => $filiado->id, 'motivo' => Str::limit($motivo, 200)]);
        });

        // Agora sim, apaga os arquivos das fotos.
        foreach ($fotos as $foto) {
            FotoDePerfil::apagar($foto);
        }
    }
}
