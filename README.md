# LMS Learning Lab — reproducible WooCommerce audit integration lab

This public repository contains a test-only WordPress/WooCommerce course-order integration lab.

**Latest verified real-browser result: 12/12 QA checks passed on 2026-10-29.**

[View observed PASS/FAIL evidence](./QA-EVIDENCE-2026-10-29.md)

[Launch the tested WordPress Playground Blueprint](https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fyeasin809040-collab%2Flms-learning-lab%2Fmain%2Fplayground%2Fblueprint-v2-repaired.json)

## Components

- `plugins/woocommerce-lms-bridge/woocommerce-lms-bridge.php`: v0.3.0 event audit-only bridge (no TalentLMS API calls).
- `plugins/lms-lab-qa-runner/lms-lab-qa-runner.php`: v1.1.0 synthetic database-only order and refund test runner.
- `playground/blueprint-v2-repaired.json`: fixed automated Playground provisioning script.
- `QA-EVIDENCE-2026-10-29.md`: exact observed results and limitations.

## Caution

**This is NOT a live payment gateway or a live TalentLMS integration.** The 12 PASS checks validate WooCommerce database state and audit-only hooks. They do not validate student enrollment, actual money transactions, or production deployment.

Never upload secrets, customer personal data, or database backup archives to this public repository.
