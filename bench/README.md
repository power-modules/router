# Router Benchmarks

This directory contains a dependency-free benchmark harness for the current `power-modules/router` implementation.

## Goals

The harness measures the current router in three areas:

- route registration cost;
- bootstrap plus first-hit cost;
- steady-state dispatch cost;
- approximate memory deltas during registration and dispatch.

It is intended to provide a reproducible baseline before the trie-based router work begins.

## Datasets

The current harness now implements the full dataset set from the trie refactor plan:

- `static-flat`
- `modular-static`
- `shared-prefix-dynamic`
- `low-shared-prefix-dynamic`
- `constrained-placeholders`
- `mixed-modules`
- `precedence`

Supported sizes:

- `small`
- `medium`
- `large`
- `xlarge`

Current route targets:

- `small`: 100 routes
- `medium`: 500 routes
- `large`: 2,500 routes
- `xlarge`: 5,000 routes

## Commands

Run a quick smoke benchmark:

```bash
make bench-quick ARGS="--dataset=shared-prefix-dynamic --size=small"
```

Run a more stable benchmark with default iterations:

```bash
make bench ARGS="--dataset=mixed-modules --size=medium"
```

Run the full repeatable benchmark matrix and write the aggregated JSON snapshot:

```bash
make bench-matrix
```

List supported datasets and sizes:

```bash
php bench/run.php --list
```

Write JSON output to a file:

```bash
php bench/run.php --dataset=precedence --size=small --output=bench/results/precedence-small.json --pretty
```

The matrix target writes `benchmark_results.json` by default. You can override the output path:

```bash
make bench-matrix BENCH_MATRIX_OUTPUT=bench/results/full-matrix.json
```

## Output

The runner emits JSON with:

- dataset metadata;
- request corpus counts;
- registration measurements;
- bootstrap-first-hit measurements;
- dispatch measurements for:
  - `hit`
  - `not-found`
  - `method-not-allowed`
  - `head-fallback`
  - `mixed`

Duration metrics are reported in nanoseconds.
Memory metrics are approximate process deltas in bytes.

## Notes

- Dispatch benchmarks pre-register the router and prime the first request so route compilation cost does not pollute steady-state dispatch results.
- Registration benchmarks reflect only explicit `registerPowerModuleRoutes()` work.
- `bootstrap-first-hit` captures the current stack's deferred route preparation cost, which is important because `league/route` builds route collector data lazily on first dispatch.
- Every dataset now emits a weighted `mixed` request corpus, combining `hit`, `not-found`, `method-not-allowed`, and `head-fallback` requests in a repeatable ratio.
- The current harness uses the existing router package directly and therefore reflects the current `league/route` plus FastRoute based behavior.