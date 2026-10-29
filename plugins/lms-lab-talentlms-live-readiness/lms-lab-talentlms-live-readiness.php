<?php
/**
 * Plugin Name: LMS Lab — TalentLMS V2 Live Readiness Adapter (Disabled by Default)
 * Description: Explicitly gated real TalentLMS HTTP transport for an approved isolated tenant. No automatic hooks, cron, payment calls, or background sync.
 * Version: 0.1.1
 */
if (!defined('ABSPATH')) exit;

/**
 * All constants must be set in private wp-config.php or server configuration.
 * NEVER put an API key in this public repository, GitHub Actions, a WordPress
 * post, browser local storage, order metadata, or a chat message.
 *
 * LLS100_TALENTLMS_DOMAIN            => 'owned-test-tenant.talentlms.com'
 * LLS100_TALENTLMS_API_KEY           => secret (never echoed or logged)
 * LLS100_ENABLE_LIVE_READS           => true only with owner authorization
 * LLS100_ALLOW_LIVE_WRITES           => true only after explicit test approval
 * LLS100_APPROVED_TEST_USER_ID       => positive TalentLMS test user ID
 * LLS100_APPROVED_TEST_COURSE_ID     => positive TalentLMS test course ID
 * LLS100_APPROVED_TEST_ACTION        => 'enroll' or 'unenroll'
 *
 * The sample does NOT configure these values. Live traffic is disabled.
 */
function lls100_setting($key, $fallback = null) {
    return defined($key) ? constant($key) : $fallback;
}
function lls100_valid_tenant($domain) {
    if (!is_string($domain)) return false;
    return (bool)preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.talentlms\.com\z/D', $domain);
}
function lls100_config_status() {
    $domain = strtolower((string)lls100_setting('LLS100_TALENTLMS_DOMAIN',''));
    $has_key = is_string(lls100_setting('LLS100_TALENTLMS_API_KEY',''))
        && strlen((string)lls100_setting('LLS100_TALENTLMS_API_KEY','')) >= 12;
    $valid = lls100_valid_tenant($domain);
    return array(
        'valid_tenant' => $valid,
        'has_private_api_key' => $has_key,
        'reads_enabled' => lls100_setting('LLS100_ENABLE_LIVE_READS',false) === true,
        'writes_enabled' => lls100_setting('LLS100_ALLOW_LIVE_WRITES',false) === true,
        'test_user_approved' => (int)lls100_setting('LLS100_APPROVED_TEST_USER_ID',0) > 0,
        'test_course_approved' => (int)lls100_setting('LLS100_APPROVED_TEST_COURSE_ID',0) > 0,
    );
}

/**
 * An injected mock transport is available only to PHP code in the test suite.
 * It receives URL and WP HTTP args, then returns a WP response / WP_Error.
 * No public route, button or hook is registered in this plugin.
 */
function lls100_request($operation, $user_id = 0, $course_id = 0, $mock_transport = null) {
    $methods = array('list_courses'=>'GET','enroll'=>'POST','unenroll'=>'DELETE');
    if (!is_string($operation) || !isset($methods[$operation])) {
        return array('state'=>'blocked','reason'=>'operation_not_allowed','http'=>0);
    }
    $domain = strtolower((string)lls100_setting('LLS100_TALENTLMS_DOMAIN',''));
    $key = (string)lls100_setting('LLS100_TALENTLMS_API_KEY','');
    if (!lls100_valid_tenant($domain) || strlen($key)<12) {
        return array('state'=>'blocked','reason'=>'private_configuration_missing','http'=>0);
    }
    $mock = is_callable($mock_transport);
    if (!$mock && lls100_setting('LLS100_ENABLE_LIVE_READS',false)!==true) {
        return array('state'=>'blocked','reason'=>'live_api_disabled','http'=>0);
    }
    $write = $operation !== 'list_courses';
    if ($write) {
        $approved_user = (int)lls100_setting('LLS100_APPROVED_TEST_USER_ID',0);
        $approved_course = (int)lls100_setting('LLS100_APPROVED_TEST_COURSE_ID',0);
        if ((int)$user_id<=0 || (int)$course_id<=0
            || (int)$user_id!==$approved_user || (int)$course_id!==$approved_course) {
            return array('state'=>'blocked','reason'=>'test_identity_not_allowlisted','http'=>0);
        }
        if (!$mock && (lls100_setting('LLS100_ALLOW_LIVE_WRITES',false)!==true
            || lls100_setting('LLS100_APPROVED_TEST_ACTION','')!==$operation)) {
            return array('state'=>'blocked','reason'=>'write_not_explicitly_approved','http'=>0);
        }
    }
    $method = $methods[$operation];
    $url = 'https://'.$domain.'/api/v2/' . ($write ? 'enrollments' : 'courses');
    if (!$write) $url .= '?page%5Bsize%5D=1';
    $args = array(
        'method'=>$method,
        'timeout'=>8,
        'redirection'=>0,
        'sslverify'=>true,
        'headers'=>array(
            'Accept'=>'application/json',
            'Content-Type'=>'application/json',
            'X-API-Key'=>$key,
            'X-API-Version'=>'2025-01-01',
        ),
    );
    if ($write) $args['body'] = wp_json_encode(array(
        'user_id'=>(int)$user_id,
        'course_id'=>(int)$course_id,
    ));

    // Either an explicitly injected local mock OR an independently authorized
    // HTTPS request. No side effects are scheduled or retried here.
    $response = $mock ? call_user_func($mock_transport, $url, $args)
                      : wp_remote_request($url, $args);
    if (is_wp_error($response)) {
        return array('state'=>$write?'review':'retry','reason'=>'transport_uncertain','http'=>0);
    }
    if (!is_array($response)) {
        return array('state'=>'review','reason'=>'invalid_transport_result','http'=>0);
    }
    $status=(int)wp_remote_retrieve_response_code($response);
    $raw=(string)wp_remote_retrieve_body($response);
    if ($status===204 && $operation==='unenroll') {
        return array('state'=>'done','reason'=>'no_content','http'=>204);
    }
    if ($status===200) {
        $parsed=json_decode($raw,true);
        if (!is_array($parsed) || json_last_error()!==JSON_ERROR_NONE
            || !array_key_exists('_data',$parsed) || isset($parsed['error'])) {
            return array('state'=>'review','reason'=>'unconfirmed_response','http'=>200);
        }
        if ($operation==='list_courses') {
            if (!is_array($parsed['_data'])) {
                return array('state'=>'review','reason'=>'invalid_course_list','http'=>200);
            }
        } else {
            // HTTP 200 plus any arbitrary _data is not proof that THIS approved
            // learner/course was changed. Reject response identity mismatches
            // or incomplete echoes; investigate manually rather than claim success.
            $data = $parsed['_data'];
            if (!is_array($data) || !array_key_exists('user_id',$data)
                || !array_key_exists('course_id',$data)
                || filter_var($data['user_id'], FILTER_VALIDATE_INT) === false
                || filter_var($data['course_id'], FILTER_VALIDATE_INT) === false
                || (int)$data['user_id'] !== (int)$user_id
                || (int)$data['course_id'] !== (int)$course_id) {
                return array('state'=>'review','reason'=>'remote_identity_not_confirmed','http'=>200);
            }
        }
        return array('state'=>'done','reason'=>'verified_response_shape','http'=>200);
    }
    if ($status===429) return array('state'=>'retry','reason'=>'rate_limited','http'=>$status);
    if ($status===409) return array('state'=>'review','reason'=>'conflict_check_remote_state','http'=>$status);
    if ($status>=500 || $status===202) {
        return array('state'=>'review','reason'=>'remote_write_or_state_uncertain','http'=>$status);
    }
    if ($status===401 || $status===403) {
        return array('state'=>'terminal','reason'=>'auth_or_api_access_denied','http'=>$status);
    }
    if ($status>=400 && $status<500) {
        return array('state'=>'terminal','reason'=>'invalid_request_or_missing_resource','http'=>$status);
    }
    return array('state'=>'review','reason'=>'unexpected_response','http'=>$status);
}
