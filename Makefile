.PHONY: all install test test-unit test-integration test-functional phpstan simulate simulate-rerun simulate-clean

SIMULATION_DIR = tmp/simulation
SIMULATION_FIXTURE = sut-questions
SIMULATION_PRESETS = multilingual=yes
SIMULATION_ENV = COMPOSER_HOME=.composer-home COMPOSER_CACHE_DIR=.composer-home/cache

all: install test phpstan

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

simulate:
	ddev exec php tests/bin/simulate-prepare.php $(SIMULATION_FIXTURE) $(SIMULATION_PRESETS)
	ddev exec -d /var/www/html/$(SIMULATION_DIR) "$(SIMULATION_ENV) PROJECT_SCAFFOLD_AUTO=0 composer install"
	ddev exec -d /var/www/html/$(SIMULATION_DIR) "$(SIMULATION_ENV) composer project:init"

simulate-rerun:
	ddev exec -d /var/www/html/$(SIMULATION_DIR) "$(SIMULATION_ENV) composer project:init"

simulate-clean:
	rm -rf $(SIMULATION_DIR)
