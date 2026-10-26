<?php
/**
 * "Önizleme sürümü" bandı (yalnızca TEKAYPER_ONIZLEME=1; son.php çağırır). Alt kenara asılı küçük bir plaka:
 * mürekkep zemin, kâğıt yazı, Osifont, antet hücresi dili. Tıklamayı engellemez (pointer-events: none).
 */
?>
<p class="onizleme" role="note">Önizleme sürümü · yayında değil</p>
<style>
.onizleme {
  position: fixed;
  right: calc(var(--c-sag) + 16px);
  bottom: 0;
  z-index: calc(var(--z-ust) + 1);
  margin: 0;
  padding: 4px 10px 3px;
  background: var(--murekkep);
  color: var(--kagit);
  border: var(--cz-ince) solid var(--murekkep);
  border-bottom: 0;
  font-family: var(--f-cizim);
  font-size: var(--t-xs);
  line-height: 1.2;
  letter-spacing: 0;
  white-space: nowrap;
  pointer-events: none;
}
.onizleme::before { content: ""; display: inline-block; width: 6px; height: 6px; margin-right: 8px; background: var(--isaret); vertical-align: 1px; }
@media (max-width: 767.98px) { .onizleme { right: calc(var(--c-sag) + 8px); } }
@media print { .onizleme { display: none; } }
</style>
