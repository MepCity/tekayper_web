<?php
/**
 * JSON-LD: firma (LocalBusiness) ve site (WebSite) her sayfada, alt sayfalarda BreadcrumbList,
 * ardından head partial'ına verilen sayfaya özel şemalar ($ek).
 */
$graf = [schema_firma(), schema_site()];
if ($bc = schema_breadcrumb($foy)) {
    $graf[] = $bc;
}
foreach ($ek ?? [] as $s) {
    if (is_array($s) && $s) {
        $graf[] = $s;
    }
}
$json = json_encode(['@context' => 'https://schema.org', '@graph' => $graf],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
?>
<script type="application/ld+json"><?= $json ?></script>
