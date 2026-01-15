# syntax=docker/dockerfile:1.5.1

ARG PHP_DOCKER_IMAGE_VERSION
FROM php:8.5.0RC4-apache-trixie

RUN a2enmod rewrite
RUN pecl install xdebug-3.5.0 && docker-php-ext-enable xdebug

RUN docker-php-ext-install pdo_mysql


COPY xdebug-tune.ini /usr/local/etc/php/conf.d/
