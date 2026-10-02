# Performance benchmark baseline

Benchmark date: 2026-10-02  
Environment: GitHub-hosted Ubuntu 24.04 runner, PHP 8.4, Node v24.21.0, Chromium 153.0.8010.12.

These numbers are a **controlled local-fixture baseline**, not a prediction of public-internet throughput. They are used to choose conservative defaults and to detect regressions.

## HTTP concurrency

200 controlled local HTTP requests:

| Concurrency | Requests/s | Peak PHP memory |
| ---: | ---: | ---: |
| 1 | 5,066.99 | 6 MiB |
| 2 | 5,975.23 | 6 MiB |
| 4 | 6,095.42 | 6 MiB |
| 8 | 4,995.56 | 6 MiB |
| 16 | 9,194.55 | 6 MiB |
| 32 | 3,523.28 | 6 MiB |

The curve is intentionally noisy on a shared CI runner, but concurrency 32 clearly adds contention and is not a sensible default.

## Mixed two-host workload

With global concurrency 4 and per-host concurrency 2:

- measured throughput: 5,001.88 requests/s on the controlled fixture;
- observed per-host concurrency never exceeded 2;
- peak PHP memory: 6 MiB.

## Queue/detector workload

1,000 queue iterations:

- average enqueue: 46.7 µs;
- average reserve+complete: 125.92 µs;
- synthetic retry cycle: 0.25 ms;
- retry succeeded;
- file descriptors remained 10 → 10;
- total benchmark time: 212.94 ms.

## Browser baseline

10 isolated Playwright contexts:

- Chromium: 153.0.8010.12;
- startup: 227.06 ms;
- workload: 545.36 ms;
- average context: 54.54 ms;
- total: 794.17 ms;
- max RSS: 143,692 KiB (~140 MiB);
- user CPU: 625,985 µs;
- system CPU: 90,276 µs.

## Recommended initial safety defaults

Until production-like experiments justify increasing them:

- global HTTP worker concurrency: **8**;
- per-host concurrent requests: **2**;
- browser workers: **2** on a machine with at least ~1 GiB free memory for browser workload;
- crawl page/depth/byte/time/browser budgets remain conservative and configurable.

The HTTP choice deliberately stays below the fastest single CI observation (16) because shared-runner measurements are noisy and public hosts must not be stressed. Per-host 2 is validated directly by the mixed-host benchmark. Browser concurrency is kept low because each browser process/context workload has a materially larger memory envelope than HTTP parsing.

Re-run the benchmark suite when PHP, Symfony HttpClient, queue implementation, Playwright/Chromium, container runtime or worker architecture changes materially.
