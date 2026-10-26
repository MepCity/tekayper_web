# Tekayper önizleme (staging) imajı: Render.com ücretsiz Web Service (Docker). Canlı barındırma için değildir.
# Site PHP 8.x, çerçevesiz, ek PHP eklentisi gerektirmez (yalnızca mbstring, imajda yerleşik).
FROM php:8.3-apache

RUN a2enmod rewrite headers expires deflate mime alias \
 && sed -ri 's/Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
 && printf '<Directory /var/www/html>\n  AllowOverride All\n  Options -Indexes +FollowSymLinks\n</Directory>\nServerName localhost\nServerTokens Prod\n' > /etc/apache2/conf-available/tekayper.conf \
 && a2enconf tekayper

COPY docker/onizleme.conf docker/onizleme-sifre.conf /etc/apache2/conf-available/
COPY docker/giris.sh /usr/local/bin/giris.sh
COPY site/ /var/www/html/

# storage: talep kayıtları, e-posta günlüğü, hız sayacı. Konteyner yeniden başlayınca silinir (önizleme için yeterli).
RUN mkdir -p /var/www/html/storage/talepler /var/www/html/storage/hiz \
 && echo ok > /var/www/html/saglik.html \
 && chown -R www-data:www-data /var/www/html/storage \
 && chmod +x /usr/local/bin/giris.sh

ENV PORT=10000
EXPOSE 10000
CMD ["/usr/local/bin/giris.sh"]
