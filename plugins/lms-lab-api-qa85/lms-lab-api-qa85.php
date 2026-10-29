<?php
/**
 * Plugin Name: LMS Learning Lab — TalentLMS V2 Failure Handling QA 85
 * Description: Controlled WordPress HTTP simulation tests for TalentLMS API V2 and WooCommerce sync queue; no actual external API traffic.
 * Version: 0.1.0
 * Requires Plugins: woocommerce
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function() {
    add_management_page('LMS Lab API QA 85','LMS Lab API QA 85','manage_woocommerce','lls85-api-qa','lls85qa_admin');
});
function lls85qa_admin() {
    if (!current_user_can('manage_woocommerce')) wp_die('Access denied');
    echo '<div class="wrap"><h1>TalentLMS V2 — HTTP Transport & Retry Simulation</h1>';
    echo '<p>Real WordPress HTTP methods; <strong>mocked responses only</strong>. No external requests, API keys, real enrollments or live payments.</p>';
    if (isset($_POST['lls85_run'])) {
        check_admin_referer('lls85_run','lls85_nonce');
        update_option('lls85_last_report',array('time'=>gmdate('c'),'result'=>lls85qa_run()),false);
    }
    $saved=get_option('lls85_last_report');
    if (is_array($saved) && isset($saved['time'],$saved['result'])) {
        $r=$saved['result'];
        echo '<h2>API Transport QA: '.($r['failed']===0?'PASS':'FAIL').'</h2>';
        echo '<p><strong>'.esc_html($r['passed']).' passed / '.esc_html($r['failed']).' failed</strong>';
        echo ' · UTC: '.esc_html($saved['time']).'</p>';
        echo '<table class="widefat striped"><thead><tr><th>Check</th><th>Result</th><th>Evidence</th></tr></thead><tbody>';
        foreach ($r['checks'] as $c) {
            echo '<tr><td>'.esc_html($c['name']).'</td><td><strong>'.($c['pass']?'PASS':'FAIL').'</strong></td><td>'.esc_html($c['detail']).'</td></tr>';
        }
        echo '</tbody></table><p><strong>Not validated:</strong> real TalentLMS API, genuine payments, production concurrency or real student browser login.</p>';
    } else echo '<p>No API transport QA saved in this WordPress session.</p>';
    echo '<form method="post">';
    wp_nonce_field('lls85_run','lls85_nonce');
    echo '<p><button type="submit" name="lls85_run" value="1" class="button button-primary">Run New Controlled QA</button></p></form></div>';
}
function lls85qa_outcome($code,$body='') {
    if ($body==='' && $code===200) $body='{"_data":{"id":999,"course_id":101}}';
    return array('code'=>$code,'body'=>$body);
}
function lls85qa_run() {
    $r=array('checks'=>array(),'passed'=>0,'failed'=>0,'test_order'=>0,'test_refund'=>0);
    $check=function($name,$ok,$detail='')use(&$r){
        $pass=(bool)$ok;
        $r['checks'][]=array('name'=>$name,'pass'=>$pass,'detail'=>(string)$detail);
        $r[$pass?'passed':'failed']++;
        return $pass;
    };
    $ready=function_exists('lls85_request') && function_exists('lls85_process_job')
        && function_exists('llsqa_order') && function_exists('llsqa_refund')
        && function_exists('lls_lab_has_access') && function_exists('llb_capture');
    if (!$check('WooCommerce, course access and API simulator loaded',$ready,'Real WordPress runtime')) return $r;

    add_filter('pre_http_request','lls85_mock_transport',1,3);
    try {
        lls85_stop_mock();
        $blocked=lls85_request('POST',3,101);
        $check('External API blocked when mock mode disabled',$blocked['state']==='blocked','Fail closed, no live outgoing requests');
        $check('No network request made while disabled',empty($GLOBALS['lls85_seen']),'No traffic');
        lls85_start_mock(array());
        $bad=lls85_request('PATCH',3,101);
        $check('Unsupported API method rejected',$bad['state']==='terminal' && $bad['reason']==='invalid_input','Only documented POST/DELETE permitted');
        $bad2=lls85_request('POST',0,101);
        $check('Invalid user ID rejected',$bad2['state']==='terminal' && empty($GLOBALS['lls85_seen']),'Positive IDs required');

        lls85_start_mock(array(lls85qa_outcome(200)));
        $ok=lls85_request('POST',3,101);
        $check('POST enrollment: 200 JSON success',$ok['state']==='done' && $ok['http']===200,'Simulated TalentLMS 200');
        $seen=$GLOBALS['lls85_seen'];
        $one=!empty($seen)?$seen[0]:array();
        $headers=isset($one['headers'])?$one['headers']:array();
        $check('Correct V2 API auth + version headers',
            isset($headers['X-API-Key'],$headers['X-API-Version'],$headers['Accept'])
            && $headers['X-API-Key']==='synthetic-key-never-a-real-credential'
            && $headers['X-API-Version']==='2025-01-01'
            && $headers['Accept']==='application/json',
            'X-API-Key, X-API-Version, Accept');
        $body=isset($one['body'])?json_decode($one['body'],true):null;
        $check('Correct documented enrollment endpoint and JSON payload',
            isset($one['url'],$one['method']) && $one['url']===LLS85_TEST_HOST.LLS85_ENDPOINT
            && $one['method']==='POST' && is_array($body)
            && $body['user_id']===3 && $body['course_id']===101,'POST /api/v2/enrollments with IDs');

        lls85_start_mock(array(lls85qa_outcome(204,'')));
        $res=lls85_request('DELETE',3,101);
        $check('DELETE unenrollment: 204 success',$res['state']==='done','No content allowed');

        foreach (array(400,401,403,404,422) as $code) {
            lls85_start_mock(array(lls85qa_outcome($code,'{"error":"invalid_request"}')));
            $res=lls85_request('POST',3,101);
            $check('HTTP '.$code.' is non-retryable',$res['state']==='terminal' && $res['http']===$code,
                'No automatic retry');
        }
        lls85_start_mock(array(lls85qa_outcome(409,'{"error":"already_enrolled"}')));
        $res=lls85_request('POST',3,101);
        $check('HTTP 409 conflict requires reconciliation',$res['state']==='review','No blind double-enroll');

        lls85_start_mock(array(lls85qa_outcome(429,'{"error":"rate_limited"}')));
        $res=lls85_request('POST',3,101);
        $check('HTTP 429 flagged for bounded retry',$res['state']==='retry','Rate limited');
        lls85_start_mock(array(lls85qa_outcome(503,'{"error":"unavailable"}')));
        $res=lls85_request('POST',3,101);
        $check('HTTP 503 write requires manual reconciliation',$res['state']==='review','Remote write status uncertain');

        lls85_start_mock(array(new WP_Error('http_request_failed','Simulated timeout')));
        $res=lls85_request('POST',3,101);
        $check('Write timeout requires reconciliation',$res['state']==='review','Do not blindly replay POST');
        lls85_start_mock(array(lls85qa_outcome(200,'not-json')));
        $res=lls85_request('POST',3,101);
        $check('Malformed HTTP 200 body is not a success',$res['state']==='terminal','Malformed JSON rejected');
        lls85_start_mock(array(lls85qa_outcome(200,'{"unrelated":true}')));
        $res=lls85_request('POST',3,101);
        $check('HTTP 200 requires expected response schema',$res['state']==='terminal','_data required');
        lls85_start_mock(array(lls85qa_outcome(202,'{"_data":{"id":1}}')));
        $res=lls85_request('POST',3,101);
        $check('HTTP 202 asynchronous result not assumed enrolled',$res['state']==='review','Await confirmation');

        // A real WooCommerce order invokes the same event handlers and queue logic.
        $pid=(int)wc_get_product_id_by_sku('LIL-COURSE-001');
        $product=$pid ? wc_get_product($pid) : false;
        if (!$check('Existing WooCommerce virtual course available',
            $product && $product->is_virtual(), 'Course product ID '.$pid)) return $r;
        $student_email='lls85-'.strtolower(wp_generate_password(10,false,false)).'@example.com';
        $order=llsqa_order($product,$student_email,true);
        $oid=(int)$order->get_id(); $r['test_order']=$oid;
        $key=$oid.':enroll';
        $check('Pending WooCommerce order does not enqueue enrollment',
            !isset(lls85_jobs()[$key]),'Order #'.$oid);
        $order->update_status('completed','Synthetic QA 85, no payment',true);
        $order=wc_get_order($oid);
        $queue=lls85_jobs();
        $check('Completed WooCommerce order enqueues one API job',
            isset($queue[$key]) && $queue[$key]['state']==='pending','Order #'.$oid.' event');
        $uid=(int)$order->get_meta('_lls_lab_student_user',true);
        $check('API queue maps to real local WordPress student',
            $uid>0 && isset($queue[$key]) && (int)$queue[$key]['user_id']===$uid,
            'Local learner user ID: '.$uid);
        do_action('woocommerce_order_status_completed',$oid);
        $queue=lls85_jobs();
        $keys=array_filter(array_keys($queue),function($k)use($key){ return $k===$key; });
        $check('Duplicate order status hook produces one job',count($keys)===1 && (int)$queue[$key]['attempts']===0,
            'Deduplicated by order + action');

        $now=1700000000;
        lls85_start_mock(array(lls85qa_outcome(429,'{"error":"rate_limited"}')));
        $step=lls85_process_job($key,$now);
        $check('429 schedules bounded retry at +30s',
            $step['state']==='retry' && $step['attempts']===1 && $step['next_at']===$now+30,
            'Controlled backoff; no sleeping');
        lls85_start_mock(array(lls85qa_outcome(200)));
        $early=lls85_process_job($key,$now+29);
        $check('Not-due retry cannot run early',$early['state']==='not_due'
            && count($GLOBALS['lls85_seen'])===0,'Waiting for due timestamp');
        $done=lls85_process_job($key,$now+30);
        $check('Retry succeeds and records terminal done',
            $done['state']==='done' && $done['attempts']===2,'429 followed by successful 200');
        $again=lls85_process_job($key,$now+31);
        $check('Completed API job cannot be double-sent',
            $again['state']==='done' && count($GLOBALS['lls85_seen'])===1,
            'Only one request on the final retry');

        $refund=llsqa_refund($order);
        $r['test_refund']=is_object($refund)?(int)$refund->get_id():0;
        $revoke_key=$oid.':unenroll';
        $queue=lls85_jobs();
        $check('Refund queues a documented DELETE enrollment operation',
            isset($queue[$revoke_key]) && $queue[$revoke_key]['action']==='unenroll',
            'Refund ID: '.$r['test_refund']);
        lls85_start_mock(array(lls85qa_outcome(204,'')));
        $end=lls85_process_job($revoke_key,$now+60);
        $check('Refund DELETE request completes without a payment gateway',
            $end['state']==='done' && !lls_lab_has_access($uid),
            'Simulated 204; local course access revoked');
        $seen=$GLOBALS['lls85_seen'];
        $check('Refund HTTP method was DELETE',
            count($seen)===1 && $seen[0]['method']==='DELETE','DELETE /api/v2/enrollments');
        do_action('woocommerce_order_status_refunded',$oid);
        $queue=lls85_jobs();
        $check('Duplicate refunded event cannot enqueue a second job',
            count(array_filter(array_keys($queue), function($k)use($revoke_key){return $k===$revoke_key;}))===1,
            'Idempotent WooCommerce hook');

        $queueJson=wp_json_encode($queue);
        $check('Persistent job records exclude API secrets and email',
            strpos($queueJson,'synthetic-key-never-a-real-credential')===false
            && strpos($queueJson,$student_email)===false,
            'No secret or customer email persisted in sync queue');

        // Explicit repeated 429 handling: max 3 attempts, dead-letter instead of infinite retries.
        $retryOrder=llsqa_order($product,'lls85-ratelimit-'.strtolower(wp_generate_password(8,false,false)).'@example.com',true);
        $retryOrder->update_status('completed','Bounded retry synthetic QA',true);
        $retryKey=$retryOrder->get_id().':enroll';
        lls85_start_mock(array(
            lls85qa_outcome(429,'{"error":"rate_limited"}'),
            lls85qa_outcome(429,'{"error":"rate_limited"}'),
            lls85qa_outcome(429,'{"error":"rate_limited"}')
        ));
        $a=lls85_process_job($retryKey,$now+100);
        $b=lls85_process_job($retryKey,$now+130);
        $c=lls85_process_job($retryKey,$now+190);
        $check('Exhausted rate-limit retries enter dead-letter safely',
            $a['state']==='retry' && $b['state']==='retry'
            && $c['state']==='dead_letter' && $c['attempts']===3,
            'Three attempts maximum');
        $after=lls85_process_job($retryKey,$now+999);
        $check('Dead-letter job cannot be retried indefinitely',
            $after['state']==='dead_letter' && count($GLOBALS['lls85_seen'])===3,
            'Manual intervention required');

        $localReport=lls85_jobs();
        $check('Persisted queue has distinct enrollment and refund jobs',
            isset($localReport[$key],$localReport[$revoke_key])
            && $localReport[$key]['state']==='done'
            && $localReport[$revoke_key]['state']==='done',
            'WordPress option persistence');
    } catch (Throwable $e) {
        $check('No unhandled runtime failure',false,$e->getMessage());
    } finally {
        lls85_stop_mock();
        remove_filter('pre_http_request','lls85_mock_transport',1);
    }
    return $r;
}
