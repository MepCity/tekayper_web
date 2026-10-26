<?php
/**
 * Föy 9, Notlar: KVKK aydınlatma metni (spec 3.9, paket P9).
 * Okuma düzeni: sol kenarda föy başlığı ve not dizini, metin tek sütun (62ch). İmza hareketi yok.
 * noindex (föy kaydında) avukat onayına kadar; sitemap.xml'de de yok.
 * TASLAK NOTU yalnızca kullanıcı tarafından kaldırılır.
 */
require __DIR__ . '/app/bootstrap.php';
$foy = foy('kvkk');
partial('head', ['foy' => $foy, 'css' => ['sayfa-yasal']]);
partial('ust', ['foy' => $foy]);

$eposta = config('email');
$notlar = [
    'sorumlu'  => 'Veri sorumlusu',
    'veriler'  => 'Hangi verileri işliyoruz?',
    'amac'     => 'Hangi amaçla işliyoruz?',
    'sebep'    => 'Hukuki sebep ve toplama yöntemi',
    'aktarim'  => 'Kimlere aktarıyoruz?',
    'saklama'  => 'Ne kadar süre saklıyoruz?',
    'tarayici' => 'Çerezler ve tarayıcı belleği',
    'haklar'   => 'Haklarınız',
    'basvuru'  => 'Başvuru',
];
$no = function (string $id) use ($notlar): int {
    return array_search($id, array_keys($notlar), true) + 1;
};
?>
<main id="icerik" class="foy foy--kvkk" data-foy="9">
  <section id="metin" class="bant yasal" data-satir="A" data-sc-act="flow" aria-labelledby="yasal-baslik">
    <div class="izgara yasal__izgara">

      <aside class="yasal__kenar" aria-label="Notlar dizini">
        <p class="yasal__foy t-alan" aria-hidden="true">Notlar</p>
        <ol class="yasal__dizin">
<?php foreach ($notlar as $id => $baslik): ?>
          <li><a href="#not-<?= e($id) ?>"><span class="yasal__dizin-no" aria-hidden="true"><?= $no($id) ?></span><span><?= e($baslik) ?></span></a></li>
<?php endforeach; ?>
        </ol>
      </aside>

      <article class="yasal__metin metin">
        <p class="yasal__taslak" role="note"><span class="yasal__taslak-etiket"><svg class="yasal__revizyon" width="18" height="16" viewBox="0 0 18 16" aria-hidden="true" focusable="false"><path d="M9 1.5 16.8 15H1.2Z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="miter"/><path d="M9 6v4.5M9 11.6v1.4" stroke="currentColor" stroke-width="1.5"/></svg><span class="yasal__taslak-yazi">Taslak</span></span> Bu metin taslaktır; yayından önce hukuk danışmanı tarafından kontrol edilecektir.</p>

        <header class="yasal__bas">
          <h1 id="yasal-baslik" data-capraz-baslik>Kişisel verilerin korunması aydınlatma metni</h1>
          <p class="yasal__alt ikincil">Web sitesindeki teklif ve iletişim formları ile tarayıcınızda tutulan tercihler için</p>
        </header>

        <section class="not" id="not-sorumlu" aria-labelledby="not-sorumlu-b">
          <h2 class="not__baslik" id="not-sorumlu-b"><span class="not__no" aria-hidden="true"><?= $no('sorumlu') ?></span><?= e($notlar['sorumlu']) ?></h2>
          <p><?= e(config('legal')) ?> (“Tekayper”), <?= e(tam_adres()) ?> adresinde faaliyet gösterir. 6698 sayılı Kişisel Verilerin Korunması Kanunu (“KVKK”) kapsamında veri sorumlusu sıfatıyla bu metni hazırlamıştır.</p>
        </section>

        <section class="not" id="not-veriler" aria-labelledby="not-veriler-b">
          <h2 class="not__baslik" id="not-veriler-b"><span class="not__no" aria-hidden="true"><?= $no('veriler') ?></span><?= e($notlar['veriler']) ?></h2>
          <ul class="not__liste">
            <li>Kimlik ve iletişim: ad soyad, firma adı (isteğe bağlı), e-posta adresi, telefon numarası.</li>
            <li>Talep içeriği: konu ve talep alanına yazdıklarınız, formu gönderdiğiniz sayfa, formla birlikte gönderdiğiniz talep listesi satırları ve pano soğutma hesabı değerleri.</li>
            <li>İşlem güvenliği: formun gönderildiği tarih ve saat, IP adresinizden türetilen ve geri çevrilemeyen bir iz. IP adresinin kendisi kaydedilmez.</li>
          </ul>
        </section>

        <section class="not" id="not-amac" aria-labelledby="not-amac-b">
          <h2 class="not__baslik" id="not-amac-b"><span class="not__no" aria-hidden="true"><?= $no('amac') ?></span><?= e($notlar['amac']) ?></h2>
          <p>Teklif ve bilgi taleplerinize dönüş yapmak, teklif hazırlamak, sizinle iletişim kurmak ve formun kötüye kullanılmasını önlemek için.</p>
        </section>

        <section class="not" id="not-sebep" aria-labelledby="not-sebep-b">
          <h2 class="not__baslik" id="not-sebep-b"><span class="not__no" aria-hidden="true"><?= $no('sebep') ?></span><?= e($notlar['sebep']) ?></h2>
          <p>Verileriniz bu web sitesindeki formlar aracılığıyla elektronik ortamda toplanır. KVKK’nın 5. maddesinin 2. fıkrasındaki “bir sözleşmenin kurulması veya ifasıyla doğrudan ilgili olması” ve “ilgili kişinin temel hak ve özgürlüklerine zarar vermemek kaydıyla veri sorumlusunun meşru menfaati” hukuki sebeplerine dayanarak işlenir.</p>
        </section>

        <section class="not" id="not-aktarim" aria-labelledby="not-aktarim-b">
          <h2 class="not__baslik" id="not-aktarim-b"><span class="not__no" aria-hidden="true"><?= $no('aktarim') ?></span><?= e($notlar['aktarim']) ?></h2>
          <p>Verileriniz, talebinizin karşılanması için gerekli olduğu ölçüde web sitesinin barındırma ve e-posta hizmetini aldığımız tedarikçilerle ve kanunen yetkili kamu kurumlarıyla paylaşılabilir. Verileriniz pazarlama amacıyla üçüncü kişilere aktarılmaz.</p>
        </section>

        <section class="not" id="not-saklama" aria-labelledby="not-saklama-b">
          <h2 class="not__baslik" id="not-saklama-b"><span class="not__no" aria-hidden="true"><?= $no('saklama') ?></span><?= e($notlar['saklama']) ?></h2>
          <p>Talebiniz sonuçlandıktan sonra, ilgili mevzuatta öngörülen süreler ve olası uyuşmazlıklarda zamanaşımı süreleri boyunca saklanır; süre dolduğunda silinir, yok edilir ya da anonim hâle getirilir.</p>
        </section>

        <section class="not" id="not-tarayici" aria-labelledby="not-tarayici-b">
          <h2 class="not__baslik" id="not-tarayici-b"><span class="not__no" aria-hidden="true"><?= $no('tarayici') ?></span><?= e($notlar['tarayici']) ?></h2>
          <p>Sitemiz çerez kullanmaz. Ses tercihiniz ve talep listeniz yalnızca kendi tarayıcınızda (localStorage) saklanır; bize gönderilmez. Talep listesini teklif formuna aktarıp gönderirseniz liste, talebinizin parçası olarak bize ulaşır.</p>
          <p>Sitede ziyaretçi istatistiği (analitik) aracı kullanılmaz. Tarayıcınızda tutulan kayıtların tamamı aşağıdadır; tarayıcınızın site verilerini silme ayarıyla hepsini silebilirsiniz.</p>
          <table class="yasal__tablo">
            <caption class="gizli">Tarayıcıda tutulan kayıtlar</caption>
            <thead><tr><th scope="col">Kayıt</th><th scope="col">Ne tutar</th><th scope="col">Nerede, ne kadar</th></tr></thead>
            <tbody>
              <tr><th scope="row" class="osi">tky.ses</th><td>Sesin açık ya da kapalı olduğu</td><td>localStorage, siz silene kadar</td></tr>
              <tr><th scope="row" class="osi">tky.talepListesi</th><td>Talep listesine eklediğiniz marka ve ürün grupları</td><td>localStorage, listeyi boşaltana ya da siz silene kadar</td></tr>
              <tr><th scope="row" class="osi">tky.cerceve</th><td>Çizim çerçevesinin bu oturumda bir kez çizildiği</td><td>sessionStorage, sekme kapanınca silinir</td></tr>
            </tbody>
          </table>
        </section>

        <section class="not" id="not-haklar" aria-labelledby="not-haklar-b">
          <h2 class="not__baslik" id="not-haklar-b"><span class="not__no" aria-hidden="true"><?= $no('haklar') ?></span><?= e($notlar['haklar']) ?></h2>
          <p>KVKK’nın 11. maddesi uyarınca; verilerinizin işlenip işlenmediğini öğrenme, işlenmişse bilgi talep etme, işlenme amacını ve amacına uygun kullanılıp kullanılmadığını öğrenme, aktarıldığı üçüncü kişileri bilme, eksik ya da yanlış işlenmişse düzeltilmesini, şartları oluştuğunda silinmesini veya yok edilmesini isteme, bu işlemlerin aktarıldığı kişilere bildirilmesini isteme, otomatik sistemlerle analiz sonucu aleyhinize bir sonuca itiraz etme ve kanuna aykırı işleme nedeniyle zarara uğramanız hâlinde zararın giderilmesini talep etme haklarına sahipsiniz.</p>
        </section>

        <section class="not" id="not-basvuru" aria-labelledby="not-basvuru-b">
          <h2 class="not__baslik" id="not-basvuru-b"><span class="not__no" aria-hidden="true"><?= $no('basvuru') ?></span><?= e($notlar['basvuru']) ?></h2>
          <p>Haklarınızla ilgili taleplerinizi <a href="mailto:<?= e($eposta) ?>"><?= e($eposta) ?></a> adresine e-postayla ya da yukarıdaki adresimize yazılı olarak iletebilirsiniz. Başvurunuz en geç 30 gün içinde ücretsiz olarak sonuçlandırılır.</p>
        </section>
      </article>

    </div>
  </section>
</main>
<?php partial('antet', ['foy' => $foy]); partial('son', ['foy' => $foy]); ?>
