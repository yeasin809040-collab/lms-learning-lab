<?php
/**
 * Plugin Name: LMS Lab — 95 Milestone Stress QA
 * Description: Real WooCommerce database tests + controlled external identity/reconciliation simulations. Disposable WordPress only.
 * Version: 0.1.0
 * Requires Plugins: woocommerce
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function(){
    add_management_page('LMS Lab 95 QA','LMS Lab 95 QA','manage_woocommerce','lls95-qa','lls95_admin');
});
function lls95_admin() {
    if (!current_user_can('manage_woocommerce')) wp_die('Unauthorized');
    echo '<div class="wrap"><h1>95% milestone — WordPress Reliability and Identity QA</h1><p>All WooCommerce orders and refunds are synthetic. Remote identity and API state are <strong>simulated</strong>. This does not contact TalentLMS.</p>';
    if (!empty($_POST['lls95_run'])) {
        check_admin_referer('lls95_run','lls95_nonce');
        update_option('lls95_last_report',array('time'=>gmdate('c'),'result'=>lls95_qa_run()),false);
    }
    $saved=get_option('lls95_last_report');
    if (is_array($saved) && isset($saved['result'],$saved['time'])) {
        $r=$saved['result'];
        echo '<h2>95 QA: '.($r['failed']===0?'PASS':'FAIL').'</h2><p><strong>'.esc_html($r['passed']).' passed / '.esc_html($r['failed']).' failed</strong> (UTC '.esc_html($saved['time']).')</p>';
        echo '<p>Order IDs: '.esc_html(implode(',',$r['orders'])).'; Refund IDs: '.esc_html(implode(',',$r['refunds'])).'</p>';
        echo '<table class="widefat striped"><thead><tr><th>Test</th><th>Result</th><th>Details</th></tr></thead><tbody>';
        foreach($r['checks'] as $c) echo '<tr><td>'.esc_html($c['name']).'</td><td><strong>'.($c['pass']?'PASS':'FAIL').'</strong></td><td>'.esc_html($c['details']).'</td></tr>';
        echo '</tbody></table>';
    } else echo '<p>No saved test report in this WordPress instance.</p>';
    echo '<form method="post">'; wp_nonce_field('lls95_run','lls95_nonce');
    echo '<p><button class="button button-primary" name="lls95_run" value="1" type="submit">Run New Test QA</button></p></form></div>';
}
function lls95_qa_run() {
    $r=array('passed'=>0,'failed'=>0,'checks'=>array(),'orders'=>array(),'refunds'=>array());
    $check=function($name,$pass,$details='')use(&$r){
        $b=(bool)$pass;
        $r['checks'][]=array('name'=>$name,'pass'=>$b,'details'=>(string)$details);
        $r[$b?'passed':'failed']++; return $b;
    };
    $ready=function_exists('lls95_remote_resolve') && function_exists('lls95_reconcile_review_job')
        && function_exists('lls95_seed_browser_demo') && function_exists('lls85_process_job')
        && function_exists('llsqa_order') && function_exists('lls85_start_mock')
        && function_exists('lls_lab_has_access');
    if (!$check('All 95% milestone WordPress plugin functions loaded',$ready,'Actual WP + WooCommerce runtime')) return $r;
    add_filter('pre_http_request','lls85_mock_transport',1,3);
    add_filter('pre_wp_mail','llsqa_no_mail',99,1);
    try {
        $demo = get_option('lls95_browser_demo',array());
        $demo_id = isset($demo['user_id'])?(int)$demo['user_id']:0;
        $check('Disposable browser-login test student exists',
            $demo_id>0 && (bool)get_user_by('id',$demo_id),
            'WordPress synthetic user: '.$demo_id);
        $demo_order=isset($demo['order_id'])?(int)$demo['order_id']:0;
        $check('Browser demo funded by completed synthetic WooCommerce order',
            $demo_order>0 && wc_get_order($demo_order) && wc_get_order($demo_order)->has_status('completed'),
            'No real money; order #'.$demo_order);
        $check('Browser student has active local course entitlement',
            $demo_id>0 && lls_lab_has_access($demo_id),
            'WordPress user metadata');
        $page_id=isset($demo['portal_id'])?(int)$demo['portal_id']:0;
        $page=$page_id?get_post($page_id):null;
        $check('Published login portal includes actual WordPress login form',
            $page && $page->post_status==='publish'
            && strpos($page->post_content,'[lls95_student_portal]')!==false,
            'Portal WordPress page ID '.$page_id);
        $pass_user=$demo_id ? get_user_by('id',$demo_id):false;
        $check('Student role is subscriber (not administrator)',
            $pass_user && in_array('subscriber',$pass_user->roles,true)
            && !in_array('administrator',$pass_user->roles,true),
            'Least privilege');

        $email='lls95-ref-'.strtolower(wp_generate_password(10,false,false)).'@example.com';
        $ref='wp-test-'.strtolower(wp_generate_password(7,false,false));
        $records=array(array('id'=>814,'email'=>strtoupper($email),'integration_user_id'=>$ref));
        $match=lls95_remote_resolve($email,$records,$ref);
        $check('Remote user identity matches exact integration reference',
            $match['state']==='matched' && $match['id']===814,
            'Synthetic remote external user ID 814');
        $miss=lls95_remote_resolve($email,array(),$ref);
        $check('Remote identity missing is not silently created',
            $miss['state']==='missing','Explicit missing result');
        $clash=lls95_remote_resolve($email,array(
            array('id'=>814,'email'=>$email,'integration_user_id'=>$ref),
            array('id'=>815,'email'=>$email,'integration_user_id'=>$ref)
        ),$ref);
        $check('Duplicate external user identity is blocked',
            $clash['state']==='ambiguous','No guess among duplicate records');
        $mismatch=lls95_remote_resolve($email,array(array(
            'id'=>814,'email'=>$email,'integration_user_id'=>'other-ref')),$ref);
        $check('Mismatched integration reference is blocked',
            $mismatch['state']==='conflict','No cross-account enrollment');
        $invalid=lls95_remote_resolve('not-an-email',$records,$ref);
        $check('Invalid identity email rejected',$invalid['state']==='invalid','No remote request');
        $non_test=lls95_remote_resolve('real@nonexample.org',$records,$ref);
        $check('Non-synthetic identity rejected',$non_test['state']==='invalid','Test-only safety');

        $pid=(int)wc_get_product_id_by_sku('LIL-COURSE-001');
        $product=$pid ? wc_get_product($pid) : false;
        if (!$check('Existing WooCommerce course mapped', $product && $product->is_virtual(),
            'Product ID '.$pid)) return $r;

        $one=llsqa_order($product,$email,true);
        $r['orders'][]=(int)$one->get_id();
        $one->update_status('completed','Synthetic remote consistency stress test',true);
        $one=wc_get_order($one->get_id());
        $uid=(int)$one->get_meta('_lls_lab_student_user',true);
        $key=$one->get_id().':enroll';
        $jobs=lls85_jobs();
        $check('Completed order created exactly one pending sync job',
            isset($jobs[$key]) && $jobs[$key]['state']==='pending',
            'Order '.$one->get_id());
        $check('Course access enabled before any simulated remote API call',
            $uid>0 && lls_lab_has_access($uid),'Local access active; simulated external pending');

        $now=1700000000;
        lls85_start_mock(array(array('code'=>503,'body'=>'{"error":"server_error"}')));
        $r1=lls85_process_job($key,$now);
        $check('Remote 503 write enters manual review',
            $r1['state']==='review' && $r1['attempts']===1,'Never blind-retry ambiguous write');
        $seen_before=count($GLOBALS['lls85_seen']);
        $r2=lls85_process_job($key,$now+100);
        $check('Manual review state prevents automatic API resend',
            $r2['state']==='review' && count($GLOBALS['lls85_seen'])===$seen_before,
            'No duplicate POST');

        $unknown=lls95_reconcile_review_job($key,'unknown');
        $check('Unknown remote state preserves manual-review hold',
            $unknown['state']==='review','Remote status is uncertain');
        $invalid_observation=lls95_reconcile_review_job($key,'random');
        $check('Malformed remote evidence is rejected',
            $invalid_observation['state']==='review','Only present/absent/unknown accepted');
        $confirmed=lls95_reconcile_review_job($key,'present');
        $check('Verified simulated remote presence reconciles POST without resend',
            $confirmed['state']==='done' && (int)lls85_jobs()[$key]['attempts']===1,
            'One POST attempt; manually verified mock outcome');
        $double=lls95_reconcile_review_job($key,'present');
        $check('Duplicate reconciliation leaves completed job unchanged',
            $double['state']==='done','No duplicate remote write');

        $lockkey='test-'.$one->get_id();
        $c1=lls95_claim_lock($lockkey,'worker-A');
        $c2=lls95_claim_lock($lockkey,'worker-B');
        $check('Second worker cannot claim already-held local lock',
            $c1 && !$c2,'Sequential lock-contention simulation');
        $other_release=lls95_release_lock($lockkey,'worker-B');
        $check('Non-owner cannot release a claimed lock',
            !$other_release,'Worker identity checked');
        $owner_release=lls95_release_lock($lockkey,'worker-A');
        $check('Owner can release lock and free job',$owner_release,'Cleanup succeeds');
        $c3=lls95_claim_lock($lockkey,'worker-B');
        $check('Lock can be reacquired after cleanup',$c3,'New worker claims job');
        lls95_release_lock($lockkey,'worker-B');

        $partial=wc_create_refund(array('order_id'=>$one->get_id(),'amount'=>5,
            'reason'=>'Synthetic partial refund / no payment', 'refund_payment'=>false,
            'restock_items'=>false));
        $is_partial=!is_wp_error($partial) && is_a($partial,'WC_Order_Refund');
        if ($is_partial) $r['refunds'][]=(int)$partial->get_id();
        $check('Partial WooCommerce refund created as real refund object',
            $is_partial,'Partial amount 5, gateway disabled');
        $one=wc_get_order($one->get_id());
        $check('Partial refund does not revoke active enrollment',
            $one && !$one->has_status('refunded') && lls_lab_has_access($uid),
            'Remaining paid order is still active');
        $check('Partial refund does not create a full unenroll API job',
            !isset(lls85_jobs()[$one->get_id().':unenroll']),
            'No premature external unenroll');

        $total_remaining=(float)$one->get_total()-5;
        $full=wc_create_refund(array('order_id'=>$one->get_id(),
            'amount'=>$total_remaining,'reason'=>'Synthetic remaining full refund',
            'refund_payment'=>false,'restock_items'=>false));
        $fully_ok=!is_wp_error($full)&&is_a($full,'WC_Order_Refund');
        if($fully_ok)$r['refunds'][]=(int)$full->get_id();
        $check('Refund of remaining balance created without money transfer',
            $fully_ok,'Refund amount '.$total_remaining);
        if(!$fully_ok)return $r;
        $one=wc_get_order($one->get_id());
        if(!$one->has_status('refunded')) $one->update_status('refunded','Synthetic full refund complete',true);
        $one=wc_get_order($one->get_id());
        $refundkey=$one->get_id().':unenroll';
        $check('Full refund removes local course access',
            $one->has_status('refunded')&&!lls_lab_has_access($uid),
            'Revoked after all refund amounts');
        $check('Full refund schedules exactly one DELETE API job',
            isset(lls85_jobs()[$refundkey]) && lls85_jobs()[$refundkey]['state']==='pending',
            'Unenroll job queued');

        lls85_start_mock(array(new WP_Error('http_request_failed','Simulated timeout')));
        $timeout=lls85_process_job($refundkey,$now+300);
        $check('DELETE timeout never blindly retried',
            $timeout['state']==='review' && $timeout['attempts']===1,
            'Status uncertain');
        $rem_observed=lls95_reconcile_review_job($refundkey,'absent');
        $check('Verified absent remote enrollment resolves DELETE',
            $rem_observed['state']==='done' &&
            (int)lls85_jobs()[$refundkey]['attempts']===1,
            'No duplicate DELETE');
        do_action('woocommerce_order_status_completed',$one->get_id());
        $check('Stale completed event cannot recreate refunded student access',
            !lls_lab_has_access($uid),'Status check blocks replay');
        do_action('woocommerce_order_status_refunded',$one->get_id());
        $check('Duplicate refunded hook leaves one local queue job',
            isset(lls85_jobs()[$refundkey]) &&
            (int)lls85_jobs()[$refundkey]['attempts']===1,
            'No duplicate API call');

        $jobs_json=wp_json_encode(lls85_jobs());
        $check('No learner email or fake API key leaked into persisted queue',
            strpos($jobs_json,$email)===false
            && strpos($jobs_json,'synthetic-key-never-a-real-credential')===false,
            'Queue stores only order/user IDs');
    } catch (Throwable $e) {
        $check('No unexpected runtime exception',false,$e->getMessage());
    } finally {
        lls85_stop_mock();
        remove_filter('pre_http_request','lls85_mock_transport',1);
        remove_filter('pre_wp_mail','llsqa_no_mail',99);
    }
    return $r;
}
