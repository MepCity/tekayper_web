/* arama.js: malzeme listesi araması, istemci tarafı (spec 3.4, paket P4).
 *
 * app/lib/arama.php (F1) ile birebir aynı kurallar; sunucu ?ara= ile JS kapalıyken aynı sonucu verir.
 * Veri sayfaya gömülüdür: <script type="application/json" id="malzeme-veri"> (arama_veri()).
 *
 *   import { aramaKur } from './arama.js';
 *   const a = aramaKur(veri);
 *   a.yap(sorgu, kategori, marka)  -> { sorgu, toplam, sonuclar: [{sira, marka, marka_ad, grup, kategori, kategori_ad, puan, yalnizMarka}] }
 *   a.vurgula(metin, sorgu)        -> HTML (eşleşen kısımlar <mark> içinde, kaçışlı)
 *   a.katla(metin)                 -> katlanmış metin
 *
 * Kurallar (arama.php başlığı):
 *  - Katlama: I -> ı, İ -> i, Türkçe küçük harf, ç ğ ı ö ş ü â î û -> c g i o s u a i u; / . - dışındaki noktalama boşluk.
 *  - Kelimeler (a-z0-9), durak kelimeleri atılır; 5+ harfli kelimeden ek atılır, kelime 5 harften kısalana kadar.
 *  - Puan: marka adı 3, grup kelimesi 3, grup kelimesi sorguyla başlıyor 2, eş anlamlı 2, kategori 2, yazım hatası 1.
 *  - Marka adı: site_ad yazılışı (MEANWELL, HANGYOUNG) ve bitişik yazım marka sayılır (3); marka adı yazım hatası 1.
 *  - Her sorgu birimi eşleşmeli (VE).
 */

const HARF = {
  'ç': 'c', 'ğ': 'g', 'ı': 'i', 'ö': 'o', 'ş': 's', 'ü': 'u', 'â': 'a', 'î': 'i', 'û': 'u',
};

/** tr_fold (yardimci.php) tek kod noktası için: I -> ı, İ -> i, küçük harf, aksan katlama */
function harfKatla(ch) {
  let k = ch === 'I' ? 'ı' : (ch === 'İ' ? 'i' : ch);
  k = k.toLocaleLowerCase('tr-TR').replace('i\u0307', 'i');
  return k.replace(/[çğıöşüâîû]/g, (c) => HARF[c]);
}

const KATLA_ONBELLEK = new Map();
/** arama_katla_harfler: harf harf, uzunluk korunur (kod noktası başına bir karakter) */
function katlaHarfler(s) {
  const out = [];
  for (const ch of s) {
    let f = KATLA_ONBELLEK.get(ch);
    if (f === undefined) {
      const parca = [...harfKatla(ch)];
      f = parca.length === 1 ? parca[0] : ((parca[0] ?? '') + ' ')[0];
      if (!/[a-z0-9]/.test(f)) f = (f === '/' || f === '.' || f === '-') ? f : ' ';
      KATLA_ONBELLEK.set(ch, f);
    }
    out.push(f);
  }
  return out;
}

export function katla(s) {
  return katlaHarfler(String(s ?? '')).join('');
}

export function aramaKur(veri) {
  const DURAK = new Set(veri.durak || ['ve', 'ile', 'icin', 'veya']);
  const EKLER = veri.ekler || ['leri', 'lari', 'nin', 'nun', 'ler', 'lar', 'si', 'su', 'in', 'un', 'i', 'u'];

  /** [[kelime, başlangıç], ...] */
  function kelimelerKonum(katli) {
    const out = [];
    const re = /[a-z0-9]+/g;
    let m;
    while ((m = re.exec(katli))) {
      if (!DURAK.has(m[0])) out.push([m[0], m.index]);
    }
    return out;
  }

  function kok(k) {
    while (k.length >= 5) {
      let kesildi = false;
      for (const ek of EKLER) {
        if (k.length - ek.length >= 3 && k.endsWith(ek)) {
          k = k.slice(0, -ek.length);
          kesildi = true;
          break;
        }
      }
      if (!kesildi) break;
    }
    return k;
  }

  const kokler = (metin) => kelimelerKonum(katla(metin)).map((x) => kok(x[0]));

  /** Damerau-Levenshtein (optimal hizalama) <= sinir */
  function yakin(a, b, sinir = 1) {
    const la = a.length;
    const lb = b.length;
    if (Math.abs(la - lb) > sinir) return false;
    const d = [];
    for (let i = 0; i <= la; i++) { d[i] = [i]; }
    for (let j = 0; j <= lb; j++) d[0][j] = j;
    for (let i = 1; i <= la; i++) {
      let enAz = Infinity;
      for (let j = 1; j <= lb; j++) {
        const c = a[i - 1] === b[j - 1] ? 0 : 1;
        d[i][j] = Math.min(d[i - 1][j] + 1, d[i][j - 1] + 1, d[i - 1][j - 1] + c);
        if (i > 1 && j > 1 && a[i - 1] === b[j - 2] && a[i - 2] === b[j - 1]) {
          d[i][j] = Math.min(d[i][j], d[i - 2][j - 2] + 1);
        }
        enAz = Math.min(enAz, d[i][j]);
      }
      if (enAz > sinir) return false;
    }
    return d[la][lb] <= sinir;
  }

  const esan = (veri.esanlamlar || []).map((satir) => satir.map(kokler));

  const ayni = (a, b) => a.length === b.length && a.every((x, i) => x === b[i]);
  function diziIcerir(saman, igne) {
    const n = igne.length;
    if (!n) return false;
    for (let i = 0; i + n <= saman.length; i++) {
      let ok = true;
      for (let j = 0; j < n; j++) { if (saman[i + j] !== igne[j]) { ok = false; break; } }
      if (ok) return true;
    }
    return false;
  }

  /** Sorgu birimleri: çok kelimeli eş anlamlılar tek birim */
  function birimler(sorgu) {
    const kk = kokler(sorgu);
    const out = [];
    let i = 0;
    while (i < kk.length) {
      let bulundu = null;
      for (const satir of esan) {
        for (const terim of satir) {
          const n = terim.length;
          if (n > 1 && ayni(kk.slice(i, i + n), terim) && (!bulundu || n > bulundu[0].length)) {
            bulundu = [terim, satir];
          }
        }
      }
      if (bulundu) {
        out.push({ kokler: bulundu[0], alternatif: bulundu[1] });
        i += bulundu[0].length;
        continue;
      }
      const k = kk[i];
      let alt = [];
      for (const satir of esan) {
        for (const terim of satir) {
          if (terim.length === 1 && (terim[0] === k || (terim[0].length >= 4 && k.startsWith(terim[0])))) {
            alt = alt.concat(satir);
            break;
          }
        }
      }
      out.push({ kokler: [k], alternatif: alt });
      i++;
    }
    return out;
  }

  /* dizin */
  const katAd = new Map();
  const katKok = new Map();
  (veri.kategoriler || []).forEach((k) => { katAd.set(k.id, k.ad); katKok.set(k.id, kokler(k.ad)); });
  const dizin = [];
  (veri.markalar || []).forEach((m, mi) => {
    const markaKok = kokler(m.ad);
    // eski sitenin yazılışı (site_ad) ve bitişik yazım ("meanwell") aynı markadır
    const markaAlt = [];
    let bitisik = [markaKok.join('')];
    if (m.site_ad) {
      const siteKok = kokler(m.site_ad);
      if (siteKok.join(' ') !== markaKok.join(' ')) markaAlt.push(siteKok);
      bitisik.push(siteKok.join(''));
    }
    bitisik = [...new Set(bitisik)];
    m.gruplar.forEach((g, gi) => {
      dizin.push({
        marka: m.slug, marka_ad: m.ad, grup: g.ad, kategori: g.kategori, kategori_ad: katAd.get(g.kategori) || '',
        kokler: kokler(g.ad), marka_kokler: markaKok, marka_alt: markaAlt, marka_bitisik: bitisik, kategori_kokler: katKok.get(g.kategori) || [],
        sira: mi * 100 + gi,
      });
    });
  });

  /** [puan, tür] */
  function birimPuani(b, g) {
    const k = b.kokler;
    const tek = k.length === 1 ? k[0] : null;
    if (diziIcerir(g.marka_kokler, k)) return [3, 'marka'];
    for (const alt of g.marka_alt) { if (diziIcerir(alt, k)) return [3, 'marka']; }
    if (tek !== null && tek.length >= 4 && g.marka_bitisik.includes(tek)) return [3, 'marka'];
    if (diziIcerir(g.kokler, k)) return [3, 'tam'];
    let en = [0, null];
    if (tek !== null && tek.length >= 3) {
      for (const gk of g.kokler) {
        if (gk.startsWith(tek)) { en = [2, 'onek']; break; }
      }
    }
    if (en[0] < 2) {
      for (const alt of b.alternatif) {
        if (diziIcerir(g.kokler, alt) || diziIcerir(g.marka_kokler, alt)) { en = [2, 'esanlam']; break; }
      }
    }
    if (en[0] < 2 && (diziIcerir(g.kategori_kokler, k) || (tek !== null && tek === g.kategori))) en = [2, 'kategori'];
    if (en[0] < 1 && tek !== null && tek.length >= 5) {
      // marka adındaki yazım hatası: marka kelimeleri, eski yazılış ve bitişik yazım
      const markaAdaylari = g.marka_kokler.concat(...g.marka_alt, g.marka_bitisik);
      for (const gk of g.kokler.concat(g.kategori_kokler, markaAdaylari)) {
        if (gk.length >= 4 && yakin(tek, gk)) { en = [1, 'yakin']; break; }
      }
    }
    return en;
  }

  /* son sorgunun birimleri (her tuşta iki kez hesaplanmasın) */
  let sonSorgu = null;
  let sonBirim = [];
  function birimlerOnbellek(sorgu) {
    if (sorgu !== sonSorgu) { sonSorgu = sorgu; sonBirim = sorgu === '' ? [] : birimler(sorgu); }
    return sonBirim;
  }

  function yap(sorgu = '', kategori = null, marka = null) {
    sorgu = [...String(sorgu).trim()].slice(0, 80).join('');
    const bb = birimlerOnbellek(sorgu);
    const sonuclar = [];
    for (const g of dizin) {
      if (kategori && g.kategori !== kategori) continue;
      if (marka && g.marka !== marka) continue;
      let puan = 0;
      let tamam = true;
      let yalnizMarka = bb.length > 0;
      for (const b of bb) {
        const [p, t] = birimPuani(b, g);
        if (p === 0) { tamam = false; break; }
        if (t !== 'marka') yalnizMarka = false;
        puan += p;
      }
      if (!tamam) continue;
      sonuclar.push({ sira: g.sira, marka: g.marka, marka_ad: g.marka_ad, grup: g.grup, kategori: g.kategori,
        kategori_ad: g.kategori_ad, puan, yalnizMarka });
    }
    if (bb.length) sonuclar.sort((a, b) => (b.puan - a.puan) || (a.sira - b.sira));
    return { sorgu, kategori, marka, toplam: dizin.length, sonuclar };
  }

  const kacis = (s) => s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));

  /** arama_vurgula */
  function vurgula(metin, sorgu) {
    sorgu = String(sorgu ?? '').trim();
    const harfler = [...String(metin)];
    if (sorgu === '' || !harfler.length) return kacis(String(metin));
    const katli = katlaHarfler(String(metin)).join('');
    const bb = birimlerOnbellek([...sorgu].slice(0, 80).join(''));
    const isaret = new Array(harfler.length).fill(false);
    for (const [kelime, bas] of kelimelerKonum(katli)) {
      const kk = kok(kelime);
      let uzun = 0;
      for (const b of bb) {
        for (const bk of b.kokler) {
          if (kk === bk || (bk.length >= 3 && kelime.startsWith(bk))) {
            uzun = Math.max(uzun, Math.min(kelime.length, kk === bk ? kk.length : bk.length));
          } else if (bk.length >= 5 && yakin(bk, kk)) {
            uzun = Math.max(uzun, kk.length);
          }
        }
        for (const alt of b.alternatif) {
          if (alt.includes(kk)) uzun = Math.max(uzun, kk.length);
        }
      }
      for (let i = 0; i < uzun; i++) isaret[bas + i] = true;
    }
    let out = '';
    let acik = false;
    harfler.forEach((ch, i) => {
      if (isaret[i] && !acik) { out += '<mark>'; acik = true; } else if (!isaret[i] && acik) { out += '</mark>'; acik = false; }
      out += kacis(ch);
    });
    return out + (acik ? '</mark>' : '');
  }

  return { yap, vurgula, katla, kokler, birimler, toplam: dizin.length };
}

export default aramaKur;
