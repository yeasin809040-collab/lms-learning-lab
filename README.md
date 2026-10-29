# LMS Learning Lab

This repository is used for learning and testing WordPress + WooCommerce integration with a **Learning Lab WooCommerce LMS Bridge (Audit Mode)**.

## Verified
- WordPress Playground screenshots showed WooCommerce 11.2.1 and the LMS Bridge v0.3.0 activated together.
- Audit-only LMS Bridge PHP stub tests passed in a separate environment.

## Not yet verified
- End-to-end WooCommerce order completion event in this specific WordPress session.
- Refund and duplicate-event protection on a real WooCommerce installation.
- Any live TalentLMS enrollment or API connection.

## Safety
Never upload WordPress database backups, API tokens, passwords, private customer information, or other secrets to this public repository.

## Next step
Add the audit-only plugin source and a clearly labeled integration test runner. Run those tests in an isolated WordPress test environment; collect actual output rather than reporting success from code review alone.
