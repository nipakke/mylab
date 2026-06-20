# =========================================================================
# STAGE 1: CSS BUILD (Node.js)
# =========================================================================
FROM node:22-alpine AS css
WORKDIR /app
COPY package.json ./
RUN npm install --ignore-scripts
COPY assets/ assets/
COPY views/ views/
RUN npm run build:css
RUN npm install htmx.org@2.0.10 --no-save && \
    mkdir -p public/assets/js && \
    cp node_modules/htmx.org/dist/htmx.js public/assets/js/htmx.js

# =========================================================================
# STAGE 2: PRODUCTION RUNTIME (PHP 8.3 CLI Alpine)
# =========================================================================
FROM php:8.3-cli-alpine

# Enable OPcache for production performance
RUN docker-php-ext-install opcache

COPY --from=css /app/public/assets/css /app/public/assets/css
COPY --from=css /app/public/assets/js  /app/public/assets/js

# Runtime configuration
ENV APP_ENV=production \
    PORT=80 \
    RATE_LIMIT=60 \
    RATE_LIMIT_WINDOW=60

WORKDIR /app
COPY . /app

# Production PHP config: OPcache + JIT optimizations
COPY docker/php.ini /usr/local/etc/php/conf.d/99-performance.ini

# Production dependencies (no dev tools)
RUN composer install --no-dev --optimize-autoloader

EXPOSE 80

CMD ["php", "bin/server"]
