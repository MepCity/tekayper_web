/* Malzeme listesi arama testleri (spec 3.4 test listesi).
 *   node arama.mjs [--sayfa tekayper-urunler.php]
 * Sayfa sözleşmesi (P4): arama kutusu input[name="ara"]; görünen sonuç satırlarında data-marka="<marka adı>"
 * (gizli satırlar hidden ya da display:none). Seçiciler ortamdan değiştirilebilir: QA_ARA_GIRDI, QA_ARA_SATIR.
 * Ayrıca: URL durumu (?ara=) yazılıyor mu, yeniden yüklemede aynı sonuç geliyor mu.
 */
import path from 'node:path';
import { tarayici, baglam, izle, url, klasor, yazJson, sunucuKontrol, arg, bekle } from './ortak.mjs';

await sunucuKontrol();
const SAYFA = arg('--sayfa', 'tekayper-urunler.php');
const GIRDI = process.env.QA_ARA_GIRDI || 'input[name="ara"]';
const SATIR = process.env.QA_ARA_SATIR || '[data-marka]';
const TESTLER = [
  ['ISIK', ['TMX', 'Philips']],
  ['isikli', ['TMX', 'Philips']],
  ['SÜRÜCÜ', ['LS Electric', 'GMT', 'ISISO']],
  ['surucu', ['LS Electric', 'GMT', 'ISISO']],
  ['encoder', ['Nemicon', 'Hanyoung', 'Autonics', 'Bedok', 'Alfa Sensör']],
  ['nyaf', ['Prysmian']],
  ['kontaktor', ['Bedok', 'Schneider Electric', 'Siemens']],
  ['röle', ['Finder', 'Omron', 'Bedok', 'Wieland', 'Hanyoung']],
  ['Klemens', ['Wieland', 'Onka', 'Klemsan', 'Gwest']],
  ['termokupl', ['ISISO']],
  ['plazma', ['Kjellberg', 'IHT']],
  ['kablo kanalı', ['Anipa', 'Klemsan', 'CKS']],
  ['kontaktr', ['Bedok']],
];

const b = await tarayici();
const ctx = await baglam(b, 'masaustu');
const p = await ctx.newPage();
const k = izle(p);
await p.goto(url(SAYFA), { waitUntil: 'load' });
if (!(await p.$(GIRDI))) { console.error(`Arama kutusu yok: ${GIRDI} (${SAYFA})`); await b.close(); process.exit(2); }
const sonuc = [];
let kotu = 0;
const gorunenler = () => p.$$eval(SATIR, (els) => [...new Set(els.filter((e) => e.offsetParent !== null && !e.closest('[hidden]')).map((e) => e.dataset.marka))]);
for (const [sorgu, beklenen] of TESTLER) {
  await p.fill(GIRDI, '');
  await p.type(GIRDI, sorgu, { delay: 20 });
  await bekle(450);
  const markalar = await gorunenler();
  const eksik = beklenen.filter((m) => !markalar.includes(m));
  const adres = await p.evaluate(() => location.search);
  const urlOk = /[?&]ara=/.test(adres);
  if (eksik.length || !urlOk) kotu++;
  sonuc.push({ sorgu, beklenen, bulunan: markalar, eksik, url: adres });
  console.log(`${eksik.length || !urlOk ? 'SORUN' : 'tamam'}  "${sorgu}": ${markalar.length} marka${eksik.length ? ', eksik: ' + eksik.join(', ') : ''}${urlOk ? '' : ', URL durumu yazılmadı'}`);
}
// URL durumu gidiş dönüş
await p.goto(url(`${SAYFA}?ara=${encodeURIComponent('encoder')}`), { waitUntil: 'load' });
await bekle(450);
const geri = await gorunenler();
const donus = ['Nemicon', 'Autonics'].every((m) => geri.includes(m));
if (!donus) kotu++;
console.log(`${donus ? 'tamam' : 'SORUN'}  ?ara=encoder ile açılış: ${geri.length} marka`);
if (k.konsol.length || k.sayfaHata.length) { kotu++; console.log('SORUN konsol', k); }
await b.close();
yazJson(path.join(klasor('arama'), 'rapor.json'), { sonuc, donus, konsol: k });
process.exit(kotu ? 1 : 0);
