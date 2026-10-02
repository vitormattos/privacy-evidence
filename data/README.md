# Research data layout

- `raw/`: acquired source artifacts; ignored by default.
- `restricted/`: artifacts requiring restricted handling; ignored by default.
- `derived/`: local derived datasets; ignored by default.
- `exports/`: generated exports; ignored by default.

Deterministic test fixtures belong under `tests/Fixtures/`, not here.
