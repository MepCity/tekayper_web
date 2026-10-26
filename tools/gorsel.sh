#!/usr/bin/env bash
# gorsel.sh: görsel hattı (paket F2, spec 4.6). Geliştirme aracı, yayına çıkmaz.
#
#   tools/gorsel.sh <girdi> <ad> [genislikler] [-k klasor] [-o kok] [--kahraman]
#
#   genislikler  boşlukla ya da virgülle, varsayılan "480 960 1440" (--kahraman ile 1920 eklenir)
#   -k klasor    site/assets/img altındaki klasör, varsayılan "urun"
#   -o kok       çıktı kökü (deneme için), varsayılan <proje>/site/assets/img
#
# Çıktı: <kok>/<klasor>/<ad>-<w>.{avif,webp,jpg}
# Kural: hiçbir zaman büyütmez. Kaynaktan geniş istenen boylar atlanır, yerine kaynağın kendi genişliği
# bir kez üretilir. AVIF: avifenc -q 52 -s 6. WebP: cwebp -q 80 -m 6. JPEG: ffmpeg -q:v 3.
# Ürün fotoğrafları beyaz zeminde, sitede multiply ile basılır: saydamlık kanalı üretilmez, meta veri silinir.
set -euo pipefail

PROJE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
KLASOR="urun"
KOK="$PROJE/site/assets/img"
KAHRAMAN=0
ARGS=()
while [[ $# -gt 0 ]]; do
  case "$1" in
    -k) KLASOR="$2"; shift 2 ;;
    -o) KOK="$2"; shift 2 ;;
    --kahraman) KAHRAMAN=1; shift ;;
    -h|--help) sed -n '2,15p' "$0"; exit 0 ;;
    *) ARGS+=("$1"); shift ;;
  esac
done
if [[ ${#ARGS[@]} -lt 2 ]]; then
  echo "Kullanım: tools/gorsel.sh <girdi> <ad> [genislikler] [-k klasor] [-o kok] [--kahraman]" >&2
  exit 64
fi
GIRDI="${ARGS[0]}"
AD="${ARGS[1]}"
GENISLIKLER="${ARGS[2]:-480 960 1440}"
GENISLIKLER="${GENISLIKLER//,/ }"
[[ $KAHRAMAN -eq 1 ]] && GENISLIKLER="$GENISLIKLER 1920"

for arac in ffmpeg ffprobe avifenc cwebp; do
  command -v "$arac" >/dev/null || { echo "Eksik araç: $arac" >&2; exit 69; }
done
[[ -f "$GIRDI" ]] || { echo "Girdi yok: $GIRDI" >&2; exit 66; }
[[ "$AD" =~ ^[a-z0-9-]+$ ]] || { echo "Ad yalnızca küçük harf, rakam ve tire içermeli: $AD" >&2; exit 65; }

KAYNAK_W=$(ffprobe -v error -select_streams v:0 -show_entries stream=width -of csv=p=0 "$GIRDI")
KAYNAK_H=$(ffprobe -v error -select_streams v:0 -show_entries stream=height -of csv=p=0 "$GIRDI")
CIKTI="$KOK/$KLASOR"
mkdir -p "$CIKTI"
GECICI="$(mktemp -d)"
trap 'rm -rf "$GECICI"' EXIT

# büyütme yok: istenen boyları süz, atlanan varsa kaynağın kendi genişliğini ekle
SECILEN=()
ATLANAN=0
for w in $(echo "$GENISLIKLER" | tr ' ' '\n' | sort -n | uniq); do
  if (( w <= KAYNAK_W )); then SECILEN+=("$w"); else ATLANAN=1; fi
done
if (( ATLANAN )); then
  var=0; for w in "${SECILEN[@]:-}"; do [[ "$w" == "$KAYNAK_W" ]] && var=1; done
  (( var )) || SECILEN+=("$KAYNAK_W")
fi

echo "$GIRDI (${KAYNAK_W}x${KAYNAK_H}) -> $CIKTI/$AD-<w>.{avif,webp,jpg}"
for w in "${SECILEN[@]}"; do
  ara="$GECICI/$AD-$w.png"
  if (( w == KAYNAK_W )); then
    ffmpeg -v error -y -i "$GIRDI" -map_metadata -1 -pix_fmt rgb24 "$ara"
  else
    ffmpeg -v error -y -i "$GIRDI" -map_metadata -1 -vf "scale=${w}:-2:flags=lanczos" -pix_fmt rgb24 "$ara"
  fi
  avifenc -q 52 -s 6 --ignore-exif --ignore-xmp "$ara" "$CIKTI/$AD-$w.avif" >/dev/null
  cwebp -quiet -q 80 -m 6 -metadata none "$ara" -o "$CIKTI/$AD-$w.webp"
  ffmpeg -v error -y -i "$ara" -q:v 3 -map_metadata -1 "$CIKTI/$AD-$w.jpg"
  h=$(ffprobe -v error -select_streams v:0 -show_entries stream=height -of csv=p=0 "$CIKTI/$AD-$w.jpg")
  printf '  %4dx%-4d  avif %6s  webp %6s  jpg %6s\n' "$w" "$h" \
    "$(du -k "$CIKTI/$AD-$w.avif" | cut -f1)K" "$(du -k "$CIKTI/$AD-$w.webp" | cut -f1)K" "$(du -k "$CIKTI/$AD-$w.jpg" | cut -f1)K"
done
(( ATLANAN )) && echo "  not: kaynaktan geniş boylar atlandı (büyütme yok), kaynak genişliği ${KAYNAK_W} üretildi."
exit 0
