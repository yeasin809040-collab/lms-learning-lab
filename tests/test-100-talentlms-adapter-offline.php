<?php
/**
 * Fail-closed PHP contract tests for TalentLMS V2 readiness adapter.
 * No WordPress or internet; any real HTTP request raises immediately.
 * All credentials/tenant names are DUMMY VALUES in isolated CI.
 */
error_reporting(E_ALL);
define('ABSPATH',__DIR__);
define('LLS100_TALENTLMS_DOMAIN','approved-learning-lab.talentlms.com');
define('LLS100_TALENTLMS_API_KEY','PUBLIC-FICTIONAL-CI-TEST-KEY-ONLY');
define('LLS100_APPROVED_TEST_USER_ID',901);
define('LLS100_APPROVED_TEST_COURSE_ID',902);

$GLOBALS['lls100_checks']=array('pass'=>0,'fail'=>0);
$GLOBALS['lls100_outbound']=0;
function wp_json_encode($value) { return json_encode($value); }
function wp_remote_request($url,$args) {
    $GLOBALS['lls100_outbound']++;
    throw new RuntimeException('REAL HTTP IS FORBIDDEN IN THESE TESTS');
}
function wp_remote_retrieve_response_code($r) { return $r['response']['code'] ?? 0; }
function wp_remote_retrieve_body($r) { return $r['body'] ?? ''; }
class WP_Error {
    private $message;
    public function __construct($code,$message='') { $this->message=$message; }
    public function get_error_message() { return $this->message; }
}
function is_wp_error($object) { return $object instanceof WP_Error; }
function ck($condition,$title) {
    if ($condition) {
        $GLOBALS['lls100_checks']['pass']++;
        echo 'PASS: '.$title."\n";
    } else {
        $GLOBALS['lls100_checks']['fail']++;
        echo 'FAIL: '.$title."\n";
    }
}
function fake_response($status,$data='') {
    return array('response'=>array('code'=>$status),'body'=>$data);
}

require dirname(__DIR__).'/plugins/lms-lab-talentlms-live-readiness/lms-lab-talentlms-live-readiness.php';

$state=lls100_config_status();
ck($state['valid_tenant']===true, 'Approved TalentLMS domain accepted');
ck($state['has_private_api_key']===true, 'Synthetic key configured for unit tests');
ck($state['reads_enabled']===false, 'Live reads disabled by default');
ck($state['writes_enabled']===false, 'Live writes disabled by default');
ck($state['test_user_approved']===true && $state['test_course_approved']===true, 'Explicit synthetic test identity configured');
ck(!array_key_exists('api_key',$state) && strpos(json_encode($state),'PUBLIC-FICTIONAL')===false, 'Config status never exposes API key');

foreach (array('example.com','foo.talentlms.com.attacker.com','http://foo.talentlms.com','127.0.0.1',
    'localhost','foo.talentlms.com:443','foo..talentlms.com','foo/talentlms.com','foo.talentlms.com/evil') as $bad) {
    ck(!lls100_valid_tenant($bad),'Unsafe host blocked: '.$bad);
}
ck(lls100_valid_tenant('learning-lab.talentlms.com'),'Valid subdomain with hyphen allowed');
ck(lls100_request('bogus',901,902)['reason']==='operation_not_allowed', 'Unknown operation fail closed');
ck(lls100_request('list_courses')['reason']==='live_api_disabled', 'Live read blocked without opt-in');
ck(lls100_request('enroll',901,902)['reason']==='live_api_disabled', 'Live enrollment blocked without opt-in');
ck(lls100_request('unenroll',901,902)['reason']==='live_api_disabled', 'Live unenrollment blocked without opt-in');
ck($GLOBALS['lls100_outbound']===0,'No real outgoing HTTP request occurred');

$mock_calls=array();
$mock=function($url,$args)use(&$mock_calls) {
    $mock_calls[]=array('url'=>$url,'args'=>$args);
    return fake_response(200,'{"_data":[{"id":902,"name":"Sample"}]}');
};
$read=lls100_request('list_courses',0,0,$mock);
ck($read['state']==='done','Mock GET returns confirmed response');
ck(count($mock_calls)===1 && $mock_calls[0]['args']['method']==='GET','GET method sent to injected mock only');
ck(strpos($mock_calls[0]['url'],'https://approved-learning-lab.talentlms.com/api/v2/courses')===0,'Read URL points at approved HTTPS tenant');
ck($mock_calls[0]['args']['headers']['X-API-Version']==='2025-01-01','Version header follows documented format');
ck($mock_calls[0]['args']['headers']['X-API-Key']==='PUBLIC-FICTIONAL-CI-TEST-KEY-ONLY','Synthetic API key only in PHP headers');
ck($mock_calls[0]['args']['headers']['Accept']==='application/json','Accept JSON header present');
ck($mock_calls[0]['args']['sslverify']===true && $mock_calls[0]['args']['redirection']===0,'HTTPS validation and redirects disabled');
ck($mock_calls[0]['args']['timeout']===8,'Bounded network timeout');
ck(!isset($mock_calls[0]['args']['body']),'GET does not send a write payload');

$before=count($mock_calls);
ck(lls100_request('enroll',900,902,$mock)['state']==='blocked','Foreign user ID prohibited');
ck(lls100_request('enroll',901,903,$mock)['state']==='blocked','Foreign course ID prohibited');
ck(lls100_request('enroll',0,902,$mock)['state']==='blocked','Zero test user rejected');
ck(count($mock_calls)===$before,'Unapproved writes never hit even mocked transport');

$success=function($url,$args)use(&$mock_calls) {
    $mock_calls[]=array('url'=>$url,'args'=>$args);
    return fake_response(200,'{"_data":{"user_id":901,"course_id":902}}');
};
$post=lls100_request('enroll',901,902,$success);
ck($post['state']==='done' && $post['http']===200,'Mock POST enrollment confirmed');
$last=end($mock_calls);
ck($last['args']['method']==='POST' && substr($last['url'],-19)==='/api/v2/enrollments','POST endpoint and method exact');
$body=json_decode($last['args']['body'],true);
ck($body===array('user_id'=>901,'course_id'=>902),'POST body contains only exact approved IDs');
$delete=lls100_request('unenroll',901,902,$success);
$last=end($mock_calls);
ck($delete['state']==='done' && $last['args']['method']==='DELETE','DELETE operation uses correct method');
ck(json_decode($last['args']['body'],true)===array('user_id'=>901,'course_id'=>902),'DELETE body contains the documented IDs');
ck(lls100_request('unenroll',901,902,function(){return fake_response(204);})['state']==='done','DELETE HTTP 204 success supported');

$codes=array(
    400=>'terminal',401=>'terminal',403=>'terminal',404=>'terminal',409=>'review',
    422=>'terminal',429=>'retry',500=>'review',503=>'review',202=>'review',
);
foreach($codes as $status=>$expected) {
    $r=lls100_request('enroll',901,902,function() use($status){return fake_response($status,'{"error":"fake"}');});
    ck($r['state']===$expected,'HTTP '.$status.' classified '.$expected);
}
ck(lls100_request('enroll',901,902,function(){return new WP_Error('timeout','fake connection error');})['state']==='review','Unknown POST timeout triggers manual review');
ck(lls100_request('list_courses',0,0,function(){return new WP_Error('timeout');})['state']==='retry','GET timeout can be retried safely');
ck(lls100_request('enroll',901,902,function(){return fake_response(200,'not-json');})['state']==='review','Malformed 200 JSON is not enrollment success');
ck(lls100_request('enroll',901,902,function(){return fake_response(200,'{"unrelated":1}');})['state']==='review','Missing _data does not falsely confirm');
ck(lls100_request('enroll',901,902,function(){return fake_response(200,'{"_data":[],"error":"fail"}');})['state']==='review','Error envelope overrides HTTP 200');
ck(lls100_request('list_courses',0,0,function(){return fake_response(200,'{"_data":123}');})['state']==='review','Unexpected list schema triggers manual review');
ck(lls100_request('enroll',901,902,function(){return 'corrupt_transport';})['state']==='review','Transport result type must be valid');
ck(lls100_request('enroll',901,902,function(){return fake_response(302);})['state']==='review','Redirect is not success');
ck($GLOBALS['lls100_outbound']===0,'No network calls during all contract tests');
$pass=$GLOBALS['lls100_checks']['pass'];
$fail=$GLOBALS['lls100_checks']['fail'];
echo "TALENTLMS_V2_ADAPTER_CONTRACT_TOTAL: {$pass} PASS / {$fail} FAIL\n";
exit($fail===0?0:1);
