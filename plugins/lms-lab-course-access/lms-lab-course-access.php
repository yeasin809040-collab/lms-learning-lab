<?php
/**
 * Plugin Name: LMS Learning Lab — Student Course Access (Test Mode)
 * Description: Test-only WordPress course entitlement: synthetic WooCommerce orders provision example.com students; completed orders grant access and refunded orders revoke it. NO TalentLMS API calls.
 * Version: 0.1.2
 * Requires Plugins: woocommerce
 */
if (!defined('ABSPATH')) { exit; }

define('LLS_LAB_COURSE_SKU', 'LIL-COURSE-001');
define('LLS_LAB_GRANTS_KEY', '_lls_lab_course_grants');

add_action('woocommerce_order_status_completed', 'lls_lab_grant_order', 30, 1);
// WooCommerce normally leaves paid virtual-only, non-downloadable orders Processing.
add_action('woocommerce_payment_complete', 'lls_lab_grant_order', 30, 1);
add_action('woocommerce_order_status_refunded', 'lls_lab_revoke_order', 30, 1);
// Stop access on terminal payment/order failures, not just a full refund.
add_action('woocommerce_order_status_cancelled', 'lls_lab_revoke_order', 30, 1);
add_action('woocommerce_order_status_failed', 'lls_lab_revoke_order', 30, 1);
add_action('init', function () {
    add_shortcode('lls_protected_course', 'lls_lab_protected_course');
});

register_activation_hook(__FILE__, function () {
    $existing = (int) get_option('lls_lab_lesson_page_id', 0);
    if ($existing && get_post($existing)) { return; }
    $page_id = wp_insert_post(array(
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => 'Learning Lab — Protected Sample Lesson',
        'post_name' => 'learning-lab-protected-sample-lesson',
        'post_content' => '<h2>Sample Course: Introduction</h2>'
            . '[lls_protected_course]PRIVATE_LAB_LESSON: This lesson is visible only to students with an active TEST enrollment.[/lls_protected_course]'
    ), true);
    if (!is_wp_error($page_id) && $page_id) {
        update_option('lls_lab_lesson_page_id', (int) $page_id, false);
    }
});

function lls_lab_eligible_order($order) {
    if (!$order || !is_a($order, 'WC_Order') || is_a($order, 'WC_Order_Refund')) { return false; }
    if ($order->get_meta('_llqa_synthetic_test', true) !== 'yes') { return false; }
    $email = sanitize_email((string) $order->get_billing_email());
    // The lab is intentionally fail-closed for real customer addresses.
    if (!is_email($email) || substr(strtolower($email), -12) !== '@example.com') { return false; }
    foreach ($order->get_items('line_item') as $item) {
        if (!is_object($item) || !method_exists($item, 'get_product')) { continue; }
        $product = $item->get_product();
        if ($product && $product->is_virtual() && $product->get_sku() === LLS_LAB_COURSE_SKU) {
            return true;
        }
    }
    return false;
}

function lls_lab_get_student($order, $create = false) {
    $email = sanitize_email((string) $order->get_billing_email());
    $saved = (int) $order->get_meta('_lls_lab_student_user', true);
    if ($saved && get_user_by('id', $saved)) { return $saved; }
    $existing = get_user_by('email', $email);
    if ($existing) { return (int) $existing->ID; }
    if (!$create) { return 0; }

    $username = 'lls_lab_student_' . (int) $order->get_id();
    $id = wp_create_user($username, wp_generate_password(32, true, true), $email);
    if (is_wp_error($id)) { return 0; }
    return (int) $id;
}

function lls_lab_order_paid_ready($order) {
    if (!$order || !method_exists($order, 'has_status')) { return false; }
    if ($order->has_status('completed')) { return true; }
    return $order->has_status('processing')
        && method_exists($order, 'get_date_paid')
        && (bool)$order->get_date_paid('edit');
}

function lls_lab_grant_order($order_id) {
    if (!function_exists('wc_get_order')) { return false; }
    $order = wc_get_order($order_id);
    if (!lls_lab_eligible_order($order) || !lls_lab_order_paid_ready($order)) { return false; }

    // Do not restore an already-refunded enrollment via stale/replayed events.
    $saved_id = (int) $order->get_meta('_lls_lab_student_user', true);
    if ($saved_id) {
        $old_grants = get_user_meta($saved_id, LLS_LAB_GRANTS_KEY, true);
        if (is_array($old_grants) && isset($old_grants[(string) $order_id])
            && $old_grants[(string) $order_id] === 'revoked') { return false; }
    }

    $id = lls_lab_get_student($order, true);
    if (!$id) { return false; }
    $grants = get_user_meta($id, LLS_LAB_GRANTS_KEY, true);
    if (!is_array($grants)) { $grants = array(); }
    $key = (string) (int) $order_id;
    if (isset($grants[$key]) && $grants[$key] === 'revoked') { return false; }
    if (isset($grants[$key]) && $grants[$key] === 'active') { return true; }

    $grants[$key] = 'active';
    update_user_meta($id, LLS_LAB_GRANTS_KEY, $grants);
    $order->update_meta_data('_lls_lab_student_user', $id);
    $order->update_meta_data('_lls_lab_grant_events', 1);
    $order->save();
    return true;
}

function lls_lab_revoke_order($order_id) {
    if (!function_exists('wc_get_order')) { return false; }
    $order = wc_get_order($order_id);
    if (!lls_lab_eligible_order($order) || !$order->has_status(array('refunded','cancelled','failed'))) { return false; }
    $id = lls_lab_get_student($order, false);
    if (!$id) { return false; }
    $grants = get_user_meta($id, LLS_LAB_GRANTS_KEY, true);
    if (!is_array($grants)) { return false; }
    $key = (string) (int) $order_id;
    if (!isset($grants[$key])) { return false; }
    if ($grants[$key] === 'revoked') { return true; }
    if ($grants[$key] !== 'active') { return false; }

    $grants[$key] = 'revoked';
    update_user_meta($id, LLS_LAB_GRANTS_KEY, $grants);
    $order->update_meta_data('_lls_lab_revoke_events', 1);
    $order->save();
    return true;
}

function lls_lab_has_access($user_id) {
    if ((int) $user_id <= 0) { return false; }
    $grants = get_user_meta((int) $user_id, LLS_LAB_GRANTS_KEY, true);
    if (!is_array($grants)) { return false; }
    foreach ($grants as $state) {
        if ($state === 'active') { return true; }
    }
    return false;
}

function lls_lab_protected_course($attrs, $content = '') {
    // Course content is rendered only when the current WordPress account has a test entitlement.
    if (!is_user_logged_in() || !lls_lab_has_access(get_current_user_id())) {
        return '<div class="lls-course-denied" data-lls-access="denied">Course access required. Please sign in with an enrolled student account.</div>';
    }
    return '<div class="lls-course-granted" data-lls-access="granted">'
        . do_shortcode((string) $content) . '</div>';
}
