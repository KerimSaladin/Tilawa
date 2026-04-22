FROM php:8.2-apache

# Install PostgreSQL + PHP extensions + supervisor
RUN apt-get update && apt-get install -y \
    postgresql postgresql-client libpq-dev \
    libpng-dev libjpeg-dev libwebp-dev \
    supervisor curl zip unzip \
    && docker-php-ext-install pdo pdo_pgsql gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Apache
RUN a2enmod rewrite headers
ENV APACHE_DOCUMENT_ROOT=/var/www/html

# App
COPY . /var/www/html/
RUN mkdir -p /var/www/html/uploads && chown -R www-data:www-data /var/www/html

# Apache site config
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/app.conf && a2enconf app

# PostgreSQL data dir
RUN mkdir -p /var/lib/postgresql/data && chown postgres:postgres /var/lib/postgresql/data

# Supervisor config
RUN mkdir -p /var/log/supervisor
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Entrypoint
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80
CMD ["/entrypoint.sh"]
