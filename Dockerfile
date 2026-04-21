FROM php:8.0-apache

# Install PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libwebp-dev libpq-dev zip unzip curl \
    && docker-php-ext-install pdo pdo_pgsql gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*



# Enable Apache mod_rewrite
RUN a2enmod rewrite headers

# Set document root
ENV APACHE_DOCUMENT_ROOT /var/www/html

# Copy app
COPY . /var/www/html/



# Create uploads dir (temp, real storage is R2)
RUN mkdir -p /var/www/html/uploads && chmod 777 /var/www/html/uploads

# Apache config
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/app.conf \
    && a2enconf app

# Set permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
