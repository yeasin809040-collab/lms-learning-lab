<?php
/**
 * Plugin Name: Learning Lab — WooCommerce LMS Bridge (Audit Mode)
 * Description: Records allowlisted course order lifecycle events; no live LMS calls or access changes.
 * Version: 0.3.2
 * Requires Plugins: woocommerce
 */
if (!defined('ABSPATH')) exit;
add_action('woocommerce_order_status_completed', 'llb_order_completed', 20, 1);
// A successfully paid virtual-only order commonly remains Processing.
add_action('woocommerce_payment_complete', 'llb_order_completed', 20, 1);
add_action('woocommerce_order_status_refunded', 'llb_order_refunded', 20, 1);
// Negative order outcomes must be visible to the synthetic lab audit.
add_action('woocommerce_order_status_cancelled', 'llb_order_cancelled', 20, 1);
add_action('woocommerce_order_status_failed', 'llb_order_failed', 20, 1);
add_action('woocommerce_order_refunded', 'llb_partial_refund', 20, 2);
function llb_order_completed($order_id) { return llb_capture($order_id, 'paid'); }
function llb_order_refunded($order_id) { return llb_capture($order_id, 'refunded'); }
function llb_order_cancelled($order_id) { return llb_capture($order_id, 'cancelled'); }
function llb_order_failed($order_id) { return llb_capture($order_id, 'failed'); }
function llb_partial_refund($order_id, $refund_id) {
    if ((int)$refund_id <= 0) return false;
    return llb_capture($order_id, 'refund_review', (int)$refund_id);
}
function llb_paid_ready($order) {
    return $order && method_exists($order, 'has_status')
        && ($order->has_status('completed')
            || ($order->has_status('processing')
                && method_exists($order, 'get_date_paid')
                && $order->get_date_paid('edit')));
}
function llb_capture($order_id, $event, $refund_id = 0) {
    if (!function_exists('wc_get_order') || !in_array($event, array('paid','refunded','refund_review','cancelled','failed'), true)) return false;
    $order = wc_get_order($order_id);
    if (!$order || !method_exists($order, 'get_items')) return false;
    // This public repository is a synthetic lab: never persist real billing
    // email addresses into audit metadata, even for a matching course SKU.
    if ($order->get_meta('_llqa_synthetic_test', true) !== 'yes') return false;
    if ($event === 'paid' && !llb_paid_ready($order)) return false;
    if ($event === 'refunded' && (!method_exists($order, 'has_status') || !$order->has_status('refunded'))) return false;
    if (($event === 'cancelled' || $event === 'failed') && !$order->has_status($event)) return false;
    if ($event === 'refund_review') {
        if ($refund_id <= 0) return false;
        $refund = wc_get_order($refund_id);
        if (!$refund || !method_exists($refund, 'get_parent_id') || (int)$refund->get_parent_id() !== (int)$order_id) return false;
    }
    $email = sanitize_email($order->get_billing_email());
    if (!$email || !is_email($email) || substr(strtolower($email), -12) !== '@example.com') return false;
    $logged = 0;
    foreach ($order->get_items('line_item') as $item) {
        if (!is_object($item) || !method_exists($item, 'get_product')) continue;
        $product = $item->get_product();
        if (!$product || !$product->is_virtual() || $product->get_sku() !== 'LIL-COURSE-001') continue;
        $product_id = (int)$item->get_product_id();
        $item_id = method_exists($item, 'get_id') ? (int)$item->get_id() : 0;
        if ($product_id <= 0 || $item_id <= 0) continue;
        $key = '_llb_event_' . md5($event . ':' . $item_id . ':' . $refund_id);
        if ($order->get_meta($key, true)) continue;
        $record = array('event'=>$event,'order_id'=>(int)$order_id,'line_item_id'=>$item_id,
            'product_id'=>$product_id,'email'=>$email,'refund_id'=>(int)$refund_id,
            'mode'=>'audit_only','timestamp'=>gmdate('c'));
        $order->update_meta_data($key, wp_json_encode($record));
        $logged++;
    }
    if ($logged) $order->save();
    return $logged;
}
