.PHONY: run build test lint clean

run:
	APP_ENV=local php src/server.php

build:
	composer install --no-dev --optimize-autoloader

test:
	./vendor/bin/pest

lint:
	php -l src/server.php

clean:
	rm -rf cache/twig
