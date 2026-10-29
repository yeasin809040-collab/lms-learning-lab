# LMS Learning Lab — WordPress + WooCommerce + API Reliability Training

This public repository contains **test-only WordPress/WooCommerce course-access integration software** and reproducible WordPress Playground tests.

## Verified learning milestones

| Milestone | Evidence | Observed result |
|---|---|---|
| 65% — WooCommerce order, audit bridge, refund and duplicate handling | [Evidence](./QA-EVIDENCE-2026-10-29.md) | 12 PASS / 0 FAIL |
| 75% — WordPress students, local protected course content and refund access controls | [Evidence](./QA-EVIDENCE-75-MILESTONE-2026-10-29.md) | 22 PASS / 0 FAIL |
| 85% — TalentLMS V2 **simulated** HTTP, error handling, retry queue and refund sync | [Evidence](./QA-EVIDENCE-85-MILESTONE-2026-10-29.md) | 38 PASS / 0 FAIL |

All tests were observed in real WordPress Playground browser environments across **separate sessions**. The percentages are internal training milestones, not a verified fraction of an end-to-end TalentLMS production integration.

## Reproduce the latest lab

[**Launch the WordPress Playground 85% milestone Blueprint**](https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fyeasin809040-collab%2Flms-learning-lab%2Fmain%2Fplayground%2Fblueprint-v4-api-reliability.json)

It installs WooCommerce and the test plugins in a new disposable environment, then runs the QA suites automatically. The latest API reliability report is shown in **Tools → LMS Lab API QA 85**.

## Components

- `plugins/woocommerce-lms-bridge/`: v0.3.0 audit-only WooCommerce order/refund events.
- `plugins/lms-lab-qa-runner/`: 12-check order/refund suite.
- `plugins/lms-lab-course-access/`: WordPress-local **synthetic-only** learner access and protected lessons.
- `plugins/lms-lab-access-qa/`: 22-check course access suite.
- `plugins/lms-lab-talentlms-sim/`: fail-closed simulated TalentLMS API V2 request/response handling and lab retry queue.
- `plugins/lms-lab-api-qa85/`: 38-check controlled API failure and queue test suite.
- `playground/blueprint-v4-api-reliability.json`: reproducible latest Blueprint.

## Scope and safety

**There is no live TalentLMS API integration yet.** The API simulation **does not make any outbound TalentLMS request** and does not use real customer credentials. Synthetic WooCommerce orders, mock HTTP responses, and local WordPress student entitlements were tested. No actual money was transferred.

The synthetic-only code, simulated key, fixed course ID and option-backed queue are **not production-ready**. They must not be used with real customer data or real payments. Live TalentLMS API testing requires a paid account with authorized API access, which has not been connected.

Never commit API keys, passwords or customer information to this public repository.
