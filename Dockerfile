FROM php:8.4-cli

RUN apt-get update \
 && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libwebp-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install gd \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . .
RUN mkdir -p uploads output inbox local/inbox local/out \
 && chmod +x start.sh

ENV PORT=8080
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
