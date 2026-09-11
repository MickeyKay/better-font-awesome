# Classic and hybrid picker search verification

This focused follow-up to PR #69 tests filtering independently of catalog population. Runtime/package bytes remain those accepted at BFA `a1824835d76bb3c20b7ffea3f7635a35ba745641`, including the unchanged BFAL development pin. No production search or rendering change is included.

## Corrected test contract

`tests/e2e/helpers/picker-search.mjs` drives the existing picker with real keyboard events, including keyup. It establishes that matching Pro Fixture appearances and unrelated GitHub are visible before searching, then checks:

1. `pro fixture`: matching appearances remain; GitHub disappears.
2. `github`: GitHub returns; Pro Fixture disappears.
3. Clearing with keyboard selection and Backspace: both return.
4. Returning to `pro fixture`: the matching appearances return and GitHub disappears again, before insertion.

The existing Classic and hybrid workflows both exercise this helper. Family coverage checks Duotone, Sharp Duotone and Utility results, then inserts Duotone through the existing media integration. Timing attachments separate opening/initialization from each keyboard query, including replacement/clearing. They are observations, not performance thresholds.

Affected local verification on the synthetic site, port 55481:

- `WP_ENV_PORT=55481 node_modules/.bin/playwright test tests/e2e/pro-kit.spec.mjs --grep 'bounded Pro Connect|family appearances'`: 2 passed, covering four editor/workflow combinations.
- `npm run lint:js`: passed. Both affected modules additionally passed ESLint recommended rules with browser/Node/WordPress globals.
- Negative control: a temporary browser capture listener stopped propagation of keyup from `.iconpicker-search`, leaving typed input and the populated catalog intact. The same helper failed at the GitHub `toBeHidden()` assertion in all four combinations. No mutation was retained in production or the committed tests.

The original `fill()` checks did not exercise keyup filtering. Their fast subsequent-search timings cannot establish real filtering latency. This corrects the timing interpretation, not the accepted installed glyph/delivery evidence.

## Separate runtime finding: hyphenated slugs

Actual keyboard input reproduced an existing adapter/widget mismatch in Classic and hybrid. With Pro Fixture initially visible, typing `pro-fixture` hides it; typing its displayed name, `pro fixture`, finds it. The generated item has title `Pro Fixture (thin)` and search terms `p r o - f i x t u r e `.

BFA/BFAL supply string search terms, while the bundled widget loops over terms as an array. Slugs whose punctuation differs from the displayed label therefore fail this search path. The strengthened positive tests use the displayed name; they do not claim slug matching works. No runtime fix is bundled here. A separate narrow fix should normalize terms at the widget adapter boundary and cover slug/alias matching without changing the public catalog contract.

## Bounded delay diagnosis

Browser method timings and Chromium performance counters were aggregated without retaining sensitive traces or proprietary assets. No account settings, credentials, catalog or saved content were changed. Synthetic comparisons used port 55481; a read-only visit to each installed editor counted real fonts and measured opening/reopening. The original acceptance run was not repeated.

| Installed editor | First opening | Reopening | Keyboard `camera` after reopening |
| --- | --- | --- | --- |
| Classic | 5.33 s | 1.46 s | 1.47 s |
| Hybrid | 7.21 s | 2.26 s | 1.78 s |

Each constructed the picker once, with 45,944 items. First-opening layout/style time was 3.22 s Classic and 4.54 s hybrid. Script time was 1.51 s and 1.36 s. The class formatter alone consumed approximately 1.07 s and 0.91 s: it linearly searches the catalog for every constructed item. Timings are instrumented single-session observations; nested method timings must not be added to total browser timings.

Reopening caused no new font requests or construction. Layout/style accounted for 1.09 s Classic and 1.89 s hybrid. Placement measurement was costly. A built-in 300 ms show/hide timer also exists, but it does not explain the multi-second main-thread costs.

Real font counts, across all font hosts in each editor visit:

| Editor | Requests | Distinct URLs | Additional-document loads | Repeated same URL within one document | Cache responses |
| --- | --- | --- | --- | --- | --- |
| Classic | 399 | 397 | 2 | 0 | 3 |
| Hybrid | 797 | 400 | 397 | 0 | 398 |

Positive-transfer responses were 396 and 399, about 6.87 MB and 7.36 MB respectively. Opening the full picker causes substantial font demand. Additional document loads, mostly cached in hybrid, are not repeated initialization or repeated loads within the same document. The original 794-request aggregate did not preserve URL identity, so its exact duplicate count cannot be reconstructed; the new samples establish the distinction rather than relabeling all 794 as redundant.

Synthetic comparisons, with browser-only catalog fixtures and no remote fonts:

- 500 items: initial opening 0.34-0.47 s; clearing a filtered query 19-23 ms.
- 16,000 items: initial opening 0.73-0.91 s; first keyboard query 0.28-0.34 s; clearing 3.72-3.73 s.
- Clearing 16,000 items caused about 15,997 style recalculations, consuming 3.50-3.53 s. A 45,944-item clearing probe exceeded the 30-second bound and was stopped; this is a lower bound, not a completed timing.
- Delaying one synthetic public font by 1.5 s left 16,000-item opening at 0.74 s and clearing at 3.77 s, but added 1.37 s to subsequent font readiness. Fonts can delay glyph readiness; they are not necessary to reproduce the expensive clearing path.

The dominant observed costs are DOM layout/style work, including restoring hidden results, plus a smaller quadratic class-lookup cost during first construction. This is not evidence of repeated initialization. Font delivery contributes bandwidth and potentially cold glyph latency, but does not explain slow warm reopens.

Smallest evidence-supported follow-ups, without a rendering/catalog redesign: use a title lookup prepared once instead of repeated linear searches (targets roughly one second of first-open work here), and investigate batched visibility updates that avoid per-item computed-style reads when restoring results. Neither is implemented or claimed to eliminate the remaining full-DOM placement cost. Any BFAL change belongs in its own repository and review.

## Acceptance continuity

All 57 production package files still match the accepted installed candidate manifest. Tests and this document are excluded by the existing package selection. The installed `a182483` real-font acceptance remains applicable to the surfaces/styles actually tested. Connection, catalog count, default, WP-Cron setting and original content hashes were unchanged after the read-only diagnosis. Port 55480 was not used.

Reuse the accepted head's compatibility/editor coverage for unchanged runtime behavior; hosted CI on the test-only update provides the current test evidence. Existing development-pin publication gates remain unchanged. No version bump, BFAL modification, merge or publication is included.
