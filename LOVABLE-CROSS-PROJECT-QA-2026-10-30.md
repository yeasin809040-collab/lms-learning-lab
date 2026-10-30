# Learning Integration Lab — Cross-Project QA Evidence (2026-10-30)

## Scope
This report separates two **different** learning artifacts:
1. **Lovable front-end demo** (project `130a6b02-3447-450e-ad1e-e740c03646a9`) — React/TanStack, fictional catalog, browser-local accounts and simulated checkout.
2. **GitHub WordPress/WooCommerce training lab** (`yeasin809040-collab/lms-learning-lab`) — isolated real WordPress/MySQL/WooCommerce/Chrome CI with synthetic orders, local student access and mocked external TalentLMS.

**They are not a connected production system.** Passing tests in one artifact does not establish integration with the other.

## Verified from connected project metadata and source review (2026-10-30)
- Lovable project is **ready as a preview** and **not published**. Workspace plan is **free**; cloud database is **disabled**.
- Source contains course catalog/details, instructor routes, browser-only demo signup/signin, simulated checkout approve/decline/free, duplicate-enrollment guard, learner dashboard, progress toggling, simulated refunds and a /qa checklist.
- Source test files: `src/test/demo-logic.test.ts` (sign-in/wrong password/duplicate email; approve/decline/duplicate checkout/refund/progress), `src/test/app-routing.test.tsx` (root route matching). These tests were **inspected**, not independently executed by this source audit.
- `src/lib/demo-store.tsx` persists state to **localStorage**; `src/lib/demo-logic.ts` stores **demo passwords in browser-local state**, and the signup page warns not to reuse real passwords. This is **not production authentication or a security boundary**.
- Checkout source explicitly says no card data and no real payment. There is no configured WordPress/WooCommerce/TalentLMS connection or cloud database.
- The /qa checklist stores manually selected PASS/FAIL/BLOCKED status in localStorage; these status toggles **are not evidence** of executed real integration tests.

## Independent GitHub lab CI (already verified, separate from Lovable)
- [Real Docker WordPress/MySQL/WooCommerce + Selenium Chrome CI](https://github.com/yeasin809040-collab/lms-learning-lab/actions/runs/40968538741): **168 PASS / 0 FAIL** (108 WordPress checks, 13 synthetic payment flow, 22 privacy/negative cases, 11 live API guards, 14 Chrome browser checks).
- [PHP/offline contracts and Blueprint checks](https://github.com/yeasin809040-collab/lms-learning-lab/actions/runs/40969037485): **69** offline TalentLMS V2 adapter contract checks, **26** reconciliation checks, **2** public-demo opt-in checks, plus PHP syntax and Blueprint JSON validation.
- [Second independent cross-check](./QA-SECOND-INDEPENDENT-CROSS-CHECK-2026-10-30.md) records four corrected synthetic-lab safety gaps.
- These are **not** real TalentLMS API transactions or real payment processing. The GitHub lab's 14 browser checks are for its **WordPress browser portal**, not the Lovable preview.

## Lovable QA matrix (evidence-level, as of source inspection)
| Area | Evidence | Status |
| --- | --- | --- |
| Catalog, course detail, instructors | Source routes and fictional dataset present | IMPLEMENTED; browser E2E pending |
| Demo sign-up/sign-in | Browser-local functions and existing unit test definitions | IMPLEMENTED; fresh run/browser E2E pending |
| Approve/decline/free checkout | Pure simulation code and unit test definitions | IMPLEMENTED; fresh run/browser E2E pending |
| Duplicate order guard | Logic checks active enrollment | IMPLEMENTED; race/stale-state regression pending |
| Refund/revoke, dashboard, progress | Source and test definitions | IMPLEMENTED; fresh run/browser E2E pending |
| Mobile layout and accessibility | Responsive CSS classes present | NOT VERIFIED by a real mobile viewport/a11y audit |
| Security | No real auth, plaintext browser-local demo passwords | DEMO ONLY; production security NOT VERIFIED |
| Real WordPress plugin and product sync | Not connected in Lovable | PENDING |
| WooCommerce real sandbox checkout | Not connected in Lovable | PENDING |
| TalentLMS real API, SSO, remote enrollment | No authorized paid test tenant connected | BLOCKED/PENDING |
| Live webhook signature, refund reconciliation | No real external endpoints connected | PENDING |
| Performance, backups, production readiness | No production deployment | PENDING |

## Source-review risks to regression-test
- The demo checkout function accepts a caller-provided course slug and price; UI uses static catalog values, but the pure function does not independently validate that course/price mapping. Add negative unit tests and enforce catalog mapping in demo logic.
- The localStorage snapshot is type-cast after JSON parse; malformed yet version-matching data may break the UI. Add schema validation and corruption recovery tests.
- Ensure refund and lesson-progress functions reject invalid identities, unknown lessons, and repeated actions. Simulated refund must not remove a learner's access if another valid paid entitlement exists.
- Never mark manually clicked /qa statuses as actual integration PASS. Keep separate **automated demo evidence** and **future external acceptance checklist**.

## Next steps (free, no external charges)
1. Run and expand Lovable's automated tests/build/lint; record exact command outputs and commit SHA.
2. If a free browser runner is available, test real Lovable preview flows at desktop and mobile viewport sizes and capture evidence. Otherwise leave browser E2E pending.
3. Harden demo-only input, storage and state transitions; rerun tests.
4. Only with separate authorized WordPress/WooCommerce/TalentLMS staging and explicit permission may the external integration gates move from PENDING.

No real money, paid plan, client data, Upwork Connects, proposals, or messages are required for these free demo QA steps.
