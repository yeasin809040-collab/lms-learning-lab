<?php
/**
 * Plugin Name: LMS Lab — Real Browser Student Login Lab
 * Description: Explicit opt-in browser login demonstration for disposable WordPress Playground only. Creates a synthetic example.com learner and private lesson, never a real customer.
 * Version: 0.1.0
 * Requires Plugins: woocommerce
 */
if (!defined('ABSPATH')) { exit; }

define('LLS95_TEST_USERNAME', 'lls95_demo_student');
define('LLS95_TEST_EMAIL', 'lls95-browser-demo@example.com');
define('LLS95_TEST_PASSWORD', 'LabDemo95!2026');

add_shortcode('lls95_student_portal', 'lls95_student_portal');

function lls95_seed_browser_demo() {
    if (!function_exists('lls_lab_has_access') || !function_exists('wc_create_order')
        || !function_exists('lls_lab_eligible_order')) {
        return new WP_Error('lls95_missing', 'Required local WordPress plugins not loaded');
    }
    $product_id = (int) wc_get_product_id_by_sku('LIL-COURSE-001');
    $product = $product_id ? wc_get_product($product_id) : false;
    if (!$product || !$product->is_virtual()) {
        return new WP_Error('lls95_course_missing', 'Synthetic course not present');
    }
    $user = get_user_by('login', LLS95_TEST_USERNAME);
    if (!$user) {
        $id = wp_create_user(LLS95_TEST_USERNAME, LLS95_TEST_PASSWORD, LLS95_TEST_EMAIL);
        if (is_wp_error($id)) return $id;
        $user = get_user_by('id', $id);
    }
    if (!$user || $user->user_email !== LLS95_TEST_EMAIL) {
        return new WP_Error('lls95_user', 'Synthetic account mismatch');
    }
    $user->set_role('subscriber');
    // This password exists solely in a disposable publicly documented test lab.
    wp_set_password(LLS95_TEST_PASSWORD, (int) $user->ID);
    $existing_order = (int) get_option('lls95_demo_order_id', 0);
    $order = $existing_order ? wc_get_order($existing_order) : false;
    if (!$order) {
        $order = wc_create_order(array('status'=>'pending'));
        if (is_wp_error($order) || !$order) return new WP_Error('lls95_order','Synthetic order creation failed');
        $order->set_billing_email(LLS95_TEST_EMAIL);
        $order->set_billing_first_name('Playground');
        $order->set_billing_last_name('Student');
        $order->update_meta_data('_llqa_synthetic_test','yes');
        $order->add_product($product,1);
        $order->calculate_totals();
        $order->save();
        $order->update_status('completed','Synthetic browser login lab — no money',true);
        update_option('lls95_demo_order_id',(int)$order->get_id(),false);
    }
    $order = wc_get_order($order->get_id());
    if (!$order || !lls_lab_eligible_order($order)) {
        return new WP_Error('lls95_invalid_order','Browser lab test order invalid');
    }
    if (!$order->has_status('completed')) {
        $order->update_status('completed','Reset synthetic browser lab order',true);
    }
    lls_lab_grant_order($order->get_id());
    if (!lls_lab_has_access((int)$user->ID)) {
        return new WP_Error('lls95_no_grant','Browser student grant missing');
    }
    $page = get_page_by_path('lls95-student-portal');
    $page_id = $page ? (int)$page->ID : wp_insert_post(array(
        'post_type'=>'page','post_title'=>'95% Student Browser Login Lab',
        'post_name'=>'lls95-student-portal','post_status'=>'publish',
        'post_content'=>'[lls95_student_portal]'
    ),true);
    if (is_wp_error($page_id)) return $page_id;
    update_option('lls95_demo_page_id',(int)$page_id,false);
    return array('user_id'=>(int)$user->ID,'order_id'=>(int)$order->get_id(),
        'portal_id'=>(int)$page_id,
        'lesson_id'=>(int)get_option('lls_lab_lesson_page_id',0),
        'ready'=>true);
}

function lls95_student_portal() {
    $uid = get_current_user_id();
    $user = $uid ? wp_get_current_user() : null;
    $enrolled = $uid && function_exists('lls_lab_has_access') && lls_lab_has_access($uid);
    $state = !$uid ? 'guest' : ($enrolled ? 'enrolled' : 'not-enrolled');
    $lesson_id = (int)get_option('lls_lab_lesson_page_id',0);
    $lesson_link = $lesson_id ? get_permalink($lesson_id) : '#';
    $portal_url = get_permalink((int)get_option('lls95_demo_page_id',0));
    $html = '<section class="lls95-login-lab" data-login-state="'.esc_attr($state).'">'
        .'<h2>Real WordPress Student Browser Session Test</h2>'
        .'<p><strong>Current session:</strong> <span id="lls95-status">'.esc_html($state).'</span></p>'
        .'<p><strong>Logged-in user:</strong> <span id="lls95-user">'.esc_html($user ? $user->user_login : 'guest').'</span></p>'
        .'<p><strong>Course entitlement:</strong> '.($enrolled ? 'ACTIVE' : 'DENIED').'</p>'
        .'<p><a id="lls95-lesson-link" href="'.esc_url($lesson_link).'">Open protected course lesson</a></p>';
    if ($uid) {
        $html .= '<p><a id="lls95-logout-link" href="'.esc_url(wp_logout_url($portal_url)).'">Log out of WordPress</a></p>';
    } else {
        $html .= '<h3>Sign in with the synthetic student</h3>'
            .wp_login_form(array('echo'=>false,'redirect'=>$portal_url,
                'label_username'=>'Username','label_password'=>'Password',
                'label_log_in'=>'Log In'));
    }
    $html .= '<p><small>Disposable test site only. No actual TalentLMS or money.</small></p></section>';
    return $html;
}
