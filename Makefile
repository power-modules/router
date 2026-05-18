.PHONY: bench bench-quick bench-matrix bench-matrix-quick bench-harness-export bench-harness-import bench-capture bench-compare test codestyle phpstan devcontainer

BENCH_MATRIX_OUTPUT ?= benchmark_results.json
BENCH_MATRIX_PROFILE ?= stable
BENCH_WORKFLOW_DIR ?= ../plans/router/trie/benchmark
BENCH_CAPTURE_LABEL ?= current

bench:
	php bench/run.php --pretty $(ARGS)

bench-quick:
	php bench/run.php --pretty --iterations=1 --warmup=0 --revs=1 $(ARGS)

bench-matrix:
	php bench/run_matrix.php --pretty --profile=$(BENCH_MATRIX_PROFILE) --output=$(BENCH_MATRIX_OUTPUT)

bench-matrix-quick:
	php bench/run_matrix.php --pretty --profile=quick --output=$(BENCH_MATRIX_OUTPUT)

bench-harness-export:
	$(BENCH_WORKFLOW_DIR)/sync_harness.sh export

bench-harness-import:
	$(BENCH_WORKFLOW_DIR)/sync_harness.sh import

bench-capture:
	$(BENCH_WORKFLOW_DIR)/capture_results.sh $(BENCH_CAPTURE_LABEL)

bench-compare:
	php $(BENCH_WORKFLOW_DIR)/compare_results.php $(LEFT) $(RIGHT) $(OUTPUT)

test:
	vendor/bin/phpunit --color=always --no-coverage --display-all-issues test/

codestyle:
	vendor/bin/php-cs-fixer check --config=.php-cs-fixer.php .

phpstan:
	vendor/bin/phpstan analyse --memory-limit=4G --configuration=phpstan.neon --no-progress --no-interaction src/ test/

devcontainer:
	docker build -t power-modules-devcontainer -f DockerfileDevContainer .
