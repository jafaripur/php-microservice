FROM php:8.0-cli-alpine3.15

LABEL description="Testing Dockerfile for build in https://github.com/jafaripur/php-microservice"
LABEL org.opencontainers.image.authors="mjafaripur@yahoo.com"

RUN apk update && apk upgrade && echo "UTC" > /etc/timezone \
	&& apk add --no-cache autoconf gcc binutils binutils-dev g++ libtool make \
	libmcrypt-dev rabbitmq-c-dev ${PHPIZE_DEPS} \
	&& docker-php-source extract \
	&& docker-php-ext-install bcmath pcntl \
	&& pecl install amqp \
	&& docker-php-ext-enable amqp \
	&& docker-php-source delete \
	&& apk del bash autoconf gcc binutils binutils-dev g++ libtool make ${PHPIZE_DEPS} \
	&& mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
	&& rm -rf /var/tmp/* \
	&& rm -rf /tmp/* \
	&& rm -rf /var/cache/apk/* \
	&& rm -rf /var/lib/apt/lists/* \
	&& rm -rf /var/cache/*

WORKDIR /app

COPY --chown=app_user . .

USER app_user

COPY --chown=app_user --from=composer:latest /usr/bin/composer .

ENV COMPOSER_HOME /app/.composer

RUN rm -rf ./docker &&  mkdir ./runtime && mkdir /app/.composer/ \
	&& ./composer install --no-autoloader --no-suggest \
	&& ./composer du -o -a --no-scripts \
	&& ./composer clear-cache \
	&& rm ./composer \
	&& rm -rf /app/.composer \
	&& rm -rf /tmp/*

ENTRYPOINT ["/app/vendor/bin/phpunit"]