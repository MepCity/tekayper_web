# Vendor kütüphaneleri

Kaynak: npm, `npm i --ignore-scripts gsap@3.15.0 lenis@1.3.27` (24.10.2026). Yalnızca `dist/` içindeki küçültülmüş dosyalar kopyalandı. `sourceMappingURL` satırları silindi (harita dosyaları sunulmuyor), başka değişiklik yok.

| Dosya | Paket | Sürüm | Lisans | SHA-256 |
|---|---|---|---|---|
| gsap/gsap.min.js | gsap | 3.15.0 | GSAP Standard "no charge" License, https://gsap.com/standard-license | 92bb9a96476f983d212a2bc4f54c889039c1696dd4461d40a736860938570fbb |
| gsap/ScrollTrigger.min.js | gsap | 3.15.0 | aynı | b0b14d67b55b0c43c756ac0b106cfcb09d0879945f6ead64451065b0672916a2 |
| gsap/DrawSVGPlugin.min.js | gsap | 3.15.0 | aynı | beb19529f54c1212f1f5117d027be01afda2f363a4926d32aa979bc11140edc1 |
| gsap/Flip.min.js | gsap | 3.15.0 | aynı | cbe3ca726350f8d230da38a14ce2384e7772e05a45cee6144dd7fe6dde868c2f |
| lenis.min.js | lenis | 1.3.27 | MIT, metin: LENIS-LICENSE.txt | 07020831fc52c75dc556473f50ddbf993ca65f1b920394ebd04dfd8c1824d952 |

GSAP 3.13 (Nisan 2025) ile birlikte tüm eklentiler (DrawSVGPlugin dahil) ücretsiz standart lisansa geçti. Lisans ticari siteler için ücretsiz kullanımı kapsar; tek kısıt GSAP ile rekabet eden bir görsel animasyon aracı yapmamaktır. Dosyaların başındaki telif yorumları korunmalıdır.

Boyutlar (gzip): gsap 28,4 KB, ScrollTrigger 18,0 KB, Flip 9,7 KB, DrawSVG 2,2 KB, Lenis 5,4 KB.

Yükleme: `app/partials/son.php` bu dosyaları `defer` ile yalnızca isteyen sayfalarda ekler (`partial('head', ['gsap' => [...], 'lenis' => true])`). Hepsi global değişken açar: `window.gsap`, `window.ScrollTrigger`, `window.DrawSVGPlugin`, `window.Flip`, `window.Lenis`. Eklenti kaydı `assets/js/site.js` içinde yapılır.

Güncelleme: sürümü değiştirmeden önce sayfaları yeniden test edin, bu tabloyu ve `son.php` içindeki listeyi güncelleyin.
