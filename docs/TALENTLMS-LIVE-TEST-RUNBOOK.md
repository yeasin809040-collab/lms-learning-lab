# Final 5%: TalentLMS V2 Live API — controlled test runbook

**Project state:** 95% internal training milestone VERIFIED. The final 5% requires **an explicitly authorized paid TalentLMS test tenant**; it has **not** yet been executed. No one should claim that real TalentLMS enrollments have already been tested.

**Existing real WordPress proof:** [108 database-backed QA checks + 14 real Chrome checks passed](../QA-EVIDENCE-95-MILESTONE-2026-10-29.md).

**New final-stage API adapter:** [Disabled-by-default TalentLMS V2 client](../plugins/lms-lab-talentlms-live-readiness/lms-lab-talentlms-live-readiness.php).

## Verified published TalentLMS rules

Official sources:

- [TalentLMS API V2 official requirements](https://help.talentlms.com/hc/en-us/articles/24874457011356-TalentLMS-API-V2)
- [V2 Getting started and headers](https://developers.talentlms.io/intro/)
- [POST /api/v2/enrollments](https://developers.talentlms.io/api/enroll-user-to-course/)
- [DELETE /api/v2/enrollments](https://developers.talentlms.io/api/delete-a-user-from-a-course/)
- [GET /api/v2/courses](https://developers.talentlms.io/api/get-courses/)

**API access requires a paid plan**; Free and Free Trial do not provide API access. The Premium Trial "Demo mode" also turns off most external integrations and cannot substitute for authorized live API verification.

TalentLMS V2 requires `X-API-Key`, `X-API-Version: YYYY-MM-DD`, `Accept: application/json`, and HTTPS. V2 enrollment calls use user_id + course_id; these IDs must belong to the **test tenant**, not the local WordPress database.

## Private configuration (not for this public repository)

Configure constants in a private, non-version-controlled WordPress `wp-config.php` on an isolated, approved test site. NEVER paste the API key into ChatGPT, GitHub files, an issue, a proposal, a WordPress page, or logs.

- `LLS100_TALENTLMS_DOMAIN`: exact owned test subdomain, e.g. `approved-test.talentlms.com` (domain only, without scheme/path).
- `LLS100_TALENTLMS_API_KEY`: private server-side credential; protect and rotate according to tenant owner's policy.
- `LLS100_ENABLE_LIVE_READS`: `true` **only after** the tenant owner explicitly authorizes validation.
- `LLS100_APPROVED_TEST_USER_ID`: ID of the designated synthetic TalentLMS user.
- `LLS100_APPROVED_TEST_COURSE_ID`: ID of the designated synthetic TalentLMS course.
- `LLS100_ALLOW_LIVE_WRITES`: remain `false` until the owner specifically approves changing test enrollments.
- `LLS100_APPROVED_TEST_ACTION`: explicitly approved single write action, either `enroll` or `unenroll`.

Without a properly configured paid tenant, real outgoing reads and writes are blocked. When writes are enabled, they are allowlisted to **one exact test user and one exact test course**. The plugin does **not** add automatic order hooks or a public API endpoint, and it makes **no background requests**. PHP mock transport injection is only for tests.

## Planned controlled End-to-End proof (not yet executed)

1. **Approval:** Obtain tenant owner's specific permission for one synthetic test user and course. Confirm account is paid and API enabled. Do not spend money, subscribe, or use real learners.
2. **Read-only:** Set private server configuration, then explicitly call `lls100_request('list_courses')` from authenticated WP-CLI in the isolated test environment. Verify a 200 JSON `_data` response. Never display or persist its API key.
3. **Enroll:** Only after approval, set `LLS100_ALLOW_LIVE_WRITES=true` and `LLS100_APPROVED_TEST_ACTION='enroll'`; explicitly call `lls100_request('enroll',TEST_USER_ID,TEST_COURSE_ID)`. Require documented 200 success or reconcile ambiguous states.
4. **Verify remotely:** Confirm in the actual TalentLMS test course admin UI (or a documented read endpoint) that this precise user was enrolled. Record only redacted test IDs and status, not private user details.
5. **Unenroll:** If and only if owner approves this specific write, switch the approved action to `unenroll` and explicitly call `lls100_request('unenroll',TEST_USER_ID,TEST_COURSE_ID)`. Verify user no longer has course access.
6. **Cleanup:** Set READS and WRITES flags back to false; remove test account/course as directed by owner; never leak key or test identity in public logs.
7. **Record proof:** Include authenticated actual API HTTP status, confirmation in remote TalentLMS, valid course mapping and test-user identity, and a subsequent WordPress order/refund lifecycle. Don't describe mock-only results as live.

**Important:** This adapter is a carefully gated **live HTTP client**, not a fully deployed automatic WordPress-to-TalentLMS production sync. It is a foundation for the final controlled test. Mapping and reconciliation with real TalentLMS users and real orders must be verified separately before production deployment.

## Tests done without any paid account

- [Public PHP API adapter contract tests](../tests/test-100-talentlms-adapter-offline.php): **59 PASS / 0 FAIL**, zero network calls. [GitHub Actions evidence](https://github.com/yeasin809040-collab/lms-learning-lab/actions/runs/40906982352).
- [Real WordPress guard test](../tests/test-100-wordpress-live-guard.php): validates live traffic is disabled in fresh WordPress. CI status must be checked before claiming its count.
- All prior WordPress/MySQL + Chrome regression results remain documented at 95%.

## Current blocker

**No paid and authorized TalentLMS tenant/API is connected.** Real API-side user enrollment/unenrollment cannot be verified without permission and valid credentials. This should be treated as an external dependency, *not* as failure of the existing WordPress integration tests.
