.PHONY: bench bench-quick bench-matrix test codestyle phpstan devcontainer

BENCH_MATRIX_OUTPUT ?= benchmark_results.json

bench:
	php bench/run.php --pretty $(ARGS)

bench-quick:
	php bench/run.php --pretty --iterations=1 --warmup=0 --revs=1 $(ARGS)

bench-matrix:
	php bench/run_matrix.php --pretty --output=$(BENCH_MATRIX_OUTPUT)

test:
	vendor/bin/phpunit --color=always --no-coverage test/

codestyle:
	vendor/bin/php-cs-fixer check --config=.php-cs-fixer.php .

phpstan:
	vendor/bin/phpstan analyse --memory-limit=4G --configuration=phpstan.neon --no-progress --no-interaction src/ test/

devcontainer:
	docker build -t power-modules-devcontainer -f DockerfileDevContainer .
