.PHONY: run build test lint clean

run:
	APP_ENV=local php apps/backend/src/server.php

build:
	cd apps/backend && composer install --no-dev --optimize-autoloader

test:
	cd apps/backend && ./vendor/bin/pest

lint:
	cd apps/backend && php -l src/server.php && php -l src/DTO/Container.php

clean:
	rm -rf apps/backend/cache/twig
