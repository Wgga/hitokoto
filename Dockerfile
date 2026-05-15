FROM php:8.3-apache

WORKDIR /var/www/html

COPY index.php ./
COPY sentences ./sentences

RUN a2enmod headers
