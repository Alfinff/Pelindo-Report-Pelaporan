FROM ricosetiawan227/bemkm-api:1.1

ENV ACCEPT_EULA=Y

# PHP_CPPFLAGS are used by the docker-php-ext-* scripts
ENV PHP_CPPFLAGS="$PHP_CPPFLAGS -std=c++11"

# Microsoft SQL Server Prerequisites
RUN docker-php-ext-install calendar
RUN apt-get install -y \
        libzip-dev \
        zip \
  && docker-php-ext-install zip

COPY nginx-site.conf /etc/nginx/sites-enabled/default
COPY entrypoint.sh /etc/entrypoint.sh

RUN rm -r /var/www/html
COPY --chown=www-data:www-data . /var/www/html

WORKDIR /var/www/html

EXPOSE 80

ENTRYPOINT ["sh", "/etc/entrypoint.sh"]
