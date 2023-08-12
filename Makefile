start:
	php -S localhost:8080 -t public public/index.php
install:
	composer install
validate:
	composer validate
lint:
	composer exec --verbose phpcs -- --standard=PSR12 public