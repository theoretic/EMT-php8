# v3 Engine — Documented Behavior Changes vs v2

Policy: the v3 rewrite is parity-with-bug by default (golden fixtures are the
contract). Only the pre-approved broken classes below may produce different
output, each with a quarantine case in `tests/Golden/quarantine/`.

These take effect only at engine cutover (Phase 5). Until then the v2 engine
is the default and none of this is user-visible.

## 1. Marker injection (quarantine: 24-injection.txt)

v2: literal `%%%INTBLOCKO235978%%%<base64>%%%INTBLOCKC235978%%%` in input text
is decoded by `decode_internal_blocks()`, letting user input smuggle arbitrary
bytes (including `<`/`>`) past tag encoding.

v3: placeholders are out-of-band (`U+E000 class index U+E001`, raw bytes only
in the token table); literal PUA codepoints in input are escaped at ingest.
Forging is impossible; marker-looking input passes through as plain text.

## 2. Literal `<` / `>` in prose (quarantine: 19-literal-angle.txt)

v2: `a < b и b > c` — the span between `<` and `>` is treated as a tag body,
base64-encoded, trimmed; spaces are destroyed (`a <b и b> c`).

v3: `<` followed by space, digit, `-`, another `<`, or EOF is literal text and
survives byte-for-byte. `<` followed by a letter or `/` opens a tag — same as
the HTML5 tokenizer — so `x<y ... >` is still consumed as a tag by design.

## 3. `>` inside quoted attributes (quarantine: 15-html-attrs-gt.html)

v2: `<a title="a > b">` is split at the first `>`; the attribute tail leaks
into rule-visible text and gets typographed.

v3: the tag scanner is quote-aware; `>` inside `"…"`/`'…'` does not terminate
the tag.

## 4. Nested protected tags (no quarantine case; covered by LexerTest)

v2: `safe_blocks()` uses a lazy regex, so `<pre>a <pre>b</pre> c</pre>`
protects only up to the inner `</pre>`; ` c` is typographed inside the outer
block.

v3: `pre`/`notg`/user safe tags are depth-tracked and protect to the outer
close tag. `script`/`style` remain non-nesting per the HTML5 raw-text rules.
Unterminated safe tags leave content unprotected (matches v2).

## 5. Crashes / silent null from PCRE

v2 historically crashed or silently emptied output on PCRE backtrack-limit
hits and strict-type violations (several fixed in the 3a2cbb6 / 3f81cd5 / 976e2e3
series). v3 rule dispatch must check `preg_last_error()` after every pass and
report through `$errors` instead of corrupting output. Additionally, a rule
that destroys a tag placeholder raises `PlaceholderIntegrityError` instead of
silently eating markup.
