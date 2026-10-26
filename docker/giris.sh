#!/bin/sh
# Konteyner girişi: Apache'yi $PORT'ta (Render 10000 verir) başlatır; önizleme kipinde noindex/robots.txt ve (varsa) şifre dosyasını hazırlar.
set -e
export PORT="${PORT:-10000}"

if [ "${TEKAYPER_ONIZLEME:-}" = "1" ]; then
  export TEKAYPER_MAIL=log
  a2enconf onizleme >/dev/null
  printf 'User-agent: *\nDisallow: /\n' > /var/www/html/robots.txt
  # Şifre isteğe bağlı: yalnızca ONIZLEME_SIFRE doluysa Basic Auth açılır (bcrypt; düz şifre diske yazılmaz)
  if [ -n "${ONIZLEME_SIFRE:-}" ]; then
    export ONIZLEME_KULLANICI="${ONIZLEME_KULLANICI:-onizleme}"
    php -r 'echo getenv("ONIZLEME_KULLANICI") . ":" . password_hash(getenv("ONIZLEME_SIFRE"), PASSWORD_BCRYPT) . "\n";' > /etc/apache2/onizleme.htpasswd
    chown root:www-data /etc/apache2/onizleme.htpasswd && chmod 640 /etc/apache2/onizleme.htpasswd
    a2enconf onizleme-sifre >/dev/null
  fi
fi

exec apache2-foreground
