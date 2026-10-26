<?php
/**
 * Föy 2, Firma bilgisi (paket P2). Dil bilgisi: bölümlü editoryal.
 * İmza: köşedeki gerçek boy antet açılıp altı hücreli içindekiler tablosu olur (A), her hücre kendi bölümünün
 * başlık hücresidir (B-F), kolofonda hücreler dolu bir antete geri katlanır (F).
 * JS kapalı ya da azaltılmış harekette: içindekiler durağan tablo, kolofon anteti durağan.
 */
require __DIR__ . '/app/bootstrap.php';
$foy = foy('kurumsal');

$tel = config('phone');
$eposta = config('email');
$geo = config('geo_yazi.kisa');

/* Altı alan: antet hücresi = bölümün başlık hücresi. 'kucuk' kapaktaki gerçek boy antette yazılı olan
   (boş = doldurulmamış), 'dolu' kolofondaki doldurulmuş antet değeri. */
$alanlar = [
    'A' => ['alan' => 'Firma', 'id' => 'unvan', 'baslik' => 'Tekayper Elektromarket',
            'ozet' => 'San. Tic. A.Ş., Alaaddinbey Mah., Nilüfer / Bursa.',
            'kucuk' => 'Tekayper', 'dolu' => 'Tekayper Elektromarket San. Tic. A.Ş.'],
    'B' => ['alan' => 'Faaliyet', 'id' => 'faaliyet', 'baslik' => 'Ne yapıyoruz',
            'ozet' => 'Ürün tedariki, mühendislik, proje, uygulama ve montaj, servis.',
            'kucuk' => '', 'dolu' => 'Tedarik, mühendislik, proje, montaj, servis'],
    'C' => ['alan' => 'Çalışma', 'id' => 'calisma', 'baslik' => 'Nasıl çalışıyoruz',
            'ozet' => 'Çözüm ortaklığı, donanım ve stok.',
            'kucuk' => '', 'dolu' => 'Çözüm ortaklığı, stok'],
    'D' => ['alan' => 'Kalite', 'id' => 'kalite', 'baslik' => 'Kalite politikası',
            'ozet' => 'ISO 9000 kalite yönetim sistemi ilkeleri.',
            'kucuk' => '', 'dolu' => 'ISO 9000 ilkeleri'],
    'E' => ['alan' => 'Konum', 'id' => 'konum', 'baslik' => 'Adres',
            'ozet' => 'Alaaddinbey Mah., Nilüfer / Bursa.',
            'kucuk' => '', 'dolu' => $geo, 'dolu_osi' => true],
    'F' => ['alan' => 'Antet', 'id' => 'antet', 'baslik' => 'Teklif ve iletişim',
            'ozet' => $tel['label'] . ', ' . $eposta . '.',
            'kucuk' => 'Föy 2 / 9', 'dolu' => 'Föy 2 / 9'],
];
$harfler = array_keys($alanlar);

/** Gerçek boy antet (kapakta boş, kolofonda dolu) */
$kucukAntet = function (string $tur) use ($alanlar): string {
    $h = '';
    foreach ($alanlar as $harf => $a) {
        $deger = $tur === 'dolu' ? $a['dolu'] : $a['kucuk'];
        $osi = $tur === 'dolu' && !empty($a['dolu_osi']);
        $h .= '<div class="krm-hucre" data-harf="' . $harf . '">'
            . '<span class="krm-hucre__etiket"><span class="krm-hucre__harf">' . $harf . '</span> ' . e($a['alan']) . '</span>'
            . ($deger !== ''
                ? '<span class="krm-hucre__deger' . ($osi ? ' krm-hucre__deger--osi' : '') . '">' . e($deger) . '</span>'
                : '<span class="krm-hucre__deger krm-hucre__deger--bos"><span class="gizli">boş</span></span>')
            . '</div>';
    }
    return $h;
};

/** Büyük hücreler: içindekiler (bağlantılı) ya da kolofondaki katlanan kopya (süs) */
$buyukHucreler = function (bool $baglanti) use ($alanlar, $foy): string {
    $h = '';
    foreach ($alanlar as $harf => $a) {
        $c = capraz('/2.' . $harf);
        $ic = '<span class="krm-hucre__etiket"><span class="krm-hucre__harf">' . $harf . '</span> ' . e($a['alan']) . '</span>'
            . '<span class="krm-hucre__deger">'
            . '<span class="krm-hucre__baslik">' . e($a['baslik']) . '</span>'
            . '<span class="krm-hucre__ozet">' . e($a['ozet']) . '</span>'
            . '<span class="krm-hucre__ref" aria-hidden="true">' . e($c['kisa']) . '</span>'
            . '</span>';
        if ($baglanti && $harf !== 'A') {
            $h .= '<a class="krm-hucre krm-hucre--baglanti" data-harf="' . $harf . '" href="' . e($c['href']) . '" data-capraz="'
                . e($c['kisa']) . '" data-capraz-tam="' . e($c['tam'] . ' ' . $c['ad']) . '">' . $ic . '</a>';
        } else {
            $h .= '<div class="krm-hucre" data-harf="' . $harf . '"' . ($baglanti ? ' aria-current="location"' : '') . '>' . $ic . '</div>';
        }
    }
    return $h;
};

/** Bölüm kenar foliosu: alan adı + harf, sonraki bölüme kesme noktası */
$folio = function (string $harf, string $sonrakiHref, string $sonrakiRef, string $sonrakiAd) use ($alanlar): string {
    return '<div class="krm-folio">'
        . '<p class="krm-folio__bas" aria-hidden="true"><span class="krm-folio__harf">' . $harf . '</span>'
        . '<span class="krm-folio__alan">' . e($alanlar[$harf]['alan']) . '</span></p>'
        . '<a class="krm-folio__sonraki" href="' . e($sonrakiHref) . '">'
        . '<svg class="krm-folio__ok" width="40" height="16" viewBox="0 0 40 16" aria-hidden="true" focusable="false"><path d="M0 8h30" fill="none" stroke="currentColor" stroke-width="2"/><path d="M30 3v10l7-5z" fill="currentColor"/></svg>'
        . '<span class="krm-folio__ref" aria-hidden="true">' . e($sonrakiRef) . '</span>'
        . '<span class="krm-folio__ad">' . e($sonrakiAd) . '</span></a>'
        . '</div>';
};

/** Bölüm başlık hücresi */
$basHucre = function (string $harf) use ($alanlar): string {
    $a = $alanlar[$harf];
    return '<header class="krm-bas">'
        . '<p class="krm-bas__etiket" aria-hidden="true"><span class="krm-hucre__harf">' . $harf . '</span> ' . e($a['alan']) . '</p>'
        . '<h2 class="krm-bas__baslik" id="krm-' . strtolower($harf) . '-baslik">' . e($a['baslik']) . '</h2>'
        . '<p class="krm-bas__ozet">' . e($a['ozet']) . '</p>'
        . '<span class="krm-bas__ref" aria-hidden="true">/2.' . $harf . '</span>'
        . '</header>';
};

partial('head', [
    'foy'     => $foy,
    'css'     => ['sayfa-kurumsal'],
    'js'      => ['sayfa-kurumsal'],
    'gsap'    => ['ScrollTrigger'],
    'lenis'   => true,
    'schema'  => [schema_sayfa('AboutPage', $foy, ['about' => ['@id' => tam_url('') . '#firma']])],
]);
partial('ust', ['foy' => $foy]);
?>
<main id="icerik" class="foy foy--kurumsal" data-foy="2">
  <script>
  // Tam hareket ve yeterli yükseklikte açılım düzeni ilk boyamadan önce kurulur (sıçrama olmasın).
  (function (h) { if (h.dataset.hareket === 'tam' && innerHeight >= 540) h.classList.add('krm-tam'); })(document.documentElement);
  </script>

  <section id="unvan" class="krm-unvan" data-satir="A" data-sc-act="pin" aria-labelledby="krm-h1">
    <div class="krm-sahne izgara">
      <div class="krm-kapak">
        <h1 class="krm-h1" id="krm-h1"><span class="krm-h1__satir">Tekayper Elektromarket</span> <span class="krm-h1__satir">San. Tic. A.Ş.</span></h1>
        <p class="krm-alt">Endüstriyel otomasyon ürünleri, mühendislik, proje, uygulama, montaj ve servis. Alaaddinbey Mahallesi, Nilüfer / Bursa.</p>
      </div>
      <div class="krm-antet krm-antet--kucuk" role="group" aria-label="Antet, doldurulmamış">
        <?= $kucukAntet('bos') ?>
      </div>
      <nav class="krm-antet krm-antet--buyuk krm-dizin" aria-label="Firma bilgisi bölümleri">
        <?= $buyukHucreler(true) ?>
      </nav>
    </div>
  </section>

  <div class="krm-govde izgara">

    <section id="faaliyet" class="krm-bolum krm-bolum--b" data-satir="B" data-sc-act="flow" aria-labelledby="krm-b-baslik">
      <?= $folio('B', '#calisma', '/2.C', 'Çalışma') ?>
      <div class="krm-icerik">
        <?= $basHucre('B') ?>
        <p class="krm-b__metin krm-giris">Tekayper, endüstriyel otomasyon ürünlerinin satışı, mühendislik hizmetleri, proje, uygulama, montaj ve servis alanlarında çalışır. Alanında tanınmış üreticilerin ürünlerini tedarik eder; bu ürünlerin teknik desteğini ve satış sonrası servisini verir.</p>
        <ol class="krm-notlar" aria-label="Faaliyet alanları">
          <li class="krm-giris"><span class="krm-notlar__no" aria-hidden="true">1</span><span class="krm-notlar__ad">Ürün tedariki</span></li>
          <li class="krm-giris"><span class="krm-notlar__no" aria-hidden="true">2</span><span class="krm-notlar__ad">Mühendislik hizmetleri</span></li>
          <li class="krm-giris"><span class="krm-notlar__no" aria-hidden="true">3</span><span class="krm-notlar__ad">Proje</span></li>
          <li class="krm-giris"><span class="krm-notlar__no" aria-hidden="true">4</span><span class="krm-notlar__ad">Uygulama ve montaj</span></li>
          <li class="krm-giris"><span class="krm-notlar__no" aria-hidden="true">5</span><span class="krm-notlar__ad">Servis</span></li>
        </ol>
      </div>
    </section>

    <section id="calisma" class="krm-bolum krm-bolum--c" data-satir="C" data-sc-act="reveal" aria-labelledby="krm-c-baslik">
      <?= $folio('C', '#kalite', '/2.D', 'Kalite') ?>
      <div class="krm-icerik">
        <?= $basHucre('C') ?>
        <div class="krm-c__sutun">
          <p class="krm-c__metin">Çalıştığımız firmalar bizi yalnızca malzeme tedarikçisi olarak değil, çözüm ortağı olarak görür. Elektrik, elektronik ve otomasyon sektöründeki imalatçı müşterilerimizin ihtiyaçlarına kısa sürede ve uygun koşullarla yanıt verecek donanıma ve stoğa sahibiz.</p>
          <p class="krm-c__baglantilar">
            <?= capraz_a('/4', 'Tedarik ettiğimiz markalar') ?>
            <?= capraz_a('/6', 'Referanslar') ?>
          </p>
        </div>
      </div>
    </section>

    <section id="kalite" class="krm-bolum krm-bolum--d" data-satir="D" data-sc-act="static" aria-labelledby="krm-d-baslik">
      <?= $folio('D', '#konum', '/2.E', 'Konum') ?>
      <div class="krm-icerik">
        <?= $basHucre('D') ?>
        <p class="krm-d__politika">Kalite politikamız, <mark>ISO 9000 kalite yönetim sistemi ilkelerini</mark> uygulamak ve müşteri memnuniyetini en üst düzeyde tutmaktır.</p>
      </div>
    </section>

    <section id="konum" class="krm-bolum krm-bolum--e" data-satir="E" data-sc-act="settle" aria-labelledby="krm-e-baslik">
      <?= $folio('E', '#antet', '/2.F', 'Antet') ?>
      <div class="krm-icerik">
        <?= $basHucre('E') ?>
        <figure class="krm-foto">
          <div class="krm-foto__ek">
            <?= resim('kurumsal/magaza-cephe', 'Tekayper mağazasının cephesi: cam cepheli binada Tekayper tabelası ve vitrinde tedarik edilen markaların afişleri.', ['sizes' => '(min-width: 768px) 540px, calc(100vw - 34px)', 'sinif' => 'krm-foto__img']) ?>
            <svg class="krm-atac" width="30" height="78" viewBox="0 0 30 78" aria-hidden="true" focusable="false"><path class="cizgi" data-sira="1" d="M9 58V14a6 6 0 0 1 12 0v52a9 9 0 0 1-18 0V22"/></svg>
          </div>
          <figcaption class="krm-foto__alt">Mağaza cephesi</figcaption>
        </figure>
        <div class="krm-e__bilgi">
          <address class="krm-e__adres"><?= e(config('address.line1')) ?><br><?= e(config('address.line2')) ?><br><?= e(config('address.district')) ?> / <?= e(config('address.city')) ?></address>
          <p class="krm-e__koordinat"><span class="krm-e__koordinat-etiket">Koordinat</span> <span class="krm-e__koordinat-deger"><?= e($geo) ?></span></p>
          <ul class="krm-e__baglantilar">
            <li><a class="krm-dis" href="<?= e(config('maps')) ?>" target="_blank" rel="noopener"><span class="krm-dis__metin">Haritada aç</span><?= glif('dis-proje', 16, 'krm-dis__glif') ?><span class="gizli"> (Google Haritalar, yeni sekmede açılır)</span></a></li>
            <li><?= capraz_a('/8', 'Yol tarifi ve iletişim') ?></li>
          </ul>
        </div>
      </div>
    </section>

    <section id="antet" class="krm-kolofon" data-satir="F" data-sc-act="flip" aria-labelledby="krm-f-baslik">
     <div class="krm-bolum krm-bolum--f">
      <?= $folio('F', url('iletisim.php#talep'), '/8.B', 'Talep') ?>
      <div class="krm-icerik">
        <?= $basHucre('F') ?>
        <p class="krm-f__metin">Teklif ve teknik soru için <a href="tel:<?= e($tel['tel']) ?>"><?= e($tel['label']) ?></a> numarasını arayın, <a href="mailto:<?= e($eposta) ?>"><?= e($eposta) ?></a> adresine yazın ya da <a href="<?= e(url('iletisim.php#talep')) ?>">talep formunu</a> doldurun.</p>
        <div class="krm-kolofon__alan">
          <div class="krm-antet krm-antet--buyuk krm-antet--katlanan" aria-hidden="true">
            <?= $buyukHucreler(false) ?>
          </div>
          <div class="krm-antet krm-antet--kucuk krm-antet--dolu" role="group" aria-label="Antet, doldurulmuş">
            <?= $kucukAntet('dolu') ?>
          </div>
        </div>
      </div>
     </div>
    </section>

  </div>
</main>
<?php
// Ortak antet footer'ı id="antet-bant" taşır; "antet" kimliği yukarıdaki F satırının bölümüne aittir.
partial('antet', ['foy' => $foy]);
partial('son', ['foy' => $foy]);
