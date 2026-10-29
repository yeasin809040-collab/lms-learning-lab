<?php
/**
 * Real WooCommerce processing-status payment regression for virtual-only course.
 * Uses WC_Order::payment_complete() with a synthetic order; NO real gateway or money.
 * This deliberately tests the normal virtual-only paid order path that old
 * "completed only" QA missed.
 */
if (!defined('ABSPATH') || !function_exists('wc_create_order') || !function_exists('lls85_queue_order')) {
    fwrite(STDERR, "WooCommerce integration environment missing\n");
    exit(1);
}
$pass=0; $fail=0;
$ck=function($ok,$label)use(&$pass,&$fail) {
    if ($ok) {++$pass;echo "PAYMENT FLOW PASS: {$label}\n";}
    else {++$fail;echo "PAYMENT FLOW FAIL: {$label}\n";}
};
$suffix=strtolower(wp_generate_password(9,false,false));
$email='payment-regression-'.$suffix.'@example.com';
$p=wc_get_product((int)wc_get_product_id_by_sku('LIL-COURSE-001'));
if (!$p) {
    fwrite(STDERR, "Synthetic course product missing\n");exit(1);
}
$ck($p->is_virtual() && !$p->is_downloadable(),'Lab course is Virtual but NOT Downloadable');
$order=wc_create_order(array('status'=>'pending'));
if (!$order || is_wp_error($order)) {
    fwrite(STDERR,"Could not create synthetic payment order\n");exit(1);
}
$order->set_billing_email($email);
$order->set_billing_first_name('Synthetic');
$order->set_billing_last_name('Payment');
$order->update_meta_data('_llqa_synthetic_test','yes');
$order->add_product($p,1);
$order->calculate_totals();
$order->save();
$id=(int)$order->get_id();
$ck($order->has_status('pending'),'Before payment order is pending');
$ck(!get_user_by('email',$email),'Pending order has no student');
$ck(!isset(lls85_jobs()[$id.':enroll']),'Pending order has no API queue job');

// Real WooCommerce payment_complete API, with a TEST transaction ID.
// This does not charge money; it simulates gateway confirmation through WC.
$paid=$order->payment_complete('SYNTHETIC-NO-MONEY-'.$suffix);
$order=wc_get_order($id);
$ck($paid && $order->has_status('processing'),'WooCommerce payment_complete sets virtual-only course order to PROCESSING');
$ck($order->get_date_paid('edit')!==null,'WooCommerce recorded the payment-complete timestamp');
$student_id=(int)$order->get_meta('_lls_lab_student_user',true);
$ck($student_id>0 && (bool)get_user_by('id',$student_id),'Paid processing order provisions synthetic WordPress student');
$ck($student_id>0 && lls_lab_has_access($student_id),'Paid processing order grants sample course access');
$ck(function_exists('llqa_event_count') && llqa_event_count($order,'paid')===1,'Audit bridge records exactly one paid event');
$jobs=lls85_jobs();
$ck(isset($jobs[$id.':enroll']) && $jobs[$id.':enroll']['state']==='pending','Paid processing order queues one mock API job');

// Confirm idempotency if WooCommerce retries the SAME completion hook.
do_action('woocommerce_payment_complete',$id,'SYNTHETIC-NO-MONEY-'.$suffix);
$order=wc_get_order($id);
$jobs=lls85_jobs();
$grants=$student_id ? get_user_meta($student_id,LLS_LAB_GRANTS_KEY,true) : array();
$ck(llqa_event_count($order,'paid')===1,'Replay does not duplicate audit metadata');
$ck($student_id>0 && (int)$order->get_meta('_lls_lab_grant_events',true)===1,'Replay does not double grant entitlement');
$ck(isset($jobs[$id.':enroll']) && count(array_filter(array_keys($jobs),function($k)use($id){return $k===$id.':enroll';}))===1,'Replay does not double-queue API work');

echo "REAL_WOOCOMMERCE_PAYMENT_FLOW_TOTAL: {$pass} PASS / {$fail} FAIL\n";
exit($fail?1:0);
