<?php
/**
 * Antet (title block) = her sayfanın alt bilgisi.
 *   partial('antet', ['foy' => $foy]);                         şematik föy, ÖLÇEK "-"
 *   partial('antet', ['foy' => $foy, 'olcek' => '1:12']);      pano föyü
 *   partial('antet', ['foy' => $foy, 'onay' => true]);         ONAY hücresinde "Teklif al" (yalnızca index)
 *   'onay' bir HTML metni de olabilir (hücre içeriği olarak basılır).
 *   'id' => 'antet-bant' (varsayılan; "antet" kimliği kurumsal.php F satırına ayrılmıştır)
 *   'sinif' => 'antet--buyuk' gibi ek sınıf (kurumsal sayfa bileşeni büyük ölçekte kullanır).
 * Hücreler data-alan ile işaretlidir (firma, adres, telefon, gsm, eposta, foy, foy-adi, tanim, guncelleme, olcek, ses, onay).
 */
$foy = $foy ?? $GLOBALS['sayfa']['foy'];
$olcek = $olcek ?? '-';
$onay = $onay ?? false;
$tel = config('phone');
$gsm = config('gsm');
$tarih = guncelleme_tarihi($foy['dosya']);
$tarihIso = DateTime::createFromFormat('d.m.Y', $tarih)->format('Y-m-d');
$hucre = function (string $alan, string $etiket, string $deger, string $ek = ''): string {
    return '<div class="antet__hucre antet__hucre--' . $alan . $ek . '" data-alan="' . $alan . '"><span class="antet__etiket">'
         . e($etiket) . '</span><span class="antet__deger">' . $deger . '</span></div>';
};
?>
<footer class="antet-bant<?= isset($sinif) ? ' ' . e($sinif) : '' ?>" id="<?= e($id ?? 'antet-bant') ?>">
  <div class="antet-bant__izgara">
    <nav class="foy-cizelgesi" aria-label="Föy listesi">
      <p class="foy-cizelgesi__baslik">Föy listesi</p>
      <ol class="foy-cizelgesi__liste">
<?php foreach (foy_listesi() as $f): ?>
        <li><a href="<?= e($f['href']) ?>"<?= $f['id'] === $foy['id'] ? ' aria-current="page"' : '' ?>><span class="foy-cizelgesi__no" aria-hidden="true"><?= (int) $f['no'] ?></span><span class="foy-cizelgesi__ad"><?= e($f['menu'] ?? 'KVKK aydınlatma metni') ?></span><span class="foy-cizelgesi__tanim" aria-hidden="true"><?= e($f['tanim']) ?></span></a></li>
<?php endforeach; ?>
      </ol>
    </nav>
    <div class="antet<?= $onay ? ' antet--onayli' : '' ?>">
      <a class="antet__logo" href="<?= e(url('')) ?>" aria-label="Tekayper, anasayfa"><?php partial('logo', ['varyant' => $foy['zemin'] === 'ozalit' ? 'beyaz' : 'renkli']); ?></a>
      <address class="antet__adres">
        <?= $hucre('firma', 'Firma', e(config('legal'))) ?>
        <?= $hucre('adres', 'Adres', '<a href="' . e(config('maps')) . '" target="_blank" rel="noopener">' . e(config('address.line1') . ' ' . config('address.line2')) . ', ' . e(config('address.district')) . ' / ' . e(config('address.city')) . '<span class="gizli"> (Google Haritalar, yeni sekmede açılır)</span></a>') ?>
        <?= $hucre('telefon', 'Telefon', '<a href="tel:' . e($tel['tel']) . '">' . e($tel['label']) . '</a>') ?>
        <?= $hucre('gsm', 'GSM', '<a href="tel:' . e($gsm['tel']) . '">' . e($gsm['label']) . '</a>') ?>
        <?= $hucre('eposta', 'E-posta', '<a href="mailto:' . e(config('email')) . '">' . e(config('email')) . '</a>') ?>
      </address>
      <?= $hucre('foy', 'Föy', e($foy['no'] > 0 ? 'Föy ' . $foy['no'] . ' / ' . $foy['toplam'] : 'Föy ? / ' . $foy['toplam'])) ?>
      <?= $hucre('foy-adi', 'Föy adı', e($foy['ad'])) ?>
      <?= $hucre('tanim', 'Tanım', '<span class="antet__osi">' . e($foy['tanim']) . '</span>') ?>
      <?= $hucre('guncelleme', 'Güncelleme', '<time datetime="' . e($tarihIso) . '">' . e($tarih) . '</time>') ?>
      <?= $hucre('olcek', 'Ölçek', '<span class="antet__osi" data-olcek>' . e($olcek) . '</span>') ?>
      <?= $hucre('ses', 'Ses', '<button class="antet__ses" type="button" data-ses-dugme aria-pressed="false"><span class="gizli">Ses: </span><span data-ses-metin>Kapalı</span></button>') ?>
<?php if ($onay): ?>
      <?= $hucre('onay', 'Onay', $onay === true ? dugme('Teklif al', url('iletisim.php#talep')) : $onay) ?>
<?php endif; ?>
      <p class="antet__not">
        <a href="<?= e(url('kvkk.php')) ?>">KVKK aydınlatma metni</a>
        <span>© <?= date('Y') ?> Tekayper</span>
        <span>Ürün görselleri temsilidir. Marka adları sahiplerine aittir.</span>
      </p>
    </div>
  </div>
</footer>
