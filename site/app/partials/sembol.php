<?php
/**
 * IEC 60617 sembol kütüphanesi (paket F2).
 *
 * Kaynak dosyalar: assets/sembol/iec-<ad>.svg (elle çizilmiş, 4 birim = 1 modül, standart hücre
 * 48x48 birim, bağlantı noktaları ızgara düğümlerinde). Arayüz glifleri (ara, kapat, indir, dis-proje,
 * menu) aynı klasörde, aynı önekle ve data-tur="ui" ile durur.
 *
 * Genel API (sayfa yapıcılar için):
 *   sembol($ad, $secenek)        Satır içi SVG (çizim animasyonu için şart). HTML dizesi döndürür.
 *   sembol_ikon($ad, $px, ...)   iec.svg sprite'ından <use> ile statik ikon (filtre düğmesi, tablo hücresi).
 *   sembol_baglanti($ad)         Bağlantı noktaları, sembol birimlerinde: [['yon','uc','x','y'], ...].
 *   sembol_listesi()             Bütün semboller ve üst verileri (dev sayfası, QA).
 *
 * sembol() seçenekleri:
 *   buyukluk  int    48 birimlik standart hücrenin piksel boyu (ölçek = buyukluk/48). Varsayılan 48.
 *                    Aynı buyukluk değeri bütün sembollerde aynı çizgi ölçeğini verir (şemada tutarlılık).
 *   sigdir    bool   true: sembolün uzun kenarı buyukluk kadar olur (raydaki düğmeler, tablo hücreleri).
 *   etiket    string Cihaz etiketi, ör. '-K1'. Osifont, sembolün solunda (EPLAN düzeni).
 *   etiket_konum     'sol' | 'sag' | 'ust' | 'alt'. Varsayılan 'sol'.
 *   baslik    string Erişilebilir ad. Verilirse SVG role="img" olur; verilmezse sembol dekoratiftir (aria-hidden).
 *   sinif     string Sarmalayıcıya eklenecek sınıflar.
 *   uclar     array  Uç numaraları, bağlantı noktası kimliğine göre: ['ust' => '13', 'alt' => '14'].
 *   yazi      string Sembol yazısı (kesme-noktasi: '/4.A', kablo: '4G2,5 mm²', besleme: değer satırı).
 *   iletken   int    kablo: eğik çizgi (iletken) sayısı. Varsayılan 4.
 *   durum     'acik' | 'kapali'. Kontaklı sembollerde başlangıç konumu (sunucu son durumu çizer).
 *   yazilar   bool   Uç numaralarını zorla göster/gizle. Varsayılan: ölçek >= 1.75 ise gösterilir
 *                    (Osifont en az 13 px kuralı; küçük boyda numaralar okunmaz).
 *   kap       'html' (sarmalayıcı span, varsayılan) | 'svg' (yalın svg) | 'g' (başka bir SVG içine gömmek için <g>).
 *   x, y, olcek      kap='g' için konum ve ölçek (üst SVG'nin birimlerinde).
 *   ayna      array  Bobin altına kontak aynası: [['13','14','/1.D'], ['21','22','/1.C','nc'], ...].
 *
 * Bu dosya bootstrap tarafından require_once ile yüklenmelidir. partial('sembol', ['sembol' => 'motor',
 * 'secenek' => [...]]) biçimiyle de çağrılabilir; o durumda sembolü ekrana basar.
 */
declare(strict_types=1);

if (!function_exists('sembol')) {

    function sembol_klasoru(): string
    {
        return dirname(__DIR__, 2) . '/assets/sembol';
    }

    /** @return array{0:string,1:string}|null [dosya yolu, önek] */
    function sembol_dosyasi(string $ad): ?array
    {
        $ad = preg_replace('/[^a-z0-9-]/', '', strtolower($ad)) ?? '';
        if ($ad === '') {
            return null;
        }
        $dosya = sembol_klasoru() . '/iec-' . $ad . '.svg';
        return is_file($dosya) && $ad !== '' ? [$dosya, 'iec'] : null;
    }

    function sembol_kaynak(string $ad): ?string
    {
        static $onbellek = [];
        if (!array_key_exists($ad, $onbellek)) {
            $d = sembol_dosyasi($ad);
            $onbellek[$ad] = $d ? (string) file_get_contents($d[0]) : null;
        }
        return $onbellek[$ad];
    }

    function sembol_dom(string $ad): ?DOMDocument
    {
        $xml = sembol_kaynak($ad);
        if ($xml === null) {
            return null;
        }
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        if (!@$dom->loadXML($xml)) {
            return null;
        }
        return $dom;
    }

    /** SVG ad alanında öğe (createElement ad alansız öğe üretir; saveXML onları default: önekiyle yazar) */
    function sembol_svg_oge(DOMDocument $dom, string $ad, ?string $metin = null): DOMElement
    {
        $el = $dom->createElementNS('http://www.w3.org/2000/svg', $ad);
        if ($metin !== null) {
            $el->appendChild($dom->createTextNode($metin));
        }
        return $el;
    }

    function sembol_sayi(float $n): string
    {
        $s = rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
        return $s === '-0' || $s === '' ? '0' : $s;
    }

    function sembol_kutu(DOMElement $svg): array
    {
        $vb = preg_split('/[\s,]+/', trim($svg->getAttribute('viewBox')));
        return [(float) ($vb[2] ?? 48), (float) ($vb[3] ?? 48)];
    }

    function sembol_html(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Bağlantı noktaları, sembol birimlerinde. */
    function sembol_baglanti(string $ad): array
    {
        $dom = sembol_dom($ad);
        if (!$dom) {
            return [];
        }
        $sonuc = [];
        foreach ($dom->getElementsByTagName('circle') as $c) {
            if (strpos(' ' . $c->getAttribute('class') . ' ', ' baglanti ') === false) {
                continue;
            }
            $sonuc[] = [
                'yon' => $c->getAttribute('data-baglanti'),
                'uc' => $c->getAttribute('data-uc'),
                'x' => (float) $c->getAttribute('cx'),
                'y' => (float) $c->getAttribute('cy'),
            ];
        }
        return $sonuc;
    }

    /** Bütün semboller: ad => [tur, baslik, aciklama, kontrol, genislik, yukseklik, baglantilar]. */
    function sembol_listesi(): array
    {
        $liste = [];
        foreach (glob(sembol_klasoru() . '/iec-*.svg') ?: [] as $dosya) {
            $ad = preg_replace('/^iec-|\.svg$/', '', basename($dosya));
            if ($ad === '') {
                continue;
            }
            $dom = sembol_dom($ad);
            if (!$dom) {
                continue;
            }
            $svg = $dom->documentElement;
            [$w, $h] = sembol_kutu($svg);
            $baslik = $dom->getElementsByTagName('title')->item(0);
            $aciklama = $dom->getElementsByTagName('desc')->item(0);
            $liste[$ad] = [
                'tur' => $svg->getAttribute('data-tur') === 'ui' ? 'ui' : 'iec',
                'baslik' => $baslik ? trim($baslik->textContent) : $ad,
                'aciklama' => $aciklama ? trim($aciklama->textContent) : '',
                'kontrol' => $svg->getAttribute('data-kontrol') === 'evet',
                'genislik' => $w,
                'yukseklik' => $h,
                'baglantilar' => sembol_baglanti($ad),
            ];
        }
        uksort($liste, static function ($a, $b) use ($liste) {
            return [$liste[$a]['tur'] === 'ui', $a] <=> [$liste[$b]['tur'] === 'ui', $b];
        });
        return $liste;
    }

    function sembol_sprite_url(): string
    {
        if (function_exists('asset')) {
            return asset('sembol/iec.svg');
        }
        $on = function_exists('base_path') ? base_path() : '';
        $dosya = sembol_klasoru() . '/iec.svg';
        $v = is_file($dosya) ? substr((string) filemtime($dosya), -6) : '0';
        return $on . '/assets/sembol/iec.svg?v=' . $v;
    }

    /** Sprite'tan statik ikon. Uç numarası ve yazı içermez; çizim animasyonu yoktur. */
    function sembol_ikon(string $ad, int $px = 32, string $sinif = '', ?string $baslik = null): string
    {
        $d = sembol_dosyasi($ad);
        $dom = sembol_dom($ad);
        if (!$d || !$dom) {
            return '';
        }
        [$w, $h] = sembol_kutu($dom->documentElement);
        $olcek = $px / max($w, $h);
        $aria = $baslik !== null
            ? 'role="img" aria-label="' . sembol_html($baslik) . '"'
            : 'aria-hidden="true" focusable="false"';
        return '<svg class="sembol-ikon sembol-ikon--' . sembol_html($ad) . ($sinif !== '' ? ' ' . sembol_html($sinif) : '')
            . '" viewBox="0 0 ' . sembol_sayi($w) . ' ' . sembol_sayi($h) . '" width="' . sembol_sayi($w * $olcek)
            . '" height="' . sembol_sayi($h * $olcek) . '" ' . $aria . '><use href="'
            . sembol_html(sembol_sprite_url()) . '#' . $d[1] . '-' . sembol_html($ad) . '"/></svg>';
    }

    function sembol_sinif_var(DOMElement $el, string $sinif): bool
    {
        return strpos(' ' . $el->getAttribute('class') . ' ', ' ' . $sinif . ' ') !== false;
    }

    /** @return DOMElement[] */
    function sembol_elemanlar(DOMDocument $dom, string $etiket, ?string $sinif = null): array
    {
        $out = [];
        foreach ($dom->getElementsByTagName($etiket) as $el) {
            if ($sinif === null || sembol_sinif_var($el, $sinif)) {
                $out[] = $el;
            }
        }
        return $out;
    }

    function sembol(string $ad, array $s = []): string
    {
        $dom = sembol_dom($ad);
        if (!$dom) {
            return '<!-- sembol yok: ' . sembol_html($ad) . ' -->';
        }
        $svg = $dom->documentElement;
        [$w, $h] = sembol_kutu($svg);

        $buyukluk = (float) ($s['buyukluk'] ?? 48);
        $olcek = !empty($s['sigdir']) ? $buyukluk / max($w, $h) : $buyukluk / 48;
        $kap = $s['kap'] ?? 'html';
        if ($kap === 'g') {
            $olcek = (float) ($s['olcek'] ?? 1);
        }

        foreach (['title', 'desc'] as $t) {
            foreach (sembol_elemanlar($dom, $t) as $el) {
                $el->parentNode->removeChild($el);
            }
        }

        // Uç numaraları
        $uclar = $s['uclar'] ?? [];
        foreach ($uclar as $uc => $metin) {
            $bulundu = false;
            foreach (sembol_elemanlar($dom, 'text') as $t) {
                if ($t->getAttribute('data-uc') === (string) $uc) {
                    $t->textContent = (string) $metin;
                    $bulundu = true;
                }
            }
            if ($bulundu) {
                continue;
            }
            foreach (sembol_elemanlar($dom, 'circle', 'baglanti') as $c) {
                if ($c->getAttribute('data-uc') !== (string) $uc) {
                    continue;
                }
                $cx = (float) $c->getAttribute('cx');
                $cy = (float) $c->getAttribute('cy');
                $yon = $c->getAttribute('data-baglanti');
                $t = sembol_svg_oge($dom, 'text', (string) $metin);
                $t->setAttribute('class', 'uc-no');
                $t->setAttribute('data-uc', (string) $uc);
                $t->setAttribute('x', sembol_sayi($yon === 'sag' ? $cx - 1 : $cx - 3));
                $t->setAttribute('y', sembol_sayi($yon === 'alt' ? $cy - 2 : ($yon === 'ust' ? $cy + 8 : $cy - 2)));
                $t->setAttribute('text-anchor', 'end');
                $t->setAttribute('font-size', '6');
                $t->setAttribute('data-sira', '9');
                $svg->insertBefore($t, sembol_elemanlar($dom, 'g', 'baglantilar')[0] ?? null);
            }
        }

        // Sembol yazısı
        if (array_key_exists('yazi', $s)) {
            foreach (sembol_elemanlar($dom, 'text', 'sembol-yazi') as $t) {
                $t->textContent = (string) $s['yazi'];
            }
        }

        // Kablo: iletken sayısı
        if ($ad === 'kablo' && isset($s['iletken'])) {
            $n = max(1, min(7, (int) $s['iletken']));
            $eski = sembol_elemanlar($dom, 'path', 'kablo__damar');
            $once = $eski ? $eski[count($eski) - 1]->nextSibling : null;
            foreach ($eski as $el) {
                $el->parentNode->removeChild($el);
            }
            for ($i = 0; $i < $n; $i++) {
                $y = 24 + ($i - ($n - 1) / 2) * 3;
                $p = sembol_svg_oge($dom, 'path');
                $p->setAttribute('class', 'kablo__damar');
                $p->setAttribute('d', 'M20 ' . sembol_sayi($y + 3) . 'L28 ' . sembol_sayi($y - 3));
                $p->setAttribute('data-sira', '2');
                $p->setAttribute('vector-effect', 'non-scaling-stroke');
                $svg->insertBefore($p, $once);
            }
        }

        // Kontak durumu
        if (isset($s['durum']) && in_array($s['durum'], ['acik', 'kapali'], true)) {
            foreach (sembol_elemanlar($dom, 'path', 'kontak__bicak') as $b) {
                $aci = (float) $b->getAttribute('data-' . $s['durum']);
                [$mx, $my] = array_map('floatval', preg_split('/\s+/', trim($b->getAttribute('data-mafsal'))));
                $b->setAttribute('transform', 'rotate(' . sembol_sayi($aci) . ' ' . sembol_sayi($mx) . ' ' . sembol_sayi($my) . ')');
                $b->setAttribute('data-aci', sembol_sayi($aci));
            }
            foreach (sembol_elemanlar($dom, 'path', 'kontak__bag') as $b) {
                $kayma = (float) $b->getAttribute('data-kayma');
                if ($kayma && $s['durum'] === 'kapali') {
                    $b->setAttribute('transform', 'translate(' . sembol_sayi($kayma) . ' 0)');
                } else {
                    $b->removeAttribute('transform');
                }
            }
        }

        // Yazılar: boşları at, küçük ölçekte gizle, Osifont en az 13.5 px kalsın
        $yazilar = $s['yazilar'] ?? ($olcek >= 1.75);
        foreach (sembol_elemanlar($dom, 'text') as $t) {
            if (trim($t->textContent) === '' || !$yazilar) {
                $t->parentNode->removeChild($t);
                continue;
            }
            if ($kap !== 'g') {
                $t->setAttribute('font-size', sembol_sayi(13.5 / $olcek));
            }
        }

        $kontrol = $svg->getAttribute('data-kontrol') === 'evet';

        if ($kap === 'g') {
            $g = sembol_svg_oge($dom, 'g');
            $g->setAttribute('class', trim('sembol-g iec-' . $ad . ' ' . ($s['sinif'] ?? '')));
            $g->setAttribute('data-sembol', $ad);
            if ($kontrol) {
                $g->setAttribute('data-kontrol', 'evet');
            }
            $g->setAttribute('transform', 'translate(' . sembol_sayi((float) ($s['x'] ?? 0)) . ' ' . sembol_sayi((float) ($s['y'] ?? 0)) . ')'
                . ($olcek != 1.0 ? ' scale(' . sembol_sayi($olcek) . ')' : ''));
            foreach (['fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'font-family'] as $a) {
                if ($svg->hasAttribute($a)) {
                    $g->setAttribute($a, $svg->getAttribute($a));
                }
            }
            while ($svg->firstChild) {
                $g->appendChild($svg->firstChild);
            }
            return preg_replace('~^<g xmlns="http://www.w3.org/2000/svg"~', '<g', $dom->saveXML($g));
        }

        $svg->setAttribute('width', sembol_sayi($w * $olcek));
        $svg->setAttribute('height', sembol_sayi($h * $olcek));
        $svg->setAttribute('class', trim('sembol__cizim ' . $svg->getAttribute('class') . ($kap === 'svg' && !empty($s['sinif']) ? ' ' . $s['sinif'] : '')));
        $baslik = $s['baslik'] ?? null;
        if ($baslik !== null && $baslik !== '') {
            $svg->setAttribute('role', 'img');
            $svg->setAttribute('aria-label', (string) $baslik);
        } else {
            $svg->setAttribute('aria-hidden', 'true');
            $svg->setAttribute('focusable', 'false');
        }
        $svgHtml = $dom->saveXML($svg);

        if ($kap === 'svg') {
            return $svgHtml;
        }

        $etiket = '';
        if (!empty($s['etiket'])) {
            $konum = in_array($s['etiket_konum'] ?? 'sol', ['sol', 'sag', 'ust', 'alt'], true) ? ($s['etiket_konum'] ?? 'sol') : 'sol';
            $etiket = '<span class="sembol__etiket sembol__etiket--' . $konum . '">' . sembol_html((string) $s['etiket']) . '</span>';
        }

        $ayna = '';
        if (!empty($s['ayna']) && is_array($s['ayna'])) {
            $ayna = sembol_ayna($s['ayna']);
        }

        $sinif = trim('sembol sembol--' . $ad . ' ' . ($s['sinif'] ?? ''));
        $aria = ($baslik === null || $baslik === '') && $ayna === '' ? ' aria-hidden="true"' : '';
        return '<span class="' . sembol_html($sinif) . '" data-sembol="' . sembol_html($ad) . '"'
            . ($kontrol ? ' data-kontrol="evet"' : '')
            . ' style="--sembol-olcek:' . sembol_sayi($olcek) . '"' . $aria . '>'
            . $svgHtml . $etiket . $ayna . '</span>';
    }

    /**
     * Kontak aynası (EPLAN düzeni): her sütunda üst uç, küçük kontak sembolü, alt uç ve çapraz referans.
     * Satır: [ust, alt, ref, tur='no'|'nc'].
     */
    function sembol_ayna(array $satirlar): string
    {
        $html = '<span class="kontak-aynasi" role="list">';
        foreach ($satirlar as $r) {
            $ust = (string) ($r[0] ?? '');
            $alt = (string) ($r[1] ?? '');
            $ref = (string) ($r[2] ?? '');
            $tur = ($r[3] ?? 'no') === 'nc' ? 'kontak-nc' : 'kontak-no';
            $refHtml = '';
            if ($ref !== '') {
                $c = function_exists('capraz') ? capraz($ref) : null;
                if (is_array($c) && !empty($c['href'])) {
                    $refHtml = '<a class="capraz ka__ref" href="' . sembol_html((string) $c['href']) . '" data-capraz="'
                        . sembol_html($ref) . '" title="' . sembol_html((string) ($c['tam'] ?? $ref)) . '">' . sembol_html($ref) . '</a>';
                } else {
                    $refHtml = '<span class="ka__ref">' . sembol_html($ref) . '</span>';
                }
            }
            $html .= '<span class="kontak-aynasi__kol" role="listitem" aria-label="Kontak ' . sembol_html($ust . '-' . $alt)
                . ($ref !== '' ? ', ' . sembol_html($ref) : '') . '">'
                . '<span class="ka__uc" aria-hidden="true">' . sembol_html($ust) . '</span>'
                . sembol($tur, ['buyukluk' => 24, 'kap' => 'svg', 'yazilar' => false, 'sinif' => 'ka__sembol'])
                . '<span class="ka__uc" aria-hidden="true">' . sembol_html($alt) . '</span>'
                . $refHtml . '</span>';
        }
        return $html . '</span>';
    }
}

// partial('sembol', ['sembol' => 'motor', 'secenek' => [...]]) ile çağrıldıysa bas.
if (isset($sembol) && is_string($sembol)) {
    echo sembol($sembol, isset($secenek) && is_array($secenek) ? $secenek : []);
}
