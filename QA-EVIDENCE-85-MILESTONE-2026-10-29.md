# 85% Training Milestone — WordPress TalentLMS V2 Transport Simulation

## Observed live WordPress result

**38 PASS / 0 FAIL** — recorded in a real WordPress Playground admin QA page on **2026-10-29 14:24:08 UTC**.

**Browser evidence:** https://agent.tinyfish.ai/runs/d47d523b-559f-4082-9d08-2789c3fc4b3f

- QA page: `Tools > LMS Lab API QA 85`
- WordPress/WooCommerce loaded in a disposable browser site; test plugin executed automatically from Blueprint.
- Test-only WooCommerce course product ID: **11**
- Synthetic test order ID: **21**
- Synthetic test refund ID: **22**
- No QA failures or PHP fatal errors observed.

## What the 38 checks cover

The test runner executes multiple individually labeled checks covering:

- Disabled-by-default outbound transport; explicit refusal to contact an API unless a controlled mock is enabled.
- V2 API request format: `POST /api/v2/enrollments` and `DELETE /api/v2/enrollments`, numeric user/course IDs, JSON request body, `Accept: application/json`, `X-API-Key`, and `X-API-Version`.
- Controlled response classification: success 200 with `_data`, 204 No Content, 202 asynchronous/unknown completion, input/auth failures 400/401/403/404/422, 409 conflict, 429 rate limit, 503 server uncertainty, simulated timeout (`WP_Error`), malformed JSON, missing required schema.
- Real WooCommerce synthetic order status -> a single queued API enrollment job for an eligible course.
- Exactly-once *local queue creation* on duplicate order events (not an external guarantee of exactly-once delivery).
- Rate-limit retry at +30 seconds, respecting due time, followed by success, stored job state and no second send after success.
- Real WooCommerce synthetic refund -> queued DELETE action; local access revoked and only one refund job created.
- Bounded retry policy (three 429 attempts then dead-letter) rather than infinite requests.
- No API credentials or learner emails stored in the local job queue.

## Proof scope and exclusions

**This is a real WordPress and WooCommerce installation, but it uses a fake API host and fully synthetic HTTP responses.** There are **zero actual TalentLMS API calls, enrollments, or account changes**. The simulated key is not a real credential. No money was charged or refunded. Real login/logout, external service connectivity, correctness under concurrent order writes, API authorization at a real tenant, production-grade reconciliation and automated job scheduling remain unverified.

The code intentionally marks uncertain writes (timeouts and server errors) as **review** instead of replaying POST/DELETE blindly. A 429 request can be retried, bounded to three attempts. The tested “queue” is a WordPress `options` data structure for laboratory purposes, not a hardened production queue; do not deploy it as-is.

The 85% figure is an internal **learning-plan milestone**, not a standardized prediction of project success or proof of a full TalentLMS integration.

The previous checks passed separately in earlier real WordPress test sessions:
- [12/12 order/audit/refund QA](./QA-EVIDENCE-2026-10-29.md).
- [22/22 local WordPress student access QA](./QA-EVIDENCE-75-MILESTONE-2026-10-29.md).
These are separate test observations. **Do not claim the sum passed in one combined run.**

## Reproducible source

- [Launch new Playground Blueprint V4](https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fyeasin809040-collab%2Flms-learning-lab%2Fmain%2Fplayground%2Fblueprint-v4-api-reliability.json)
- [API simulator source](./plugins/lms-lab-talentlms-sim/lms-lab-talentlms-sim.php)
- [38-check API reliability test suite](./plugins/lms-lab-api-qa85/lms-lab-api-qa85.php)
- [Versioned Blueprint](./playground/blueprint-v4-api-reliability.json)

Official reference:
- https://developers.talentlms.io/api/talentlms-public-api/
- https://developers.talentlms.io/api/enroll-user-to-course/
- https://developers.talentlms.io/api/delete-a-user-from-a-course/
- https://help.talentlms.com/hc/en-us/articles/24874457011356-TalentLMS-API-V2

## Next technical milestone: 85 → 95

- Test authenticated WordPress student login, session/cookie state, protected content and logout in a real browser.
- Add simulated TalentLMS user identity reconciliation and outcome verification (avoid treating ambiguous responses as success).
- Test concurrent/multiple orders, partial refunds, retries, and recovery from worker restarts.
- Test actual TalentLMS V2 only with an authorized paid tenant, credentials stored privately, after customer sign-off.
