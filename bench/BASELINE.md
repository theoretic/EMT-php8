# Engine Baselines

## v3 engine (default since Phase 5 cutover), PHP 8.5.4, Windows 11, 2026-08-08

| case | bytes | min ms | median ms | p90 ms | vs v2 min |
|---|---|---|---|---|---|
| 02-plain-prose.txt | 2733 | 1.674 | 1.863 | 2.419 | -20% |
| 14-html-fragment.html | 922 | 0.872 | 0.922 | 1.226 | -34% |
| 22-medium-15k.html | 15166 | 9.182 | 10.189 | 11.356 | par |
| 23-large-100k.html | 100208 | 55.513 | 62.049 | 74.684 | -34% |

Peak memory: 10.0 MB (v2: 22.0 MB).

Wins so far come from removing the eval/base64 layers, one-shot placeholder
integrity validation, and static rule-table memoization. The Phase 6 levers
(strpos gates, dictionary merges) have not been applied yet — the 2x target
on the 15KB case is Phase 6 work.

# v2 Engine Baseline (historical reference)

Recorded with `php bench/bench.php` (median of 30 runs, warmup excluded) against
the committed golden corpus. These are the numbers the v3 engine is measured
against; target is **>=2x on 22-medium-15k.html**, stretch 3x.

## PHP 8.5.4, Windows 11 (dev machine, 2026-08-08)

| case | bytes | min ms | median ms | p90 ms |
|---|---|---|---|---|
| 02-plain-prose.txt | 2733 | 2.083 | 2.228 | 2.661 |
| 14-html-fragment.html | 922 | 1.330 | 1.471 | 1.690 |
| 22-medium-15k.html | 15166 | 9.136 | 9.491 | 10.776 |
| 23-large-100k.html | 100208 | 84.384 | 86.078 | 87.824 |

Peak memory: 22.0 MB.

Observations:

- Scaling is **superlinear**: 6.6x input (15KB -> 100KB) costs 9x time. The
  per-rule full-text `preg_replace` passes (~154 per apply) dominate and each
  pass reallocates the whole string.
- Small inputs pay a near-constant ~1.1-1.3 ms floor: object construction,
  rule-table build and 51 `eval()` closure compilations per `fast_apply()`.

Regenerate this table when the environment or engine changes; keep old rows if
adding a new environment (append a new section rather than overwriting).
