/* axe-core erişilebilirlik taraması (spec 6.4), masaüstü ve telefon.
 *   node erisim.mjs [sayfa ...]
 * WCAG 2.0/2.1 A ve AA kuralları. Ciddi (serious) ve kritik (critical) ihlal sıfır olmalı.
 * Gerçek metni gizleyerek geçmeye çalışmayın. Çıktı: _qa/erisim/rapor.json
 */
import path from 'node:path';
import { createRequire } from 'node:module';
import { tarayici, baglam, url, klasor, yazJson, sunucuKontrol, SAYFALAR, konumsal, bekle, QA_DIR } from './ortak.mjs';

await sunucuKontrol();
const require = createRequire(path.join(QA_DIR, 'package.json'));
const AXE = require.resolve('axe-core/axe.min.js');
const sayfalar = konumsal().length ? konumsal() : SAYFALAR;
const b = await tarayici();
const rapor = {};
let agir = 0;
for (const g of ['masaustu', 'telefon']) {
  for (const s of sayfalar) {
    const ctx = await baglam(b, g);
    const p = await ctx.newPage();
    await p.goto(url(s), { waitUntil: 'load' });
    await bekle(900);
    await p.addScriptTag({ path: AXE });
    const sonuc = await p.evaluate(() => window.axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] } }));
    const ihlal = sonuc.violations.map((v) => ({ id: v.id, etki: v.impact, aciklama: v.help, adet: v.nodes.length, ornek: v.nodes.slice(0, 3).map((n) => n.target.join(' ')) }));
    const n = ihlal.filter((v) => v.etki === 'serious' || v.etki === 'critical').length;
    agir += n;
    rapor[`${s} ${g}`] = ihlal;
    console.log(`${n ? 'SORUN' : 'tamam'}  ${s} ${g}: ${ihlal.length} ihlal türü, ${n} ciddi/kritik${ihlal.length ? ' (' + ihlal.map((v) => `${v.id}:${v.etki}`).join(', ') + ')' : ''}`);
    await ctx.close();
  }
}
await b.close();
yazJson(path.join(klasor('erisim'), 'rapor.json'), rapor);
process.exit(agir ? 1 : 0);
