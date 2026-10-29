# LMS Learning Lab — WordPress + WooCommerce + API Reliability Training

This public repository contains **test-only WordPress/WooCommerce course-access integration software** with reproducible WordPress Playground tests and a real Docker WordPress/MySQL + Chrome browser GitHub Actions workflow.

## Independent cross-check (Oct 29, 2026)

**[Read the full independent readiness audit](./QA-INDEPENDENT-CROSS-CHECK-2026-10-29.md).** A real WooCommerce virtual-only payment event issue was found and corrected in the audit, student-access and mock queue plugins. The resulting [WordPress + Chrome CI run](https://github.com/yeasin809040-collab/lms-learning-lab/actions/runs/40909797213) passed **146 / 146** checks: 108 existing WordPress QA, 13 new payment-completion checks, 11 live API guards and 14 browser tests. The result verifies the **isolated test lab**, not a production-ready TalentLMS connection.

## Verified learning milestones

| Milestone | Evidence | Observed result |
|---|---|---|
| 65% — WooCommerce order, audit bridge, refund and duplicate handling | [Evidence](./QA-EVIDENCE-2026-10-29.md) | 12 PASS / 0 FAIL |
| 75% — WordPress students, local protected course content and refund access controls | [Evidence](./QA-EVIDENCE-75-MILESTONE-2026-10-29.md) | 22 PASS / 0 FAIL |
| 85% — TalentLMS V2 **simulated** HTTP, error handling, retry queue and refund sync | [Evidence](./QA-EVIDENCE-85-MILESTONE-2026-10-29.md) | 38 PASS / 0 FAIL |
| 95% — Real WordPress/MySQL integration and Chrome student login, cookie, lesson/logout/relogin E2E | [Evidence](./QA-EVIDENCE-95-MILESTONE-2026-10-29.md) | **108 WordPress QA PASS + 14 Chrome PASS; 0 FAIL** |

The 65%, 75% and 85% milestones were first observed in real WordPress Playground sessions. The 95% milestone was verified on an **isolated WordPress/MySQL installation on GitHub Actions**, with a **real Chrome/Selenium browser**. These percentages are internal learning milestones, not a verified fraction of an end-to-end TalentLMS production integration.

## Reproduce the latest lab

**Recommended: [Verified 95% real WordPress + Chrome CI run](https://github.com/yeasin809040-collab/lms-learning-lab/actions/runs/40905383959)**. The source workflow is [wordpress-e2e.yml](./.github/workflows/wordpress-e2e.yml), which automatically tests synthetic WooCommerce course orders, API simulations, WordPress student login/logout, browser authentication cookies and protected lessons. The previous failed browser test was traced to a `localhost` versus `127.0.0.1` cookie-origin mismatch and resolved by following canonical WordPress permalinks.


[**Launch the WordPress Playground 85% milestone Blueprint**](https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fyeasin809040-collab%2Flms-learning-lab%2Fmain%2Fplayground%2Fblueprint-v4-api-reliability.json)

It installs WooCommerce and the test plugins in a new disposable environment, then runs the QA suites automatically. The latest API reliability report is shown in **Tools → LMS Lab API QA 85**.

## Components

- `plugins/woocommerce-lms-bridge/`: v0.3.0 audit-only WooCommerce order/refund events.
- `plugins/lms-lab-qa-runner/`: 12-check order/refund suite.
- `plugins/lms-lab-course-access/`: WordPress-local **synthetic-only** learner access and protected lessons.
- `plugins/lms-lab-access-qa/`: 22-check course access suite.
- `plugins/lms-lab-talentlms-sim/`: fail-closed simulated TalentLMS API V2 request/response handling and lab retry queue.
- `plugins/lms-lab-api-qa85/`: 38-check controlled API failure and queue test suite.
- `playground/blueprint-v4-api-reliability.json`: reproducible 85% Blueprint.
- `plugins/lms-lab-browser-login/`: synthetic subscriber and real WordPress browser sign-in portal.
- `plugins/lms-lab-sync-reconciliation/`: safe simulated external identity resolution and manual job review.
- `plugins/lms-lab-qa95/`: 36-check WordPress QA suite.
- `tests/test-browser-login-selenium.py`: 14 verified real Chrome browser assertions.
- `.github/workflows/wordpress-e2e.yml`: reproducible real WordPress/MySQL/Chrome E2E pipeline.

## Scope and safety

**There is no live TalentLMS API integration yet.** The API simulation **does not make any outbound TalentLMS request** and does not use real customer credentials. Synthetic WooCommerce orders, mock HTTP responses, and local WordPress student entitlements were tested. No actual money was transferred.

The synthetic-only code, simulated key, fixed course ID and option-backed queue are **not production-ready**. They must not be used with real customer data or real payments. Live TalentLMS API testing requires a paid account with authorized API access, which has not been connected.

Never commit real API keys, passwords or customer information to this public repository. The demonstration password is deliberately public and synthetic, for **disposable CI/browser environments only**; never reuse it elsewhere.

## Final 5%: gated live TalentLMS test — **in progress**

**95% training milestone remains verified; 100% is NOT claimed.**

- [TalentLMS V2 real API readiness adapter](./plugins/lms-lab-talentlms-live-readiness/lms-lab-talentlms-live-readiness.php) is **disabled by default**, limited to private server-side credentials, approved single test user/course IDs, HTTPS and explicit per-action write consent.
- [59 PHP contract checks passed, 0 failed](https://github.com/yeasin809040-collab/lms-learning-lab/actions/runs/40906982352), testing safe requests and errors with **no real API calls**.
- [Real WordPress guard test](./tests/test-100-wordpress-live-guard.php) passed **11 / 11** on actual WordPress/MySQL; [CI evidence](https://github.com/yeasin809040-collab/lms-learning-lab/actions/runs/40907369807) also reconfirmed 108 WordPress QA checks and 14 real Chrome checks. Full pre-live proof: [final 5% evidence](./QA-EVIDENCE-FINAL-5-PRELIVE-2026-10-29.md).
- [**Readiness, owner authorization and safe real test procedure**](./docs/TALENTLMS-LIVE-TEST-RUNBOOK.md).

**No authorized paid TalentLMS API tenant is currently connected.** Neither real enrollment nor unenrollment has been observed. TalentLMS Free/Trial plans do not offer API access. Do not paste API secrets into chat, GitHub or WordPress posts, and do not enable production writes without exact test account permission.
