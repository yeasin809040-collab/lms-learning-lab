<?php
/**
 * Fail-closed check executed in REAL WordPress/MySQL with no TalentLMS
 * credentials, domain, or live permissions configured. Never contacts the API.
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "Missing WordPress runtime\n"); exit(1); }
if (!function_exists('lls100_request') || !function_exists('lls100_config_status')) {
    fwrite(STDERR, "Live-readiness adapter not activated\n"); exit(1);
}
$pass=0; $fail=0;
$assert=function($ok,$label)use(&$pass,&$fail) {
    if ($ok) { $pass++; echo "GUARD PASS: {$label}\n"; }
    else { $fail++; echo "GUARD FAIL: {$label}\n"; }
};
$events=0;
$deny=function($pre,$args,$url)use(&$events) {
    $events++;
    return new WP_Error('lls100_no_real_traffic','Unexpected HTTP call from 100% guarded lab');
};
add_filter('pre_http_request',$deny,1,3);
try {
    $status=lls100_config_status();
    $assert($status['valid_tenant']===false,'No live tenant configured in fresh WordPress');
    $assert($status['has_private_api_key']===false,'No TalentLMS key present in WordPress');
    $assert($status['reads_enabled']===false,'Live READ capability is off');
    $assert($status['writes_enabled']===false,'Live WRITE capability is off');
    $assert(!lls100_valid_tenant('evil.com'),'Non-TalentLMS host rejected');
    $assert(!lls100_valid_tenant('foo.talentlms.com.evil.com'),'SSRF hostname suffix rejected');
    $assert(lls100_request('list_courses')['state']==='blocked','Course GET blocked without private configuration');
    $assert(lls100_request('enroll',901,902)['state']==='blocked','Actual enrollment blocked without configuration');
    $assert(lls100_request('unenroll',901,902)['state']==='blocked','Actual unenrollment blocked without configuration');
    $assert(lls100_request('reset_password',901,902)['reason']==='operation_not_allowed','Unknown/unsafe actions rejected');
    $assert($events===0,'WordPress emitted zero external API requests');
} catch(Throwable $e) {
    $fail++;
    echo "GUARD EXCEPTION: {$e->getMessage()}\n";
} finally {
    remove_filter('pre_http_request',$deny,1);
}
echo "WORDPRESS_LIVE_TALENTLMS_GUARD_TOTAL: {$pass} PASS / {$fail} FAIL\n";
exit($fail ? 1 : 0);
