FROM php:8.2.24-apache

RUN a2enmod rewrite && \
    docker-php-ext-install mysqli pdo pdo_mysql && \
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"  && \
    php composer-setup.php  && \
    php -r "unlink('composer-setup.php');"  && \
    mv composer.phar /usr/local/bin/composer && \
    apt update && install zip -y

EXPOSE 80