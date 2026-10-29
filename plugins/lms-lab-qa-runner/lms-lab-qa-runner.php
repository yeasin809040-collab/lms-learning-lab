<?php
/**
 * Plugin Name: LMS Lab — Real WooCommerce QA Runner
 * Description: Synthetic database-only WooCommerce order, bridge hook, refund and duplicate QA. Test environments only!
 * Version: 1.1.0
 * Requires Plugins: woocommerce
 */
if (!defined('ABSPATH')) exit;
add_action('admin_menu', function () {
    add_management_page('LMS Lab Real QA','LMS Lab Real QA','manage_woocommerce','lms-lab-real-qa','llqa_admin_page');
});
function llqa_admin_page() {
    if (!current_user_can('manage_woocommerce')) wp_die('Access denied.');
    echo '<div class="wrap"><h1>LMS Lab — Real WooCommerce QA</h1><p><strong>TEST ONLY:</strong> All test orders use synthetic data. No gateway payment/refund and no TalentLMS access.</p>';
    if (isset($_POST['llqa_run'])) {
        check_admin_referer('llqa_run', 'llqa_nonce');
        $result = llqa_run_tests();
        update_option('llqa_last_report',array('time'=>gmdate('c'),'result'=>$result),false);
    }
    $previous = get_option('llqa_last_report');
    if (is_array($previous) && isset($previous['time'], $previous['result'])) {
        $r = $previous['result'];
        $all = (int)$r['failed'] === 0;
        echo '<h2>Last real WooCommerce audit test: '.($all ? 'PASS' : 'FAIL').'</h2>';
        echo '<p><strong>'.esc_html($r['passed']).' passed; '.esc_html($r['failed']).' failed.</strong> '
        .'Order ID: '.esc_html($r['order_id']).'; Refund ID: '.esc_html($r['refund_id'])
        .'; UTC: '.esc_html($previous['time']).'</p>';
        echo '<table class="widefat striped"><thead><tr><th>Check</th><th>Result</th><th>Details</th></tr></thead><tbody>';
        foreach ($r['checks'] as $c) {
            echo '<tr><td>'.esc_html($c['label']).'</td><td><strong>'.($c['pass'] ? 'PASS' : 'FAIL').'</strong></td><td>'.esc_html($c['details']).'</td></tr>';
        }
        echo '</tbody></table>';
        echo '<p><strong>Scope:</strong> WooCommerce database objects and the audit-only bridge; real payment, TalentLMS enrollment, production security and external services are NOT tested.</p>';
    } else echo '<p>No recorded live QA run in this WordPress instance.</p>';
    echo '<form method="post">';
    wp_nonce_field('llqa_run','llqa_nonce');
    echo '<p><button class="button button-primary" name="llqa_run" value="1" type="submit">Run a NEW synthetic test order + refund</button></p></form></div>';
}
function llqa_event_count($order, $event) {
    if (!$order || !method_exists($order,'get_meta_data')) return 0;
    $n=0;
    foreach ($order->get_meta_data() as $m) {
        if (strpos((string)$m->key,'_llb_event_')!==0) continue;
        $v=json_decode((string)$m->value,true);
        if (is_array($v) && isset($v['event']) && $v['event']===$event) $n++;
    }
    return $n;
}
function llqa_run_tests() {
    $r=['checks'=>[],'passed'=>0,'failed'=>0,'order_id'=>0,'refund_id'=>0];
    $ck=function($pass,$label,$details='') use (&$r) {
        $ok=(bool)$pass; $r['checks'][]=['pass'=>$ok,'label'=>$label,'details'=>(string)$details];
        $r[$ok?'passed':'failed']++; return $ok;
    };
    if (!function_exists('wc_create_order') || !class_exists('WC_Product_Simple') || !function_exists('llb_capture')) {
        $ck(false,'WooCommerce + Bridge runtime','Required plugin missing');return $r;
    }
    $ck(true,'WooCommerce + Bridge loaded','Real WordPress runtime');
    try {
        $pid=(int)wc_get_product_id_by_sku('LIL-COURSE-001');
        if ($pid) $p=wc_get_product($pid);
        else {
            $p=new WC_Product_Simple();
            $p->set_name('LMS QA Test Course');
            $p->set_sku('LIL-COURSE-001');
            $p->set_virtual(true);
            $p->set_regular_price('49.00');
            $p->set_status('publish');
            $p->save();
        }
        if (!$ck($p instanceof WC_Product && $p->is_virtual() && $p->get_sku()==='LIL-COURSE-001','Virtual course product','Product ID: '.($p?$p->get_id():0))) return $r;
        $o=wc_create_order(['status'=>'pending']);
        if (!$o || is_wp_error($o)) throw new RuntimeException('wc_create_order failed');
        $o->set_billing_first_name('Synthetic');
        $o->set_billing_last_name('Test');
        $o->set_billing_email('qa-test@example.com');
        $o->update_meta_data('_llqa_synthetic_test','yes');
        $o->add_product($p,1);
        $o->calculate_totals();
        $o->save();
        $r['order_id']=(int)$o->get_id();
        $ck($r['order_id']>0 && count($o->get_items('line_item'))===1,'Order created','WooCommerce order #'.$r['order_id']);
        $o->update_status('completed','Synthetic QA; NO payment received',true);
        $o=wc_get_order($r['order_id']);
        $ck($o && $o->has_status('completed'),'Order completed','Only database status; no payment');
        $paid=llqa_event_count($o,'paid');
        $ck($paid===1,'Paid audit event captured','Count: '.$paid);
        do_action('woocommerce_order_status_completed',$r['order_id']);
        $o=wc_get_order($r['order_id']);
        $ck(llqa_event_count($o,'paid')===1,'Duplicate completed event prevented','Replay leaves 1 record');
        $ref=wc_create_refund(['order_id'=>$r['order_id'],'amount'=>(float)$o->get_total(),'reason'=>'Synthetic QA only','refund_payment'=>false,'restock_items'=>false]);
        $ok=!is_wp_error($ref) && $ref instanceof WC_Order_Refund && $ref->get_id()>0;
        $r['refund_id']=$ok?(int)$ref->get_id():0;
        $ck($ok,'Refund created','WooCommerce refund #'.$r['refund_id'].'; NO payment gateway');
        if (!$ok) return $r;
        $o=wc_get_order($r['order_id']);
        $n=llqa_event_count($o,'refund_review');
        $ck($n===1,'Refund audit event captured','Count: '.$n);
        do_action('woocommerce_order_refunded',$r['order_id'],$r['refund_id']);
        $o=wc_get_order($r['order_id']);
        $ck(llqa_event_count($o,'refund_review')===1,'Duplicate refund event prevented','Replay leaves 1 record');
        if (!$o->has_status('refunded')) $o->update_status('refunded','Synthetic QA status; no money',true);
        $o=wc_get_order($r['order_id']);
        $ck($o && $o->has_status('refunded'),'Order status refunded','WooCommerce database status');
        $ck(llqa_event_count($o,'refunded')===1,'Full refund audit event captured','One record');
        do_action('woocommerce_order_status_refunded',$r['order_id']);
        $o=wc_get_order($r['order_id']);
        $ck(llqa_event_count($o,'refunded')===1,'Duplicate refunded status prevented','Replay leaves 1 record');
    } catch (Throwable $e) {
        $ck(false,'Unexpected WooCommerce error',$e->getMessage());
    }
    return $r;
}
