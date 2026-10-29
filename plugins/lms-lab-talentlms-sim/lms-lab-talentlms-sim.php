<?php
/**
 * Plugin Name: LMS Learning Lab — TalentLMS V2 API Transport Simulator
 * Description: Test-only, fail-closed WordPress HTTP client, audit-safe retry queue and controlled TalentLMS API V2 responses. NEVER sends real TalentLMS traffic.
 * Version: 0.1.2
 * Requires Plugins: woocommerce
 */
if (!defined('ABSPATH')) exit;

define('LLS85_ENDPOINT', '/api/v2/enrollments');
define('LLS85_TEST_HOST', 'https://lms-learning-lab.invalid');
define('LLS85_API_VERSION', '2025-01-01');

add_action('woocommerce_order_status_completed', function($id) { lls85_queue_order($id, 'enroll'); }, 50, 1);
add_action('woocommerce_payment_complete', function($id) { lls85_queue_order($id, 'enroll'); }, 50, 1);
add_action('woocommerce_order_status_refunded', function($id) { lls85_queue_order($id, 'unenroll'); }, 50, 1);
add_action('woocommerce_order_status_cancelled', function($id) { lls85_queue_order($id, 'unenroll'); }, 50, 1);
add_action('woocommerce_order_status_failed', function($id) { lls85_queue_order($id, 'unenroll'); }, 50, 1);

function lls85_jobs() {
    $jobs = get_option('lls85_test_jobs', array());
    return is_array($jobs) ? $jobs : array();
}
function lls85_save_jobs($jobs) {
    update_option('lls85_test_jobs', $jobs, false);
}
function lls85_queue_order($order_id, $action) {
    if (!function_exists('lls_lab_eligible_order') || !in_array($action, array('enroll','unenroll'), true)) return false;
    $order = wc_get_order($order_id);
    if (!lls_lab_eligible_order($order)) return false;
    if ($action === 'enroll' && (!function_exists('lls_lab_order_paid_ready') || !lls_lab_order_paid_ready($order))) return false;
    if ($action === 'unenroll' && !$order->has_status(array('refunded','cancelled','failed'))) return false;

    $uid = (int) $order->get_meta('_lls_lab_student_user', true);
    if (!$uid || !get_user_by('id', $uid)) return false;
    if ($action === 'unenroll' && function_exists('lls_lab_has_access') && lls_lab_has_access($uid)) {
        // Another order still grants the course. Unenrolling would be incorrect.
        return false;
    }
    $key = (string)((int)$order_id) . ':' . $action;
    $jobs = lls85_jobs();
    if (isset($jobs[$key])) return $key; // no duplicate event/job
    $jobs[$key] = array(
        'order_id' => (int)$order_id,
        'user_id' => $uid,
        'course_id' => 101, // Fake external LMS ID in simulation only
        'action' => $action,
        'state' => 'pending',
        'attempts' => 0,
        'next_at' => 0,
        'last_code' => 0,
        'error_type' => '',
    );
    lls85_save_jobs($jobs);
    return $key;
}

function lls85_mock_transport($pre, $args, $url) {
    if ($url !== LLS85_TEST_HOST . LLS85_ENDPOINT) return $pre;
    if (empty($GLOBALS['lls85_mock_active'])) return new WP_Error('lls85_blocked', 'No test response is configured');
    $GLOBALS['lls85_seen'][] = array(
        'method' => isset($args['method']) ? $args['method'] : '',
        'url' => $url,
        'headers' => isset($args['headers']) ? $args['headers'] : array(),
        'body' => isset($args['body']) ? (string)$args['body'] : '',
    );
    $outcome = array_shift($GLOBALS['lls85_outcomes']);
    if (is_wp_error($outcome)) return $outcome;
    if (!is_array($outcome) || !isset($outcome['code'])) return new WP_Error('lls85_missing_mock', 'Missing controlled response');
    return array(
        'headers' => array('Content-Type'=>'application/json'),
        'body' => isset($outcome['body']) ? (string)$outcome['body'] : '',
        'response' => array('code'=>(int)$outcome['code'], 'message'=>'Simulated'),
        'cookies' => array(),
        'filename' => null,
    );
}
function lls85_start_mock($outcomes) {
    $GLOBALS['lls85_mock_active'] = true;
    $GLOBALS['lls85_outcomes'] = $outcomes;
    $GLOBALS['lls85_seen'] = array();
}
function lls85_stop_mock() {
    $GLOBALS['lls85_mock_active'] = false;
    $GLOBALS['lls85_outcomes'] = array();
}

function lls85_request($method, $user_id, $course_id) {
    $method = strtoupper((string) $method);
    // Fail-closed. Simulated credentials are used only inside the test harness,
    // no true TalentLMS tenant or customer secrets are permitted.
    if (empty($GLOBALS['lls85_mock_active'])) {
        return array('state'=>'blocked', 'http'=>0, 'reason'=>'disabled');
    }
    if (!in_array($method, array('POST','DELETE'), true)
       || (int)$user_id <= 0 || (int)$course_id <= 0) {
        return array('state'=>'terminal', 'http'=>0, 'reason'=>'invalid_input');
    }
    $response = wp_remote_request(LLS85_TEST_HOST . LLS85_ENDPOINT, array(
        'method' => $method,
        'timeout' => 4,
        'redirection' => 0,
        'sslverify' => true,
        'headers' => array(
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-API-Key' => 'synthetic-key-never-a-real-credential',
            'X-API-Version' => LLS85_API_VERSION,
        ),
        'body' => wp_json_encode(array('user_id'=>(int)$user_id,'course_id'=>(int)$course_id)),
    ));
    if (is_wp_error($response)) {
        // A write might have succeeded remotely despite a client-side timeout:
        // do NOT blindly replay; reconcile before retry.
        return array('state'=>'review', 'http'=>0, 'reason'=>'transport_uncertain');
    }
    $code = (int) wp_remote_retrieve_response_code($response);
    $body = (string) wp_remote_retrieve_body($response);
    if ($code === 204) return array('state'=>'done','http'=>204,'reason'=>'no_content');
    if ($code >= 200 && $code < 300) {
        if ($code === 202) return array('state'=>'review','http'=>202,'reason'=>'async_await_confirmation');
        $parsed = json_decode($body, true);
        if (!is_array($parsed) || json_last_error() !== JSON_ERROR_NONE
            || (isset($parsed['error']) && $parsed['error'])
            || !array_key_exists('_data',$parsed)) {
            return array('state'=>'terminal','http'=>$code,'reason'=>'invalid_json_or_schema');
        }
        return array('state'=>'done','http'=>$code,'reason'=>'confirmed');
    }
    if ($code === 429) return array('state'=>'retry','http'=>429,'reason'=>'rate_limited');
    if ($code === 409) return array('state'=>'review','http'=>409,'reason'=>'conflict_reconcile');
    if ($code >= 500) return array('state'=>'review','http'=>$code,'reason'=>'server_uncertain');
    if ($code >= 400) return array('state'=>'terminal','http'=>$code,'reason'=>'client_or_auth_failure');
    return array('state'=>'terminal','http'=>$code,'reason'=>'unexpected_status');
}

function lls85_process_job($job_key, $now) {
    $jobs = lls85_jobs();
    if (!isset($jobs[$job_key])) return array('state'=>'missing','reason'=>'no_job');
    $job = $jobs[$job_key];
    if (!in_array($job['state'], array('pending','retry'), true)) {
        return array('state'=>$job['state'],'reason'=>'already_final_or_review');
    }
    if ((int)$job['next_at'] > (int)$now) {
        return array('state'=>'not_due','reason'=>'scheduled');
    }
    $order = wc_get_order((int)$job['order_id']);
    if (!$order || !lls_lab_eligible_order($order)) {
        $job['state']='terminal'; $job['error_type']='invalid_order';
    } elseif ($job['action']==='enroll' && (!function_exists('lls_lab_order_paid_ready') || !lls_lab_order_paid_ready($order))) {
        $job['state']='cancelled'; $job['error_type']='order_no_longer_paid_ready';
    } elseif ($job['action']==='unenroll' && (!$order->has_status(array('refunded','cancelled','failed'))
           || lls_lab_has_access((int)$job['user_id']))) {
        $job['state']='cancelled'; $job['error_type']='access_state_changed';
    } else {
        $job['attempts']++;
        $result = lls85_request($job['action']==='enroll'?'POST':'DELETE',
            (int)$job['user_id'], (int)$job['course_id']);
        $job['last_code']=(int)$result['http'];
        $job['error_type']=$result['reason'];
        if ($result['state']==='retry') {
            if ($job['attempts'] >= 3) {
                $job['state']='dead_letter';
            } else {
                $job['state']='retry';
                $job['next_at']=(int)$now + min(3600, 30*(1 << ($job['attempts']-1)));
            }
        } else {
            $job['state']=$result['state'];
        }
    }
    $jobs[$job_key]=$job;
    lls85_save_jobs($jobs);
    return array(
        'state'=>$job['state'], 'http'=>(int)$job['last_code'],
        'attempts'=>(int)$job['attempts'], 'next_at'=>(int)$job['next_at'],
        'reason'=>$job['error_type']
    );
}
