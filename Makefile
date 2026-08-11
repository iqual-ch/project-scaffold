.PHONY: install test test-unit test-integration test-functional phpstan

install:
	ddev composer install

test:
	ddev composer test

test-unit:
	ddev composer test:unit

test-integration:
	ddev composer test:integration

test-functional:
	ddev composer test:functional

phpstan:
	ddev composer phpstan
