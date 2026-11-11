FROM php:8.2-apache

RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli \
    && a2enmod headers rewrite expires deflate \
    && sed -ri 's#/var/www/html#/var/www/html/public#g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's#/var/www/#/var/www/html/public/#g' /etc/apache2/apache2.conf \
    && sed -ri '/<Directory \/var\/www\/html\/public\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
    && printf 'ServerTokens Prod\nServerSignature Off\n' >> /etc/apache2/conf-available/security.conf \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'expose_php=Off\ndisplay_errors=Off\nlog_errors=On\nsession.cookie_httponly=1\nsession.use_strict_mode=1\n' > "$PHP_INI_DIR/conf.d/zz-hardening.ini"

WORKDIR /var/www/html
COPY . /var/www/html
