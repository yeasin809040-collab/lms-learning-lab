<?php
/**
 * Plugin Name: LMS Learning Lab — Student Access QA 75
 * Description: Runs synthetic WordPress student creation, course access, refund revocation, and replay tests. For isolated test WordPress installations only.
 * Version: 0.1.0
 * Requires Plugins: woocommerce
 */
if (!defined('ABSPATH')) { exit; }

add_action('admin_menu', function () {
    add_management_page('LMS Lab Student Access QA', 'LMS Lab Student Access QA',
        'manage_woocommerce', 'lls-lab-access-qa', 'llsqa_admin_page');
});

function llsqa_admin_page() {
    if (!current_user_can('manage_woocommerce')) { wp_die('Access denied'); }
    echo '<div class="wrap"><h1>LMS Lab — Student Access Integration QA</h1>';
    echo '<p><strong>TEST ENVIRONMENT ONLY:</strong> Synthetic example.com learners and WooCommerce database orders; no money, live TalentLMS enrollment, or external API calls.</p>';
    if (!empty($_POST['llsqa_run'])) {
        check_admin_referer('llsqa_run','llsqa_nonce');
        $result = llsqa_run();
        update_option('llsqa_last_report', array('time' => gmdate('c'), 'result' => $result), false);
    }
    $saved = get_option('llsqa_last_report');
    if (is_array($saved) && isset($saved['result'], $saved['time'])) {
        $r = $saved['result'];
        echo '<h2>Live student access QA: '.($r['failed'] === 0 ? 'PASS' : 'FAIL').'</h2>';
        echo '<p><strong>'.esc_html($r['passed']).' passed / '.esc_html($r['failed']).' failed</strong>. ';
        echo 'Orders: '.esc_html(implode(', ', $r['orders'])).'; Refunded: '.esc_html(implode(', ', $r['refunds']));
        echo '; Test time (UTC): '.esc_html($saved['time']).'</p>';
        echo '<table class="widefat striped"><thead><tr><th>Check</th><th>Result</th><th>Evidence</th></tr></thead><tbody>';
        foreach ($r['checks'] as $check) {
            echo '<tr><td>'.esc_html($check['name']).'</td><td><strong>'.($check['pass'] ? 'PASS' : 'FAIL').'</strong></td><td>'.esc_html($check['detail']).'</td></tr>';
        }
        echo '</tbody></table><p>Scope: WordPress local entitlement demo only. Does NOT confirm TalentLMS API integration.</p>';
    } else {
        echo '<p>No saved student access QA report found in this instance.</p>';
    }
    echo '<form method="post">';
    wp_nonce_field('llsqa_run','llsqa_nonce');
    echo '<p><button class="button button-primary" type="submit" name="llsqa_run" value="1">Run New Synthetic Student Access Tests</button></p></form></div>';
}

function llsqa_no_mail($pre) { return true; }
function llsqa_order($product, $email, $lab = true) {
    $order = wc_create_order(array('status' => 'pending'));
    if (!$order || is_wp_error($order)) { throw new RuntimeException('wc_create_order failed'); }
    $order->set_billing_first_name('Synthetic');
    $order->set_billing_last_name('Learner');
    $order->set_billing_email($email);
    if ($lab) { $order->update_meta_data('_llqa_synthetic_test','yes'); }
    $order->add_product($product, 1);
    $order->calculate_totals();
    $order->save();
    return $order;
}
function llsqa_render_as($user_id) {
    $old = get_current_user_id();
    try {
        wp_set_current_user((int) $user_id);
        return do_shortcode('[lls_protected_course]PRIVATE_LAB_LESSON[/lls_protected_course]');
    } finally {
        wp_set_current_user($old);
    }
}
function llsqa_refund($order) {
    $refund = wc_create_refund(array(
        'order_id' => $order->get_id(), 'amount' => (float) $order->get_total(),
        'reason' => 'Synthetic learning lab QA', 'refund_payment' => false,
        'restock_items' => false
    ));
    if (is_wp_error($refund)) { throw new RuntimeException($refund->get_error_message()); }
    $order = wc_get_order($order->get_id());
    if (!$order->has_status('refunded')) {
        $order->update_status('refunded', 'Synthetic QA access revocation — no payment', true);
    }
    return $refund;
}

function llsqa_run() {
    $r = array('checks'=>array(),'passed'=>0,'failed'=>0,'orders'=>array(),'refunds'=>array());
    $check = function($name, $passed, $detail = '') use (&$r) {
        $pass = (bool) $passed;
        $r['checks'][] = array('name'=>$name,'pass'=>$pass,'detail'=>(string)$detail);
        $r[$pass ? 'passed' : 'failed']++;
        return $pass;
    };
    $ready = function_exists('lls_lab_grant_order') && function_exists('lls_lab_revoke_order')
        && function_exists('lls_lab_has_access') && function_exists('llb_capture')
        && function_exists('wc_create_order') && function_exists('wc_create_refund');
    if (!$check('Real WooCommerce + audit bridge + access plugin loaded', $ready, 'WordPress plugin functions')) { return $r; }
    add_filter('pre_wp_mail', 'llsqa_no_mail', 99, 1);
    try {
        $pid = (int) wc_get_product_id_by_sku('LIL-COURSE-001');
        if ($pid) { $course = wc_get_product($pid); }
        else {
            $course = new WC_Product_Simple();
            $course->set_name('LMS QA Test Course');
            $course->set_sku('LIL-COURSE-001');
            $course->set_virtual(true);
            $course->set_regular_price('49.00');
            $course->set_status('publish');
            $course->save();
        }
        if (!$check('Virtual course SKU mapped', $course instanceof WC_Product && $course->is_virtual()
            && $course->get_sku() === 'LIL-COURSE-001', 'Product ID: '.($course ? $course->get_id() : 0))) { return $r; }

        $suffix = strtolower(wp_generate_password(12, false, false));
        $student_email = 'lls-' . $suffix . '@example.com';
        $first = llsqa_order($course, $student_email);
        $r['orders'][] = $first->get_id();
        do_action('woocommerce_order_status_completed', $first->get_id());
        $check('Pending order cannot grant access', !get_user_by('email',$student_email)
            && !(int) wc_get_order($first->get_id())->get_meta('_lls_lab_student_user', true), 'Pending status + injected completed hook');

        $unmarked_email = 'lls-unmarked-'.$suffix.'@example.com';
        $unmarked = llsqa_order($course, $unmarked_email, false);
        $r['orders'][] = $unmarked->get_id();
        $unmarked->update_status('completed', 'Unmarked negative test', true);
        $check('Non-test order cannot create student', !get_user_by('email', $unmarked_email), 'No _llqa_synthetic_test flag');

        $other = new WC_Product_Simple();
        $other->set_name('Not A Course - QA');
        $other->set_sku('NOT-COURSE-'.$suffix);
        $other->set_virtual(true);
        $other->set_regular_price('5');
        $other->set_status('publish');
        $other->save();
        $wrong_email = 'lls-wrong-'.$suffix.'@example.com';
        $wrong = llsqa_order($other, $wrong_email);
        $r['orders'][] = $wrong->get_id();
        $wrong->update_status('completed', 'Wrong SKU negative test', true);
        $check('Non-course SKU cannot create student', !get_user_by('email',$wrong_email), 'Unlisted SKU ignored');

        $first->update_status('completed','Synthetic completed - no payment',true);
        $first = wc_get_order($first->get_id());
        $uid = (int) $first->get_meta('_lls_lab_student_user',true);
        $student = $uid ? get_user_by('id',$uid) : false;
        $check('Completed order creates WordPress learner', $uid > 0 && $student
            && $student->user_email === $student_email, 'Learner user ID: '.$uid);
        $check('Course entitlement granted', lls_lab_has_access($uid), 'WordPress user meta entitlement active');
        $check('Protected lesson visible to enrolled learner', strpos(llsqa_render_as($uid),'PRIVATE_LAB_LESSON')!==false, 'Shortcode access=granted');
        $check('Guest blocked from private lesson', strpos(llsqa_render_as(0),'PRIVATE_LAB_LESSON') === false, 'Shortcode access=denied');
        $check('Unrelated account blocked from lesson', strpos(llsqa_render_as(get_current_user_id()),'PRIVATE_LAB_LESSON')===false, 'Administrator has no synthetic grant');

        $count_before = (int) $first->get_meta('_lls_lab_grant_events',true);
        do_action('woocommerce_order_status_completed',$first->get_id());
        $first = wc_get_order($first->get_id());
        $grants = get_user_meta($uid, LLS_LAB_GRANTS_KEY, true);
        $check('Duplicate completion does not double grant', $count_before===1
            && (int)$first->get_meta('_lls_lab_grant_events',true)===1
            && is_array($grants) && count($grants)===1, 'One active order grant');
        $check('Paid audit bridge event retained', function_exists('llqa_event_count')
            && llqa_event_count($first,'paid')===1, 'Audit count = 1');

        $second = llsqa_order($course,$student_email);
        $r['orders'][] = $second->get_id();
        $second->update_status('completed','Second synthetic order',true);
        $second = wc_get_order($second->get_id());
        $uid2 = (int) $second->get_meta('_lls_lab_student_user',true);
        $check('Repeat purchase reuses learner', $uid2 === $uid && $uid>0, 'Same WordPress student');
        $grants = get_user_meta($uid, LLS_LAB_GRANTS_KEY,true);
        $check('Two course orders have independent grants',is_array($grants) && count($grants)===2
            && lls_lab_has_access($uid),'Two active order-based entitlements');

        $refund1 = llsqa_refund($first);
        $r['refunds'][] = $refund1->get_id();
        $first = wc_get_order($first->get_id());
        $grants = get_user_meta($uid, LLS_LAB_GRANTS_KEY,true);
        $check('First refunded order entitlement revoked', $first->has_status('refunded')
            && isset($grants[(string)$first->get_id()]) && $grants[(string)$first->get_id()] === 'revoked',
            'First order state revoked; no money');
        $check('Second active order preserves course access', lls_lab_has_access($uid)
            && strpos(llsqa_render_as($uid),'PRIVATE_LAB_LESSON')!==false, 'No incorrect blanket suspension');
        do_action('woocommerce_order_status_refunded',$first->get_id());
        $first = wc_get_order($first->get_id());
        $check('First refund replay does not duplicate revocation',
            (int)$first->get_meta('_lls_lab_revoke_events',true) === 1, 'Revoke event count 1');

        $refund2 = llsqa_refund($second);
        $r['refunds'][] = $refund2->get_id();
        $second = wc_get_order($second->get_id());
        $grants = get_user_meta($uid, LLS_LAB_GRANTS_KEY, true);
        $all_revoked = is_array($grants) && count($grants)===2 && count(array_filter($grants,function($state){ return $state==='revoked'; }))===2;
        $check('Second refund revokes final active grant', $second->has_status('refunded') && $all_revoked, 'Both order grants revoked');
        $check('Learner cannot access after all refunds', !lls_lab_has_access($uid)
            && strpos(llsqa_render_as($uid),'PRIVATE_LAB_LESSON')===false, 'Protected lesson denied');
        do_action('woocommerce_order_status_refunded',$second->get_id());
        $second = wc_get_order($second->get_id());
        $check('Second refund replay is idempotent', (int)$second->get_meta('_lls_lab_revoke_events',true)===1, 'One revoke event');
        do_action('woocommerce_order_status_completed',$second->get_id());
        $check('Stale completion cannot restore refunded access', !lls_lab_has_access($uid)
            && (int)wc_get_order($second->get_id())->get_meta('_lls_lab_grant_events',true)===1,
            'Refund status blocks replayed completed hook');

        $pageid = (int)get_option('lls_lab_lesson_page_id', 0);
        $post = $pageid ? get_post($pageid) : null;
        $check('Real protected WordPress lesson page exists', $post && $post->post_status==='publish'
            && strpos($post->post_content,'[lls_protected_course]')!==false,
            'Published page ID: '.$pageid);
    } catch (Throwable $e) {
        $check('Unexpected QA runtime error',false,$e->getMessage());
    } finally {
        remove_filter('pre_wp_mail','llsqa_no_mail',99);
    }
    return $r;
}
