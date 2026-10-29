<?php
/**
 * Independent double-audit regressions with real WooCommerce/MySQL.
 * Uses synthetic-only orders and simulated payment_complete; no real payment.
 */
if (!defined('ABSPATH') || !function_exists('wc_create_order')
    || !function_exists('lls85_queue_order') || !function_exists('llqa_event_count')) {
    fwrite(STDERR, "Missing WP/WooCommerce QA dependencies\n"); exit(1);
}
$pass=0; $fail=0; $outbound=0;
$ck=function($condition,$name)use(&$pass,&$fail) {
    if ($condition){$pass++;echo "DOUBLE AUDIT PASS: ".$name."\n";}
    else {$fail++;echo "DOUBLE AUDIT FAIL: ".$name."\n";}
};
$mail_block=function($pre){return true;};
$deny_http=function($pre,$args,$url)use(&$outbound) {
    if (strpos($url, 'talentlms.com') !== false) {$outbound++;}
    return new WP_Error('blocked','No external traffic in synthetic double audit');
};
add_filter('pre_wp_mail',$mail_block,99,1);
add_filter('pre_http_request',$deny_http,99,3);
try {
    $product=wc_get_product((int)wc_get_product_id_by_sku('LIL-COURSE-001'));
    if (!$product || !$product->is_virtual()) throw new RuntimeException('Synthetic course unavailable');
    $suffix=strtolower(wp_generate_password(9,false,false));
    $create=function($email,$marked)use($product) {
        $o=wc_create_order(array('status'=>'pending'));
        if (!$o || is_wp_error($o)) throw new RuntimeException('Could not create QA order');
        $o->set_billing_email($email);
        $o->set_billing_first_name('Synthetic');
        $o->set_billing_last_name('Crosscheck');
        if ($marked) $o->update_meta_data('_llqa_synthetic_test','yes');
        $o->add_product($product,1);
        $o->calculate_totals();
        $o->save();
        return $o;
    };

    // A matching SKU in a real-looking/non-test WooCommerce order must not
    // write a billing email into the audit bridge, create a student or enqueue.
    $external='synthetic-'.$suffix.'@example.net';
    $nonlab=$create($external,true);
    $nonlab_id=$nonlab->get_id();
    $nonlab->payment_complete('TEST-NONLAB-'.$suffix);
    $nonlab=wc_get_order($nonlab_id);
    $ck(llqa_event_count($nonlab,'paid')===0, 'Non-example.com customer is NOT stored in audit metadata');
    $ck((int)$nonlab->get_meta('_lls_lab_student_user',true)===0 && !get_user_by('email',$external),
        'Nonlab customer is NOT automatically registered');
    $ck(!isset(lls85_jobs()[$nonlab_id.':enroll']), 'Nonlab customer never queues LMS API job');

    $unflagged_email='unflagged-'.$suffix.'@example.com';
    $unflagged=$create($unflagged_email,false);
    $unflagged_id=$unflagged->get_id();
    $unflagged->payment_complete('TEST-UNMARKED-'.$suffix);
    $unflagged=wc_get_order($unflagged_id);
    $ck(llqa_event_count($unflagged,'paid')===0,'Unmarked order is NOT recorded as paid synthetic audit');
    $ck(!get_user_by('email',$unflagged_email),'Unmarked order does not create a learner');
    $ck(!isset(lls85_jobs()[$unflagged_id.':enroll']),'Unmarked order does not queue a sync');

    // A paid and then cancelled WooCommerce order must lose access and should
    // queue a single safe simulated remote unenroll (if no other active grants).
    $cancel_email='cancel-'.$suffix.'@example.com';
    $cancelled=$create($cancel_email,true);
    $cancelled_id=$cancelled->get_id();
    $ck(!get_user_by('email',$cancel_email), 'Pending cancellation specimen has no learner');
    $cancelled->payment_complete('TEST-PAID-CANCEL-'.$suffix);
    $cancelled=wc_get_order($cancelled_id);
    $uid=(int)$cancelled->get_meta('_lls_lab_student_user',true);
    $ck($uid>0 && lls_lab_has_access($uid) && $cancelled->has_status('processing'),
        'Gateway-confirmed Processing order initially grants the learner');
    $ck(isset(lls85_jobs()[$cancelled_id.':enroll']),'Paid Processing order queues enrollment');
    $cancelled->update_status('cancelled','Synthetic double-audit cancellation: no payment',true);
    $cancelled=wc_get_order($cancelled_id);
    $grants=get_user_meta($uid,LLS_LAB_GRANTS_KEY,true);
    $ck(!lls_lab_has_access($uid) && $grants[(string)$cancelled_id]==='revoked',
        'Cancellation removes protected lesson entitlement');
    $ck(llqa_event_count($cancelled,'cancelled')===1,'Cancellation audit event recorded exactly once');
    $ck(isset(lls85_jobs()[$cancelled_id.':unenroll'])
         && lls85_jobs()[$cancelled_id.':unenroll']['state']==='pending',
        'Cancelled paid order queues safe DELETE simulation');
    $bad_enroll=lls85_process_job($cancelled_id.':enroll',time()+1000);
    $ck($bad_enroll['state']==='cancelled','Queued enrollment cannot execute after cancellation');
    do_action('woocommerce_order_status_cancelled',$cancelled_id);
    do_action('woocommerce_payment_complete',$cancelled_id,'STILL-NO-MONEY');
    $cancelled=wc_get_order($cancelled_id);
    $ck(llqa_event_count($cancelled,'cancelled')===1
         && (int)$cancelled->get_meta('_lls_lab_revoke_events',true)===1,
        'Repeated cancellation event cannot duplicate audit/revocation');
    $ck(!lls_lab_has_access($uid)
         && lls85_jobs()[$cancelled_id.':enroll']['state']==='cancelled',
        'Replayed paid hook cannot restore cancelled course access');

    // For paid orders subsequently moved to failed, enforce the same policy.
    $failed_email='failed-'.$suffix.'@example.com';
    $failed_order=$create($failed_email,true);
    $failed_id=$failed_order->get_id();
    $failed_order->payment_complete('TEST-PAID-FAILED-'.$suffix);
    $failed_order=wc_get_order($failed_id);
    $failed_uid=(int)$failed_order->get_meta('_lls_lab_student_user',true);
    $ck($failed_uid>0 && lls_lab_has_access($failed_uid),'Second paid synthetic learner receives initial access');
    $failed_order->update_status('failed','Synthetic charge reversal simulation',true);
    $failed_order=wc_get_order($failed_id);
    $ck(!lls_lab_has_access($failed_uid),'Failed order removes learner access');
    $ck(llqa_event_count($failed_order,'failed')===1,'Failed order recorded in synthetic audit');
    $ck(isset(lls85_jobs()[$failed_id.':unenroll']),'Failed order queues remote unenroll simulation');
    $ck(lls85_process_job($failed_id.':enroll',time()+1000)['state']==='cancelled',
        'Failed order cannot later process its old enrollment');

    $ck(function_exists('lls95_disposable_demo_enabled')
         && lls95_disposable_demo_enabled()===true,
         'Only the disposable wp-env test sets explicit public-password demo opt-in');
    $ck($outbound===0,'No real TalentLMS request during independent cross-check');
} catch(Throwable $e) {
    $fail++;
    echo "DOUBLE AUDIT EXCEPTION: ".$e->getMessage()."\n";
} finally {
    remove_filter('pre_wp_mail',$mail_block,99);
    remove_filter('pre_http_request',$deny_http,99);
}
echo "WORDPRESS_DOUBLE_AUDIT_TOTAL: ".$pass." PASS / ".$fail." FAIL\n";
exit($fail?1:0);
