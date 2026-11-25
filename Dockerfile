# Image PHP 8.2 + Apache pour les ateliers sécurité (inclusion de fichier + téléversement)
FROM php:8.2-apache

# Configuration PHP durcie + limites de téléversement (laboratoire)
COPY config/php-security.ini /usr/local/etc/php/conf.d/zz-lab-security.ini

# Application complète (la racine web est public/)
COPY . /var/www/html/

# Vhost + protections Apache (DocumentRoot = public/, dossiers sensibles interdits)
COPY config/apache-security.conf /etc/apache2/conf-available/lab-security.conf

RUN a2dissite 000-default \
    && a2enconf lab-security \
    && mkdir -p /var/www/html/storage /var/www/html/public/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 770 /var/www/html/storage /var/www/html/public/uploads

EXPOSE 80

CMD ["apache2-foreground"]
