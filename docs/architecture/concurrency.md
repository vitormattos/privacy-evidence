# Bounded worker and scheduling architecture

Privacy Evidence separates lightweight HTTP acquisition from heavyweight browser execution.

## Queue stages

- `fetch`: HTTP acquisition, persistence, link discovery and static detector execution.
- `browser`: Playwright rendering/instrumentation and detector execution over the rendered observation.

A fetch worker **enqueues** browser work; it does not launch Chromium inline.

## Worker pools

The CLI exposes recyclable workers:

```bash
bin/privacy-evidence worker RUN_ID fetch --max-jobs=100
bin/privacy-evidence worker RUN_ID browser --max-jobs=25
```

and bounded process pools:

```bash
bin/privacy-evidence workers RUN_ID fetch --workers=8 --per-host-concurrency=2 --min-host-delay-ms=250
bin/privacy-evidence workers RUN_ID browser --workers=2 --per-host-concurrency=1 --min-host-delay-ms=250
```

The number of processes is the global concurrency ceiling for that stage. Browser and HTTP pools therefore cannot consume each other's worker slots.

## Queue safety

SQLite queue reservations use an immediate transaction and conditional pending→running transition. Jobs have:

- stable deduplication keys;
- priority;
- host identity;
- enqueue/reservation timestamps;
- attempt count;
- retry availability time;
- dead-letter state after maximum attempts.

The queue applies bounded capacity/backpressure and exponential retry delay. A resumed interrupted run requeues jobs that were left in `running`.

## Host fairness and politeness

Reservations enforce both:
- simultaneous per-host job ceiling;
- minimum start delay for the same host.

Candidates that cannot currently run do not block eligible work for other hosts. Privacy-relevant crawl candidates carry higher priority, but equal-priority work is ordered by enqueue time/id.

## Worker recycling

`--max-jobs` bounds each worker lifetime. Pools create a new wave as pending work remains, limiting long-lived PHP/browser process growth.

## Baseline defaults

The initial defaults come from the controlled 2026-10-02 benchmark. See `docs/development/performance.md`.

These defaults are safety limits, not claims of optimal public-internet throughput. ResearchRun records the active scheduler/budget configuration.
