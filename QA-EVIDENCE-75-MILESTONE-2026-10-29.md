# 75% Learning Milestone — WordPress Student Course Access QA

**Verified: 22 PASS / 0 FAIL in a real WordPress Playground instance** on 2026-10-29.
**Measured scope:** WordPress user accounts + WooCommerce order lifecycle + local course access + refund revocation. The percentage is the project's own training milestone, not a standardized competency score.

- Browser record: https://agent.tinyfish.ai/runs/ebb31c4d-80be-4ba8-91a6-8c3fc15f70d7
- Report timestamp: 2026-10-29T14:07:41+00:00 (as displayed in the real WordPress admin report).
- Test runner: Tools > LMS Lab Student Access QA.
- Result: **22 passed, 0 failed**.
- Product ID: 11; synthetic WordPress learner user ID: 3.
- Synthetic WooCommerce order IDs: 14, 15, 17, 18.
- WooCommerce refund IDs: 19, 20; **no real money or payment gateway**.
- Tested Blueprint: [Launch a separate disposable WordPress test instance](https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fyeasin809040-collab%2Flms-learning-lab%2Fmain%2Fplayground%2Fblueprint-v3-student-access.json).

## Exact observed tests

| # | Test | Result |
|---|---|---|
| 1 | Real WooCommerce, audit bridge and course access plugin loaded | PASS |
| 2 | Virtual course product SKU mapped | PASS |
| 3 | Pending order cannot grant access | PASS |
| 4 | Unmarked real-customer order cannot create test student | PASS |
| 5 | Non-course SKU cannot create student | PASS |
| 6 | Completed order creates WordPress learner | PASS |
| 7 | Course entitlement granted in WordPress user metadata | PASS |
| 8 | Protected lesson visible to enrolled learner | PASS |
| 9 | Guest blocked from protected lesson | PASS |
| 10 | Unrelated WordPress account blocked from protected lesson | PASS |
| 11 | Duplicate order-completion event does not double-grant | PASS |
| 12 | Existing paid audit hook retained | PASS |
| 13 | Second purchase reuses existing learner account | PASS |
| 14 | Two course orders create independent entitlements | PASS |
| 15 | First refunded order's entitlement revoked | PASS |
| 16 | Second active order preserves access after first refund | PASS |
| 17 | First refund replay doesn't duplicate revocation | PASS |
| 18 | Second refund revokes the last active entitlement | PASS |
| 19 | After both refunds learner loses course access | PASS |
| 20 | Second refund replay is idempotent | PASS |
| 21 | Stale completion event cannot restore refunded access | PASS |
| 22 | Protected WordPress lesson page created and published | PASS |

The earlier [12/12 WooCommerce audit-only checks](./QA-EVIDENCE-2026-10-29.md) passed in a previous separate WordPress browser run. Their re-check **could not be inspected in the new run** because the Playground iframe became unresponsive during secondary navigation (`net::ERR_FAILED`). Do not claim "34/34 verified in one run"; it was **12/12 previously + 22/22 newly observed**, in separate runs.

## Honest technical limitations

- This is **real WordPress and WooCommerce database activity**, including synthetic account creation, order status events, refunds, metadata entitlements, and protected page rendering.
- The Student Access QA tests rendered the protected shortcode under different WordPress current-user contexts. **Actual browser login/logout flows were not tested**.
- The new student access plugin is **test-only**: it accepts only orders explicitly tagged `_llqa_synthetic_test=yes` whose billing address ends in `@example.com`. Normal customers never receive access from this plugin.
- No external TalentLMS instance or API was accessed. This is WordPress-local course access, **not TalentLMS enrollment**.
- No real payments or financial refunds. Production availability, external webhooks, concurrent updates, data protection, and real LMS synchronization are not confirmed.
- On first opening this Blueprint, WordPress Playground displayed a transient "hiccup" that recovered after retry. Later browser navigation encountered `net::ERR_FAILED`, but the saved Student Access QA result was visible and verified before that failure.

## Code components and reproducible setup

- `plugins/woocommerce-lms-bridge/woocommerce-lms-bridge.php`: audit-only v0.3.0 order/refund events.
- `plugins/lms-lab-course-access/lms-lab-course-access.php`: test-only WordPress student provisioning, order-level entitlements and protected lesson.
- `plugins/lms-lab-access-qa/lms-lab-access-qa.php`: 22-check new QA suite.
- `plugins/lms-lab-qa-runner/lms-lab-qa-runner.php`: 12-check prior suite.
- `playground/blueprint-v3-student-access.json`: installs the four plugins and WooCommerce; executes both suites and saves both reports. Opens Student Access QA.

## Next milestone (75% → 85%)

1. Test real browser student login/logout with an actual login credential created for the lab.
2. Build a TalentLMS API v2 **adapter in disabled-by-default mode**, using official API version/key headers and endpoint contracts.
3. Simulate API successes, 401/403, rate-limit (429), timeouts, 5xx failures, retries and reconciliation.
4. Execute genuine TalentLMS course enroll/un-enroll only with an authorized paid TalentLMS test tenant (API is not available on Free plan).

Official API docs: https://developers.talentlms.io/api/talentlms-public-api/ and https://help.talentlms.com/hc/en-us/articles/24874457011356-TalentLMS-API-V2.
