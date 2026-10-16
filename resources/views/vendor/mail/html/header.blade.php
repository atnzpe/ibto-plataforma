{{--
    TOPO DE TODOS OS E-MAILS DA PLATAFORMA (marca do IBTO, 17/10/2026).

    O emblema do IBTO sobre a faixa escura e o nome da instituição. A imagem
    fica em /imagens/email/ (pasta liberada da senha da homologação), em PNG
    (o WebP não abre em vários programas de e-mail). Se o programa bloquear
    imagens, aparece o texto alternativo "IBTO".

    $url: para onde o topo leva (a plataforma). Ex.: https://cursos.ib-to.org
--}}
@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<img src="{{ rtrim(config('app.url'), '/') }}/imagens/email/ibto-emblema.png" class="logo" width="72" height="72" alt="IBTO">
<span class="header-nome">Instituto Brasileiro de Treinamento Operacional</span>
</a>
</td>
</tr>
