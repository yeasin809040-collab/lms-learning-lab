<?php
/**
 * Local PHP behavior checks for the 95% reconciliation simulator.
 * This is NOT WordPress or TalentLMS; uses minimal stand-ins for WordPress option/email APIs.
 */
error_reporting(E_ALL);
define('ABSPATH', __DIR__ . '/');

$GLOBALS['lls_test_opts'] = array();
$GLOBALS['lls95_pass'] = 0;
$GLOBALS['lls95_fail'] = 0;

function add_action(...$args) { return true; }
function get_option($k,$default=false) { return array_key_exists($k,$GLOBALS['lls_test_opts']) ? $GLOBALS['lls_test_opts'][$k] : $default; }
function update_option($k,$v,$autoload=false) { $GLOBALS['lls_test_opts'][$k]=$v; return true; }
function add_option($k,$v,$deprecated='',$autoload='no') { if(array_key_exists($k,$GLOBALS['lls_test_opts']))return false; $GLOBALS['lls_test_opts'][$k]=$v; return true; }
function delete_option($k) { if(!array_key_exists($k,$GLOBALS['lls_test_opts']))return false; unset($GLOBALS['lls_test_opts'][$k]); return true; }
function sanitize_email($s) {
    $v=filter_var((string)$s,FILTER_SANITIZE_EMAIL);
    return $v===false?'':$v;
}
function is_email($s) { return (bool)filter_var($s,FILTER_VALIDATE_EMAIL); }
function lls85_jobs() { return get_option('lls85_test_jobs',array()); }
function lls85_save_jobs($j) { update_option('lls85_test_jobs',$j); }

require dirname(__DIR__).'/plugins/lms-lab-sync-reconciliation/lms-lab-sync-reconciliation.php';

function ok($condition,$label) {
    if($condition){$GLOBALS['lls95_pass']++; echo "PASS: $label\n";}
    else {$GLOBALS['lls95_fail']++; echo "FAIL: $label\n";}
}
$ref='wp-user-182';
$email='demo-182@example.com';
$one=array(array('id'=>781,'email'=>$email,'integration_user_id'=>$ref));

ok(lls95_remote_resolve(strtoupper($email),$one,$ref)['state']==='matched','Case-insensitive exact match');
ok(lls95_remote_resolve($email,$one,$ref)['id']===781,'Matched external id');
ok(lls95_remote_resolve($email,array(),$ref)['state']==='missing','Remote account missing');
ok(lls95_remote_resolve($email,array_merge($one,array(array('id'=>782,'email'=>$email,'integration_user_id'=>$ref))),$ref)['state']==='ambiguous','Duplicate account detected');
ok(lls95_remote_resolve($email,array(array('id'=>781,'email'=>$email,'integration_user_id'=>'wrong')),$ref)['state']==='conflict','Reference collision detected');
ok(lls95_remote_resolve($email,array(array('id'=>0,'email'=>$email,'integration_user_id'=>$ref)),$ref)['state']==='conflict','Zero external id rejected');
ok(lls95_remote_resolve('not email',array(),$ref)['state']==='invalid','Invalid email rejected');
ok(lls95_remote_resolve('external@othersite.com',array(),$ref)['state']==='invalid','Real-person domain excluded from lab');
ok(lls95_remote_resolve($email,'not-an-array',$ref)['state']==='invalid','Wrong record type rejected');
ok(lls95_remote_resolve($email,array(array('id'=>785,'email'=>'different@example.com','integration_user_id'=>$ref)),$ref)['state']==='missing','Cross-email collision cannot match');

$jobs=array(
 '10:enroll'=>array('action'=>'enroll','state'=>'review','attempts'=>1,'last_code'=>503),
 '20:unenroll'=>array('action'=>'unenroll','state'=>'review','attempts'=>1,'last_code'=>0),
 '30:enroll'=>array('action'=>'enroll','state'=>'done','attempts'=>1,'last_code'=>200),
 '40:enroll'=>array('action'=>'enroll','state'=>'review','attempts'=>1,'last_code'=>503),
 '50:unenroll'=>array('action'=>'unenroll','state'=>'review','attempts'=>1,'last_code'=>0)
);
lls85_save_jobs($jobs);
ok(lls95_reconcile_review_job('none','present')['state']==='missing','Unknown job stays missing');
ok(lls95_reconcile_review_job('30:enroll','present')['state']==='done','Already done cannot replay');
ok(lls95_reconcile_review_job('10:enroll','unknown')['state']==='review','Unknown remote status stays under review');
ok(lls95_reconcile_review_job('10:enroll','invalid')['state']==='review','Unexpected evidence rejected');
ok(lls95_reconcile_review_job('10:enroll','present')['state']==='done','Verified enrollment completes reconciliation');
ok(lls95_reconcile_review_job('10:enroll','present')['reason']==='not_under_review','Repeat reconciliation is idempotent');
ok(lls85_jobs()['10:enroll']['attempts']===1,'Reconciled job does not automatically resend POST');
ok(lls95_reconcile_review_job('20:unenroll','absent')['state']==='done','Verified unenrollment completes reconciliation');
ok(lls95_reconcile_review_job('40:enroll','absent')['state']==='retry','Verified absence allows deliberate enrollment retry');
ok(lls95_reconcile_review_job('50:unenroll','present')['state']==='retry','Verified presence allows deliberate unenrollment retry');

ok(lls95_claim_lock('job-3','worker-A'),'First worker claims lock');
ok(!lls95_claim_lock('job-3','worker-B'),'Second worker cannot claim same lock');
ok(!lls95_release_lock('job-3','worker-B'),'Nonowner cannot release lock');
ok(lls95_release_lock('job-3','worker-A'),'Owner releases lock');
ok(lls95_claim_lock('job-3','worker-B'),'Claim available after release');
ok(lls95_release_lock('job-3','worker-B'),'Second worker releases lock');
echo "TOTAL: {$GLOBALS['lls95_pass']} passed; {$GLOBALS['lls95_fail']} failed.\n";
exit($GLOBALS['lls95_fail'] ? 1 : 0);
