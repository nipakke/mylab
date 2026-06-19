.PHONY: run build test lint format clean css

run:
	APP_ENV=local php bin/server

build:
	composer install --no-dev --optimize-autoloader

test:
	./vendor/bin/pest

lint:
	composer lint
format:
	composer format

css:
	npm run build:css

clean:
	rm -rf cache/twig
