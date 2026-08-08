# Engine Baselines

## v3 engine (default), after Phase 6 gates — PHP 8.5.4, Windows 11, 2026-08-08

Same-machine A/B via `EMT_ENGINE`, min of 30:

| case | bytes | v2 min ms | v3 min ms | speedup |
|---|---|---|---|---|
| 02-plain-prose.txt | 2733 | 2.083 | 1.494 | 1.39x |
| 14-html-fragment.html | 922 | 1.377 | 0.773 | 1.78x |
| 22-medium-15k.html | 15166 | 9.383 | 8.212 | 1.14x |
| 23-large-100k.html | 100208 | 86.117 | 50.713 | 1.70x |
| sparse-15k.txt (bench-only) | 26890 | 11.334 | 9.731 | 1.16x |

Peak memory: 10.0 MB (v2: 22.0 MB). v2's superlinear scaling is gone —
100KB now costs ~6x the 15KB case for 6.6x the input.

Where the wins came from: no eval/base64 escape layers, one-shot placeholder
integrity validation, static rule-table memoization, single-pass entity
normalization, and ~45 pattern-implied literal gates (Gate::any/anyCI/digits)
that skip preg passes when a rule's mandatory literal is absent.

Honest note on the original 2x/15KB target: the per-rule profile is flat
(bench/profile_rules.php — no rule exceeds ~6%), and the remaining cost is
intrinsic preg passes by Cyrillic-prose rules (prepositions, surnames,
particles) whose patterns imply no gateable literal. Getting past ~1.2x on
feature-dense Russian text requires merging rule passes or per-token
application — a semantic-risk change deliberately deferred (v4 candidate)
because byte-parity is the project's core contract.

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
