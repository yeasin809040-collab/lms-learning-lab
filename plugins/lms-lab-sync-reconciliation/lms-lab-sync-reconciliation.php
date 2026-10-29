<?php
/**
 * Plugin Name: LMS Lab — Identity & API Reconciliation Simulator 95
 * Description: Synthetic-only API uncertainty resolution, fake external identity matching and lock-claim demo. No remote TalentLMS calls.
 * Version: 0.1.0
 * Requires Plugins: woocommerce
 */
if (!defined('ABSPATH')) { exit; }

function lls95_remote_resolve($email, $records, $expected_ref) {
    $email = strtolower(sanitize_email((string) $email));
    if (!is_email($email) || substr($email,-12) !== '@example.com' || !is_array($records)) {
        return array('state'=>'invalid','id'=>0);
    }
    $matches = array();
    foreach ($records as $entry) {
        if (!is_array($entry)) continue;
        $found_email = isset($entry['email']) ? strtolower(sanitize_email($entry['email'])) : '';
        if ($found_email !== $email) continue;
        $id = isset($entry['id']) ? (int)$entry['id'] : 0;
        $ref = isset($entry['integration_user_id']) ? (string)$entry['integration_user_id'] : '';
        if ($id<=0 || $ref!==(string)$expected_ref) {
            return array('state'=>'conflict','id'=>0);
        }
        $matches[]=$id;
    }
    if (count($matches) > 1) return array('state'=>'ambiguous','id'=>0);
    if (count($matches)===0) return array('state'=>'missing','id'=>0);
    return array('state'=>'matched','id'=>$matches[0]);
}

function lls95_reconcile_review_job($key, $verified_remote_state) {
    $jobs=lls85_jobs();
    if (!isset($jobs[$key])) return array('state'=>'missing','reason'=>'missing_job');
    $job=$jobs[$key];
    if ($job['state']!=='review') return array('state'=>$job['state'],'reason'=>'not_under_review');
    if (!in_array($verified_remote_state,array('present','absent','unknown'),true)) {
        return array('state'=>'review','reason'=>'invalid_evidence');
    }
    if ($verified_remote_state==='unknown') {
        return array('state'=>'review','reason'=>'unresolved');
    }
    // "present" and "absent" are injected observations in this lab, NOT real API reads.
    $expected=$job['action']==='enroll'?'present':'absent';
    if ($verified_remote_state===$expected) {
        $job['state']='done';
        $job['error_type']='confirmed_after_review';
    } else {
        // The remote state was explicitly inspected in a simulation. Do not send
        // until another deliberate worker operation and current-order revalidation.
        $job['state']='retry';
        $job['error_type']='remote_state_checked';
        $job['next_at']=0;
    }
    $job['reviewed_at']=time();
    $jobs[$key]=$job;
    lls85_save_jobs($jobs);
    return array('state'=>$job['state'],'reason'=>$job['error_type']);
}

function lls95_claim_lock($key, $actor) {
    if (!is_string($actor) || $actor==='') return false;
    return add_option('lls95_claim_'.md5((string)$key),$actor,'','no');
}
function lls95_release_lock($key, $actor) {
    $name='lls95_claim_'.md5((string)$key);
    if (get_option($name)!==$actor) return false;
    return delete_option($name);
}
