<?php

/*
 * CONFIGURAÇÃO DE SEGURANÇA (07/11/2026): regras extras de proteção das contas.
 *
 * Verificação em duas etapas obrigatória para a EQUIPE: quem tem acesso a dados
 * sensíveis (financeiro, ficha dos filiados, permissões, relatórios, aprovação
 * de cadastros) só usa a plataforma depois de ligar a verificação em duas etapas
 * (código de 6 números do aplicativo autenticador) ou de cadastrar uma chave de
 * acesso (passkey). Assim, se a senha de alguém da equipe vazar, a senha sozinha
 * não abre a conta. Aluno e instrutor comum não são afetados.
 */

return [

    // true (o padrão) = a regra vale. Para desligar numa emergência (ex.: alguém
    // da equipe perdeu o celular e precisa entrar), no .env do servidor:
    // SEGURANCA_EXIGIR_2FA_EQUIPE=false. Nos testes automáticos fica desligada
    // (phpunit.xml), e os testes da própria regra a ligam quando precisam.
    'exigir_2fa_equipe' => (bool) env('SEGURANCA_EXIGIR_2FA_EQUIPE', true),

    // As permissões que contam como "acesso sensível". Ter QUALQUER uma delas
    // basta para a regra valer. Ex.: quem só tem 'financeiro.ver' também entra.
    'permissoes_sensiveis' => [
        'financeiro.ver',
        'financeiro.lancar',
        'filiados.acessar',
        'filiados.gerenciar',
        'filiados.editar',
        'filiados.anonimizar',
        'permissoes.gerenciar',
        'usuarios.gerenciar',
        'cadastros.aprovar',
        'relatorios.acessar',
    ],

];
