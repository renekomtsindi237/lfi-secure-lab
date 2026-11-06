# Image PHP + Apache pour le devoir anti-LFI
FROM php:8.2-apache

# Fichiers de l'application dans la racine web
COPY index.php /var/www/html/
COPY includes/ /var/www/html/includes/
COPY source/ /var/www/html/source/

# Droits corrects pour Apache
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
