<?php
/**
 * Genel yardımcılar (bootstrap.php yükler). Sayfa yazarlarının kullandığı adlar spec 4.3 ile aynıdır:
 * config, e, base_path, url, asset, data, partial, foy, foy_listesi, capraz, capraz_a, resim (gorsel.php),
 * sembol (F2), tr_fold, tr_sirala, form_token, form_token_valid (form.php), json_gom, guncelleme_tarihi,
 * kategori_markalari, talep_no (form.php).
 */

/** Nokta ile iç içe ayar okur: config('phone.label') */
function config(string $key, $default = null)
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** Form anahtarı için gizli değer. Ayarda yoksa storage/anahtar.txt bir kez üretilir. */
function gizli_anahtar(): string
{
    static $s = null;
    if ($s !== null) {
        return $s;
    }
    $s = (string) config('secret', '');
    if (strlen($s) >= 16) {
        return $s;
    }
    $dosya = ROOT . '/storage/anahtar.txt';
    if (is_file($dosya)) {
        $s = trim((string) file_get_contents($dosya));
    }
    if (strlen($s) < 16) {
        $s = bin2hex(random_bytes(32));
        @file_put_contents($dosya, $s, LOCK_EX);
        @chmod($dosya, 0600);
    }
    return $s;
}

/* ---------------------------------------------------------------- çıktı */

/** HTML kaçışı */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Site bir alt klasörde çalışıyorsa adresleri ona göre kurar. */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
        $root = realpath(ROOT) ?: ROOT;
        $base = '';
        if ($docRoot !== '' && strpos($root, $docRoot) === 0) {
            $base = str_replace('\\', '/', substr($root, strlen($docRoot)));
        }
        $base = rtrim($base, '/');
    }
    return $base;
}

/** Site içi adres: url('kurumsal.php'), url('') = ana sayfa */
function url(string $path = ''): string
{
    if ($path === '' || $path === 'index.php' || $path === '/') {
        return base_path() . '/';
    }
    if (strpos($path, 'index.php#') === 0 || strpos($path, 'index.php?') === 0) {
        return base_path() . '/' . substr($path, 9);
    }
    return base_path() . '/' . ltrim($path, '/');
}

/** Kanonik tam adres: tam_url('kurumsal.php') */
function tam_url(string $path = ''): string
{
    $kok = rtrim((string) config('url'), '/');
    if ($path === '' || $path === 'index.php') {
        return $kok . '/';
    }
    return $kok . '/' . ltrim($path, '/');
}

/** assets/ altındaki dosya, önbellek kırıcı ?v= ile: asset('css/temel.css') */
function asset(string $path): string
{
    $file = ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr((string) filemtime($file), -6) : '0';
    return base_path() . '/assets/' . ltrim($path, '/') . '?v=' . $v;
}

/** app/data/{ad}.php dosyasını bir kez yükler */
function data(string $name)
{
    static $cache = [];
    if (!array_key_exists($name, $cache)) {
        $cache[$name] = require APP . '/data/' . $name . '.php';
    }
    return $cache[$name];
}

/** app/partials/{ad}.php dosyasını değişkenlerle yükler */
function partial(string $name, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require APP . '/partials/' . $name . '.php';
}

/** Bir partial'ı metin olarak döndürür */
function partial_metin(string $name, array $vars = []): string
{
    ob_start();
    partial($name, $vars);
    return (string) ob_get_clean();
}

/**
 * <script type="application/json" id="..."> içine veri gömer (JS aynı veriyi okur).
 */
function json_gom(string $id, $veri): string
{
    $json = json_encode($veri, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return '<script type="application/json" id="' . e($id) . '">' . $json . '</script>';
}

/* ---------------------------------------------------------------- föy kaydı */

/** Föy kaydı + hesaplanan alanlar (id, href, etiket "Föy 5/9") */
function foy(string $id): array
{
    $foyler = data('foyler');
    if (!isset($foyler[$id])) {
        throw new InvalidArgumentException('Bilinmeyen föy: ' . $id);
    }
    $f = $foyler[$id];
    $f['id'] = $id;
    $f['href'] = url($f['dosya']);
    $f['toplam'] = foy_toplam();
    $f['etiket'] = 'Föy ' . $f['no'] . '/' . $f['toplam'];
    $f['zemin'] = $f['zemin'] ?? 'kagit';
    $f['noindex'] = $f['noindex'] ?? false;
    $f['ust'] = $f['ust'] ?? null;
    return $f;
}

/** Projedeki föy sayısı (404 hariç) */
function foy_toplam(): int
{
    return count(array_filter(data('foyler'), fn ($f) => $f['no'] > 0));
}

/** Föy listesi 1..9 (numara sırasıyla). $yokDahil: 404 föyünü de ekler. */
function foy_listesi(bool $yokDahil = false): array
{
    $liste = [];
    foreach (data('foyler') as $id => $f) {
        if ($f['no'] === 0 && !$yokDahil) {
            continue;
        }
        $liste[] = foy($id);
    }
    usort($liste, fn ($a, $b) => $a['no'] <=> $b['no']);
    return $liste;
}

/** Numaradan föy: foy_no(5) */
function foy_no(int $no): ?array
{
    foreach (data('foyler') as $id => $f) {
        if ($f['no'] === $no) {
            return foy($id);
        }
    }
    return null;
}

/**
 * Çapraz referansı çözer: capraz('/5.C')
 *   ['href' => '/pano-sogutma-sistemleri.php#modeller', 'kisa' => '/5.C', 'tam' => '=TKY+SGT/5.C',
 *    'ad' => 'Modeller', 'foy' => 'sogutma', 'satir' => 'C', 'id' => 'modeller']
 * Bilinmeyen referansta null döner ve uyarı yazar.
 */
function capraz(string $ref): ?array
{
    if (!preg_match('~^(?:=TKY\+[A-Z]+)?/?(\d+)(?:\.([A-Z]))?$~u', trim($ref), $m)) {
        trigger_error('Çapraz referans biçimi hatalı: ' . $ref, E_USER_WARNING);
        return null;
    }
    $f = foy_no((int) $m[1]);
    if ($f === null) {
        trigger_error('Çapraz referans föyü yok: ' . $ref, E_USER_WARNING);
        return null;
    }
    $harf = $m[2] ?? '';
    $kisa = '/' . $f['no'] . ($harf !== '' ? '.' . $harf : '');
    if ($harf === '') {
        return ['href' => $f['href'], 'kisa' => $kisa, 'tam' => $f['tanim'] . $kisa, 'ad' => $f['ad'],
                'foy' => $f['id'], 'satir' => null, 'id' => null];
    }
    if (!isset($f['satirlar'][$harf])) {
        trigger_error('Çapraz referans satırı yok: ' . $ref, E_USER_WARNING);
        return null;
    }
    [$id, $ad] = $f['satirlar'][$harf];
    return ['href' => $f['href'] . '#' . $id, 'kisa' => $kisa, 'tam' => $f['tanim'] . $kisa, 'ad' => $ad,
            'foy' => $f['id'], 'satir' => $harf, 'id' => $id];
}

/**
 * Çapraz referans bağlantısı:
 *   capraz_a('/5.C', 'TPK modelleri')
 *   <a class="capraz" href="..." data-capraz="/5.C" data-capraz-tam="=TKY+SGT/5.C Modeller">TPK modelleri <span class="capraz__ref">/5.C</span></a>
 * $opts: sinif (ek sınıf), sorgu (?kategori=... gibi, # öncesine eklenir), ref_gizle (yalnızca metin)
 */
function capraz_a(string $ref, string $metin, array $opts = []): string
{
    $c = capraz($ref);
    if ($c === null) {
        return e($metin);
    }
    $href = $c['href'];
    if (!empty($opts['sorgu'])) {
        $parca = explode('#', $href, 2);
        $href = $parca[0] . '?' . (is_array($opts['sorgu']) ? http_build_query($opts['sorgu']) : ltrim((string) $opts['sorgu'], '?'))
              . (isset($parca[1]) ? '#' . $parca[1] : '');
    }
    $sinif = trim('capraz ' . ($opts['sinif'] ?? ''));
    $cur = (($GLOBALS['sayfa']['foy']['id'] ?? '') === $c['foy'] && $c['satir'] === null) ? ' aria-current="page"' : '';
    $refHtml = empty($opts['ref_gizle']) ? '<span class="capraz__ref" aria-hidden="true">' . e($c['kisa']) . '</span>' : '';
    return '<a class="' . e($sinif) . '" href="' . e($href) . '" data-capraz="' . e($c['kisa']) . '" data-capraz-tam="'
         . e($c['tam'] . ' ' . $c['ad']) . '"' . $cur . '><span class="capraz__metin">' . e($metin) . '</span>' . $refHtml . '</a>';
}

/**
 * Düğme: dugme('Teklif al', url('iletisim.php#talep'))
 * $opts: tur ('birincil' varsayılan, 'ikincil' = altı çizili metin bağlantısı), etiket (aria-label),
 *        sinif, ref (ikincil türde Osifont çapraz eki, ör. '/4'), button (true = <button>), tip (submit/button),
 *        hedef ('_blank'), ozellik (ek HTML özellikleri dizisi)
 */
function dugme(string $metin, ?string $href = null, array $opts = []): string
{
    $tur = $opts['tur'] ?? 'birincil';
    $sinif = trim('dugme dugme--' . $tur . ' ' . ($opts['sinif'] ?? ''));
    $oz = '';
    foreach (($opts['ozellik'] ?? []) as $k => $v) {
        $oz .= ' ' . e($k) . ($v === true ? '' : '="' . e($v) . '"');
    }
    if (!empty($opts['etiket'])) {
        $oz .= ' aria-label="' . e($opts['etiket']) . '"';
    }
    $ic = '<span class="dugme__etiket" data-etiket="' . e($metin) . '">' . e($metin) . '</span>';
    if (!empty($opts['ref'])) {
        $ic .= '<span class="capraz__ref" aria-hidden="true">' . e($opts['ref']) . '</span>';
    }
    if (!empty($opts['button']) || $href === null) {
        $tip = $opts['tip'] ?? 'button';
        return '<button class="' . e($sinif) . '" type="' . e($tip) . '"' . $oz . '>' . $ic . '</button>';
    }
    if (!empty($opts['hedef'])) {
        $oz .= ' target="' . e($opts['hedef']) . '" rel="noopener"';
    }
    return '<a class="' . e($sinif) . '" href="' . e($href) . '"' . $oz . '>' . $ic . '</a>';
}

/** Sayfa dosyasının son değişiklik tarihi, gg.aa.yyyy */
function guncelleme_tarihi(string $dosya): string
{
    $yol = is_file($dosya) ? $dosya : ROOT . '/' . ltrim($dosya, '/');
    if (!is_file($yol)) {
        $yol = $_SERVER['SCRIPT_FILENAME'] ?? __FILE__;
    }
    return date('d.m.Y', (int) filemtime($yol));
}

/** PDF boyutu, ondalık MB, Türkçe: "4,4 MB" */
function pdf_boyut(string $dosya): string
{
    $yol = ROOT . '/' . ltrim($dosya, '/');
    if (!is_file($yol)) {
        return '';
    }
    return number_format(filesize($yol) / 1000000, 1, ',', '.') . ' MB';
}

/* ---------------------------------------------------------------- Türkçe metin */

/** Türkçe küçük harf + aksan katlama (arama, eşleştirme): "IŞIKLI" -> "isikli" */
function tr_fold(string $s): string
{
    $s = strtr($s, ['I' => 'ı', 'İ' => 'i']);
    $s = mb_strtolower($s, 'UTF-8');
    return strtr($s, ['ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u', 'â' => 'a', 'î' => 'i', 'û' => 'u', 'i̇' => 'i']);
}

/** Türkçe büyük harf: "iletişim" -> "İLETİŞİM" */
function tr_buyuk(string $s): string
{
    return mb_strtoupper(strtr($s, ['i' => 'İ', 'ı' => 'I']), 'UTF-8');
}

/** Türkçe küçük harf: "IŞIK" -> "ışık" */
function tr_kucuk(string $s): string
{
    return mb_strtolower(strtr($s, ['I' => 'ı', 'İ' => 'i']), 'UTF-8');
}

/** Diziyi Türkçe alfabe sırasına dizer (Ç C'den, Ş S'den sonra). $key: alt dizide sıralanacak alan. */
function tr_sirala(array $dizi, ?string $key = null): array
{
    $al = fn ($x) => (string) ($key === null ? $x : ($x[$key] ?? ''));
    if (class_exists('Collator')) {
        $c = new Collator('tr_TR');
        usort($dizi, fn ($a, $b) => $c->compare($al($a), $al($b)));
        return $dizi;
    }
    $sira = 'aâbcçdefgğhıiîjklmnoöprsştuüûvyz';
    $anahtar = function (string $s) use ($sira): array {
        $s = tr_kucuk($s);
        $out = [];
        foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            $p = mb_strpos($sira, $ch);
            $out[] = $p === false ? 100 + mb_ord($ch) : $p;
        }
        return $out;
    };
    usort($dizi, fn ($a, $b) => $anahtar($al($a)) <=> $anahtar($al($b)));
    return $dizi;
}

/* ---------------------------------------------------------------- ürün verisi */

/** Toplam marka ve grup sayısı (sayfa metinlerinde elle yazmak yerine) */
function marka_sayisi(): int
{
    return count(data('markalar'));
}

function grup_sayisi(): int
{
    $n = 0;
    foreach (data('markalar') as $m) {
        $n += count($m['gruplar']);
    }
    return $n;
}

/** Slug ile marka */
function marka(string $slug): ?array
{
    foreach (data('markalar') as $m) {
        if ($m['slug'] === $slug) {
            return $m;
        }
    }
    return null;
}

/** Katlanmış metni kelime ayraçlarına göre düzler (tire ve noktalama boşluk olur) */
function kelime_duz(string $s): string
{
    return trim((string) preg_replace('/[^a-z0-9]+/', ' ', tr_fold($s)));
}

/** $metin içinde $ifade bir kelime başından itibaren geçiyor mu (ikisi de katlanır) */
function kelime_basi_var(string $metin, string $ifade): bool
{
    $m = ' ' . kelime_duz($metin);
    $i = kelime_duz($ifade);
    return $i !== '' && strpos($m, ' ' . $i) !== false;
}

/** Grup adından kategori kuralıyla kategori bulur (ilk eşleşen kural). Eşleşme yoksa null. */
function kategori_bul(string $grupAdi): ?string
{
    foreach (data('kategoriler')['kurallar'] as [$kat, $kelimeler]) {
        foreach ($kelimeler as $k) {
            if (kelime_basi_var($grupAdi, $k)) {
                return $kat;
            }
        }
    }
    return null;
}

/**
 * Bir kategoride grubu olan markalar (markalar.php sırasıyla).
 *   kategori_markalari('role')                                  role kategorisinde grubu olan her marka
 *   kategori_markalari(['koruma','role'], ['koruma ve kontrol']) yalnızca filtreye uyan grupları olanlar
 *   kategori_markalari('sensor', [], ['nemicon','hanyoung'])     açık liste (kategori yine kontrol edilir)
 * Dönüş: [['slug','ad','gruplar' => [eşleşen grup adları]], ...]
 */
function kategori_markalari($kategori, array $filtre = [], ?array $slug = null): array
{
    $katlar = (array) $kategori;
    $sonuc = [];
    foreach (data('markalar') as $m) {
        if ($slug !== null && !in_array($m['slug'], $slug, true)) {
            continue;
        }
        $eslesen = [];
        foreach ($m['gruplar'] as $g) {
            if (!in_array($g['kategori'], $katlar, true)) {
                continue;
            }
            if ($filtre) {
                $uyar = false;
                foreach ($filtre as $f) {
                    if (kelime_basi_var($g['ad'], $f)) {
                        $uyar = true;
                        break;
                    }
                }
                if (!$uyar) {
                    continue;
                }
            }
            $eslesen[] = $g['ad'];
        }
        if ($eslesen) {
            $sonuc[] = ['slug' => $m['slug'], 'ad' => $m['ad'], 'gruplar' => $eslesen];
        }
    }
    if ($slug !== null) {
        $sira = array_flip($slug);
        usort($sonuc, fn ($a, $b) => $sira[$a['slug']] <=> $sira[$b['slug']]);
    }
    return $sonuc;
}

/**
 * Ana sayfa istasyonunun marka satırları:
 *   [['etiket' => null|'enkoder', 'markalar' => ['Schneider Electric', 'Siemens'], 'not' => null], ...]
 */
function istasyon_markalari(array $istasyon): array
{
    $satirlar = [];
    foreach ($istasyon['markalar'] ?? [] as $s) {
        $liste = kategori_markalari($s['kategori'], $s['filtre'] ?? [], $s['slug'] ?? null);
        $satirlar[] = [
            'etiket'   => $s['etiket'] ?? null,
            'markalar' => array_column($liste, 'ad'),
            'not'      => $s['not'] ?? null,
        ];
    }
    return $satirlar;
}

/** Armendus açıklaması: Tekayper teyidi gelene kadar (show_armendus_note = false) kısa cümle. */
function armendus_cumlesi(): string
{
    if (config('show_armendus_note')) {
        return "Taşıyıcı kol, pano ve kiosk sistemleri. Teknokol ürünleri artık Armendus adıyla sunuluyor; ürünler armendus.com'da.";
    }
    return "Taşıyıcı kol, pano ve kiosk sistemleri armendus.com'da.";
}

/** Tam adres tek satır */
function tam_adres(): string
{
    $a = config('address');
    return $a['line1'] . ' ' . $a['line2'] . ', ' . $a['district'] . ' / ' . $a['city'];
}

/** F2'nin sembol partial'ını bir kez yükler; sembol() tanımlıysa true */
function sembol_hazir(): bool
{
    if (function_exists('sembol')) {
        return true;
    }
    $dosya = APP . '/partials/sembol.php';
    if (is_file($dosya)) {
        ob_start();
        require_once $dosya;
        ob_end_clean();
    }
    return function_exists('sembol');
}

/**
 * Arayüz glifi (hoparlor, kapat, dis-proje, menu, indir, ara). F2'nin sembol kütüphanesinde varsa onu,
 * yoksa aynı modülde (24 birim, 1,5 px çizgi) çizilmiş yedeği basar. Hep aria-hidden.
 */
function glif(string $ad, int $boyut = 24, string $sinif = ''): string
{
    if (is_file(ROOT . '/assets/sembol/iec-' . $ad . '.svg') && sembol_hazir()) {
        return (string) sembol($ad, ['buyukluk' => $boyut, 'sinif' => trim('glif glif--' . $ad . ' ' . $sinif)]);
    }
    $yollar = [
        'hoparlor'  => '<path d="M3.75 9.75h4.5v4.5h-4.5z"/><path d="M8.25 9.75 13.5 5.25v13.5l-5.25-4.5"/>'
                     . '<path class="glif__dalga" d="M16.5 9a4.2 4.2 0 0 1 0 6"/><path class="glif__dalga" d="M18.75 6.75a7.5 7.5 0 0 1 0 10.5"/>'
                     . '<path class="glif__sessiz" d="M16.5 9.75l4.5 4.5M21 9.75l-4.5 4.5"/>',
        'kapat'     => '<path d="M6 6l12 12M18 6 6 18"/>',
        'dis-proje' => '<path d="M5.25 21V3.75"/><path d="M5.25 4.5h12l-3 4.125 3 4.125h-12"/>',
        'menu'      => '<path d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/>',
        'indir'     => '<path d="M12 3.75v11.25"/><path d="m7.5 10.5 4.5 4.5 4.5-4.5"/><path d="M4.5 19.5h15"/>',
        'ara'       => '<circle cx="10.5" cy="10.5" r="6"/><path d="m15 15 5.25 5.25"/>',
    ];
    if (!isset($yollar[$ad])) {
        return '';
    }
    return '<svg class="' . e(trim('glif glif--' . $ad . ' ' . $sinif)) . '" width="' . $boyut . '" height="' . $boyut
         . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="square" aria-hidden="true" focusable="false">'
         . $yollar[$ad] . '</svg>';
}
