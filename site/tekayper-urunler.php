<?php
/**
 * Föy 4, Malzeme listesi (spec 3.4, paket P4). Dil bilgisi: canlı yüzey (araç ilk pikselden çalışır).
 * Sunucu bütün listeyi çizer (37 marka, 178 grup): JS kapalıyken ?ara=&kategori=&marka= ile süzer,
 * talep listesi işaret kutularıyla iletisim.php'ye GET ile gider. JS açıkken assets/js/sayfa-malzeme.js
 * aynı kurallarla (assets/js/arama.js) anında süzer, talep listesini localStorage'da tutar.
 * QA sözleşmesi: arama kutusu input[name="ara"], görünen her sonuç satırında data-marka="<marka adı>".
 */
require __DIR__ . '/app/bootstrap.php';
require_once APP . '/lib/arama.php';
sembol_hazir();

$foy = foy('malzeme');
$istek = arama_istek();
$sorgu = $istek['sorgu'];
$aktifKat = $istek['kategori'];
$aktifMarka = $istek['marka'];

$katlar = data('kategoriler')['liste'];
$markalar = data('markalar');
$markaSayisi = marka_sayisi();
$grupSayisi = grup_sayisi();

/* eşleşmeler: sıra => sonuç (sorgu sırasıyla) */
$eslesen = [];
foreach ($istek['sonuclar'] as $s) {
    $eslesen[$s['sira']] = $s;
}

/* düz (ilgi sırası) mi, marka blokları mı: sorgu yalnızca marka adıyla eşleşiyorsa bütün marka bloğu gösterilir */
$birimler = $sorgu === '' ? [] : arama_birimler($sorgu);
$dizin = [];
foreach (arama_dizin() as $g) {
    $dizin[$g['sira']] = $g;
}
$yalnizMarka = (bool) $eslesen;
foreach ($eslesen as $sira => $s) {
    foreach ($birimler as $b) {
        if (arama_birim_puani($b, $dizin[$sira])[1] !== 'marka') {
            $yalnizMarka = false;
            break 2;
        }
    }
}
$duz = $birimler && !$yalnizMarka;

/* rayın sayıları: kategori dışındaki süzgeçlerle (sorgu, marka) her kategoride kaç eşleşme */
$raySayilari = array_fill_keys(array_keys($katlar), 0);
foreach (arama_yap($sorgu, null, $aktifMarka)['sonuclar'] as $s) {
    $raySayilari[$s['kategori']]++;
}
$katToplam = array_fill_keys(array_keys($katlar), 0);
foreach ($markalar as $m) {
    foreach ($m['gruplar'] as $g) {
        $katToplam[$g['kategori']]++;
    }
}

/* kategori fotoğrafı (asset-plan U serisi): dosya varsa 64 px küçük resim, yoksa yalnızca sembol */
$katFoto = [
    'koruma' => 'u01', 'role' => 'u03', 'surucu' => 'u05', 'plc' => 'u07', 'sensor' => 'u09', 'olcum' => 'u15',
    'tartim' => 'u23', 'kumanda' => 'u10', 'guc' => 'u08', 'klemens' => 'u11', 'kablo' => 'u12', 'pano' => 'u18',
    'priz' => 'u20', 'plazma' => 'u22', 'mekanik' => 'u24',
];
$fotoAd = function (string $kat) use ($katFoto): ?string {
    $id = $katFoto[$kat] ?? null;
    if ($id === null) {
        return null;
    }
    foreach (glob(ROOT . '/assets/img/urun/' . $id . '-*-480.*') ?: [] as $yol) {
        if (preg_match('~/(' . $id . '-[a-z0-9-]+)-480\.(avif|webp|jpg)$~', $yol, $m)) {
            return 'urun/' . $m[1];
        }
    }
    return null;
};

/* sonuç başlığı metinleri */
$sayi = count($eslesen);
$markaSay = count(array_unique(array_column($istek['sonuclar'], 'marka')));
$markaAd = $aktifMarka ? marka($aktifMarka)['ad'] : null;
if ($aktifKat) {
    $baslikAd = $katlar[$aktifKat]['ad'];
    $baslikAlt = $sayi . ' grup, ' . $markaSay . ' marka';
} elseif ($sorgu !== '') {
    $baslikAd = '“' . $sorgu . '”';
    $baslikAlt = $sayi . ' grup, ' . $markaSay . ' marka';
} elseif ($markaAd) {
    $baslikAd = $markaAd;
    $baslikAlt = $sayi . ' grup';
} else {
    $baslikAd = 'Bütün ürün grupları';
    $baslikAlt = $grupSayisi . ' grup, ' . $markaSayisi . ' marka';
}

/* adres yardımcısı: geçerli süzgeçlerden biri değişmiş olarak */
$adres = function (array $degis = []) use ($sorgu, $aktifKat, $aktifMarka): string {
    $p = array_merge(['ara' => $sorgu, 'kategori' => $aktifKat, 'marka' => $aktifMarka], $degis);
    $p = array_filter($p, fn ($v) => $v !== null && $v !== '');
    return url('tekayper-urunler.php') . ($p ? '?' . http_build_query($p, '', '&', PHP_QUERY_RFC3986) : '') . '#liste';
};
$gizliAlanlar = function (array $haric) use ($sorgu, $aktifKat, $aktifMarka): string {
    $out = '';
    foreach (['ara' => $sorgu, 'kategori' => $aktifKat, 'marka' => $aktifMarka] as $k => $v) {
        if (!in_array($k, $haric, true) && $v !== null && $v !== '') {
            $out .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
        }
    }
    return $out;
};

/* satır çıktısı */
$poz = 0;
$pozlar = [];
foreach ($markalar as $mi => $m) {
    foreach ($m['gruplar'] as $gi => $g) {
        $pozlar[$mi * 100 + $gi] = ++$poz;
    }
}
$satir = function (array $m, array $g, int $sira) use ($eslesen, $sorgu, $katlar, $pozlar): string {
    $gorunur = isset($eslesen[$sira]);
    $kat = $g['kategori'];
    $grupHtml = $sorgu !== '' && $gorunur ? arama_vurgula($g['ad'], $sorgu) : e($g['ad']);
    $markaHtml = $sorgu !== '' && $gorunur ? arama_vurgula($m['ad'], $sorgu) : e($m['ad']);
    $deger = $m['ad'] . ': ' . $g['ad'];
    return '<tr class="bom__satir" data-sira="' . $sira . '" data-marka="' . e($m['ad']) . '" data-slug="' . e($m['slug'])
        . '" data-grup="' . e($g['ad']) . '" data-kategori="' . e($kat) . '"' . ($gorunur ? '' : ' hidden') . '>'
        . '<td class="bom__poz">' . $pozlar[$sira] . '</td>'
        . '<td class="bom__sembol" data-sembol="' . e($katlar[$kat]['sembol']) . '">' . sembol_ikon($katlar[$kat]['sembol'], 32) . '</td>'
        . '<th class="bom__grup" scope="row"><span class="bom__metin" data-vurgu="grup">' . $grupHtml . '</span></th>'
        . '<td class="bom__marka"><span class="bom__metin" data-vurgu="marka">' . $markaHtml . '</span></td>'
        . '<td class="bom__kategori">' . e($katlar[$kat]['ad']) . '</td>'
        . '<td class="bom__talep"><label class="ekle"><input class="ekle__girdi" type="checkbox" name="grup[]" value="' . e($deger)
        . '" form="talep-formu"><span class="ekle__kutu" aria-hidden="true"></span><span class="ekle__etiket">Listeye ekle</span>'
        . '<span class="gizli">: ' . e($m['ad']) . ', ' . e($g['ad']) . '</span></label></td>'
        . '</tr>';
};

$schemaListe = [];
foreach ($markalar as $i => $m) {
    $schemaListe[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $m['ad']];
}

partial('head', [
    'foy' => $foy,
    'css' => ['sayfa-malzeme'],
    'js' => ['sayfa-malzeme'],
    'schema' => [schema_sayfa('CollectionPage', $foy, [
        'mainEntity' => ['@type' => 'ItemList', 'name' => 'Tedarik edilen markalar', 'numberOfItems' => $markaSayisi, 'itemListElement' => $schemaListe],
    ])],
]);
partial('ust', ['foy' => $foy]);
?>
<main id="icerik" class="foy foy--malzeme" data-foy="4" data-mod="<?= $duz ? 'duz' : 'grup' ?>">

  <section id="arama" class="malz-a" data-satir="A" data-sc-act="flow" aria-labelledby="malz-h1">
    <div class="izgara malz-a__izgara">
      <div class="malz-a__baslik">
        <?php partial('kesme-noktasi', ['ref' => '/3', 'sinif' => 'malz-a__kesme']); ?>
        <h1 id="malz-h1">Tekayper ürünleri</h1>
        <p class="malz-a__sayim"><span class="sayi"><?= $markaSayisi ?></span> marka, <span class="sayi"><?= $grupSayisi ?></span> ürün grubu</p>
      </div>
      <form class="arama" role="search" method="get" action="<?= e(url('tekayper-urunler.php')) ?>#liste" aria-label="Malzeme listesinde ara">
        <div class="arama__hucre">
          <label class="arama__etiket" for="ara">Ara<span class="gizli">: ürün, marka ya da grup</span></label>
          <input class="arama__girdi" id="ara" type="search" name="ara" value="<?= e($sorgu) ?>" placeholder="Ürün, marka ya da grup ara"
                 autocomplete="off" autocapitalize="off" spellcheck="false" enterkeyhint="search" maxlength="80"
                 aria-describedby="ara-yardim" aria-controls="bom">
          <p class="arama__yardim" id="ara-yardim">Örnek: röle, NYAF, enkoder, Finder. Türkçe karakterle ya da karaktersiz yazabilirsiniz.</p>
        </div>
        <?= $gizliAlanlar(['ara']) ?>
        <div class="arama__sayac" aria-hidden="true">
          <span class="arama__sayac-etiket">Eşleşme</span>
          <span class="arama__sayac-deger sayi" data-sayac><?= $sayi ?></span>
          <span class="arama__sayac-toplam">/ <?= $grupSayisi ?></span>
        </div>
        <div class="arama__dugmeler">
          <button class="arama__ara dugme dugme--birincil" type="submit"><span class="dugme__etiket" data-etiket="Ara">Ara</span></button>
          <a class="arama__temizle" href="<?= e($adres(['ara' => null])) ?>" data-temizle<?= $sorgu === '' ? ' data-bos' : '' ?>>Temizle</a>
        </div>
      </form>
    </div>
  </section>

  <div class="arac">
    <section id="liste" class="malz-b" data-satir="B" data-sc-act="flow" aria-label="Liste">
      <div class="izgara malz-b__izgara">

        <form class="ray" method="get" action="<?= e(url('tekayper-urunler.php')) ?>#liste" aria-labelledby="ray-baslik">
          <?= $gizliAlanlar(['kategori']) ?>
          <p class="ray__baslik" id="ray-baslik">Kategoriler</p>
          <ul class="ray__liste">
<?php foreach ($katlar as $id => $k): $basili = $aktifKat === $id; ?>
            <li class="ray__oge">
              <button class="ray__dugme" type="submit" name="kategori" value="<?= $basili ? '' : e($id) ?>" aria-pressed="<?= $basili ? 'true' : 'false' ?>"
                      data-kategori="<?= e($id) ?>"<?= $raySayilari[$id] === 0 ? ' data-bos' : '' ?>>
                <span class="ray__sembol"><?= sembol($k['sembol'], ['buyukluk' => 48, 'sigdir' => true]) ?></span>
                <span class="ray__ad"><span class="ray__ad-metin"><?= e($k['ad']) ?></span></span>
                <span class="ray__sayi sayi" data-sayi aria-hidden="true"><?= $raySayilari[$id] ?></span>
                <span class="gizli" data-sayi-metin>, <?= $raySayilari[$id] ?> grup</span>
              </button>
            </li>
<?php endforeach; ?>
          </ul>
        </form>

        <div class="sonuc">
          <div class="sonuc__arac">
          <div class="sonuc-baslik" data-durum="<?= $aktifKat ? 'kategori' : ($sorgu !== '' ? 'sorgu' : 'bos') ?>">
            <div class="sonuc-baslik__yuva" aria-hidden="true">
              <span class="yuva__kose"></span>
              <span class="yuva__ipucu">Sembol seçin</span>
              <div class="yuva__sembol" data-yuva><?php if ($aktifKat): ?><?= sembol($katlar[$aktifKat]['sembol'], ['buyukluk' => 96, 'sigdir' => true, 'durum' => 'kapali']) ?><?php endif; ?></div>
            </div>
<?php foreach ($katlar as $id => $k): $ad = $fotoAd($id); if ($ad === null) continue; ?>
            <figure class="sonuc-baslik__foto" data-foto="<?= e($id) ?>"<?= $aktifKat === $id ? '' : ' hidden' ?>>
              <?= resim($ad, $k['ad'] . ', temsilî görsel', ['sizes' => '64px', 'genislik' => 480, 'sinif' => 'sonuc-baslik__resim']) ?>
            </figure>
<?php endforeach; ?>
            <div class="sonuc-baslik__metin">
              <p class="sonuc-baslik__ad" data-capraz-baslik data-baslik-ad><?= e($baslikAd) ?></p>
              <p class="sonuc-baslik__alt" data-baslik-alt><?= e($baslikAlt) ?></p>
            </div>
            <div class="sonuc-baslik__suzgec" data-suzgecler>
<?php if ($markaAd): ?>
              <a class="suzgec" href="<?= e($adres(['marka' => null])) ?>" data-suzgec-kaldir="marka"><span class="suzgec__etiket">Marka:</span> <?= e($markaAd) ?><span class="suzgec__x" aria-hidden="true"></span><span class="gizli"> (süzgeci kaldır)</span></a>
<?php endif; ?>
<?php if ($aktifKat): ?>
              <a class="suzgec" href="<?= e($adres(['kategori' => null])) ?>" data-suzgec-kaldir="kategori"><span class="suzgec__etiket">Kategori:</span> <?= e($katlar[$aktifKat]['ad']) ?><span class="suzgec__x" aria-hidden="true"></span><span class="gizli"> (süzgeci kaldır)</span></a>
<?php endif; ?>
            </div>
          </div>

          <table class="bom" id="bom">
            <caption class="gizli">Malzeme listesi: ürün grubu, marka ve kategori</caption>
            <thead>
              <tr>
                <th class="bom__poz" scope="col"><abbr title="Pozisyon">Poz.</abbr></th>
                <th class="bom__sembol" scope="col">Sembol</th>
                <th class="bom__grup" scope="col">Ürün grubu</th>
                <th class="bom__marka" scope="col">Marka</th>
                <th class="bom__kategori" scope="col">Kategori</th>
                <th class="bom__talep" scope="col">Talep</th>
              </tr>
            </thead>
            <tbody class="bom__duz" data-blok="duz"<?= $duz ? '' : ' hidden' ?>>
<?php if ($duz): foreach ($istek['sonuclar'] as $s): $mi = intdiv($s['sira'], 100); $gi = $s['sira'] % 100; ?>
              <?= $satir($markalar[$mi], $markalar[$mi]['gruplar'][$gi], $s['sira']) ?>

<?php endforeach; endif; ?>
            </tbody>
<?php foreach ($markalar as $mi => $m):
    $gorunen = 0;
    foreach ($m['gruplar'] as $gi => $g) {
        $gorunen += isset($eslesen[$mi * 100 + $gi]) ? 1 : 0;
    }
?>
            <tbody class="bom__blok" data-blok="<?= e($m['slug']) ?>"<?= (!$duz && $gorunen) ? '' : ' hidden' ?>>
              <tr class="bom__marka-satir">
                <td colspan="6"><h2 class="bom__marka-baslik" id="marka-<?= e($m['slug']) ?>"><?= e($m['ad']) ?></h2><span class="bom__marka-sayi" data-blok-sayi><?= $gorunen ?: count($m['gruplar']) ?> grup</span></td>
              </tr>
<?php foreach ($m['gruplar'] as $gi => $g): $sira = $mi * 100 + $gi; if ($duz && isset($eslesen[$sira])) continue; ?>
              <?= $satir($m, $g, $sira) ?>

<?php endforeach; ?>
            </tbody>
<?php endforeach; ?>
          </table>

          <div class="bom-bos" data-bos<?= $sayi === 0 ? '' : ' hidden' ?>>
            <p class="bom-bos__metin" data-bos-metin>“<?= e($sorgu) ?>” için listede eşleşme yok. Liste tedarik edebildiğimiz her ürünü kapsamaz; ürünü bize sorun.</p>
            <a class="dugme dugme--birincil" data-bos-sor href="<?= e(url('iletisim.php') . '?' . http_build_query(['konu' => $sorgu], '', '&', PHP_QUERY_RFC3986) . '#talep') ?>"><span class="dugme__etiket" data-etiket="Bu ürünü sor">Bu ürünü sor</span></a>
          </div>

          </div>

          <div class="notlar malz-notlar">
            <p class="notlar__baslik">Notlar</p>
            <ol class="notlar__liste">
              <li>Ürün grupları üreticilerin ürün aileleridir. Stok ve fiyat için teklif isteyin.</li>
              <li>Ürün görselleri temsilidir. Marka adları sahiplerine aittir.</li>
            </ol>
          </div>
        </div>

      </div>
    </section>

    <section id="talep-listesi" class="malz-c" data-satir="C" data-sc-act="flow" aria-labelledby="talep-b">
      <div class="izgara malz-c__izgara">
        <div class="talep__bas">
          <h2 id="talep-b">Talep listesi</h2>
          <p class="talep__aciklama ikincil" data-talep-aciklama>Satırlarda işaretlediğiniz grupları alttaki düğmeyle teklif formuna aktarın. Form, seçtiğiniz grupları Talebiniz alanına yazar.</p>
        </div>
        <div class="talep__govde" data-talep-govde>
          <form id="talep-formu" method="get" action="<?= e(url('iletisim.php')) ?>#talep">
            <input type="hidden" name="konu" value="Talep listesi">
          </form>
        </div>
      </div>
    </section>

    <div class="durum" data-durum-cubugu>
      <div class="durum__ic">
        <p class="durum__hucre durum__hucre--eslesme"><span class="durum__etiket" aria-hidden="true">Eşleşme</span><span class="durum__deger" data-durum-eslesme><?= $grupSayisi ?> gruptan <?= $sayi ?> eşleşme</span></p>
        <p class="durum__hucre durum__hucre--talep"><span class="durum__etiket" aria-hidden="true">Talep listesi</span><span class="durum__deger" data-durum-talep>İşaretlediğiniz gruplar</span></p>
        <div class="durum__eylem">
          <button class="dugme dugme--birincil durum__aktar" type="submit" form="talep-formu" data-aktar><span class="dugme__etiket" data-etiket="Listeyi teklif formuna aktar">Listeyi teklif formuna aktar</span></button>
        </div>
      </div>
    </div>
  </div>

  <template id="yuva-semboller">
<?php foreach ($katlar as $id => $k): ?>
    <?= str_replace('<span class="sembol ', '<span data-yuva-icin="' . e($id) . '" class="sembol ', sembol($k['sembol'], ['buyukluk' => 96, 'sigdir' => true])) ?>

<?php endforeach; ?>
  </template>
  <p class="gizli" aria-live="polite" data-canli></p>
  <?= json_gom('malzeme-veri', arama_veri()) ?>
</main>
<svg class="tel" aria-hidden="true" focusable="false" data-tel></svg>
<?php partial('antet', ['foy' => $foy]); partial('son', ['foy' => $foy]); ?>
