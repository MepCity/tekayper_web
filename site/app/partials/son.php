<?php
/**
 * Betikler ve </body></html>. head partial'ında seçilen GSAP eklentileri ve Lenis yalnızca o sayfada yüklenir.
 *   partial('son', ['foy' => $foy]);
 */
$s = $GLOBALS['sayfa'] ?? ['gsap' => false, 'lenis' => false, 'js' => []];
if ($s['gsap']) {
    echo '<script src="' . e(asset('js/vendor/gsap/gsap.min.js')) . '" defer></script>' . "\n";
    foreach ($s['gsap'] as $eklenti) {
        echo '<script src="' . e(asset('js/vendor/gsap/' . $eklenti . '.min.js')) . '" defer></script>' . "\n";
    }
}
if ($s['lenis']) {
    echo '<script src="' . e(asset('js/vendor/lenis.min.js')) . '" defer></script>' . "\n";
}
echo '<script type="module" src="' . e(asset('js/site.js')) . '"></script>' . "\n";
foreach ($s['js'] as $j) {
    if (is_file(ROOT . '/assets/js/' . $j . '.js')) {
        echo '<script type="module" src="' . e(asset('js/' . $j . '.js')) . '"></script>' . "\n";
    }
}
if (onizleme()) {
    partial('onizleme');
}
?>
</body>
</html>
