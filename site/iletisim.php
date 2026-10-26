<?php
/**
 * Föy 8, Talep ve iletişim (spec 3.8, paket P8). Dil bilgisi: ritmik kesme listesi.
 *   A #adres  telefon çubuğu (görüntü ölçeğinde tel: bağlantısı), GSM / e-posta / adres hücreleri, vaziyet planı (OSM)
 *   B #talep  teklif formu bir antet gibi: köşede Osifont etiketli hücreler, TARİH / TALEP NO / ONAY onay hücreleri
 *   C #kase   başarıdan sonra tam genişlik mürekkep bant, talep numarası görüntü ölçeğinde; yoksa yalnızca antet
 *
 * Durumlar:
 *   boş            ?konu=, ?liste=1, ?hesap= ile ön doldurma (adreste yalnızca teknik değerler, kişisel veri yok)
 *   JS hata/başarı sayfa-iletisim.js fetch ile teklif-gonder.php'ye gönderir (Accept: application/json)
 *   JS yok, hata   teklif-gonder.php bu dosyayı bir fonksiyonun içinden require eder; durum form_durum() içindedir
 *   JS yok, başarı 303 ile ?talep=T-YYYYMMDD-NN#kase; kaşe yalnızca talep_dogrula() kaydı bulursa basılır
 * Bu dosya global değişkenlere güvenmez ve fonksiyon tanımlamaz (iki kez yüklenmeye dayanıklı).
 */
require __DIR__ . '/app/bootstrap.php';

$foy = foy('iletisim');
$tel = config('phone');
$gsm = config('gsm');
$eposta = (string) config('email');

/* ---------------------------------------------------------------- durum */
$yenidenCizim = isset($GLOBALS['form_durum']);
$durum = form_durum();
$hatalar = $durum['hatalar'] ?? [];
$genel = $durum['genel'] ?? null;
$degerler = $durum['degerler'] ?? [];

$talep = null;
if (!$yenidenCizim && isset($_GET['talep']) && is_string($_GET['talep'])) {
    $talep = talep_dogrula(substr($_GET['talep'], 0, 24));
}

/* pano hesabından gelen teknik değerler: yalnızca güvenli karakterler, 600 karakter */
$hesapTemizle = static fn (string $s): string => trim((string) preg_replace('/[^\p{L}\p{N} .,;:=|&\/+\-_()%°²]/u', '', $s));
$hesap = $yenidenCizim ? (string) ($degerler['hesap'] ?? '') : $hesapTemizle(form_al($_GET, 'hesap', 600));

/* hesap dizgesini okunur satırlara çevirir: "h=2000;w=800;qv=900" -> ["Yükseklik: 2000 mm", ...] */
$hesapSatirlari = static function (string $ham): array {
    $etiketler = [
        'h' => ['Yükseklik', 'mm'], 'w' => ['Genişlik', 'mm'], 'd' => ['Derinlik', 'mm'],
        'kurulum' => ['Kurulum şekli', ''], 'k' => ['Pano gövdesi (k)', ''], 'qv' => ['Panodaki ısı kaybı', 'W'],
        'tu' => ['En yüksek ortam sıcaklığı', '°C'], 'ti' => ['İstenen iç sıcaklık', '°C'],
        'qk' => ['Gereken soğutma', 'W'], 'model' => ['Model', ''],
    ];
    $satirlar = [];
    foreach (preg_split('/[;|&]/u', $ham) ?: [] as $parca) {
        if (!preg_match('/^\s*([\p{L}_]+)\s*[=:]\s*(.+?)\s*$/u', $parca, $m)) {
            continue;
        }
        $anahtar = mb_strtolower($m[1]);
        [$ad, $birim] = $etiketler[$anahtar] ?? [$m[1], ''];
        $satirlar[] = $ad . ': ' . $m[2] . ($birim !== '' && !preg_match('/[a-z°]\s*$/iu', $m[2]) ? ' ' . $birim : '');
    }
    return $satirlar ?: ($ham !== '' ? [$ham] : []);
};

/* ön doldurma (yalnızca ilk çizimde; yeniden çizimde gönderilen değerler kullanılır) */
$listeIstegi = ($_GET['liste'] ?? '') === '1';
$on = [];
if (!$yenidenCizim) {
    $konu = form_al($_GET, 'konu', 200);
    if ($konu === '' && $listeIstegi) {
        $konu = 'Talep listesi';
    }
    if ($konu === '' && $hesap !== '') {
        $konu = 'Pano soğutma';
    }
    if ($konu !== '') {
        $on['konu'] = $konu;
    }
    // JS kapalı talep listesi (tekayper-urunler.php): ?grup[]=... en fazla 200 öğe, öğe başına 160 karakter
    $grupSatirlari = [];
    if (isset($_GET['grup']) && is_array($_GET['grup'])) {
        foreach (array_slice($_GET['grup'], 0, 200) as $g) {
            $g = is_string($g) ? form_al(['g' => str_replace(["\r", "\n"], ' ', $g)], 'g', 160) : '';
            if ($g !== '' && !in_array($g, $grupSatirlari, true)) {
                $grupSatirlari[] = $g;
            }
        }
    }
    $mesajParcalari = [];
    if ($hesap !== '') {
        $mesajParcalari[] = "Pano soğutma hesabı:\n" . implode("\n", $hesapSatirlari($hesap));
    }
    if ($grupSatirlari) {
        $mesajParcalari[] = implode("\n", $grupSatirlari);
    }
    if ($mesajParcalari) {
        $on['mesaj'] = mb_substr(implode("\n\n", $mesajParcalari), 0, 4000);
    }
}

/* kaynak sayfa: bu siteden gelindiyse yalnızca yol (sorgu ve kişisel veri yok) */
$kaynak = (string) ($degerler['kaynak'] ?? '');
if (!$yenidenCizim) {
    $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $refHost = strtolower((string) parse_url($ref, PHP_URL_HOST) . (parse_url($ref, PHP_URL_PORT) ? ':' . parse_url($ref, PHP_URL_PORT) : ''));
    if ($ref !== '' && $refHost === strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''))) {
        $yol = (string) parse_url($ref, PHP_URL_PATH);
        if ($yol !== '' && basename($yol) !== 'iletisim.php') {
            $kaynak = mb_substr($yol, 0, 120);
        }
    }
}

/* hücre çıktısı: hata varsa revizyon numarası, ilk hatalı alana autofocus (JS kapalıyken forma kaydırır) */
$sira = 0;
$ilkHata = $hatalar ? array_key_first(array_intersect_key(form_alanlari(), $hatalar)) : null;
$odakAlan = $ilkHata ?? ($genel ? 'adsoyad' : null);
$hucre = static function (string $ad, array $opts = []) use ($hatalar, $on, $odakAlan, &$sira): string {
    if (isset($on[$ad]) && !isset($opts['deger'])) {
        $opts['deger'] = $on[$ad];
    }
    $html = form_hucre($ad, $opts);
    if (isset($hatalar[$ad])) {
        $sira++;
        $html = str_replace('<span class="hucre__revizyon" aria-hidden="true"></span>',
            '<span class="hucre__revizyon" aria-hidden="true" data-rev="' . $sira . '"></span>', $html);
    }
    if ($odakAlan === $ad) {
        $html = preg_replace('/ id="alan-' . preg_quote($ad, '/') . '"/', ' id="alan-' . $ad . '" autofocus', $html, 1) ?? $html;
    }
    return $html;
};

$bugun = date('d.m.Y');

/* sunucu tarafı kaşe (JS kapalı başarı): cizim.js kase() ile aynı çizim, sarsıntısız */
$kaseSvg = static function (string $no, string $tarih): string {
    $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    return '<div class="kase ilt-kase" role="img" aria-label="' . $esc('TALEP ALINDI, ' . $no . ', ' . $tarih) . '">'
         . '<svg viewBox="0 0 200 200" aria-hidden="true" focusable="false"><defs>'
         . '<filter id="kase-s-f" x="0" y="0" width="100%" height="100%"><feTurbulence type="fractalNoise" baseFrequency="0.85" numOctaves="2" seed="7" result="n"/>'
         . '<feColorMatrix in="n" type="matrix" values="0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 -2.6 2.05"/></filter>'
         . '<mask id="kase-s-d"><rect width="200" height="200" fill="#fff" filter="url(#kase-s-f)"/></mask>'
         . '<mask id="kase-s-b"><rect width="200" height="200" fill="#fff"/><rect x="0" y="72" width="200" height="56" fill="#000"/></mask></defs>'
         . '<g class="kase__murekkep" fill="none" stroke="currentColor" mask="url(#kase-s-d)"><g mask="url(#kase-s-b)">'
         . '<circle cx="100" cy="100" r="93" stroke-width="3.2"/><circle cx="100" cy="100" r="86" stroke-width="1.4"/></g>'
         . '<rect x="2" y="74" width="196" height="52" stroke-width="3"/><rect x="8" y="80" width="184" height="40" stroke-width="1.2"/>'
         . '<text class="kase__metin" x="100" y="100" text-anchor="middle" dominant-baseline="central" font-size="25" fill="currentColor" stroke="none">TALEP ALINDI</text>'
         . '<text class="kase__kucuk" x="100" y="52" text-anchor="middle" dominant-baseline="central" font-size="17" fill="currentColor" stroke="none">' . $esc($no) . '</text>'
         . '<text class="kase__kucuk" x="100" y="150" text-anchor="middle" dominant-baseline="central" font-size="17" fill="currentColor" stroke="none">' . $esc($tarih) . '</text>'
         . '</g></svg></div>';
};

$durumImza = $talep ? 'form:kaseli kase:sunucu' : ($hatalar || $genel ? 'form:hata kase:yok' : 'form:bos kase:yok');

partial('head', [
    'foy' => $foy,
    'css' => ['form', 'sayfa-iletisim'],
    'js' => ['sayfa-iletisim'],
    'schema' => [schema_sayfa('ContactPage', $foy, ['mainEntity' => ['@id' => tam_url('') . '#firma']])],
    'noindex' => $yenidenCizim ? true : null,
]);
partial('ust', ['foy' => $foy]);
?>
<main id="icerik" class="foy foy--iletisim" data-foy="8" data-sc-verify-state="<?= e($durumImza) ?>">
  <script>
    /* Bölüm girişleri JS hazırken gizli başlar; modül 3 sn içinde gelmezse her şey açılır. */
    (function (h) {
      if (h.dataset.hareket === 'az') return;
      h.classList.add('ilt-hazir');
      setTimeout(function () { if (!window.__iltHazir) h.classList.remove('ilt-hazir'); }, 3000);
    })(document.documentElement);
  </script>

  <section id="adres" class="ilt-a" data-satir="A" data-sc-act="flow" aria-labelledby="ilt-baslik">
    <div class="izgara ilt-a__ust">
      <h1 id="ilt-baslik">İletişim</h1>
      <p class="ilt-a__not"><?= capraz_a('/8.B', 'Teklif al') ?></p>
    </div>

    <a class="ilt-bar" href="tel:<?= e($tel['tel']) ?>" data-ilt-bar>
      <span class="ilt-bar__etiket">Telefon</span>
      <span class="ilt-bar__no"><span class="ilt-bar__parca">0224</span> <span class="ilt-bar__parca">441 70 88</span></span>
      <span class="ilt-bar__isaret" aria-hidden="true">
        <span class="ilt-bar__etiket">Telefon</span>
        <span class="ilt-bar__no"><span class="ilt-bar__parca">0224</span> <span class="ilt-bar__parca">441 70 88</span></span>
      </span>
    </a>

    <div class="izgara">
      <div class="ilt-hucreler">
        <div class="ilt-hucre ilt-hucre--gsm">
          <span class="ilt-hucre__etiket">GSM</span>
          <a class="ilt-hucre__deger" href="tel:<?= e($gsm['tel']) ?>"><?= e($gsm['label']) ?></a>
        </div>
        <div class="ilt-hucre ilt-hucre--eposta">
          <span class="ilt-hucre__etiket">E-posta</span>
          <a class="ilt-hucre__deger" href="mailto:<?= e($eposta) ?>"><?= e($eposta) ?></a>
        </div>
        <div class="ilt-hucre ilt-hucre--adres">
          <span class="ilt-hucre__etiket">Adres</span>
          <address class="ilt-hucre__adres"><?= e(config('address.line1')) ?><br><?= e(config('address.line2')) ?><br><?= e(config('address.district')) ?> / <?= e(config('address.city')) ?></address>
          <p class="ilt-hucre__alt">
            <a class="alti-cizili" href="<?= e(config('maps')) ?>" target="_blank" rel="noopener">Haritada aç<span class="gizli"> (Google Haritalar, yeni sekmede açılır)</span></a>
            <span class="ilt-hucre__koordinat"><?= e(config('geo_yazi.uzun')) ?></span>
          </p>
        </div>
        <figure class="ilt-hucre ilt-hucre--plan">
          <span class="ilt-hucre__etiket" aria-hidden="true">Vaziyet planı</span>
          <svg class="plan" viewBox="-240 -190 480 250" role="img" aria-labelledby="plan-baslik" focusable="false">
            <title id="plan-baslik">Vaziyet planı: Pazar Caddesi ile 646. Sokak arasındaki sanayi adaları, Tekayper konumu işaretli. Ölçek çubuğu 100 metre.</title>
            <g class="plan__binalar" data-ilt-plan>
              <path class="plan__bina" d="M66.6 6.9 L15.7 -103.7 L-6.3 -93.7 L44.6 16.8 L66.6 6.9Z"/>
              <path class="plan__bina" d="M103 -52.9 L72.1 -38.9 L84.8 -11.3 L115.7 -25.3 L103 -52.9Z"/>
              <path class="plan__bina" d="M71.1 -124.4 L97.5 -67 L63.7 -51.7 L37.3 -109 L71.1 -124.4Z"/>
              <path class="plan__bina" d="M-62.3 -11.5 L-86.9 -65 L-109.3 -54.8 L-84.6 -1.4 L-62.3 -11.5Z"/>
              <path class="plan__bina" d="M-31.4 -82.3 L6.1 -0.7 L-15.5 9.1 L-53.1 -72.4 L-31.4 -82.3Z"/>
            </g>
            <g class="plan__yollar" data-ilt-plan>
              <path data-sira="1" class="plan__yol plan__yol--ana" d="M52.2 -190 L-4.7 -165.8 L-45.8 -146.8 L-95.7 -124.9 L-141.5 -104.3 L-193.9 -79.7 L-237.9 -61.8 L-240 -60.8"/>
              <path data-sira="2" class="plan__yol" d="M240 -45 L194.6 -24.8 L146.3 -3.4 L84.7 23.5 L66.6 37.5 L60.2 34.4 L52.8 33.3 L46.4 32.2 L32.3 31.1 L-76 -74.8 L-87.2 22.4 L-148.9 18.9 L-202.8 14 L-240 10.6"/>
              <path data-sira="3" class="plan__yol" d="M-16 -190 L-4.7 -165.8 L18.8 -115.8 L-25.4 -97.1 L-72.1 -76.5 L-76 -74.8 L-119.6 -55.6"/>
              <path data-sira="3" class="plan__yol" d="M-25.4 -97.1 L32.3 31.1"/>
              <path data-sira="3" class="plan__yol" d="M18.8 -115.8 L23.8 -106.2 L56.3 -36.5 L84.7 23.5"/>
              <path data-sira="3" class="plan__yol" d="M56.3 -36.5 L120 -64.1"/>
              <path data-sira="3" class="plan__yol" d="M23.8 -106.2 L87 -134.8"/>
              <path data-sira="4" class="plan__yol" d="M168.2 49.2 L146.3 -3.4 L125.3 -52.7 L120 -64.1 L87 -134.8 L61.3 -190"/>
              <path data-sira="4" class="plan__yol" d="M119.3 -190 L172.9 -73"/>
              <path data-sira="4" class="plan__yol" d="M125.3 -52.7 L172.9 -73 L221.1 -94.4"/>
              <path data-sira="4" class="plan__yol" d="M240 -54 L221.1 -94.4 L175.9 -180.7 L170.7 -190"/>
              <path data-sira="4" class="plan__yol" d="M175.9 -180.7 L203.9 -190"/>
              <path data-sira="4" class="plan__yol" d="M194.6 -24.8 L226.5 52.1"/>
              <path data-sira="4" class="plan__yol" d="M240 53.3 L226.5 52.1 L183.4 48.3 L176.5 48.3 L168.2 49.2 L162.2 50.5 L155.9 52.4 L137.9 60"/>
              <path data-sira="4" class="plan__yol" d="M81 60 L75.2 46.7 L71.7 41.8 L66.6 37.5"/>
              <path data-sira="5" class="plan__yol" d="M-202.8 14 L-206.5 3.9 L-237.9 -61.8 L-240 -66.2"/>
              <path data-sira="5" class="plan__yol" d="M-193.9 -79.7 L-163.5 -14.1 L-148.9 18.9"/>
              <path data-sira="5" class="plan__yol" d="M-141.5 -104.3 L-119.6 -55.6 L-87.2 22.4"/>
              <path data-sira="5" class="plan__yol" d="M-206.5 3.9 L-163.5 -14.1"/>
              <path data-sira="5" class="plan__yol" d="M-238.3 -190 L-211.5 -132.7"/>
              <path data-sira="5" class="plan__yol" d="M-240 -120.5 L-211.5 -132.7 L-164.6 -152.2 L-117.7 -171.7"/>
              <path data-sira="5" class="plan__yol" d="M-182.2 -190 L-164.6 -152.2"/>
              <path data-sira="5" class="plan__yol" d="M-95.7 -124.9 L-117.7 -171.7 L-125.2 -190"/>
              <path data-sira="5" class="plan__yol" d="M-66.6 -190 L-45.8 -146.8"/>
            </g>
            <g class="plan__yazi" aria-hidden="true">
              <text x="-128" y="-122" transform="rotate(-24.6 -128 -122)">Pazar Cd.</text>
              <text x="176" y="-26" transform="rotate(-23.8 176 -26)">646. Sk.</text>
            </g>
            <g class="plan__nokta" data-ilt-nokta>
              <circle class="plan__isaret" cx="0" cy="0" r="11"/>
              <circle class="plan__halka" cx="0" cy="0" r="6.5"/>
              <path class="plan__arti" d="M-15 0H15M0 -15V15"/>
              <text class="plan__ad" x="14" y="-12">Tekayper</text>
            </g>
            <g class="plan__olcek" aria-hidden="true">
              <path d="M-226 44h100M-226 39v10M-176 41v6M-126 39v10"/>
              <text x="-226" y="34" text-anchor="middle">0</text>
              <text x="-176" y="34" text-anchor="middle">50</text>
              <text x="-126" y="34" text-anchor="middle">100 m</text>
            </g>
            <g class="plan__kuzey" aria-hidden="true" transform="translate(218 -160)">
              <path d="M0 18V-18M-6 -8 0 -18 6 -8"/>
              <text x="0" y="34" text-anchor="middle">K</text>
            </g>
          </svg>
          <figcaption class="ilt-hucre__kaynak">Harita verisi <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">© OpenStreetMap katkıcıları<span class="gizli"> (yeni sekmede açılır)</span></a></figcaption>
        </figure>
      </div>
    </div>
  </section>

  <section id="talep" class="ilt-b" data-satir="B" data-sc-act="flow" aria-labelledby="talep-baslik">
    <div class="izgara ilt-b__izgara">
      <div class="ilt-b__yan">
        <h2 id="talep-baslik">Teklif al</h2>
        <p class="ilt-b__metin">Ürünü, markayı ve adedi yazın. Talebiniz <?= e($eposta) ?> adresine iletilir.</p>
        <p class="ilt-b__zorunlu ikincil"><span aria-hidden="true">*</span> işaretli alanlar zorunludur.</p>
      </div>

<?php if ($talep): ?>
      <div class="ilt-form ilt-form--kaseli">
        <div class="form-hucreler ilt-antet ilt-antet--kaseli">
          <div class="hucre is-kilitli ilt-hucre-durum" data-ilt-sira>
            <span class="hucre__etiket">Durum</span>
            <p class="hucre__deger">Talebiniz alındı.</p>
          </div>
          <div class="hucre is-kilitli ilt-tarih" data-ilt-sira>
            <span class="hucre__etiket">Tarih</span>
            <span class="hucre__deger sayi"><?= e($talep['tarih']) ?></span>
          </div>
          <div class="hucre is-kilitli ilt-talepno" data-ilt-sira>
            <span class="hucre__etiket">Talep no</span>
            <span class="hucre__deger ilt-talepno__deger"><?= e($talep['no']) ?></span>
          </div>
          <div class="hucre is-kilitli ilt-onay is-kaseli" data-ilt-sira>
            <span class="hucre__etiket">Onay</span>
            <?= $kaseSvg($talep['no'], $talep['tarih']) ?>
          </div>
        </div>
      </div>
      <div class="ilt-sonuc">
        <p class="ilt-sonuc__metin">Talebiniz <a href="mailto:<?= e($eposta) ?>"><?= e($eposta) ?></a> adresine iletildi. Acil durumlarda <a href="tel:<?= e($tel['tel']) ?>"><?= e($tel['label']) ?></a>.</p>
        <?= dugme('Yeni talep yaz', url('iletisim.php#talep'), ['tur' => 'ikincil', 'ref' => '/8.B']) ?>
      </div>
<?php else: ?>
      <form class="ilt-form" id="talep-formu" method="post" action="<?= e(url('teklif-gonder.php')) ?>" novalidate data-ilt-form aria-labelledby="talep-baslik">
        <p class="form-uyari" id="form-uyari" role="alert"<?= $genel && !$hatalar ? '' : ' hidden' ?>><?= e($genel && !$hatalar ? $genel : '') ?></p>
        <div class="ilt-revizyon" id="revizyon" data-ilt-revizyon<?= $hatalar ? '' : ' hidden' ?>>
          <p class="ilt-revizyon__baslik"><span class="hucre__etiket">Revizyon</span> <span class="ilt-revizyon__ozet" data-ilt-rev-ozet><?= $hatalar ? e(count($hatalar) . ' alan düzeltilmeli.') : '' ?></span></p>
          <ol class="ilt-revizyon__liste" data-ilt-rev-liste>
<?php $r = 0; foreach (array_intersect_key(form_alanlari(), $hatalar) as $ad => $t): $r++; ?>
            <li><a href="#alan-<?= e($ad) ?>"><span class="ilt-rev" aria-hidden="true"><?= $r ?></span><span class="ilt-revizyon__alan"><?= e($ad === 'kvkk' ? 'Aydınlatma metni' : $t['etiket']) ?></span></a> <?= e($hatalar[$ad]) ?></li>
<?php endforeach; ?>
          </ol>
        </div>
        <div class="form-hucreler ilt-antet">
          <?= $hucre('adsoyad', ['sinif' => 'ilt-c-adsoyad']) ?>
          <?= $hucre('firma', ['sinif' => 'ilt-c-firma']) ?>
          <?= $hucre('telefon', ['sinif' => 'ilt-c-telefon']) ?>
          <?= $hucre('eposta', ['sinif' => 'ilt-c-eposta']) ?>
          <?= $hucre('konu', ['sinif' => 'ilt-c-konu']) ?>
          <?= $hucre('mesaj', ['sinif' => 'ilt-c-mesaj', 'satir' => 5, 'yardim' => $listeIstegi ? 'Talep listesindeki ürünler buraya satır satır aktarılır.' : 'Her satıra bir ürün yazabilirsiniz.']) ?>
<?php if ($listeIstegi): ?>
          <noscript><p class="ilt-noscript">Talep listesi tarayıcınızda saklanır ve aktarmak için JavaScript gerekir. Ürünleri bu alana yazabilirsiniz.</p></noscript>
<?php endif; ?>
          <?= $hucre('kvkk', ['sinif' => 'ilt-c-kvkk']) ?>
          <div class="hucre ilt-tarih">
            <span class="hucre__etiket">Tarih</span>
            <span class="hucre__deger sayi" data-ilt-tarih><?= e($bugun) ?></span>
          </div>
          <div class="hucre ilt-talepno">
            <span class="hucre__etiket">Talep no</span>
            <span class="hucre__deger ilt-talepno__deger" data-ilt-no>Gönderince verilir</span>
          </div>
          <div class="hucre ilt-onay" data-ilt-onay>
            <span class="hucre__etiket">Onay</span>
            <?= dugme('Talebi gönder', null, ['button' => true, 'tip' => 'submit', 'sinif' => 'ilt-gonder']) ?>
          </div>
        </div>
        <?= form_gizli_alanlar($kaynak, $hesap) ?>
      </form>
      <div class="ilt-sonuc" data-ilt-sonuc tabindex="-1" hidden>
        <p class="ilt-sonuc__metin"><span class="gizli" data-ilt-sonuc-no></span>Talebiniz <a href="mailto:<?= e($eposta) ?>"><?= e($eposta) ?></a> adresine iletildi. Acil durumlarda <a href="tel:<?= e($tel['tel']) ?>"><?= e($tel['label']) ?></a>.</p>
        <?= dugme('Yeni talep yaz', null, ['tur' => 'ikincil', 'button' => true, 'ozellik' => ['data-ilt-yeni' => true]]) ?>
      </div>
<?php endif; ?>
    </div>
  </section>

  <section id="kase" class="ilt-c<?= $talep ? ' is-acik' : '' ?>" data-satir="C" data-sc-act="flow" aria-labelledby="kase-baslik">
    <div class="ilt-c__bant" data-ilt-bant<?= $talep ? '' : ' hidden' ?>>
      <h2 id="kase-baslik" class="ilt-c__baslik">Talep alındı</h2>
      <span class="ilt-c__etiket" aria-hidden="true">Talep no</span>
      <p class="ilt-c__no"><span class="gizli">Talep numaranız </span><span data-ilt-bant-no><?= e($talep['no'] ?? '') ?></span></p>
      <p class="ilt-c__tarih"><span class="ilt-c__tarih-etiket" aria-hidden="true">Tarih</span> <span data-ilt-bant-tarih><?= e($talep['tarih'] ?? '') ?></span></p>
    </div>
  </section>
  <?= json_gom('ilt-veri', [
      'hatalar' => form_hata_metinleri(),
      'etiketler' => array_merge(array_map(static fn ($t) => $t['etiket'], form_alanlari()), ['kvkk' => 'Aydınlatma metni']),
      'sunucu' => 'Talebiniz şu an iletilemedi. ' . $tel['label'] . ' numarasını arayın ya da ' . $eposta . ' adresine yazın.',
      'liste' => $listeIstegi,
      'iletisim' => url('iletisim.php'),
  ]) ?>
</main>
<?php
partial('antet', ['foy' => $foy]);
partial('son', ['foy' => $foy]);
