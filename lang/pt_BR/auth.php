<?php

/*
 * MENSAGENS DE LOGIN em português (antes saíam em inglês, do Laravel).
 * O Laravel escolhe esta pasta porque o .env tem APP_LOCALE=pt_BR.
 * ":seconds" é trocado pelo número de segundos. Ex.: "...em 60 segundos."
 */

return [

    // Senha ou e-mail errados. A frase não diz qual dos dois errou, de propósito:
    // assim ninguém descobre quais e-mails têm conta na plataforma.
    'failed' => 'E-mail ou senha incorretos.',

    // Senha atual errada (ex.: ao trocar a senha em Configurações · Segurança).
    'password' => 'A senha informada está incorreta.',

    // Muitas tentativas seguidas (a trava contra quem tenta adivinhar senhas).
    'throttle' => 'Muitas tentativas de entrar. Tente de novo em :seconds segundos.',

];
