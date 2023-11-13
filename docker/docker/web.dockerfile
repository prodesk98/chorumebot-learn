FROM php:8.2.9-fpm

ARG WWWGROUP

ENV DEBIAN_FRONTEND noninteractive

WORKDIR /app

RUN apt-get update -y \
    && apt-get install -y \
        busybox gnupg gosu curl ca-certificates zip unzip git supervisor sqlite3 dnsutils \
        libfreetype6-dev \
        libicu-dev \
        libjpeg-dev \
        libmagickwand-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        libcap2-bin \
        libpng-dev \
        libgmp-dev \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && apt-get update \
    && apt-get install -y nodejs yarn \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j "$(nproc)" bcmath exif gd gmp intl mysqli opcache pcntl pdo_mysql zip \
    && pecl install imagick pcntl redis swoole xdebug --with-maximum-processors="$(nproc)" \
    && docker-php-ext-enable imagick opcache redis swoole xdebug \
    && apt-get purge -y --auto-remove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*


RUN groupadd --force -g $WWWGROUP raft
RUN useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 raft

RUN chown -R raft:raft /app && chmod -R 755 /app

COPY confs/php/ini/* /usr/local/etc/php/conf.d/
COPY confs/php/fpm/* /usr/local/etc/php-fpm.d/
COPY confs/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY --chmod=777 ../confs/entrypoint.sh /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]