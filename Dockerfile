FROM php:7-apache

ENV COMPOSER_ALLOW_SUPERUSER 1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
    && rm -rf /var/lib/apt/lists/*

RUN cd /root/ \
  	&& curl -sS https://getcomposer.org/installer | php \
  	&& ln -s /root/composer.phar /usr/local/bin/composer \
	&& a2enmod rewrite \
	&& a2dissite 000-default

WORKDIR /code/

COPY . /code/
COPY ./site.conf /etc/apache2/sites-available/site.conf
RUN a2ensite site.conf \
	&& composer install --no-interaction \
	&& chmod -R a+rwx /code/var/
