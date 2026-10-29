<?php
/**
 * Standalone security check: the published demo password must never seed
 * a WordPress account when the explicit disposable-lab flag is absent.
 * No WordPress installation or external network is needed.
 */
error_reporting(E_ALL);
define('ABSPATH', __DIR__);
$GLOBALS['no_seed_checks']=0;
function add_shortcode($name,$callback) { return true; }
class WP_Error {
    private $code;
    public function __construct($code,$message='') {$this->code=$code;}
    public function get_error_code(){return $this->code;}
}
require dirname(__DIR__).'/plugins/lms-lab-browser-login/lms-lab-browser-login.php';
if (lls95_disposable_demo_enabled() !== false) {
    fwrite(STDERR,"FAIL: Public demo automatically enabled\n");exit(1);
}
$GLOBALS['no_seed_checks']++;
$r=lls95_seed_browser_demo();
if (!$r instanceof WP_Error || $r->get_error_code() !== 'lls95_demo_disabled') {
    fwrite(STDERR,"FAIL: Public demo account not blocked without opt-in\n");exit(1);
}
$GLOBALS['no_seed_checks']++;
echo "PUBLIC_DEMO_OPTIN_SECURITY_TOTAL: 2 PASS / 0 FAIL\n";
exit(0);
