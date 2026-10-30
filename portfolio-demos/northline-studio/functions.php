<?php
/**
 * Northline Studio — fictional business website demo.
 * No external services are contacted by this theme.
 */

if (!defined('ABSPATH')) {
    exit;
}

function northline_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('responsive-embeds');
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script'));
    add_theme_support('custom-logo', array('height' => 80, 'width' => 240, 'flex-height' => true, 'flex-width' => true));

    register_nav_menus(array('primary' => __('Primary navigation', 'northline-studio')));
}
add_action('after_setup_theme', 'northline_setup');

function northline_assets() {
    wp_enqueue_style('northline-style', get_stylesheet_uri(), array(), '1.0.0');
    wp_enqueue_script('northline-script', get_template_directory_uri() . '/assets/site.js', array(), '1.0.0', true);
}
add_action('wp_enqueue_scripts', 'northline_assets');

function northline_customize_register($wp_customize) {
    $wp_customize->add_section('northline_home', array(
        'title' => __('Northline homepage', 'northline-studio'),
        'priority' => 30,
        'description' => __('Replace all fictional demo copy with the client\'s approved brand content.', 'northline-studio'),
    ));

    $fields = array(
        'northline_eyebrow' => array('label' => 'Hero eyebrow', 'default' => 'A SMALL STUDIO FOR BIG NEXT STEPS', 'type' => 'text'),
        'northline_heading' => array('label' => 'Hero heading', 'default' => 'Make your next move', 'type' => 'text'),
        'northline_heading_accent' => array('label' => 'Hero heading accent', 'default' => 'a meaningful one.', 'type' => 'text'),
        'northline_intro' => array('label' => 'Hero description', 'default' => 'A thoughtful digital presence brings your story, services and next steps into focus. Let\'s create a website that feels distinctly yours.', 'type' => 'textarea'),
        'northline_contact_heading' => array('label' => 'Contact heading', 'default' => 'Let\'s make something clear and compelling.', 'type' => 'text'),
    );

    foreach ($fields as $id => $field) {
        $wp_customize->add_setting($id, array(
            'default' => $field['default'],
            'sanitize_callback' => $field['type'] === 'textarea' ? 'sanitize_textarea_field' : 'sanitize_text_field',
            'transport' => 'refresh',
        ));
        $wp_customize->add_control($id, array(
            'label' => __($field['label'], 'northline-studio'),
            'section' => 'northline_home',
            'type' => $field['type'],
        ));
    }
}
add_action('customize_register', 'northline_customize_register');

/** Process the demo form only after the site admin has configured WordPress email. */
function northline_handle_contact() {
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        wp_die(esc_html__('Invalid request.', 'northline-studio'), '', array('response' => 405));
    }

    $raw_nonce = isset($_POST['northline_nonce']) && is_string($_POST['northline_nonce']) ? sanitize_text_field(wp_unslash($_POST['northline_nonce'])) : '';
    if (!wp_verify_nonce($raw_nonce, 'northline_contact')) {
        wp_safe_redirect(add_query_arg('contact', 'invalid', home_url('/')) . '#contact');
        exit;
    }

    // Honeypot field. Empty in real human submissions.
    if (!empty($_POST['website'])) {
        wp_safe_redirect(home_url('/#contact'));
        exit;
    }

    $name = isset($_POST['name']) && is_string($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $email = isset($_POST['email']) && is_string($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $message = isset($_POST['message']) && is_string($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

    if (!$name || strlen($name) > 120 || !is_email($email) || !$message || strlen($message) > 3000) {
        wp_safe_redirect(add_query_arg('contact', 'invalid', home_url('/')) . '#contact');
        exit;
    }

    // Simple per-request-origin limit to reduce abuse; never persist a raw IP address.
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
    $key = 'northline_contact_' . md5(wp_salt('nonce') . $ip);
    if (get_transient($key)) {
        wp_safe_redirect(add_query_arg('contact', 'slowdown', home_url('/')) . '#contact');
        exit;
    }
    set_transient($key, 1, MINUTE_IN_SECONDS);

    $subject = sprintf('Website enquiry from %s', $name);
    $body = sprintf("Name: %s\nEmail: %s\n\nMessage:\n%s", $name, $email, $message);
    $sent = wp_mail(get_option('admin_email'), $subject, $body, array('Reply-To: ' . $email));

    wp_safe_redirect(add_query_arg('contact', $sent ? 'sent' : 'error', home_url('/')) . '#contact');
    exit;
}
add_action('admin_post_nopriv_northline_contact', 'northline_handle_contact');
add_action('admin_post_northline_contact', 'northline_handle_contact');
