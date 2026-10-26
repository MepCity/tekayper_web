<?php
/**
 * Geliştirme sayfası: bütün ortak bileşenler (paket F1). .htaccess ile kapalı, yükleme paketine girmez.
 * ?foy=sogutma gibi bir föy kimliğiyle başlık, cetvel ve antet o föyün verisiyle çizilir.
 */
require dirname(__DIR__) . '/app/bootstrap.php';
require APP . '/lib/arama.php';

$foyId = isset($_GET['foy']) && is_string($_GET['foy']) && isset(data('foyler')[$_GET['foy']]) ? $_GET['foy'] : 'ana';
$foy = foy($foyId);
$foy['dosya'] = '_dev/kitchen.php';
$foy['satirlar'] = [
    'A' => ['renk', 'Renk ve kontrast'], 'B' => ['yazi', 'Yazı'], 'C' => ['dugmeler', 'Düğmeler'],
    'D' => ['capraz', 'Çapraz referans'], 'E' => ['form', 'Form hücreleri'], 'F' => ['parcalar', 'Parçalar'],
];

/** WCAG göreli parlaklık ve kontrast */
function parlaklik(string $hex): float
{
    $hex = ltrim($hex, '#');
    $k = [];
    foreach ([0, 2, 4] as $i) {
        $c = hexdec(substr($hex, $i, 2)) / 255;
        $k[] = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }
    return 0.2126 * $k[0] + 0.7152 * $k[1] + 0.0722 * $k[2];
}
function kontrast(string $a, string $b): float
{
    $x = parlaklik($a);
    $y = parlaklik($b);
    return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
}

$renkler = [
    ['Kağıt', '--kagit', '#F1F2EE', 'zemin'], ['Mürekkep', '--murekkep', '#1B1D1F', 'metin, çizgi, plaka'],
    ['İşaret kalemi', '--isaret', '#F9B233', 'mürekkebin altında bant'], ['Yapı çizgisi', '--yapi', '#3EB8EB', 'süs, yapı ızgarası'],
    ['Özalit', '--ozalit', '#16324A', 'yalnızca kataloglar zemini'], ['Hücre', '--n-100', '#E6E8E3', 'antet hücresi, giriş'],
    ['Seçili hücre', '--n-200', '#D9DCD6', 'basılı hücre'], ['Pasif', '--n-300', '#B9BEC2', 'pasif kenar'],
    ['Kılçizgi', '--n-400', '#8E959C', 'ızgara, çentik (metin değil)'], ['İkincil metin', '--n-600', '#4B5157', 'açıklama'],
    ['Plaka üzeri', '--n-700', '#2C3034', 'plaka hover'], ['Hata', '--hata', '#B8281E', 'hata kenarı ve cümlesi'],
    ['Başarı', '--basari', '#2E6E45', 'onay'], ['Bilgi', '--bilgi', '#0F6F94', 'metin düzeyinde camgöbeği'],
    ['İşaret koyu', '--isaret-koyu', '#B9800A', 'üstü mürekkepsiz sarı çizgi'],
];
$ciftler = [
    ['Gövde metni', '#1B1D1F', '#F1F2EE', 4.5], ['İkincil metin', '#4B5157', '#F1F2EE', 4.5],
    ['Hücre içi metin', '#1B1D1F', '#E6E8E3', 4.5], ['Hücre içi ikincil', '#4B5157', '#E6E8E3', 4.5],
    ['Plaka (düğme) metni', '#F1F2EE', '#1B1D1F', 4.5], ['Plaka hover metni', '#F1F2EE', '#2C3034', 4.5],
    ['İşaret bandı üstünde mürekkep', '#1B1D1F', '#F9B233', 4.5], ['Hata cümlesi', '#B8281E', '#F1F2EE', 4.5],
    ['Hata cümlesi (hücre)', '#B8281E', '#E6E8E3', 4.5], ['Bilgi metni', '#0F6F94', '#F1F2EE', 4.5],
    ['Sol cetvel pasif harf (%64)', '#686A6A', '#F1F2EE', 4.5], ['Odak halkası', '#1B1D1F', '#F1F2EE', 3.0],
    ['Kılçizgi (arayüz değil, süs)', '#8E959C', '#F1F2EE', 0], ['İşaret koyu çizgi', '#B9800A', '#F1F2EE', 3.0],
    ['Özalit: metin', '#F1F2EE', '#16324A', 4.5], ['Özalit: ikincil metin', '#C9D3DC', '#16324A', 4.5],
    ['Özalit: sarı', '#F9B233', '#16324A', 3.0], ['Özalit: camgöbeği', '#3EB8EB', '#16324A', 3.0],
    ['Özalit: plaka metni', '#16324A', '#F1F2EE', 4.5],
    ['Sarı kağıt üzerinde (yasak, bilgi için)', '#F9B233', '#F1F2EE', 0], ['Camgöbeği kağıt üzerinde (yalnız süs)', '#3EB8EB', '#F1F2EE', 0],
];

partial('head', [
    'foy' => $foy, 'css' => ['form'], 'noindex' => true,
    'baslik' => 'Bileşen sayfası | ' . $foy['etiket'] . ' ' . $foy['ad'],
]);
partial('ust', ['foy' => $foy]);
?>
<main id="icerik" class="foy foy--kitchen" data-foy="<?= (int) $foy['no'] ?>">

  <section id="renk" class="bant" data-satir="A" data-sc-act="flow" aria-labelledby="renk-b">
    <div class="izgara">
      <div class="kt-baslik">
        <?php partial('kesme-noktasi', ['ref' => '/3']); ?>
        <h1 id="renk-b">Bileşen sayfası</h1>
        <p class="ikincil olcu">Ortak sistemin bütün parçaları. Başlık, cetveller ve antet şu föyün verisiyle çizildi: <strong><?= e($foy['etiket'] . ' ' . $foy['ad']) ?></strong>. Föy değiştir:
<?php foreach (foy_listesi(true) as $f): ?>
          <a class="alti-cizili" href="?foy=<?= e($f['id']) ?>"<?= $f['id'] === $foyId ? ' aria-current="page"' : '' ?>><?= (int) $f['no'] ?></a>
<?php endforeach; ?>
        </p>
      </div>
      <h2 class="kt-h2">Renk ve kontrast</h2>
      <ul class="kt-renkler">
<?php foreach ($renkler as [$ad, $var, $hex, $rol]): ?>
        <li><span class="kt-renk" style="background: var(<?= e($var) ?>)"></span><span class="kt-renk__ad"><?= e($ad) ?></span><span class="osi"><?= e($hex) ?> <?= e($var) ?></span><span class="t-kucuk ikincil"><?= e($rol) ?></span></li>
<?php endforeach; ?>
      </ul>
      <div class="tablo-izgara kt-kontrast" style="--tablo-sutunlar: minmax(0, 3fr) repeat(4, minmax(0, 1fr))" role="table" aria-label="Kontrast tablosu">
        <div class="tablo-izgara__baslik" role="columnheader">Kullanım</div><div class="tablo-izgara__baslik" role="columnheader">Örnek</div><div class="tablo-izgara__baslik" role="columnheader">Oran</div><div class="tablo-izgara__baslik" role="columnheader">Gereken</div><div class="tablo-izgara__baslik" role="columnheader">Sonuç</div>
<?php $gecmeyen = 0; foreach ($ciftler as [$ad, $on, $arka, $esik]): $o = kontrast($on, $arka); $gec = $esik === 0 || $o >= $esik; if (!$gec) $gecmeyen++; ?>
        <div role="cell"><?= e($ad) ?></div>
        <div role="cell" style="background: <?= e($arka) ?>; color: <?= e($on) ?>; font-weight: 560">Aa 0224</div>
        <div role="cell" class="sayi"><?= number_format($o, 2, ',', '') ?>:1</div>
        <div role="cell" class="sayi"><?= $esik ? number_format($esik, 1, ',', '') . ':1' : 'süs' ?></div>
        <div role="cell"><?= $esik === 0 ? 'metin taşımaz' : ($gec ? 'geçti' : '<strong>kaldı</strong>') ?></div>
<?php endforeach; ?>
      </div>
      <p class="t-kucuk" data-kontrast-sonuc="<?= $gecmeyen ?>">Metin ve arayüz çiftlerinden kalan: <?= $gecmeyen ?>.</p>
    </div>
  </section>

  <section id="yazi" class="bant" data-satir="B" data-sc-act="flow" aria-labelledby="yazi-b">
    <div class="izgara">
      <h2 id="yazi-b" class="kt-h2">Yazı</h2>
      <div class="kt-yazi">
        <p class="t-display">Tek hat şeması</p>
        <p class="t-h1">Endüstriyel otomasyon malzemesi ve teknik destek, Bursa Nilüfer</p>
        <p class="t-h2">Pano soğutma sistemleri</p>
        <p class="t-h3">Kontaktör ve röle, 23 grup</p>
        <p class="olcu">37 markanın 178 ürün grubunu tedarik ediyor; mühendislik, montaj ve servis hizmeti veriyoruz. Gövde metni 17 px, satır 1,6, ölçü 62 karakter. Türkçe: ğ Ğ ş Ş ı İ ç Ç ö Ö ü Ü â î û.</p>
        <p class="t-kucuk ikincil olcu">Küçük metin 15 px: Ürün grupları üreticilerin ürün aileleridir. Stok ve fiyat için teklif isteyin.</p>
        <p class="osi">Osifont 14 px (çizim katmanı): -Q1 -K1 /5.C =TKY+SGT ğĞşŞıİçÇöÖüÜ 40,2084 K 28,9299 D</p>
        <p class="t-alan">Antet alan etiketi: firma, föy adı, güncelleme, ölçek</p>
        <p>Vurgu: <mark>işaret kalemi bandı</mark> metnin altında, x yüksekliğinde. <strong>Kalın vurgu 560.</strong></p>
        <p class="kt-genislik" aria-hidden="true"><span data-genislik="chilldin" style="font-family: ChillDIN">ŞĞİşğıİ</span> <span data-genislik="yedek" style="font-family: Arial">ŞĞİşğıİ</span> <span data-genislik="osifont" class="osi">ŞĞİşğıİ</span></p>
      </div>
    </div>
  </section>

  <section id="dugmeler" class="bant" data-satir="C" data-sc-act="flow" aria-labelledby="dugme-b">
    <div class="izgara">
      <h2 id="dugme-b" class="kt-h2">Düğmeler</h2>
      <div class="kt-satir">
        <?= dugme('Teklif al', url('iletisim.php#talep')) ?>
        <?= dugme('Teklif Al', url('iletisim.php#talep'), ['sinif' => 'dugme--kucuk']) ?>
        <?= dugme('Talebi gönder', null, ['button' => true, 'ozellik' => ['disabled' => true]]) ?>
        <?= dugme('Ürünleri incele', url('tekayper-urunler.php'), ['tur' => 'ikincil', 'ref' => '/4']) ?>
        <?= capraz_a('/2', 'Firma bilgisi') ?>
      </div>
      <div class="kt-ozalit" data-zemin="ozalit">
        <p class="osi">Özalit zemin (kataloglar.php)</p>
        <div class="kt-satir">
          <?= dugme('Tekayper kataloğunu indir', url('1.pdf')) ?>
          <?= dugme('Armendus kataloğu', url('katalog1.pdf'), ['tur' => 'ikincil', 'ref' => '/7.C']) ?>
          <?= capraz_a('/7.B', 'Sayfa dizini') ?>
        </div>
      </div>
    </div>
  </section>

  <section id="capraz" class="bant" data-satir="D" data-sc-act="flow" aria-labelledby="capraz-b">
    <div class="izgara">
      <h2 id="capraz-b" class="kt-h2">Çapraz referans</h2>
      <p class="olcu">Bağlantılar: <?= capraz_a('/5.C', 'TPK modelleri') ?>, <?= capraz_a('/4.B', 'Kontaktörler', ['sorgu' => ['kategori' => 'role']]) ?>, <?= capraz_a('/1.C', 'Kumanda') ?>, aynı sayfada <a class="capraz" href="#renk" data-capraz-tam="Bileşen sayfası, satır A Renk"><span class="capraz__metin">A satırına dön</span><span class="capraz__ref" aria-hidden="true">.A</span></a>. Üzerinde 400 ms bekleyince tam tanım görünür.</p>
      <div class="kt-satir">
        <?php partial('kesme-noktasi', ['ref' => '/3', 'yon' => 'gelen']); ?>
        <?php partial('kesme-noktasi', ['ref' => '/4.A', 'yon' => 'giden']); ?>
        <button class="dugme dugme--ikincil" type="button" data-kt-vurgu="#form"><span class="dugme__etiket">E satırını vurgula</span></button>
      </div>
      <p>Cihaz etiketi: <span class="cihaz">-Q1</span> <span class="cihaz">-K1</span> <span class="cihaz">=TKY+SGT/5.C</span>. Doğrulanmamış değer: teyit ediliyor <span class="revizyon" role="img" aria-label="revizyon işareti"></span></p>
      <div class="kt-notlar">
        <h3 class="notlar__baslik">Notlar</h3>
        <ol class="notlar__liste">
          <li><span><strong>Ürün tedariki:</strong> Tanınmış üreticilerin otomasyon ve elektrik ürünlerini ihtiyacınıza göre temin ediyoruz.</span></li>
          <li><span><strong>Mühendislik:</strong> Projenize uygun ürün seçimi ve mühendislik desteği veriyoruz.</span></li>
          <li><span><strong>Proje, uygulama, montaj ve servis:</strong> Pano ve otomasyon işlerinde projeden satış sonrası servise kadar çalışıyoruz.</span></li>
        </ol>
      </div>
      <div class="tablo-izgara kt-kayit" style="--tablo-sutunlar: 4rem 8.5rem minmax(0, 1fr) minmax(0, 1.4fr)" role="table" aria-label="Föy kaydı çözümlemesi">
        <div class="tablo-izgara__baslik" role="columnheader">Kısa</div><div class="tablo-izgara__baslik" role="columnheader">Tam</div><div class="tablo-izgara__baslik" role="columnheader">Ad</div><div class="tablo-izgara__baslik" role="columnheader">Adres</div>
<?php $say = 0; foreach (foy_listesi(true) as $f): foreach ($f['satirlar'] as $h => $s): $c = capraz('/' . $f['no'] . '.' . $h); $say++; ?>
        <div role="cell" class="osi"><?= e($c['kisa']) ?></div><div role="cell" class="osi"><?= e($c['tam']) ?></div><div role="cell"><?= e($c['ad']) ?></div><div role="cell" class="t-kucuk"><?= e($c['href']) ?></div>
<?php endforeach; endforeach; ?>
      </div>
      <p class="t-kucuk" data-capraz-sayi="<?= $say ?>">Kayıttaki <?= $say ?> satırın hepsi çözüldü.</p>
    </div>
  </section>

  <section id="form" class="bant" data-satir="E" data-sc-act="flow" aria-labelledby="form-b">
    <div class="izgara">
      <h2 id="form-b" class="kt-h2">Form hücreleri</h2>
      <p class="form-uyari" role="alert">Talebiniz şu an iletilemedi. <?= e(config('phone.label')) ?> numarasını arayın ya da <?= e(config('email')) ?> adresine yazın.</p>
      <form class="kt-form" method="post" action="<?= e(url('teklif-gonder.php')) ?>" novalidate>
        <div class="form-hucreler">
          <?= form_hucre('adsoyad') ?>
          <?= form_hucre('firma') ?>
          <?= form_hucre('telefon', ['hata' => form_hata_metinleri()['telefon'], 'deger' => '0224 441']) ?>
          <?= form_hucre('eposta') ?>
          <?= form_hucre('konu', ['deger' => 'Pano soğutma: TPK 1000, gereken 1.033 W', 'sinif' => 'hucre--genis']) ?>
          <?= form_hucre('mesaj', ['deger' => "Finder: Güvenlik röleleri\nWieland: Klemens ve aksesuarlar", 'yardim' => 'Talep listesinden gelen satırlar burada görünür.']) ?>
          <?= form_hucre('kvkk') ?>
        </div>
        <?= form_gizli_alanlar('_dev/kitchen.php') ?>
        <div class="kt-satir"><?= dugme('Talebi gönder', null, ['button' => true, 'tip' => 'submit']) ?></div>
      </form>
      <div class="form-hucreler kt-kilitli" style="--hucre-sutun: 3">
        <div class="hucre is-kilitli"><span class="hucre__etiket">Ad soyad</span><span class="hucre__deger">Kilitli hücre (gönderildi)</span></div>
        <div class="hucre is-kilitli"><span class="hucre__etiket">Telefon</span><span class="hucre__deger sayi">0224 441 70 88</span></div>
        <div class="hucre is-kilitli"><span class="hucre__etiket">Onay</span><span class="hucre__deger" data-kase-yeri>Kaşe yeri (F2 kase())</span></div>
      </div>
    </div>
  </section>

  <section id="parcalar" class="bant" data-satir="F" data-sc-act="flow" aria-labelledby="parca-b">
    <div class="izgara">
      <h2 id="parca-b" class="kt-h2">Parçalar</h2>

      <h3>Kılçizgi tablo (malzeme listesi biçimi)</h3>
      <div class="tablo-izgara kt-bom" style="--tablo-sutunlar: 3.5rem minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1.4fr)" role="table" aria-label="Malzeme listesi örneği">
        <div class="tablo-izgara__baslik" role="columnheader">Sembol</div><div class="tablo-izgara__baslik" role="columnheader">Ürün grubu</div><div class="tablo-izgara__baslik" role="columnheader">Marka</div><div class="tablo-izgara__baslik" role="columnheader">Kategori</div>
<?php foreach (array_slice(arama_yap('kontaktr')['sonuclar'], 0, 5) as $r): $kat = data('kategoriler')['liste'][$r['kategori']]; ?>
        <div role="cell" class="kt-sembol-hucre"><?= sembol_hazir() ? sembol($kat['sembol'], ['buyukluk' => 32, 'sigdir' => true]) : '' ?></div>
        <div role="cell"><?= arama_vurgula($r['grup'], 'kontaktr') ?></div><div role="cell"><?= e($r['marka_ad']) ?></div><div role="cell"><?= e($r['kategori_ad']) ?></div>
<?php endforeach; ?>
      </div>
      <p class="t-kucuk ikincil">Sorgu "kontaktr" (yazım hatası): sunucu araması <?= count(arama_yap('kontaktr')['sonuclar']) ?> grup buldu.</p>

      <h3>Ana sayfa istasyonları (markalar veriden)</h3>
      <div class="tablo-izgara" style="--tablo-sutunlar: 5rem minmax(0, 1.2fr) minmax(0, 2fr)" role="table" aria-label="İstasyon markaları">
        <div class="tablo-izgara__baslik" role="columnheader">Etiket</div><div class="tablo-izgara__baslik" role="columnheader">İstasyon</div><div class="tablo-izgara__baslik" role="columnheader">Markalar</div>
<?php foreach (data('istasyonlar') as $i): if (!$i['etiket']) continue; ?>
        <div role="cell" class="cihaz"><?= e(implode(' ', $i['etiket'])) ?></div><div role="cell"><?= e($i['baslik']) ?></div>
        <div role="cell"><?= $i['markalar'] ? e(implode('; ', array_map(fn ($s) => ($s['etiket'] ? $s['etiket'] . ': ' : '') . implode(', ', $s['markalar']), istasyon_markalari($i)))) : e($i['metin'] ?? '') ?></div>
<?php endforeach; ?>
      </div>

      <h3>Görsel yok durumu</h3>
      <p class="t-kucuk">resim('urun/u01') <?= resim('urun/u01', 'Kompakt şalter, temsilî görsel') === '' ? 'boş döndü: istasyon yalnızca sembolle görünür, 404 isteği yok.' : 'görseli buldu.' ?></p>
      <div class="kt-satir">
        <?= resim('urun/u01', 'Kompakt şalter, temsilî görsel', ['sizes' => '240px']) ?: (sembol_hazir() ? sembol('devre-kesici', ['buyukluk' => 96, 'etiket' => '-Q1']) : '') ?>
      </div>

      <h3>Talep listesi (liste.js)</h3>
      <div class="kt-liste">
        <div class="kt-satir">
          <button class="dugme dugme--ikincil" type="button" data-kt-ekle="Finder|Güvenlik röleleri" aria-pressed="false"><span class="dugme__etiket">Finder: Güvenlik röleleri</span></button>
          <button class="dugme dugme--ikincil" type="button" data-kt-ekle="Wieland|Klemens ve aksesuarlar" aria-pressed="false"><span class="dugme__etiket">Wieland: Klemens ve aksesuarlar</span></button>
          <button class="dugme dugme--ikincil" type="button" data-kt-temizle><span class="dugme__etiket">Listeyi temizle</span></button>
        </div>
        <p class="osi" aria-live="polite" data-kt-liste-durum>Talep listesi: 0 grup</p>
      </div>

      <h3>Ses</h3>
      <div class="kt-satir">
        <button class="dugme dugme--ikincil" type="button" data-ses-dugme aria-label="Sesi aç"><?= glif('hoparlor', 24) ?><span class="dugme__etiket" data-ses-metin>Kapalı</span></button>
        <span class="t-kucuk ikincil">Başlıktaki ve antetteki düğmelerle aynı tercih (localStorage tky.ses).</span>
      </div>

      <h3>Gömülü veri</h3>
      <?= json_gom('kt-veri', ['marka' => marka_sayisi(), 'grup' => grup_sayisi(), 'kategori' => count(data('kategoriler')['liste'])]) ?>
      <p class="osi" data-kt-veri>json_gom: </p>
    </div>
  </section>
</main>
<?php partial('antet', ['foy' => $foy, 'olcek' => $foyId === 'sogutma' ? '1:12' : '-', 'onay' => $foyId === 'ana']); ?>
<style>
  @media (max-width: 767.98px) { .kt-kayit { --tablo-sutunlar: 3.5rem minmax(0, 1fr) !important; } .kt-kayit > :nth-child(4n+3), .kt-kayit > :nth-child(4n+4) { display: none; } }
  .kt-baslik { display: grid; gap: var(--s-4); margin-bottom: var(--s-7); }
  .kt-h2 { margin-bottom: var(--s-5); }
  .kt-renkler { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: var(--s-4); margin-bottom: var(--s-7); }
  .kt-renkler li { display: grid; gap: 2px; }
  .kt-renk { height: 56px; border: 1px solid var(--kil); }
  .kt-renk__ad { font-weight: 560; }
  .kt-yazi { display: grid; gap: var(--s-5); }
  .kt-satir { display: flex; flex-wrap: wrap; align-items: center; gap: var(--s-5); margin-block: var(--s-5); }
  .kt-ozalit { margin-top: var(--s-6); padding: var(--s-5); background: var(--zemin); color: var(--metin); }
  .kt-notlar { margin-block: var(--s-6); display: grid; gap: var(--s-3); max-width: var(--olcu); }
  .kt-form { display: grid; gap: var(--s-4); margin-block: var(--s-5); max-width: 960px; }
  .kt-kilitli { max-width: 960px; margin-top: var(--s-6); }
  .kt-sembol-hucre { display: grid; place-items: center; }
  #parcalar h3 { margin: var(--s-7) 0 var(--s-4); }
  .kt-kontrast { margin-bottom: var(--s-3); }
</style>
<script type="module">
  import { liste } from '../assets/js/liste.js';
  const durum = document.querySelector('[data-kt-liste-durum]');
  const ciz = () => {
    durum.textContent = 'Talep listesi: ' + liste.sayi() + ' grup' + (liste.sayi() ? ' (' + liste.satirlar().join('; ') + ')' : '');
    document.querySelectorAll('[data-kt-ekle]').forEach((b) => { const [m, g] = b.dataset.ktEkle.split('|'); b.setAttribute('aria-pressed', liste.varMi(m, g) ? 'true' : 'false'); });
  };
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-kt-ekle]');
    if (b) { const [m, g] = b.dataset.ktEkle.split('|'); liste.degistir(m, g); }
    if (e.target.closest('[data-kt-temizle]')) liste.temizle();
    const v = e.target.closest('[data-kt-vurgu]');
    if (v) window.tky.kaydir(v.dataset.ktVurgu).then(() => window.tky.hedefVurgula(v.dataset.ktVurgu));
  });
  liste.dinle(ciz); ciz();
  const veri = JSON.parse(document.getElementById('kt-veri').textContent);
  document.querySelector('[data-kt-veri]').textContent += JSON.stringify(veri);
  const w = {}; document.querySelectorAll('[data-genislik]').forEach((s) => { w[s.dataset.genislik] = Math.round(s.getBoundingClientRect().width); });
  document.documentElement.dataset.genislik = JSON.stringify(w);
</script>
<?php partial('son', ['foy' => $foy]); ?>
