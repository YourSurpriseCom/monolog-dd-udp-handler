.DEFAULT_GOAL := help

.PHONY: help
help:
	@printf "\033[33mUsage:\033[0m\n  make [target] [arg=\"val\"...]\n\n\033[33mTargets:\033[0m\n"
	@grep -E '^[-a-zA-Z0-9_\.\/]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[32m%-15s\033[0m %s\n", $$1, $$2}'

.PHONY: install ## install dependencies
install: vendor/autoload.php

vendor/autoload.php: composer.json composer.lock
	composer install

.PHONY: check ## Run project checks
check: phpcs phpstan

.PHONY: phpcs
phpcs: vendor
	./vendor/bin/phpcs

.PHONY: phpstan
phpstan: vendor
	./vendor/bin/phpstan analyse

