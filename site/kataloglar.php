<?php
/**
 * Föy 7, Ekler (kataloglar). Paket P7. Spec 3.7.
 * Dil bilgisi: filmik tek çekim (uyarlanmış). Özalit zemin, tek kaydırmalı sabitleme (B), kapanışta ikinci kısa sabitleme (D).
 * İmza: markadan sayfaya. Katalog kapağının arkasından gerçek sayfalar çıkar ve yanında gevşek bir yığın olur;
 * dizindeki bir markaya gelince o markanın sayfası yığının önüne kayar, tıklayınca PDF o sayfada açılır.
 * Sayfa haritası: app/data/katalog-sayfalari.php (her sayfa PDF'ten çizdirilip bakılarak doğrulandı).
 */
require __DIR__ . '/app/bootstrap.php';
$foy = foy('ekler');
$kat = data('kataloglar');
$tky = $kat['tekayper'];
$arm = $kat['armendus'];
$tkn = $kat['teknokol'];
$harita = data('katalog-sayfalari');
$sayfalar = $harita['sayfalar'];

// sayı ile birim satır sonunda ayrılmasın
$nb = fn (string $s) => str_replace(' ', "\u{00A0}", $s);
$boyut = [
    'tekayper' => $nb(pdf_boyut($tky['dosya'])),
    'armendus' => $nb(pdf_boyut($arm['dosya'])),
    'teknokol' => $nb(pdf_boyut($tkn['dosya'])),
];
$tkyMeta = 'PDF, ' . $tky['sayfa'] . ' sayfa, ' . $boyut['tekayper'] . ', ' . $tky['ay'];

/* Yığındaki her sayfanın duruşu (sabit, sayfa sırasından türetilir; JS de aynısını okur).
   x/y: sayfa genişliğinin yüzdesi, r: derece. Spec: her sayfa -2 ile 4 derece döner, 40-90 px kayar. */
$durus = function (int $i): array {
    $t = fn (int $k) => (crc32('ek1-' . $i . '-' . $k) % 1000) / 1000;
    return [
        'x' => round(80 + $t(1) * 14, 2),   // yığın tabanı + 40-90 px aralığına denk gelen kayma (sayfa genişliğinin yüzdesi)
        'y' => round(-3 + $t(2) * 6, 2),
        'r' => round(-2 + $t(3) * 6, 2),
    ];
};
$pdfHref = fn (int $no) => url($tky['dosya']) . '#page=' . $no;
$gorselKok = base_path() . '/assets/img/';
$yanAd = ['sol' => 'sol', 'sag' => 'sağ', 'tam' => 'çift'];

/* JS'in gerçek görseli sonradan kurması için: noscript içinde aynı <picture> (JS yokken yığın görünür) */
$sayfaResmi = fn (string $ad) => resim($ad, '', ['sizes' => '(min-width: 1024px) 360px, 90vw', 'sinif' => 'yaprak__resim']);

partial('head', [
    'foy'     => $foy,
    'css'     => ['sayfa-ekler'],
    'js'      => ['sayfa-ekler'],
    'gsap'    => ['ScrollTrigger'],
    'lenis'   => true,
    'onyukle' => [['katalog/kapak-tekayper', '(min-width: 1024px) 420px, 72vw']],
    'schema'  => [schema_sayfa('CollectionPage', $foy, [
        'hasPart' => array_map(fn ($k) => ['@type' => 'DigitalDocument', 'name' => $k['baslik'], 'url' => tam_url($k['dosya']),
            'encodingFormat' => 'application/pdf', 'inLanguage' => $k['dil'] === 'Türkçe' ? 'tr' : 'en', 'numberOfPages' => $k['sayfa']],
            [$tky, $arm, $tkn]),
    ])],
]);
partial('ust', ['foy' => $foy]);
?>
<main id="icerik" class="foy foy--ekler" data-foy="7" data-gorsel-kok="<?= e($gorselKok) ?>">

  <div class="ekler-akis izgara" data-akis>

    <section class="kapak" id="kapak" data-izgara="cizim alt" data-satir="A" data-sc-act="parallax" aria-labelledby="kapak-baslik">
      <div class="kapak__metin">
        <h1 class="kapak__baslik" id="kapak-baslik">Kataloglar</h1>
        <p class="kapak__alt">Tekayper ürün kataloğu ve Armendus kataloğu, PDF olarak.</p>
        <div class="indir">
          <?= dugme('Tekayper kataloğunu indir', url($tky['dosya']), ['ozellik' => ['download' => true, 'type' => 'application/pdf', 'aria-describedby' => 'indir-meta']]) ?>
          <p class="indir__meta osi" id="indir-meta"><?= e($tkyMeta) ?></p>
        </div>
      </div>
    </section>

    <div class="sahne" data-sahne>
      <div class="yigin" data-yigin style="--sayfa-sayisi: <?= count($sayfalar) ?>">
<?php foreach ($sayfalar as $i => $s): $d = $durus($i); ?>
        <a class="yaprak" href="<?= e($pdfHref($s['sayfa'])) ?>" target="_blank" rel="noopener" tabindex="-1" aria-hidden="true"
           data-yaprak="<?= $i ?>" data-ad="<?= e($s['gorsel']) ?>" style="--px: <?= $d['x'] ?>; --py: <?= $d['y'] ?>; --pr: <?= $d['r'] ?>; --i: <?= $i ?>;">
          <noscript><?= $sayfaResmi($s['gorsel']) ?></noscript>
        </a>
<?php endforeach; ?>
        <figure class="katalog-kapak" data-kapak>
          <?= resim($tky['kapak'], 'Tekayper ürün kataloğu kapağı, Haziran 2021', ['sizes' => '(min-width: 1024px) 420px, 72vw', 'onyukle' => true, 'sinif' => 'katalog-kapak__resim']) ?>
          <span class="ek-etiketi" data-ek-etiketi aria-hidden="true">
            <svg class="ek-etiketi__klips" viewBox="0 0 20 56" width="20" height="56" focusable="false"><path d="M6 50V10a4 4 0 0 1 8 0v34a6 6 0 0 1-12 0V14" fill="none" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke"/></svg>
            <span class="ek-etiketi__kart"><span class="ek-etiketi__no"><?= e($tky['ek']) ?></span><span class="ek-etiketi__dosya"><?= e($tky['dosya']) ?></span></span>
          </span>
        </figure>
      </div>
      <p class="sahne__altyazi osi" data-altyazi aria-hidden="true"><span data-altyazi-sayfa>s. <?= (int) end($sayfalar)['sayfa'] ?></span> <span data-altyazi-ad><?= e(end($sayfalar)['ad']) ?></span> <span class="sahne__konu" data-altyazi-konu><?= e(end($sayfalar)['konu']) ?></span></p>
    </div>

    <section class="dizin-bolum" id="sayfa-dizini" data-satir="B" data-sc-act="pin" aria-labelledby="dizin-baslik">
      <div class="dizin" data-dizin>
        <div class="dizin__ust">
          <h2 class="dizin__baslik" id="dizin-baslik">Sayfa dizini</h2>
          <p class="dizin__aciklama">Markanın sayfasına gidin: PDF o sayfada, yeni sekmede açılır.</p>
        </div>
        <ol class="dizin__liste">
<?php foreach ($sayfalar as $i => $s): $panel = 'onizleme-' . $i; ?>
          <li class="dizin__satir" data-satir-no="<?= $i ?>">
            <a class="dizin__a" href="<?= e($pdfHref($s['sayfa'])) ?>" target="_blank" rel="noopener" data-dizin-a="<?= $i ?>">
              <span class="dizin__ad"><?= e($s['ad']) ?><?php if (!empty($s['ek_dizin'])): ?> <span class="dizin__ek"><?= e($s['ek_dizin']) ?></span><?php endif; ?></span>
              <span class="dizin__nokta" aria-hidden="true"></span>
              <span class="dizin__sayfa osi"><span class="gizli">, katalog sayfası </span><span aria-hidden="true">s. </span><?= (int) $s['sayfa'] ?></span>
              <span class="gizli"> (PDF, yeni sekmede açılır)</span>
            </a>
            <button class="dizin__ac" type="button" aria-expanded="false" aria-controls="<?= e($panel) ?>" data-dizin-ac="<?= $i ?>">
              <span class="dizin__ad"><?= e($s['ad']) ?><?php if (!empty($s['ek_dizin'])): ?> <span class="dizin__ek"><?= e($s['ek_dizin']) ?></span><?php endif; ?></span>
              <span class="dizin__nokta" aria-hidden="true"></span>
              <span class="dizin__sayfa osi"><span class="gizli">, katalog sayfası </span><span aria-hidden="true">s. </span><?= (int) $s['sayfa'] ?></span>
            </button>
            <div class="dizin__onizleme" id="<?= e($panel) ?>" hidden>
              <div class="dizin__resim" data-ad="<?= e($s['gorsel']) ?>"></div>
              <a class="dizin__pdf alti-cizili" href="<?= e($pdfHref($s['sayfa'])) ?>" target="_blank" rel="noopener">Sayfayı aç (s. <?= (int) $s['sayfa'] ?>)<span class="gizli">, <?= e($s['ad']) ?>, PDF yeni sekmede açılır</span></a>
            </div>
          </li>
<?php endforeach; ?>
        </ol>
        <p class="dizin__not">
          <?= capraz_a('/4', 'Ürün grupları ve hızlı arama') ?>
          <?= capraz_a('/5', 'TPK pano klimaları ve ısı hesabı') ?>
        </p>
      </div>
    </section>

  </div>

  <section class="diger izgara" id="diger" data-satir="C" data-sc-act="flow" aria-labelledby="diger-baslik">
    <h2 class="diger__baslik" id="diger-baslik">Diğer kataloglar</h2>
    <article class="ek ek--armendus" aria-labelledby="armendus-baslik">
      <figure class="ek__kapak">
        <?= resim($arm['kapak'], 'Armendus kataloğu kapağı', ['sizes' => '(min-width: 1024px) 260px, 60vw', 'sinif' => 'ek__resim']) ?>
        <span class="ek__no osi" aria-hidden="true"><?= e($arm['ek']) ?></span>
      </figure>
      <div class="ek__metin">
        <h3 class="ek__baslik" id="armendus-baslik"><?= e($arm['baslik']) ?></h3>
        <p>Taşıyıcı kol, pano ve kiosk sistemleri. <?= e($arm['dil']) ?>, <?= (int) $arm['sayfa'] ?> sayfa, <?= e($boyut['armendus']) ?>, <?= e($arm['ay']) ?>.</p>
        <p class="ek__indir"><a class="alti-cizili" href="<?= e(url($arm['dosya'])) ?>" download type="application/pdf">Armendus kataloğunu indir<span class="gizli"> (PDF, <?= e($boyut['armendus']) ?>)</span></a> <span class="osi ek__dosya" aria-hidden="true">/<?= e($arm['dosya']) ?></span></p>
      </div>
    </article>
    <p class="arsiv">
      <span class="arsiv__etiket osi"><?= e($tkn['ek']) ?> Arşiv</span>
      <span class="arsiv__metin"><a class="alti-cizili" href="<?= e(url($tkn['dosya'])) ?>" download type="application/pdf"><?= e($tkn['baslik']) ?></a>: <?= e($tkn['not']) ?> PDF, <?= (int) $tkn['sayfa'] ?> sayfa, <?= e($boyut['teknokol']) ?>.</span>
    </p>
  </section>

  <section class="kapanis" id="indir" data-satir="D" data-sc-act="pin" aria-labelledby="kapanis-baslik">
    <div class="kapanis__sahne" data-kapanis>
      <div class="kapanis__yigin" aria-hidden="true">
<?php
$kapanisSecim = [3, 6, 11, 16, 20, 24];
foreach ($kapanisSecim as $k => $i): $s = $sayfalar[$i]; ?>
        <span class="kapanis__yaprak" data-ad="<?= e($s['gorsel']) ?>" style="--k: <?= $k ?>; --kn: <?= count($kapanisSecim) ?>;"><noscript><?= $sayfaResmi($s['gorsel']) ?></noscript></span>
<?php endforeach; ?>
        <span class="kapanis__kapak"><?= resim($tky['kapak'], '', ['sizes' => '(min-width: 1024px) 300px, 50vw', 'sinif' => 'kapanis__resim']) ?></span>
      </div>
      <div class="kapanis__metin">
        <h2 class="kapanis__baslik" id="kapanis-baslik"><?= e($tky['baslik']) ?></h2>
        <p class="kapanis__meta osi"><?= e($tky['ek']) ?>, <?= e($tky['dosya']) ?>, <?= (int) $tky['sayfa'] ?> sayfa, <?= e($tky['ay']) ?></p>
        <?= dugme('Tekayper kataloğunu indir (PDF, ' . $boyut['tekayper'] . ')', url($tky['dosya']), ['ozellik' => ['download' => true, 'type' => 'application/pdf']]) ?>
      </div>
    </div>
  </section>

</main>
<?= json_gom('ekler-veri', array_map(fn ($s) => ['ad' => $s['ad'], 'sayfa' => $s['sayfa'], 'konu' => $s['konu'] ?? ''], $sayfalar)) ?>

<?php
partial('antet', ['foy' => $foy]);
partial('son', ['foy' => $foy]);
