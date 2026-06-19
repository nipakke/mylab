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

# =========================================================================
# STAGE 2: CADDY BUILDER (FrankenPHP with custom modules)
# =========================================================================
FROM lucaslorentz/caddy-docker-proxy:ci-alpine AS caddy-builder
RUN xcaddy build \
    --with github.com/tailscale/caddy-tailscale \
    --with github.com/caddyserver/nginx-adapter

# =========================================================================
# STAGE 3: PRODUCTION SERVER RUNTIME
# =========================================================================
FROM dunglas/frankenphp:latest-alpine

COPY --from=caddy-builder /usr/bin/caddy /usr/bin/frankenphp
COPY --from=css /app/public/assets/css /app/public/assets/css

# Runtime configuration (override at container start)
ENV APP_ENV=production \
    RATE_LIMIT=60 \
    RATE_LIMIT_WINDOW=60 \
    TRUST_PROXY=auto \
    PROXY_IP_HEADER=X-Forwarded-For
WORKDIR /app
COPY . /app

# Production PHP config: OPcache + JIT optimizations
COPY docker/php.ini /usr/local/etc/php/conf.d/99-performance.ini

# Run vendor clean updates
RUN composer install --no-dev --optimize-autoloader
