/* F2 kabul testi: _dev/semboller.php
 *   node semboller.mjs [--cikti <klasör>]      (varsayılan _qa/F2)
 * Denetler: her sembol 4 boyda var; her sembolün çizimi < 900 ms; azaltılmış harekette son durum hemen;
 * ses kapalıyken ve liste boşken cal() sessiz, hatasız ve mp3 isteği yok; konsol hatası yok.
 * Yazar: ekran görüntüleri (tam tablo dilimleri, 192 px yakın plan), sonuc.json. Çıkış kodu 1 = başarısız.
 */
import fs from 'node:fs';
import path from 'node:path';
import { tarayici, baglam, izle, url, klasor, yazJson, sunucuKontrol, arg, bekle } from './ortak.mjs';

await sunucuKontrol();
const OUT = arg('--cikti') ? path.resolve(arg('--cikti')) : klasor('F2');
const SINIR = 900;
const b = await tarayici();
const sonuc = { tarih: new Date().toISOString(), basarisiz: [] };
const hata = (m) => { sonuc.basarisiz.push(m); console.log('BAŞARISIZ', m); };

/* 1. Tam hareket: boyutlar, süreler, ekran görüntüleri */
{
  const ctx = await baglam(b, { width: 1440, height: 900, deviceScaleFactor: 2 });
  const p = await ctx.newPage();
  const k = izle(p);
  await p.goto(url('_dev/semboller.php'));
  await p.waitForSelector('body[data-hazir="evet"]');
  const sayim = await p.evaluate(() => [...document.querySelectorAll('tr[data-ad]')].map((tr) => ({
    ad: tr.dataset.ad,
    boylar: [...tr.querySelectorAll('td.boy')].map((td) => td.querySelector('svg')?.getAttribute('height')),
    kontrol: tr.dataset.kontrol === 'evet',
  })));
  sonuc.sembolSayisi = sayim.length;
  sonuc.kontrol = sayim.filter((s) => s.kontrol).map((s) => s.ad);
  sayim.forEach((s) => { if (s.boylar.length !== 4 || s.boylar.some((h) => !h)) hata(`${s.ad}: 4 boyun hepsi yok`); });
  const sureler = await p.evaluate(() => window.__f2.olcHepsi());
  sonuc.sureler = sureler;
  Object.entries(sureler).forEach(([ad, ms]) => { if (ms >= SINIR) hata(`${ad}: çizim ${ms} ms (sınır ${SINIR})`); });
  sonuc.enUzun = Math.max(...Object.values(sureler));
  await bekle(200);
  const kalan = await p.evaluate(() => window.__f2.sonDurum());
  if (kalan.length) hata(`çizimden sonra son durumda olmayan öğe: ${kalan.slice(0, 5).join(', ')}`);

  // tablo dilimleri
  const satirlar = await p.$$('tr[data-ad]');
  for (let i = 0; i < satirlar.length; i += 6) {
    const sonEl = satirlar[Math.min(i + 5, satirlar.length - 1)];
    await sonEl.scrollIntoViewIfNeeded();
    const a = await satirlar[i].boundingBox();
    const s = await sonEl.boundingBox();
    const yTop = await p.evaluate(() => window.scrollY);
    await p.screenshot({ path: path.join(OUT, `tablo-${String(i / 6 + 1).padStart(2, '0')}.png`), fullPage: true,
      clip: { x: 0, y: a.y + yTop, width: 1440, height: s.y + s.height - a.y } });
  }
  // 192 px yakın plan: her sembol tek tek
  const yakin = await p.$$('td.boy[data-px="192"]');
  const adlar = sayim.map((s) => s.ad);
  fs.mkdirSync(path.join(OUT, 'yakin'), { recursive: true });
  for (let i = 0; i < yakin.length; i++) {
    await yakin[i].screenshot({ path: path.join(OUT, 'yakin', `${adlar[i]}-192.png`) });
  }
  // ilkeller
  await p.evaluate(async () => {
    const f = window.__f2.islemler;
    await f.ciz(); await f.enerji(); await f['vurgu-kalici'](); await f.kase(); await f.kontak();
    window.__f2.kaydirCizelge.progress(0.6);
  });
  await bekle(300);
  const ilkel = await p.$('.ilkeller');
  await ilkel.scrollIntoViewIfNeeded();
  await ilkel.screenshot({ path: path.join(OUT, 'ilkeller.png') });
  // sprite
  await (await p.$('.sprite')).screenshot({ path: path.join(OUT, 'sprite-32.png') });
  sonuc.konsol = k;
  if (k.konsol.length || k.sayfaHata.length || k.istek.length) hata(`konsol/istek: ${JSON.stringify(k)}`);
  await ctx.close();
}

/* 2. Azaltılmış hareket: çağrı anında son durum, ölçülen süre ~0 */
{
  const ctx = await baglam(b, 'masaustu', { azHareket: true });
  const p = await ctx.newPage();
  const k = izle(p);
  await p.goto(url('_dev/semboller.php'));
  await p.waitForSelector('body[data-hazir="evet"]');
  const r = await p.evaluate(async () => {
    const f = window.__f2;
    const hareket = document.documentElement.dataset.hareket;
    const t0 = performance.now();
    const pr = Promise.all([...document.querySelectorAll('.sembol')].map((s) => f.ilkeller.sembolCiz(s)));
    const anlik = f.sonDurum(); // aynı tikte, await öncesi
    await pr;
    f.islemler.ciz(); f.islemler.enerji(); f.islemler.kontak();
    const anlik2 = f.sonDurum();
    const bant = !!document.querySelector('.hat-enerji.is-enerjili');
    const bicak = document.querySelector('.ornek-kontak .kontak__bicak').getAttribute('transform');
    await f.islemler.kase();
    const kase = getComputedStyle(document.querySelector('#onay .kase')).opacity;
    return { hareket, ms: Math.round(performance.now() - t0), anlik, anlik2, bant, bicak, kase };
  });
  sonuc.azHareket = r;
  if (r.hareket !== 'az') hata(`azaltılmış harekette data-hareket=${r.hareket}`);
  if (r.anlik.length || r.anlik2.length) hata(`azaltılmış harekette son durum anında değil: ${[...r.anlik, ...r.anlik2].slice(0, 5)}`);
  if (!r.bant) hata('azaltılmış harekette enerji bandı son durumda değil');
  if (!/rotate\(0 /.test(r.bicak)) hata(`azaltılmış harekette kontak kapanmadı: ${r.bicak}`);
  await p.screenshot({ path: path.join(OUT, 'azhareket-ust.png') });
  const ilkel = await p.$('.ilkeller');
  await ilkel.scrollIntoViewIfNeeded();
  await ilkel.screenshot({ path: path.join(OUT, 'azhareket-ilkeller.png') });
  if (k.konsol.length || k.sayfaHata.length || k.istek.length) hata(`azhareket konsol/istek: ${JSON.stringify(k)}`);
  await ctx.close();
}

/* 3. Ses: kapalıyken hiçbir istek; açıkken liste boşsa cal() sessiz ve mp3 isteği yok */
{
  const ctx = await baglam(b, 'masaustu');
  const p = await ctx.newPage();
  const k = izle(p);
  const istekler = [];
  p.on('request', (r) => { if (/\/assets\/ses\//.test(r.url())) istekler.push(r.url()); });
  await p.goto(url('_dev/semboller.php'));
  await p.waitForSelector('body[data-hazir="evet"]');
  const kapali = await p.evaluate(async () => { await window.__f2.islemler['ses-cal'](); return window.__f2.ses.durum(); });
  const kapaliIstek = istekler.length;
  await p.click('#ses-ac');
  await bekle(300);
  const acik = await p.evaluate(async () => {
    const f = window.__f2;
    await f.islemler['ses-cal']();
    await f.ilkeller.kontak(document.querySelector('.ornek-kontak'), true);
    f.ses.dongu('ugultu', document.querySelector('.ornek-kontak'));
    await new Promise((r) => setTimeout(r, 400));
    return f.ses.durum();
  });
  await p.click('#ses-ac');
  sonuc.ses = { kapali, kapaliIstek, acik, istekler, tercih: await p.evaluate(() => localStorage.getItem('tky.ses')) };
  if (kapaliIstek) hata(`ses kapalıyken ${kapaliIstek} istek`);
  if (istekler.some((u) => /\.mp3/.test(u))) hata(`liste boşken mp3 isteği: ${istekler}`);
  if (k.konsol.length || k.sayfaHata.length || k.istek.length) hata(`ses konsol/istek: ${JSON.stringify(k)}`);
  await ctx.close();
}

/* 4. Telefon: taşma yok */
{
  const ctx = await baglam(b, 'telefon');
  const p = await ctx.newPage();
  await p.goto(url('_dev/semboller.php?ad=kontaktor'));
  await p.waitForSelector('body[data-hazir="evet"]');
  await p.screenshot({ path: path.join(OUT, 'telefon-kontaktor.png') });
  await ctx.close();
}

await b.close();
sonuc.gecti = sonuc.basarisiz.length === 0;
yazJson(path.join(OUT, 'sonuc.json'), sonuc);
console.log(`${sonuc.sembolSayisi} sembol, en uzun çizim ${sonuc.enUzun} ms, kontrol: ${sonuc.kontrol.length}. ${sonuc.gecti ? 'GEÇTİ' : 'BAŞARISIZ'}`);
process.exit(sonuc.gecti ? 0 : 1);
