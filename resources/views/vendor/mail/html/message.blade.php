{{--
    MOLDURA DE TODOS OS E-MAILS DA PLATAFORMA (marca do IBTO e segurança, 17/10/2026).

    - Topo com o emblema (header.blade.php).
    - O conteúdo de cada e-mail no meio.
    - Rodapé com o AVISO DE SEGURANÇA, igual em todos os e-mails, contra golpes
      (phishing): o IBTO nunca pede senha, código ou dados bancários por
      e-mail ou WhatsApp, e o endereço oficial é mostrado por extenso, para a
      pessoa conferir antes de clicar.
--}}
<x-mail::layout>
{{-- Topo: o emblema e o nome do IBTO. --}}
<x-slot:header>
<x-mail::header :url="config('app.url')" />
</x-slot:header>

{{-- O conteúdo do e-mail (texto, botão e assinatura). --}}
{!! $slot !!}

{{-- "Não consegue clicar?": o link por extenso, para copiar e colar. --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Rodapé: aviso de segurança + instituição. --}}
<x-slot:footer>
<x-mail::footer>
**Segurança:** o IBTO nunca pede a sua senha, códigos ou dados bancários por e-mail, telefone ou WhatsApp. Antes de clicar, confira se o endereço começa com **{{ rtrim(config('app.url'), '/') }}**. Na dúvida, não clique: abra a plataforma digitando o endereço no navegador.

Instituto Brasileiro de Treinamento Operacional — Formação real. Resultado operacional.<br>
Esta é uma mensagem automática de {{ config('mail.from.address') }}. © {{ date('Y') }} IBTO.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
