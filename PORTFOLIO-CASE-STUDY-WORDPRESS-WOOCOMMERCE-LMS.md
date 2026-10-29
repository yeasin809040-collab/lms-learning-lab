# WordPress + WooCommerce Course Access & Reliability Lab

**Portfolio case study | Independent AI-assisted demonstration project | October 2026**

**Project type:** Test-only WordPress/WooCommerce LMS integration prototype, not paid client work.  
**Tech:** WordPress, PHP, WooCommerce, MySQL, WordPress Playground, WP-CLI, GitHub Actions, Chrome/Selenium.

## Project challenge

A digital-course store needs to recognize genuine WooCommerce order-state transitions, grant access to the correct WordPress learner, prevent duplicate processing, deny guests and non-eligible users, and handle refunds without leaving course content accessible. External LMS API calls must also behave safely when a remote request fails or times out.

## What I prepared and tested

- Developed a synthetic-only WooCommerce-to-WordPress learner-access flow using explicit test orders and `@example.com` users, not actual customer data.
- Implemented protected sample lesson access and verified a **real WordPress login, authentication cookie, logged-out denial, incorrect-password rejection and re-login** in Chrome.
- Corrected a meaningful order-state gap: **paid virtual-only WooCommerce products can stay `Processing` rather than `Completed`**. Added checks using the actual WooCommerce `payment_complete()` path.
- Verified idempotent handling of repeated order events, local partial/full refund behavior, and test-only entitlement updates.
- Built a simulated TalentLMS V2 HTTP adapter to classify authentication errors, rate limits, server errors and uncertain write outcomes. Live external writes are **disabled by default**.
- Used reproducible Docker WordPress/MySQL CI and Selenium Chrome rather than relying only on screenshots or static code review.

## Verified results

| Check category | Results | Proof |
|---|---:|---|
| WordPress/WooCommerce integration suites | **108 PASS / 0 FAIL** | [Full GitHub CI run](https://github.com/yeasin809040-collab/lms-learning-lab/actions/runs/40909797213) |
| Paid virtual-only `Processing` order lifecycle | **13 PASS / 0 FAIL** | [Same CI run](https://github.com/yeasin809040-collab/lms-learning-lab/actions/runs/40909797213) |
| Real WordPress safe outbound-API guards | **11 PASS / 0 FAIL** | [Same CI run](https://github.com/yeasin809040-collab/lms-learning-lab/actions/runs/40909797213) |
| Real Chrome student login/lesson/logout sequence | **14 PASS / 0 FAIL** | [Same CI run](https://github.com/yeasin809040-collab/lms-learning-lab/actions/runs/40909797213) |

**146 PASS / 0 FAIL in one independent WordPress/Chrome CI run.** Separately, **59/59 offline TalentLMS adapter contract tests passed** using controlled responses. No real TalentLMS enrollment, real payment, live customers or real client environment was involved.

## What the sample demonstrates to a WordPress maintenance client

- Reproduce a bug before changing code, then add an automated regression test.
- Understand payment/order status transitions and protect content correctly.
- Check permissions and login sessions, including logged-out behavior.
- Make changes in an isolated/staging environment and retain a reproducible evidence trail.
- Document findings clearly, including what has **not** been verified.

## Evidence and scope

- [Source repository](https://github.com/yeasin809040-collab/lms-learning-lab)
- [Independent cross-check report](https://github.com/yeasin809040-collab/lms-learning-lab/blob/main/QA-INDEPENDENT-CROSS-CHECK-2026-10-29.md)
- [Verified WordPress + Chrome job run](https://github.com/yeasin809040-collab/lms-learning-lab/actions/runs/40909797213)
- [Safe TalentLMS V2 pre-live tests](https://github.com/yeasin809040-collab/lms-learning-lab/blob/main/QA-EVIDENCE-FINAL-5-PRELIVE-2026-10-29.md)

**Important limitation:** This is a portfolio-quality **learning and validation lab**, not a turnkey production integration. Its test accounts, one-course mapping, staged-only access policy and simulated external API must not be deployed as a production customer system. A real client project requires separate access, scope, staging, approval, backups and acceptance tests.
