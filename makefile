test:
	ddev exec php bin/phpunit

stan:
	ddev exec vendor/bin/phpstan analyse

cs-check:
	ddev exec vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix:
	ddev exec vendor/bin/php-cs-fixer fix

coverage:
	ddev exec bash -c "XDEBUG_MODE=coverage php bin/phpunit --coverage-text"

console:
	ddev exec php bin/console
