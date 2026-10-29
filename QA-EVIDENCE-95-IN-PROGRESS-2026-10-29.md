# 95% training milestone — progress and blockers (2026-10-29)

**Status: IN PROGRESS. DO NOT CLAIM THE 95% MILESTONE AS VERIFIED.**

### Completed work

1. [Student browser login lab plugin](./plugins/lms-lab-browser-login/lms-lab-browser-login.php): explicitly seeds one disposable WordPress subscriber and one completed synthetic WooCommerce order, creates a WordPress page containing a login form and a link to a protected lesson. The disposable account is seeded by the Blueprint; user must never deploy this on a public production site.
2. [External identity & reconciliation simulator](./plugins/lms-lab-sync-reconciliation/lms-lab-sync-reconciliation.php): synthetic user identity matching, ambiguous identity detection, explicit reconciliation states after uncertain API writes, option-based lock-claim simulation. **No TalentLMS API calls**.
3. [95% WordPress QA plugin](./plugins/lms-lab-qa95/lms-lab-qa95.php): QA for synthetic subscriber, completed order, entitlement, identity match/conflict, manual 503 and timeout review, partial and full refunds, API jobs and stale events.
4. [V5 student login Blueprint](./playground/blueprint-v5-browser-login-and-reconciliation.json) and [V5A WP admin QA Blueprint](./playground/blueprint-v5a-admin-qa.json) install new plugins and attempt to execute/save the WordPress QA results.
5. [GitHub Actions PHP syntax, Blueprint JSON, and offline 95% regression workflow](./.github/workflows/php-lint.yml) passed. [**CI run #3**](https://github.com/yeasin809040-collab/lms-learning-lab/actions/runs/40897301321) completed **successfully**; job logs explicitly reported **26 passed / 0 failed** for the new synthetic identity/reconciliation/locking regression suite.
6. [26-check offline PHP suite](./tests/test-95-reconciliation-offline.php) has real executable assertions. This is a **stubbed, local PHP test**, not a WordPress or TalentLMS browser test.

### Real browser tests attempted — NOT PASSED

- [V5 browser login run](https://agent.tinyfish.ai/runs/b3feebd4-9aba-4662-9cc9-64d3d87aa1de): Playground created a sandbox with student portal links, but `wpsandbox.wordpress.net` repeatedly returned `net::ERR_FAILED`. The agent **could not** inspect authenticated student session, visit a protected lesson successfully, log out, log back in, or view the saved 95 QA report.
- [V5A admin QA run](https://agent.tinyfish.ai/runs/3de52a4e-fb98-41f1-96dc-641512417a9a): WordPress provisioning appeared to succeed and `Tools > LMS Lab 95 QA` was present in navigation, but the QA page itself repeatedly returned `net::ERR_FAILED`. **No 95 QA PASS/FAIL count was observed**.

Neither run observed any actual payment or made a real TalentLMS API call. No new TalentLMS enrollment has been verified.

### What must be verified before 95% is claimed

- Real WordPress browser session authenticated as the synthetic *subscriber*, with protected-lesson marker visible.
- Actual logout, guest denied access, and repeat login using the WordPress login form, with course access restored.
- The V5A 95% WordPress QA report's exact PASS and FAIL results, including test order/refund IDs.
- A WordPress-runtime partial refund test plus queued API sync reconciliation under explicitly controlled HTTP mocks.
- Review any failures and rerun until the relevant checks pass.

### Additional limitations

- GitHub Actions' lock claim test is sequential simulation, not a simultaneous worker race test; production concurrency is **not proven**.
- The demonstration credential is hardcoded, synthetic, and stored in a PUBLIC source repo; **only use with fresh disposable Playground**. Never deploy the browser login seed to any production server.
- Access handling is WordPress-local and test-only; live TalentLMS API access remains unavailable. The user's 85% milestone remains the most recent verified learning checkpoint.

### Launch links (new disposable sessions)

- [WordPress Playground V5 — synthetic student browser portal](https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fyeasin809040-collab%2Flms-learning-lab%2Fmain%2Fplayground%2Fblueprint-v5-browser-login-and-reconciliation.json)
- [WordPress Playground V5A — 95% WP admin QA report](https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fyeasin809040-collab%2Flms-learning-lab%2Fmain%2Fplayground%2Fblueprint-v5a-admin-qa.json)

**Do not upload real student records, passwords, payment details, or backups into this public repository.**
