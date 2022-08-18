FROM php:8.1-cli-alpine

LABEL description="Testing Dockerfile for build in https://github.com/jafaripur/php-microservice"
LABEL org.opencontainers.image.authors="mjafaripur@yahoo.com"

RUN apk update && apk upgrade && echo "UTC" > /etc/timezone \
	&& apk add --no-cache autoconf gcc binutils binutils-dev g++ libtool make \
	libmcrypt-dev rabbitmq-c-dev ${PHPIZE_DEPS} \
	&& docker-php-source extract \
	&& docker-php-ext-install bcmath pcntl \
	&& pecl install igbinary \
	&& pecl install msgpack \
	&& pecl install amqp \
	&& docker-php-ext-enable igbinary msgpack amqp \
	&& docker-php-source delete \
	&& apk del bash autoconf gcc binutils binutils-dev g++ libtool make ${PHPIZE_DEPS} \
	&& mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
	&& rm -rf /var/tmp/* \
	&& rm -rf /tmp/* \
	&& rm -rf /var/cache/apk/* \
	&& rm -rf /var/lib/apt/lists/* \
	&& rm -rf /var/cache/* \
	&& mkdir /app

WORKDIR /app

COPY . .

COPY --from=composer:latest /usr/bin/composer .

ENV COMPOSER_HOME /app/.composer

RUN rm -rf ./docker &&  mkdir ./runtime && mkdir /app/.composer/ \
	&& ./composer validate --strict \
	&& ./composer install -o -a \
	&& ./composer clear-cache \
	&& rm ./composer \
	&& rm -rf /app/.composer \
	&& rm -rf /tmp/*

ENTRYPOINT ["/app/vendor/bin/phpunit"]