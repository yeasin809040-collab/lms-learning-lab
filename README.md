# LMS Learning Lab — WooCommerce Course Access Test Lab

A reproducible **WordPress + WooCommerce** learning project with actual WordPress database-backed synthetic purchase and course-access QA.

## Verified testing milestones

| Milestone | Browser evidence | Result |
|---|---|---|
| 65% — WooCommerce orders, Bridge audits and refunds | [12-check report](./QA-EVIDENCE-2026-10-29.md) | **12 PASS / 0 FAIL** |
| 75% — WordPress student account provisioning, protected lessons, refunds, duplicate & multiple-order handling | [22-check report](./QA-EVIDENCE-75-MILESTONE-2026-10-29.md) | **22 PASS / 0 FAIL** |

These percentages are internal **learning-plan checkpoints**, not verified percentages of a full TalentLMS project.

### Try the latest lab

[**Launch WordPress Playground student-access QA Blueprint**](https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fyeasin809040-collab%2Flms-learning-lab%2Fmain%2Fplayground%2Fblueprint-v3-student-access.json)

Provisioning installs WooCommerce and four test plugins in a fresh disposable instance. The initial synthetic QA runs in the Blueprint. To inspect the saved result navigate to **Tools → LMS Lab Student Access QA**. For the earlier audit suite use **Tools → LMS Lab Real QA**.

## Architecture

- `plugins/woocommerce-lms-bridge/`: audit-only WordPress/WooCommerce event bridge. No real TalentLMS calls.
- `plugins/lms-lab-qa-runner/`: WooCommerce order, audit and refund QA.
- `plugins/lms-lab-course-access/`: **test-only** WordPress learner provisioning, order-level entitlements, page protection and refund revocation.
- `plugins/lms-lab-access-qa/`: 22 additional student-access checks.
- `playground/blueprint-v3-student-access.json`: repeatable provisioning and synthetic testing.

## Important limits

**This is not a live TalentLMS integration.** Student entitlement lives locally in WordPress, not TalentLMS. The lab accepts only orders explicitly marked synthetic with `@example.com` emails. No payment gateway runs, and login/logout through a real web browser is not yet verified. Avoid deploying the test-only plugins to production.

The first 12-check suite passed in one real-browser run, and the 22-check suite passed in a second real-browser run; do not treat them as 34 tests observed in one execution.

The TalentLMS API v2 requires `X-API-Key` and `X-API-Version` and is limited to paid plans; API enrollment remains an upcoming, unverified task.

Never commit secrets or real customer data to this public repository.
