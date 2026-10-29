<?php
/**
 * Run actual WordPress+WooCommerce synthetic integration suites from WP-CLI.
 * This file is executed using: wp eval-file wp-content/lms-lab-tests/run-wp-env-integration.php
 * NEVER run on a production WordPress site.
 */
if (!defined('ABSPATH')) { fwrite(STDERR, "WordPress not bootstrapped\n"); exit(1); }

$functions = array('wc_create_order','llqa_run_tests','llsqa_run','lls85qa_run',
    'lls95_seed_browser_demo','lls95_qa_run');
foreach($functions as $f) {
    if (!function_exists($f)) {
        fwrite(STDERR, "Missing runtime function: $f\n");
        exit(1);
    }
}

// All records are synthetic; guarantee no outbound email for these tests.
$lls95_mail_block = function($pre){ return true; };
add_filter('pre_wp_mail',$lls95_mail_block,99,1);

$results=array();
$errors=array();
try {
    $base=llqa_run_tests();
    update_option('llqa_last_report',array('time'=>gmdate('c'),'result'=>$base),false);
    $results['woocommerce_audit_65']=$base;

    $student=llsqa_run();
    update_option('llsqa_last_report',array('time'=>gmdate('c'),'result'=>$student),false);
    $results['student_access_75']=$student;

    $api=lls85qa_run();
    update_option('lls85_last_report',array('time'=>gmdate('c'),'result'=>$api),false);
    $results['api_simulation_85']=$api;

    $demo=lls95_seed_browser_demo();
    if (is_wp_error($demo)) throw new RuntimeException('95 browser seeding failed: '.$demo->get_error_message());
    update_option('lls95_browser_demo',$demo,false);

    $reconcile=lls95_qa_run();
    update_option('lls95_last_report',array('time'=>gmdate('c'),'result'=>$reconcile),false);
    $results['reconciliation_95']=$reconcile;

    echo "Disposable synthetic browser student: ".wp_json_encode($demo)."\n";
} catch(Throwable $e) {
    $errors[]=$e->getMessage();
} finally {
    remove_filter('pre_wp_mail',$lls95_mail_block,99);
}
$total=0; $failed=0;
foreach($results as $name=>$r) {
    $count=isset($r['passed'])?(int)$r['passed']:0;
    $bad=isset($r['failed'])?(int)$r['failed']:1;
    $total+=$count; $failed+=$bad;
    echo "SUITE {$name}: {$count} PASS / {$bad} FAIL\n";
    foreach(isset($r['checks'])?$r['checks']:array() as $c) {
        if (empty($c['pass'])) {
            $label=isset($c['name'])?$c['name']:(isset($c['label'])?$c['label']:'unnamed');
            $detail=isset($c['details'])?$c['details']:(isset($c['detail'])?$c['detail']:'');
            echo "FAILED CHECK: {$name} | {$label} | {$detail}\n";
        }
    }
}
foreach($errors as $e) {
    $failed++; echo "RUNTIME ERROR: {$e}\n";
}
echo "WORDPRESS_REAL_DATABASE_TOTAL: {$total} PASS / {$failed} FAIL\n";
if(count($results)!==4 || $failed!==0) exit(1);
exit(0);
