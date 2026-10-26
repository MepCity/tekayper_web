<?php
/**
 * SEO yardımcıları ve JSON-LD şemaları (spec 4.7).
 * Sayfaya özel şema dizileri head partial'ına 'schema' => [...] ile verilir; schema.php hepsini yazar.
 */

/** Sayfanın kanonik adresi (sorgu dizesi olmadan) */
function kanonik(array $foy): string
{
    return tam_url($foy['dosya']);
}

/** Paylaşım görseli: assets/img/og/{id}.png varsa o, yoksa genel */
function og_gorsel(array $foy): string
{
    foreach ([$foy['id'], 'genel'] as $ad) {
        if (is_file(ROOT . '/assets/img/og/' . $ad . '.png')) {
            return tam_url('assets/img/og/' . $ad . '.png');
        }
    }
    return tam_url('apple-touch-icon.png');
}

/** Firma (LocalBusiness) şeması, her sayfada */
function schema_firma(): array
{
    $a = config('address');
    $s = [
        '@type' => 'LocalBusiness',
        '@id' => tam_url('') . '#firma',
        'name' => config('legal'),
        'alternateName' => config('name'),
        'url' => tam_url(''),
        'logo' => tam_url('assets/img/logo/tekayper-logo-plaka.svg'),
        'telephone' => config('phone.schema'),
        'email' => config('email'),
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => $a['line1'] . ' ' . $a['line2'],
            'addressLocality' => $a['district'],
            'addressRegion' => $a['city'],
            'addressCountry' => $a['country'],
        ],
        'geo' => ['@type' => 'GeoCoordinates', 'latitude' => config('geo.lat'), 'longitude' => config('geo.lng')],
        'hasMap' => config('maps'),
    ];
    // Cephe fotoğrafı işlendiyse (P2) görsel olarak eklenir
    foreach (glob(ROOT . '/assets/img/kurumsal/*cephe*.jpg') ?: [] as $yol) {
        $s['image'] = tam_url('assets/img/kurumsal/' . basename($yol));
        break;
    }
    return $s;
}

/** WebSite şeması (ana sayfa) */
function schema_site(): array
{
    return ['@type' => 'WebSite', '@id' => tam_url('') . '#site', 'name' => config('name'), 'url' => tam_url(''), 'inLanguage' => 'tr-TR',
            'publisher' => ['@id' => tam_url('') . '#firma']];
}

/** BreadcrumbList: Anasayfa > (üst föy) > föy */
function schema_breadcrumb(array $foy): ?array
{
    if ($foy['no'] <= 1) {
        return null;
    }
    $zincir = [foy('ana')];
    if (!empty($foy['ust'])) {
        $zincir[] = foy($foy['ust']);
    }
    $zincir[] = $foy;
    $ogeler = [];
    foreach ($zincir as $i => $f) {
        $ogeler[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $f['menu'] ?? $f['ad'], 'item' => kanonik($f)];
    }
    return ['@type' => 'BreadcrumbList', 'itemListElement' => $ogeler];
}

/** Sayfa türü şeması kısayolu: schema_sayfa('AboutPage', $foy) */
function schema_sayfa(string $tur, array $foy, array $ek = []): array
{
    return ['@type' => $tur, 'name' => $foy['baslik'], 'description' => $foy['aciklama'], 'url' => kanonik($foy),
            'inLanguage' => 'tr-TR', 'isPartOf' => ['@id' => tam_url('') . '#site']] + $ek;
}
