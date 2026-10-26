#!/usr/bin/env bash
# ses.sh: ses hattı (paket F2, spec 2.7 ve 4.6). Geliştirme aracı, yayına çıkmaz.
#
#   tools/ses.sh <girdi> <ad> [kisa|ortam] [-o klasor]
#
#   ad      ses adı, ses.js anahtarıyla aynı: sayfa kalem kontaktor motor ugultu fan kontak kase antet katalog
#   kisa    (varsayılan) baştaki ve sondaki sessizliği kırpar, -16 LUFS
#   ortam   döngü sesleri (kalem, ugultu, fan): kırpmaz (döngü dikişi bozulmasın), -30 LUFS
#   -o      çıktı klasörü (deneme için), varsayılan <proje>/site/assets/ses
#
# Çıktı: <klasor>/<ad>.mp3 (44,1 kHz, mono, 96 kbps, gerçek tepe -1,5 dBTP) ve <klasor>/liste.json
# (klasördeki bütün mp3'lerin listesi; ses.js yalnızca listedeki sesleri ister, eksik ses sessiz geçer).
# Ses yüksekliği EBU R128 ile ölçülür (kısa sesler döngüyle), doğrusal kazanç ve -1,5 dBTP sınırlayıcı uygulanır.
set -euo pipefail
export LC_NUMERIC=C

PROJE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CIKTI="$PROJE/site/assets/ses"
ARGS=()
while [[ $# -gt 0 ]]; do
  case "$1" in
    -o) CIKTI="$2"; shift 2 ;;
    -h|--help) sed -n '2,16p' "$0"; exit 0 ;;
    *) ARGS+=("$1"); shift ;;
  esac
done
if [[ ${#ARGS[@]} -lt 2 ]]; then
  echo "Kullanım: tools/ses.sh <girdi> <ad> [kisa|ortam] [-o klasor]" >&2
  exit 64
fi
GIRDI="${ARGS[0]}"
AD="${ARGS[1]}"
TUR="${ARGS[2]:-kisa}"
[[ -f "$GIRDI" ]] || { echo "Girdi yok: $GIRDI" >&2; exit 66; }
[[ "$AD" =~ ^[a-z0-9-]+$ ]] || { echo "Ad yalnızca küçük harf, rakam ve tire içermeli: $AD" >&2; exit 65; }
command -v ffmpeg >/dev/null || { echo "Eksik araç: ffmpeg" >&2; exit 69; }

case "$TUR" in
  kisa)  HEDEF=-16; KIRP="silenceremove=start_periods=1:start_threshold=-50dB:start_silence=0.02,areverse,silenceremove=start_periods=1:start_threshold=-50dB:start_silence=0.05,areverse," ;;
  ortam) HEDEF=-30; KIRP="" ;;
  *) echo "Tür kisa ya da ortam olmalı: $TUR" >&2; exit 64 ;;
esac

mkdir -p "$CIKTI"
GECICI="$(mktemp -d)"
trap 'rm -rf "$GECICI"' EXIT

# 1. kırp, mono 44,1 kHz ara dosya
ffmpeg -v error -y -i "$GIRDI" -af "${KIRP}aformat=channel_layouts=mono" -ar 44100 -ac 1 "$GECICI/ara.wav"

# 2. ölç: kısa seslerde EBU R128 ölçümü 400 ms bloklar ister; ses art arda çevrilerek (en az ~6 s) ölçülür
olc() {
  LC_ALL=C ffmpeg -hide_banner -nostats -stream_loop 15 -i "$1" -af ebur128 -f null - 2>&1 \
    | sed -n 's/^ *I: *\(-*[0-9.]*\) LUFS/\1/p' | tail -1
}
MI=$(olc "$GECICI/ara.wav")
if [[ -z "$MI" || "$MI" == "-70.0" ]]; then
  echo "Ses ölçülemedi (sessiz ya da boş girdi): $GIRDI" >&2; exit 65
fi
KAZANC=$(LC_ALL=C awk -v h="$HEDEF" -v m="$MI" 'BEGIN { printf "%.2f", h - m }')

# 3. doğrusal kazanç + gerçek tepe sınırlayıcı (-1,5 dBTP = 0,841) ve kodlama
ffmpeg -v error -y -i "$GECICI/ara.wav" -af "volume=${KAZANC}dB,alimiter=limit=0.841:level=false:attack=1:release=50" \
  -ar 44100 -ac 1 -c:a libmp3lame -b:a 96k -map_metadata -1 "$CIKTI/$AD.mp3"

# 4. doğrula ve listeyi yaz
SURE=$(ffprobe -v error -show_entries format=duration -of csv=p=0 "$CIKTI/$AD.mp3")
SON=$(olc "$CIKTI/$AD.mp3")
printf '%s -> %s/%s.mp3  süre %.2f s  ölçülen %s LUFS (hedef %s, girişte %s, kazanç %s dB)\n' "$GIRDI" "$CIKTI" "$AD" "$SURE" "${SON:-?}" "$HEDEF" "$MI" "$KAZANC"

{
  printf '{\n'
  ilk=1
  for f in "$CIKTI"/*.mp3; do
    [[ -e "$f" ]] || continue
    a="$(basename "$f" .mp3)"
    v=$(stat -f %m "$f" 2>/dev/null || stat -c %Y "$f")
    (( ilk )) || printf ',\n'
    printf '  "%s": "%s.mp3?v=%s"' "$a" "$a" "$v"
    ilk=0
  done
  printf '\n}\n'
} > "$CIKTI/liste.json"
echo "liste.json: $(grep -c '"' "$CIKTI/liste.json") ses"
