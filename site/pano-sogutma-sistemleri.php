<?php
/**
 * Föy 5, Isı hesabı (paket P5, spec 3.5). Dil bilgisi: bölünmüş sahne. Bölme çizgisi pano yan duvarıdır.
 * A ic-dis: pano içi / pano dışı, duvarda TPK. B hesap: çizim formdur (IEC/TR 60890). C modeller. D teklif: duvar kenara kayar.
 * JS kapalıyken form GET ile aynı sayfaya gelir; hesap app/lib/sogutma.php ile sunucuda yapılır.
 */
require __DIR__ . '/app/bootstrap.php';
require_once APP . '/lib/sogutma.php';
sembol_hazir();

$foy = foy('sogutma');
$girdi = sogutma_girdi($_GET);
$g = $girdi['g'];
$hatalar = $girdi['hatalar'];
$r = sogutma_hesapla($g);
$metin = sogutma_metinler($g, $r);
$teklifUrl = sogutma_teklif_url($metin);
const KUTU_W = 760;
const KUTU_H = 700;
$yer = sogutma_yerlesim($g, $r, KUTU_W, KUTU_H);
$tpk = data('tpk');
$alanlar = sogutma_alanlar();
$kurulumlar = sogutma_kurulumlar();
$govdeler = sogutma_govdeler();
$basamaklar = sogutma_basamaklar();

// modeller kapasiteye göre, P serisi önce
$modeller = $tpk['modeller'];
usort($modeller, fn ($a, $b) => [$a['w'], $a['seri'] === 'p' ? 0 : 1] <=> [$b['w'], $b['seri'] === 'p' ? 0 : 1]);

// JSON-LD: her model bir Product (teklif yok)
$urunSema = [];
foreach ($modeller as $m) {
    [$my, $mg, $md] = $m['ebat'];
    $s = [
        '@type' => 'Product',
        'name' => $m['kod'] . ' pano kliması',
        'description' => $m['kod'] . ', ' . ($m['seri'] === 'p' ? 'iç ortam için P serisi, galvaniz-inox kasa' : 'dış ortam serisi, boyalı kasa')
            . ', soğutma kapasitesi ' . $m['w'] . ' W, ' . $m['ip'] . ', dikili tip yan kapak montaj.',
        'brand' => ['@type' => 'Brand', 'name' => 'Teknokol'],
        'height' => ['@type' => 'QuantitativeValue', 'value' => $my, 'unitCode' => 'MMT'],
        'width' => ['@type' => 'QuantitativeValue', 'value' => $mg, 'unitCode' => 'MMT'],
        'depth' => ['@type' => 'QuantitativeValue', 'value' => $md, 'unitCode' => 'MMT'],
        'additionalProperty' => [
            ['@type' => 'PropertyValue', 'name' => 'Soğutma kapasitesi', 'value' => $m['w'], 'unitCode' => 'WTT'],
            ['@type' => 'PropertyValue', 'name' => 'Koruma sınıfı', 'value' => $m['ip']],
            ['@type' => 'PropertyValue', 'name' => 'Soğutucu gaz', 'value' => $tpk['ortak']['gaz']],
        ],
    ];
    if (resim_var('tpk/' . $m['slug'])) {
        $d = resim_dosyalari('tpk/' . $m['slug']);
        $s['image'] = tam_url('assets/img/tpk/' . $m['slug'] . '-' . array_key_last($d) . '.jpg');
    }
    $urunSema[] = $s;
}

partial('head', [
    'foy' => $foy,
    'css' => ['form', 'sayfa-pano'],
    'js' => ['sayfa-pano'],
    'gsap' => ['ScrollTrigger'],
    'onyukle' => [['tpk/tpk-2000p', '(min-width: 768px) 160px, 120px']],
    'schema' => $urunSema,
]);
partial('ust', ['foy' => $foy]);

/** Kurulum piktogramı: plan görünüşü (üstten). Duvar taralı, komşu pano kesik, kapalı tavan taralı. */
$piktogram = function (int $no) use ($kurulumlar): string {
    $k = $kurulumlar[$no];
    $o = '<svg class="kurulum__cizim" viewBox="0 0 72 48" width="72" height="48" aria-hidden="true" focusable="false">';
    if ($k['duvar']) {
        $o .= '<path class="cizgi" d="M4 12H68"/><path class="cizgi--ince" d="M8 12l4-6M16 12l4-6M24 12l4-6M32 12l4-6M40 12l4-6M48 12l4-6M56 12l4-6M64 12l4-6"/>';
    }
    if ($k['komsu'] >= 1) {
        $o .= '<path class="cizgi--kesik" d="M10 16H28V36H10"/>';
    }
    if ($k['komsu'] === 2) {
        $o .= '<path class="cizgi--kesik" d="M44 16H62M44 36H62M62 16V36"/>';
    }
    if (!$k['tavan']) {
        $o .= '<path class="cizgi--ince" d="M28 22l6-6M28 28l12-12M28 34l16-16M32 36l12-12M38 36l6-6"/>';
    }
    $o .= '<rect class="cizgi" x="28" y="16" width="16" height="20"/><path class="cizgi--kalin" d="M28 36H44"/>';
    $o .= '</svg>';
    return $o;
};

$hucreSayi = function (string $ad, string $sinif = '', ?string $yardim = null) use ($alanlar, $girdi, $hatalar): string {
    $a = $alanlar[$ad];
    $hata = $hatalar[$ad] ?? null;
    $desc = 'alan-' . $ad . '-aralik' . ($yardim ? ' alan-' . $ad . '-yardim' : '') . ' alan-' . $ad . '-hata';
    return '<div class="hucre hucre--sayi' . ($hata ? ' hucre--hata' : '') . ($sinif ? ' ' . $sinif : '') . '" data-alan="' . e($ad) . '">'
        . '<label class="hucre__etiket" for="' . e($ad) . '">' . e($a['etiket']) . ' (' . e($a['birim']) . ')</label>'
        . '<input class="hucre__deger" type="text" inputmode="numeric" autocomplete="off" spellcheck="false" id="' . e($ad) . '" name="' . e($ad) . '"'
        . ' value="' . e($girdi['ham'][$ad]) . '" data-min="' . $a['min'] . '" data-max="' . $a['max'] . '" data-adim="' . $a['adim'] . '"'
        . ' aria-describedby="' . e($desc) . '"' . ($hata ? ' aria-invalid="true"' : '') . '>'
        . '<span class="hucre__revizyon" aria-hidden="true"></span>'
        . '<span class="gizli" id="alan-' . e($ad) . '-aralik">' . e(sogutma_tr($a['min']) . ' ile ' . sogutma_tr($a['max']) . ' ' . $a['birim'] . ' arası; ok tuşlarıyla ' . sogutma_tr($a['adim']) . ' ' . $a['birim'] . ' değişir') . '</span>'
        . ($yardim ? '<p class="hucre__yardim" id="alan-' . e($ad) . '-yardim">' . e($yardim) . '</p>' : '')
        . '<p class="hucre__hata" id="alan-' . e($ad) . '-hata"' . ($hata ? '' : ' hidden') . '>' . e((string) $hata) . '</p>'
        . '</div>';
};

$sonucHtml = function () use ($metin, $r, $teklifUrl): string {
    $o = '<dl class="denge__liste">';
    foreach ($metin['satirlar'] as [$kod, $ad, $deger]) {
        $o .= '<div class="denge__satir denge__satir--' . e(strtolower($kod)) . '"><dt><span class="denge__kod" aria-hidden="true">' . e($kod) . '</span> ' . e($ad)
            . '</dt><dd class="sayi" data-deger="' . e($kod) . '">' . e($deger) . '</dd></div>';
    }
    $o .= '</dl><div class="denge__sonuc" data-durum="' . e($r['durum']) . '">';
    if ($metin['oneri']) {
        $o .= '<p class="denge__oneri" data-metin="oneri">' . e($metin['oneri']) . '</p>';
    }
    if ($metin['durum_metni']) {
        $o .= '<p class="denge__oneri" data-metin="durum">' . e($metin['durum_metni']) . '</p>';
    }
    if ($metin['kucuk']) {
        $o .= '<p class="denge__not" data-metin="kucuk">' . e($metin['kucuk']) . '</p>';
    }
    if ($metin['montaj']) {
        $o .= '<p class="denge__not' . ($metin['montaj_uyari'] ? ' denge__not--uyari' : '') . '" data-metin="montaj">' . ($metin['montaj_uyari'] ? '<span class="revizyon" aria-hidden="true"></span> ' : '') . e($metin['montaj']) . '</p>';
    }
    if ($metin['aralik']) {
        $o .= '<p class="denge__not denge__not--uyari" data-metin="aralik"><span class="revizyon" aria-hidden="true"></span> ' . e($metin['aralik']) . '</p>';
    }
    $o .= '</div>';
    return $o;
};
?>
<main id="icerik" class="foy foy--sogutma" data-foy="5">

  <section id="ic-dis" class="bant ic-dis" data-izgara="cizim" data-satir="A" data-sc-act="flow" aria-labelledby="ic-dis-baslik">
    <div class="izgara">
      <div class="ic-dis__bas">
        <?php partial('kesme-noktasi', ['ref' => '/3', 'sinif' => 'ic-dis__kesme']); ?>
        <h1 id="ic-dis-baslik">Pano soğutma sistemleri</h1>
        <p class="ic-dis__alt">TPK serisi: 450 W ile 3340 W arası soğutma kapasitesi, iç ortam için P serisi, dış ortam için boyalı kasa serisi.</p>
        <div class="ic-dis__eylem">
          <?= dugme('Teklif al', url('iletisim.php') . '?' . http_build_query(['konu' => 'Pano soğutma: TPK pano kliması'], '', '&', PHP_QUERY_RFC3986) . '#talep') ?>
          <?= dugme('Soğutma ihtiyacını hesapla', '#hesap', ['tur' => 'ikincil', 'ref' => '/5.B']) ?>
        </div>
      </div>
      <div class="sahne" data-sahne>
        <div class="sahne__izgara" aria-hidden="true"></div>
        <div class="sahne__ic">
          <div class="sahne__metin">
            <h2 class="sahne__baslik">Pano içi</h2>
            <p>Sürücüler, güç kaynakları ve kontaktörler çalışırken ısı üretir. Bu ısı dışarı atılmazsa iç sıcaklık cihazların çalışma sınırını aşar.</p>
          </div>
          <svg class="sahne__pano" viewBox="-96 -40 256 520" aria-hidden="true" focusable="false">
            <g class="sahne__cizim">
              <path class="cizgi--ince" d="M-80 400H160" data-sira="1"/>
              <path class="cizgi--ince" d="M-74 410l8-10M-62 410l8-10M-50 410l8-10M-38 410l8-10M-26 410l8-10M-14 410l8-10M-2 410l8-10M10 410l8-10M22 410l8-10M34 410l8-10M46 410l8-10M58 410l8-10M70 410l8-10M82 410l8-10M94 410l8-10M106 410l8-10M118 410l8-10M130 410l8-10M142 410l8-10" data-sira="1"/>
              <rect class="cizgi" x="0" y="0" width="160" height="400" data-sira="2"/>
              <rect class="cizgi--ince" x="6" y="6" width="148" height="388" data-sira="3"/>
              <rect class="cizgi--ince" x="138" y="176" width="7" height="44" data-sira="3"/>
              <rect class="cizgi" x="18" y="44" width="64" height="104" data-sira="4"/>
              <path class="cizgi--ince" d="M18 148L82 44M24 60q4-5 8 0t8 0M60 132q4-5 8 0t8 0" data-sira="5"/>
              <rect class="cizgi" x="18" y="188" width="50" height="62" data-sira="4"/>
              <path class="cizgi--ince" d="M18 250L68 188M23 202q4-5 8 0t8 0M52 236h10M52 240h10" data-sira="5"/>
              <path class="cizgi--ince" d="M12 300H148" data-sira="4"/>
              <rect class="cizgi" x="20" y="284" width="20" height="32" data-sira="5"/>
              <rect class="cizgi" x="46" y="284" width="20" height="32" data-sira="5"/>
              <path class="cizgi--ince sahne__isi" d="M90 70H152M90 96H152M90 122H152M76 206H152M76 232H152M74 300H152" data-sira="6"/>
              <path class="sahne__ok" d="M158 70l-8-3v6zM158 96l-8-3v6zM158 122l-8-3v6zM158 206l-8-3v6zM158 232l-8-3v6zM158 300l-8-3v6z" data-sira="7"/>
              <text class="sahne__etiket" x="18" y="36" data-sira="8">-U1</text>
              <text class="sahne__etiket" x="18" y="180" data-sira="8">-G1</text>
              <text class="sahne__etiket" x="20" y="276" data-sira="8">-K1 -K2</text>
              <path class="cizgi--ince" d="M-6 0H-50M-6 400H-50M-40 0V400" data-sira="9"/>
              <path class="sahne__ok" d="M-40 0l-3 9h6zM-40 400l-3-9h6z" data-sira="9"/>
              <text class="sahne__etiket" x="-48" y="200" text-anchor="middle" transform="rotate(-90 -48 200)" data-sira="10">2000</text>
              <path class="cizgi--ince" d="M0 404V446M160 404V446M0 440H160" data-sira="9"/>
              <path class="sahne__ok" d="M0 440l9-3v6zM160 440l-9-3v6z" data-sira="9"/>
              <text class="sahne__etiket" x="80" y="434" text-anchor="middle" data-sira="10">800</text>
              <text class="sahne__etiket" x="0" y="474" data-sira="10">Derinlik 600</text>
            </g>
          </svg>
        </div>
        <div class="sahne__duvar" aria-hidden="true"></div>
        <figure class="sahne__tpk" data-tpk>
          <?= resim('tpk/tpk-2000p', 'TPK 2000P pano kliması, üretici kataloğundaki ürün görseli', ['sizes' => '(min-width: 768px) 160px, 120px', 'onyukle' => true, 'sinif' => 'sahne__tpk-gorsel']) ?>
          <svg class="sahne__cikis" viewBox="0 0 200 120" aria-hidden="true" focusable="false">
            <path class="cizgi--ince sahne__isi" d="M0 30q12-8 24 0t24 0t24 0t24 0M0 60q12-8 24 0t24 0t24 0t24 0M0 90q12-8 24 0t24 0t24 0t24 0" data-sira="1"/>
            <path class="sahne__ok" d="M104 30l-9-3.5v7zM104 60l-9-3.5v7zM104 90l-9-3.5v7z" data-sira="2"/>
          </svg>
          <figcaption class="sahne__tpk-etiket"><span class="cihaz">-E1</span> TPK, yan kapak montaj</figcaption>
        </figure>
        <div class="sahne__dis">
          <div class="sahne__metin">
            <h2 class="sahne__baslik">Pano dışı</h2>
            <p>TPK pano klimaları ısıyı panonun dışına atar ve iç sıcaklığı termostatta ayarlı değerde tutar. Termostat fabrika ayarı 30 °C.</p>
          </div>

          <div class="sahne__ortam">
            <svg class="sahne__ortam-kota" viewBox="0 0 240 44" aria-hidden="true" focusable="false">
              <path class="cizgi--ince" d="M12 44V14M228 44V14M12 20H228" data-sira="3"/>
              <path class="sahne__ok" d="M12 20l9-3v6zM228 20l-9-3v6z" data-sira="4"/>
              <text class="sahne__etiket" x="16" y="12" data-sira="5">+20 °C</text>
              <text class="sahne__etiket" x="224" y="12" text-anchor="end" data-sira="5">+55 °C</text>
              <text class="sahne__etiket" x="120" y="40" text-anchor="middle" data-sira="5">Tu</text>
            </svg>
            <p class="sahne__ortam-metin">TPK çalışma aralığı: ortam sıcaklığı +20&nbsp;°C ile +55&nbsp;°C arası.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="hesap" class="bant hesap" data-izgara="cizim alt" data-satir="B" data-sc-act="flow" aria-labelledby="hesap-baslik">
    <div class="izgara">
      <div class="hesap__bas">
        <h2 id="hesap-baslik">Soğutma ihtiyacı</h2>
        <p class="olcu">Pano ölçülerini çizimdeki ölçü değerlerine yazın, kurulum şeklini seçin. Hesap ve çizim birlikte güncellenir. Hesap IEC/TR 60890 yöntemine göre bir ön seçimdir.</p>
      </div>
      <form class="hesap__form" method="get" action="<?= e(url($foy['dosya'])) ?>#hesap" novalidate data-hesap>
        <div class="hesap__ic">
          <div class="cizim" data-cizim>
            <div class="cizim__olculer form-hucreler" data-olculer>
              <?= $hucreSayi('h', 'cizim__olcu') ?>
              <?= $hucreSayi('w', 'cizim__olcu') ?>
              <?= $hucreSayi('d', 'cizim__olcu') ?>
            </div>
            <div class="cizim__kutu" data-kutu>
              <?= sogutma_cizim_svg($g, $r, $yer, KUTU_W, KUTU_H) ?>
            </div>
          </div>
          <fieldset class="kurulum">
            <legend class="kurulum__baslik">Kurulum şekli</legend>
            <div class="kurulum__secenekler">
<?php foreach ($kurulumlar as $no => $k): ?>
              <div class="kurulum__secenek">
                <input class="kurulum__girdi" type="radio" name="kurulum" value="<?= $no ?>" id="<?= $no === 1 ? 'kurulum' : 'kurulum-' . $no ?>"<?= (int) $g['kurulum'] === $no ? ' checked' : '' ?>>
                <label class="kurulum__etiket" for="<?= $no === 1 ? 'kurulum' : 'kurulum-' . $no ?>" title="<?= e($k['ad']) ?>">
                  <?= $piktogram($no) ?>
                  <span class="kurulum__ad"><span class="gizli"><?= $no ?>: <?= e($k['ad']) ?></span><span aria-hidden="true"><?= e($k['kisa']) ?></span></span>
                </label>
              </div>
<?php endforeach; ?>
            </div>
            <p class="kurulum__formul"><span class="gizli">Etkin yüzey formülü: </span><span data-formul><?= e($kurulumlar[(int) $g['kurulum']]['formul']) ?></span></p>
          </fieldset>
        </div>

        <div class="hesap__dis">
          <div class="hesap__akis" data-akis data-yon="<?= sogutma_tr($r['Qs']) === '0' ? 'yok' : ($r['Qs'] > 0 ? 'iceri' : 'disari') ?>" aria-hidden="true">
            <svg class="hesap__akis-ok" viewBox="0 0 64 20" width="64" height="20"><path class="cizgi--ince" d="M4 10H60"/><path class="hesap__akis-uc" d="M60 10l-10-4v8z"/></svg>
            <?php $qsYon = sogutma_tr($r['Qs']) === '0' ? '' : ($r['Qs'] > 0 ? ' içeri' : ' dışarı'); ?><span class="hesap__akis-deger" data-akis-deger>Qs <?= e(sogutma_isaretli($r['Qs']) . $qsYon) ?></span>
          </div>
          <div class="hesap__hucreler form-hucreler">
            <div class="hucre hucre--secim hucre--genis" data-alan="k">
              <label class="hucre__etiket" for="k">Pano gövdesi</label>
              <select class="hucre__deger" id="k" name="k" aria-describedby="alan-k-yardim">
<?php foreach ($govdeler as $kd => $ad): ?>
                <option value="<?= e($kd) ?>"<?= sogutma_k_anahtar((float) $g['k']) === $kd ? ' selected' : '' ?>><?= e($ad) ?>, k <?= e(str_replace('.', ',', $kd)) ?></option>
<?php endforeach; ?>
              </select>
              <p class="hucre__yardim" id="alan-k-yardim">k: ısı geçirgenlik katsayısı, W/m²K</p>
            </div>
            <?= $hucreSayi('qv', 'hucre--genis', 'Cihaz kataloglarındaki güç kaybı değerlerinin toplamı.') ?>
            <?= $hucreSayi('tu') ?>
            <?= $hucreSayi('ti') ?>
          </div>
          <div class="hesap__gonder"><?= dugme('Hesapla', null, ['button' => true, 'tip' => 'submit']) ?></div>

          <div class="denge" id="sonuc">
            <h3 class="denge__baslik">Isı dengesi</h3>
            <div data-sonuc><?= $sonucHtml() ?></div>
            <p class="gizli" aria-live="polite" aria-atomic="true" data-duyuru></p>
          </div>
          <div class="denge__alt">
            <p class="denge__teklif"><?= dugme('Bu hesapla teklif al', $teklifUrl, ['tur' => 'ikincil', 'ref' => '/8.B', 'ozellik' => ['data-teklif' => true]]) ?></p>
            <p class="denge__dipnot">%10 pay, ölçü ve değer belirsizliği için eklenir. Hesap IEC/TR 60890 yöntemine göre bir ön seçimdir. Kesin seçim için cihaz listenizle bize danışın. Kapasite değerleri üretici kataloğundandır (Teknokol, 2021, <a class="alti-cizili" href="<?= e(url('2.pdf')) ?>#page=7" target="_blank" rel="noopener">PDF s. 7-10<span class="gizli"> (PDF, yeni sekmede açılır)</span></a>). <?= capraz_a('/5.C', 'Tüm modeller') ?></p>
          </div>
          <div class="denge__fan" data-fan<?= $r['durum'] === 'yok' ? '' : ' hidden' ?>>
            <?php $fanFoto = resim('urun/u18', 'Filtreli fan, temsilî görsel', ['sizes' => '96px']); ?>
            <?= $fanFoto !== '' ? $fanFoto : sembol('fan', ['buyukluk' => 72, 'etiket' => '-E2']) ?>
          </div>
        </div>
      </form>
    </div>
  </section>

  <section id="modeller" class="bant modeller" data-satir="C" data-sc-act="flow" aria-labelledby="modeller-baslik">
    <div class="izgara">
      <div class="modeller__bas">
        <h2 id="modeller-baslik">Tüm modeller</h2>
        <p class="olcu">Tedarik ettiğimiz TPK pano klimaları iki seridir. P serisi: galvaniz-inox kasa, dijital termostat, açılabilir kapak, kolay bakım. Dış ortam serisi: boyalı kasa.</p>
      </div>
      <div class="modeller__tablo-kap">
        <table class="modeller__tablo">
          <caption class="gizli">TPK pano klimaları, 10 model, kapasiteye göre sıralı</caption>
          <thead>
            <tr>
              <th scope="col">Model</th>
              <th scope="col">Seri</th>
              <th scope="col" class="sag">Soğutma (W)</th>
              <th scope="col" class="sag">Güç tüketimi (W)</th>
              <th scope="col">Ebat Y x G x D (mm)</th>
              <th scope="col">IP</th>
              <th scope="col" class="sag">Ağırlık (kg)</th>
              <th scope="col">Gerilim</th>
            </tr>
          </thead>
          <tbody>
<?php foreach ($modeller as $m): $onerilen = $r['durum'] === 'model' && (int) $m['w'] === (int) $r['kapasite']; ?>
            <tr class="modeller__satir<?= $m['seri'] === 'p' ? ' modeller__satir--p' : '' ?><?= $onerilen ? ' is-onerilen' : '' ?>" data-kapasite="<?= (int) $m['w'] ?>">
              <th scope="row" class="modeller__model">
                <span class="modeller__foto"><?= resim('tpk/' . $m['slug'], $m['kod'] . ' pano kliması, üretici kataloğundaki ürün görseli', ['sizes' => '44px', 'genislik' => 320]) ?></span>
                <span class="modeller__kod"><span class="modeller__ad"><?= e($m['kod']) ?></span><span class="modeller__isaret" data-onerilen-metin><?= $onerilen ? 'Hesaba göre önerilen' : '' ?></span></span>
              </th>
              <td data-baslik="Seri"><?= $m['seri'] === 'p' ? 'P serisi, iç ortam' : 'Dış ortam serisi' ?></td>
              <td data-baslik="Soğutma (W)" class="sag sayi"><?= e(sogutma_tr((float) $m['w'])) ?></td>
              <td data-baslik="Güç tüketimi (W)" class="sag sayi"><?php if ($m['tuketim'] === null): ?><span class="modeller__teyit"><span class="revizyon" aria-hidden="true"></span> teyit ediliyor<sup><a class="modeller__dipnot-ref" href="#dipnot-tuketim" aria-label="Dipnot 1">1</a></sup></span><?php else: ?><?= e(str_replace(' W', '', $m['tuketim'])) ?><?php endif; ?></td>
              <td data-baslik="Ebat Y x G x D (mm)" class="sayi"><?= e(implode(' x ', array_map(fn ($v) => sogutma_tr((float) $v), $m['ebat']))) ?></td>
              <td data-baslik="IP"><?= e($m['ip']) ?></td>
              <td data-baslik="Ağırlık (kg)" class="sag sayi"><?= e($m['net']) ?></td>
              <td data-baslik="Gerilim" class="sayi"><?= e($m['gerilim']) ?></td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="modeller__notlar">
        <ol class="notlar__liste">
          <li><span>Soğutucu gaz <?= e($tpk['ortak']['gaz']) ?>. Çalışma aralığı <?= e($tpk['ortak']['aralik']) ?> ortam sıcaklığı.</span></li>
          <li><span>Montaj: dikili tip, yan kapak (dik duvar). Termostat fabrika ayarı <?= e($tpk['ortak']['termostat']) ?>.</span></li>
          <li id="dipnot-tuketim"><span>TPK 3000P ve TPK 3000 güç tüketimi: katalogda 690 W yazıyor; üreticiden teyit ediliyor.</span></li>
          <li><span>Değerler üretici kataloğundandır (Teknokol, 2021, <a class="alti-cizili" href="<?= e(url('2.pdf')) ?>#page=7" target="_blank" rel="noopener">PDF s. 7-10<span class="gizli"> (PDF, yeni sekmede açılır)</span></a>). Ağırlık net ağırlıktır.</span></li>
        </ol>
      </div>
    </div>
  </section>

  <section id="teklif" class="bant teklif" data-satir="D" data-sc-act="flow" aria-labelledby="teklif-baslik">
    <div class="izgara">
      <div class="teklif__sahne" data-kapanis>
        <div class="teklif__izgara" aria-hidden="true"></div>
        <div class="teklif__ic">
          <h2 id="teklif-baslik">Hesabınız</h2>
          <p class="teklif__ozet" data-ozet><?= e($metin['ozet']) ?></p>
          <p class="teklif__eylem"><?= dugme('Bu hesapla teklif al', $teklifUrl, ['ozellik' => ['data-teklif' => true]]) ?></p>
          <p class="teklif__not">Bağlantı forma yalnızca hesap değerlerini taşır. Ad, telefon ve e-posta bilgisini formda siz yazarsınız.</p>
        </div>
        <div class="teklif__duvar" data-duvar aria-hidden="true"></div>
        <div class="teklif__dis" data-dis>
          <p class="teklif__dis-baslik">Pano dışı</p>
          <p>Cihaz listesiyle birlikte hesaplamak isterseniz arayın: <a class="alti-cizili" href="tel:<?= e(config('phone.tel')) ?>"><?= e(config('phone.label')) ?></a>.</p>
        </div>
      </div>
    </div>
  </section>

</main>
<?= json_gom('pano-veri', [
    'alanlar' => $alanlar,
    'govdeler' => $govdeler,
    'kurulumlar' => $kurulumlar,
    'basamaklar' => array_values($basamaklar),
    'olcekler' => sogutma_olcekler(),
    'iletisim' => url('iletisim.php'),
    'sayfa' => url($foy['dosya']),
]) ?>
<?php partial('antet', ['foy' => $foy, 'olcek' => '1:' . $yer['n']]); partial('son', ['foy' => $foy]); ?>
