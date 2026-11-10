<?php

declare(strict_types=1);

use App\Support\HtmlSanitizer;

it('flattens legacy markup into clean semantic html', function () {
    $html = '<p><span style="font-family:arial;"><strong><span style="color:red">Başlık</span></strong></span></p>'
        .'<pre style="overflow:auto">Satır 1'."\n".'Satır 2</pre>'
        .'<div class="row"><div class="col-sm-8" style="width:900px"><b>Kalın</b> metin</div></div>'
        .'<table width="1200" style="width:1200px"><tr><td style="width:600px" colspan="2">Hücre</td></tr></table>'
        .'<p>&nbsp;</p><script>alert(1)</script><a href="javascript:x()" onclick="y()">bağlantı</a>';

    $clean = HtmlSanitizer::clean($html);

    expect($clean)
        ->toContain('<strong>Başlık</strong>')
        ->toContain('<p>Satır 1<br>Satır 2</p>')
        ->toContain('<p><strong>Kalın</strong> metin</p>')
        ->toContain('<td colspan="2">Hücre</td>')
        ->not->toContain('style=')
        ->not->toContain('width')
        ->not->toContain('<pre')
        ->not->toContain('<div')
        ->not->toContain('<span')
        ->not->toContain('script')
        ->not->toContain('javascript')
        ->not->toContain('onclick')
        ->not->toContain('&nbsp;');
});

it('keeps text alignment and safe links/images from the rich text editor', function () {
    $clean = HtmlSanitizer::clean('<p style="text-align: center">Orta</p><a href="https://ornek.com.tr" target="_blank">site</a><img src="/storage/a.jpg" alt="a"><img src="data:image/png;base64,xx">');

    expect($clean)
        ->toContain('<p style="text-align: center">Orta</p>')
        ->toContain('rel="noopener noreferrer"')
        ->toContain('<img src="/storage/a.jpg" alt="a">')
        ->not->toContain('data:image');
});
