<?php

/**
 * Atualiza a pasta vendor/ (as bibliotecas do PHP) no servidor da HostGator.
 *
 * O deploy (FTPS) não manda o vendor solto, porque são ~8.300 arquivos e a
 * conexão cai. Ele manda um único vendor.zip e o arquivo VENDOR_VERSAO, com a
 * "impressão digital" das bibliotecas. O Cron Job roda este script antes do
 * artisan: se a versão publicada for diferente da última aplicada, ele
 * descompacta o zip numa pasta nova e só troca pela atual se tudo deu certo.
 *
 * Por que PHP puro, sem Laravel: o Laravel (e o artisan) precisa do vendor para
 * ligar. Se o vendor estiver velho ou pela metade, o artisan nem abre; então
 * quem conserta o vendor não pode depender dele.
 *
 * Uso pelo Cron: php deploy/atualizar-vendor.php
 * Guia: docs/publicacao-homologacao.md.
 */

declare(strict_types=1);

/**
 * Descompacta o vendor.zip publicado, se ele ainda não foi aplicado.
 *
 * @param  string  $raiz  Pasta do projeto, onde ficam o artisan e o vendor.zip.
 *                        Ex.: '/home1/edua1187/cursos-homolog.ib-to.org'.
 * @return string Um código que diz o que aconteceu:
 *                VENDOR_NADA_A_FAZER (nada novo), VENDOR_APLICADO (trocou o
 *                vendor), VENDOR_EM_ANDAMENTO (outro Cron já está trocando),
 *                VENDOR_SEM_ZIP (o PHP do servidor não abre zip) ou
 *                VENDOR_FALHOU (deu erro; o vendor antigo ficou intacto).
 */
function atualizarVendor(string $raiz): string
{
    // Arquivo que o deploy grava com a versão das bibliotecas publicadas.
    $arquivoPublicado = $raiz.'/VENDOR_VERSAO';
    // Arquivo que ESTE script grava com a última versão que ele aplicou.
    $arquivoAplicado = $raiz.'/storage/app/vendor-aplicado';

    // Sem VENDOR_VERSAO é a máquina de um desenvolvedor (o arquivo só existe no
    // pacote do deploy): não há nada para atualizar. Se existir, segue.
    if (! is_file($arquivoPublicado)) {
        return 'VENDOR_NADA_A_FAZER';
    }

    // (Trecho escrito pelo Gleyson em 07/10/2026, no modelo do PosDeploy.php;
    // a leitura passou para lerVersao() depois que o PHPStan apontou o caso
    // em que file_get_contents devolve false.)
    // Lê a versão publicada pelo deploy.
    $publicada = lerVersao($arquivoPublicado);
    // Lê a última versão aplicada ('' na primeira vez, quando o arquivo não existe).
    $aplicada = lerVersao($arquivoAplicado);
    // Se a versão publicada veio vazia OU é igual à aplicada, não há nada novo.
    // Se não for (versão diferente), o código continua e troca o vendor.
    if ($publicada === '' || $publicada === $aplicada) {
        return 'VENDOR_NADA_A_FAZER';
    }

    // A HostGator pode não ter a extensão zip no PHP. Sem ela não dá para
    // descompactar; o código vai para o log para a equipe pedir ao suporte.
    if (! class_exists(ZipArchive::class)) {
        registrarNoLog($raiz, 'VENDOR_SEM_ZIP');

        return 'VENDOR_SEM_ZIP';
    }

    // Trava: o Cron roda a cada 5 minutos e descompactar pode demorar. Se outro
    // Cron já estiver trocando o vendor, este sai sem fazer nada.
    $trava = fopen($raiz.'/storage/app/vendor.lock', 'c');
    // LOCK_EX = só um por vez; LOCK_NB = não espera, desiste se estiver ocupado.
    if ($trava === false || ! flock($trava, LOCK_EX | LOCK_NB)) {
        return 'VENDOR_EM_ANDAMENTO';
    }

    // Pastas usadas na troca: o vendor atual, a nova (descompactada) e a
    // antiga (o vendor atual guardado até a nova estar no lugar).
    $vendor = $raiz.'/vendor';
    $novo = $raiz.'/vendor-novo';
    $antigo = $raiz.'/vendor-antigo';

    try {
        // Restos de uma tentativa anterior que caiu no meio são apagados antes.
        apagarPasta($novo);
        apagarPasta($antigo);

        // Descompacta numa pasta separada: se falhar no meio, o vendor que
        // está funcionando nem foi tocado.
        $zip = new ZipArchive;
        // open() devolve true quando abriu; se o zip estiver faltando ou
        // corrompido, devolve um número de erro e a gente desiste.
        if ($zip->open($raiz.'/vendor.zip') !== true || ! $zip->extractTo($novo)) {
            throw new RuntimeException('zip');
        }
        $zip->close();

        // Conferência mínima: sem o autoload.php, a pasta nova não serve.
        if (! is_file($novo.'/autoload.php')) {
            throw new RuntimeException('autoload');
        }

        // A troca: guarda o vendor atual (se existir) e põe o novo no lugar.
        // rename() é quase instantâneo, então o site fica sem vendor só por
        // uma fração de segundo.
        if (is_dir($vendor) && ! rename($vendor, $antigo)) {
            throw new RuntimeException('guardar');
        }
        if (! rename($novo, $vendor)) {
            // Não deu para pôr o novo: devolve o antigo ao lugar e desiste.
            rename($antigo, $vendor);
            throw new RuntimeException('trocar');
        }

        // Deu certo: apaga o vendor antigo e marca a versão como aplicada.
        apagarPasta($antigo);
        file_put_contents($arquivoAplicado, $publicada);
    } catch (Throwable) {
        // Só o código no log, sem a mensagem do erro (pode ter caminhos
        // internos do servidor). A versão não é marcada como aplicada, então o
        // próximo Cron (5 minutos depois) tenta de novo.
        apagarPasta($novo);
        registrarNoLog($raiz, 'VENDOR_FALHOU');

        return 'VENDOR_FALHOU';
    } finally {
        // Solta a trava em qualquer caso (deu certo ou não).
        flock($trava, LOCK_UN);
        fclose($trava);
    }

    return 'VENDOR_APLICADO';
}

/**
 * Lê uma versão gravada num arquivo (VENDOR_VERSAO ou vendor-aplicado).
 *
 * @param  string  $arquivo  Caminho do arquivo. Ex.: '/home1/.../VENDOR_VERSAO'.
 * @return string A versão sem espaços nem quebra de linha nas pontas (o GitHub
 *                grava "abc123" com uma quebra de linha no fim; sem limpar, nunca
 *                seria igual). Devolve '' se o arquivo não existe ou não pôde ser lido.
 */
function lerVersao(string $arquivo): string
{
    // Arquivo que não existe (ex.: primeira vez, sem vendor-aplicado): versão vazia.
    if (! is_file($arquivo)) {
        return '';
    }

    // file_get_contents devolve o texto, ou false se não conseguiu ler
    // (ex.: falta de permissão).
    $conteudo = file_get_contents($arquivo);

    // Não conseguiu ler: trata como vazio. Conseguiu: devolve o texto limpo.
    return $conteudo === false ? '' : trim($conteudo);
}

/**
 * Apaga uma pasta com tudo o que tem dentro. Se ela não existir, não faz nada.
 *
 * @param  string  $pasta  Caminho da pasta. Ex.: '/home1/.../vendor-antigo'.
 */
function apagarPasta(string $pasta): void
{
    // Pasta que não existe: nada para apagar.
    if (! is_dir($pasta)) {
        return;
    }

    // Percorre tudo lá dentro, dos arquivos mais fundos para cima (CHILD_FIRST),
    // porque uma pasta só pode ser apagada depois de ficar vazia.
    $itens = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($pasta, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($itens as $item) {
        // Pasta (vazia neste ponto) com rmdir; arquivo com unlink.
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($pasta);
}

/**
 * Escreve um código no log do dia deste script, sem dados internos.
 *
 * @param  string  $raiz  Pasta do projeto. Ex.: '/home1/edua1187/cursos-homolog.ib-to.org'.
 * @param  string  $codigo  Código estável do que aconteceu. Ex.: 'VENDOR_FALHOU'.
 */
function registrarNoLog(string $raiz, string $codigo): void
{
    // Um arquivo por dia, como o log do Laravel: storage/logs/vendor-AAAA-MM-DD.log.
    $arquivo = $raiz.'/storage/logs/vendor-'.date('Y-m-d').'.log';
    // FILE_APPEND acrescenta no fim, sem apagar as linhas anteriores.
    file_put_contents($arquivo, '['.date('Y-m-d H:i:s').'] '.$codigo.PHP_EOL, FILE_APPEND);
}

// Só roda sozinho quando chamado direto (pelo Cron: php deploy/atualizar-vendor.php).
// Quando os testes carregam este arquivo, só as funções são lidas e nada roda.
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    // A pasta do projeto é a de cima de deploy/ (dirname sobe um nível).
    echo atualizarVendor(dirname(__DIR__)).PHP_EOL;
}
