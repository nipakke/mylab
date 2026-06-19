# =========================================================================
# STAGE 1: ASSET COMPILE RUNTIME (No Node modules required)
# =========================================================================
FROM lucaslorentz/caddy-docker-proxy:ci-alpine AS builder
RUN xcaddy build \
    --with github.com/tailscale/caddy-tailscale \
    --with github.com/caddyserver/nginx-adapter \
    --with github.com/orefalo/caddy-tailwind

# =========================================================================
# STAGE 2: PRODUCTION SERVER RUNTIME
# =========================================================================
FROM dunglas/frankenphp:latest-alpine

COPY --from=builder /usr/bin/caddy /usr/bin/frankenphp

ENV APP_ENV=production
WORKDIR /app
COPY . /app

# Run vendor clean updates
RUN composer install --no-dev --optimize-autoloader

# Go binary optimization: Generates minimized deployment assets with zero JS tools
RUN frankenphp tailwind --input /app/src/views/layout.twig --output /app/public/assets/css/app.min.css --minify
