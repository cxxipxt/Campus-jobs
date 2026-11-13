FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install mbstring pdo_mysql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY app/ /var/www/app/
COPY frontend/ /var/www/frontend/

RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/frontend|' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/frontend>\n    AllowOverride FileInfo AuthConfig Indexes\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/campusjobs.conf \
    && a2enconf campusjobs

WORKDIR /var/www/frontend
