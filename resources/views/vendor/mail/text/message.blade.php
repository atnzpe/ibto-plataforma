{{--
    VERSÃO SÓ TEXTO de todos os e-mails (para programas que não mostram
    imagens nem cores). Mesmo conteúdo e o mesmo AVISO DE SEGURANÇA da versão
    com a marca (html/message.blade.php).
--}}
INSTITUTO BRASILEIRO DE TREINAMENTO OPERACIONAL (IBTO)

{!! strip_tags($slot) !!}
@isset($subcopy)

{!! strip_tags($subcopy) !!}
@endisset

---
Segurança: o IBTO nunca pede a sua senha, códigos ou dados bancários por e-mail, telefone ou WhatsApp. Antes de clicar, confira se o endereço começa com {{ rtrim(config('app.url'), '/') }}. Na dúvida, não clique: abra a plataforma digitando o endereço no navegador.

Instituto Brasileiro de Treinamento Operacional — Formação real. Resultado operacional.
Mensagem automática de {{ config('mail.from.address') }}. © {{ date('Y') }} IBTO.
