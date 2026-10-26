<?php
/**
 * iec.svg sprite üretici (paket F2). Geliştirme aracı, yayına çıkmaz.
 *
 *   php tools/sembol-sprite.php            site/assets/sembol/iec.svg dosyasını yeniden yazar
 *   php tools/sembol-sprite.php --kontrol  yalnızca kaynak dosyaları denetler (XML, viewBox, data-sira, bağlantı ızgarası)
 *
 * Sprite statik kullanım içindir (filtre düğmeleri, tablo hücreleri): uç numaraları, yazılar,
 * bağlantı noktaları ve data-* öznitelikleri atılır. <use> gölge ağacına sayfa CSS'i ulaşmaz;
 * durum değiştiren semboller (hoparlör, kontaklar) satır içi sembol() ile kullanılmalıdır.
 */
declare(strict_types=1);

$klasor = dirname(__DIR__) . '/site/assets/sembol';
$sadeceKontrol = in_array('--kontrol', $argv, true);
$dosyalar = glob($klasor . '/iec-*.svg') ?: [];
sort($dosyalar);

$hatalar = [];
$semboller = [];

foreach ($dosyalar as $dosya) {
    $ad = basename($dosya, '.svg');
    $dom = new DOMDocument();
    if (!@$dom->loadXML((string) file_get_contents($dosya))) {
        $hatalar[] = "$ad: XML okunamadı";
        continue;
    }
    $svg = $dom->documentElement;
    $vb = preg_split('/\s+/', trim($svg->getAttribute('viewBox')));
    if (count($vb) !== 4 || fmod((float) $vb[2], 16) != 0.0 || fmod((float) $vb[3], 8) != 0.0) {
        $hatalar[] = "$ad: viewBox 16 birim ızgarasında değil (" . $svg->getAttribute('viewBox') . ')';
    }
    $xp = new DOMXPath($dom);
    $xp->registerNamespace('s', 'http://www.w3.org/2000/svg');
    $cizilir = $xp->query('//s:path|//s:line|//s:circle[not(contains(@class,"baglanti"))]|//s:rect|//s:polyline|//s:polygon|//s:ellipse');
    foreach ($cizilir as $el) {
        /** @var DOMElement $el */
        if (!$el->hasAttribute('data-sira')) {
            $hatalar[] = "$ad: data-sira eksik (" . $el->nodeName . ')';
        }
        $dolgu = strpos($el->getAttribute('class'), 'dolgu') !== false;
        if (!$dolgu && $el->getAttribute('vector-effect') !== 'non-scaling-stroke') {
            $hatalar[] = "$ad: vector-effect eksik";
        }
    }
    foreach ($xp->query('//s:circle[contains(@class,"baglanti")]') as $b) {
        /** @var DOMElement $b */
        foreach (['cx', 'cy'] as $k) {
            if (fmod((float) $b->getAttribute($k), 4) != 0.0) {
                $hatalar[] = "$ad: bağlantı noktası 4 birim ızgarasında değil ({$b->getAttribute('data-uc')} $k=" . $b->getAttribute($k) . ')';
            }
        }
    }
    if ($svg->getAttribute('data-tur') !== 'ui' && $xp->query('//s:circle[contains(@class,"baglanti")]')->length === 0 && $ad !== 'iec-pano-siniri' && $ad !== 'iec-hoparlor') {
        $hatalar[] = "$ad: bağlantı noktası yok";
    }
    $semboller[$ad] = $dom;
}

if ($hatalar) {
    fwrite(STDERR, implode("\n", $hatalar) . "\n");
}
if ($sadeceKontrol) {
    echo count($semboller) . " sembol denetlendi, " . count($hatalar) . " hata\n";
    exit($hatalar ? 1 : 0);
}

$out = ['<svg xmlns="http://www.w3.org/2000/svg">',
    '<!-- Üretildi: tools/sembol-sprite.php. Elle düzenlemeyin; kaynak iec-*.svg dosyalarıdır. -->'];
foreach ($semboller as $ad => $dom) {
    $svg = $dom->documentElement;
    $xp = new DOMXPath($dom);
    $xp->registerNamespace('s', 'http://www.w3.org/2000/svg');
    foreach ($xp->query('//s:title|//s:desc|//s:text|//s:g[contains(@class,"baglantilar")]|//*[contains(@class,"hoparlor__kapali")]') as $n) {
        $n->parentNode->removeChild($n);
    }
    foreach ($xp->query('//*') as $el) {
        /** @var DOMElement $el */
        foreach (iterator_to_array($el->attributes) as $at) {
            if (strpos($at->name, 'data-') === 0) {
                $el->removeAttribute($at->name);
            }
        }
    }
    $sym = '<symbol id="' . $ad . '" viewBox="' . $svg->getAttribute('viewBox') . '"';
    foreach (['fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit'] as $a) {
        if ($svg->hasAttribute($a)) {
            $sym .= ' ' . $a . '="' . $svg->getAttribute($a) . '"';
        }
    }
    $sym .= ' overflow="visible">';
    foreach ($svg->childNodes as $c) {
        if ($c instanceof DOMElement) {
            $sym .= $dom->saveXML($c);
        }
    }
    $out[] = $sym . '</symbol>';
}
$out[] = '</svg>';
$xml = preg_replace('/\sxmlns="http:\/\/www\.w3\.org\/2000\/svg"(?=[^>]*>)/', '', implode("\n", $out));
$xml = preg_replace('/^<svg\b/', '<svg xmlns="http://www.w3.org/2000/svg"', (string) $xml, 1);
file_put_contents($klasor . '/iec.svg', $xml . "\n");
echo count($semboller) . " sembol iec.svg dosyasına yazıldı (" . round(strlen((string) $xml) / 1024, 1) . " KB)\n";
exit($hatalar ? 1 : 0);
