<?php
/**
 * Plugin Name: OneLogin SAML Admin Login
 * Description: Forces admin login through SAML (OneLogin).
 * Version: 1.0
 * Author: Kyaw Zayar Win <kyawzayarwin.com>
 * Author URI: https://kyawzayarwin.com
 * License: Beerware License 1.0
 * License URI: https://beerware.org/
 * Text Domain: wp-saml-sso
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/saml-settings.php';

use OneLogin\Saml2\Auth;

add_action('init', function () {
    if (!isset($_GET['sso'])) return;

    $auth = new Auth(get_saml_settings());

    if (!isset($_GET['acs'])) {
        $auth->login(home_url('/wp-admin')); 
        exit;
    }

    $auth->processResponse();

    $errors = $auth->getErrors();
    if (!empty($errors)) {
        wp_die('SAML error: ' . implode(', ', $errors));
    }

    if (!$auth->isAuthenticated()) {
        wp_die('SAML authentication failed.');
    }

    $email     = $auth->getAttribute('email')[0] ?? null;
    $firstName = $auth->getAttribute('firstName')[0] ?? '';
    $lastName  = $auth->getAttribute('lastName')[0] ?? '';
    $username  = sanitize_user(strstr($email, '@', true));

    if (!$email) {
        wp_die('Email not provided in SAML response.');
    }

    $user = get_user_by('email', $email);

    if (!$user) {
        // Auto-provision new user with role 'editor' (change as needed)
        $randomPassword = wp_generate_password(12, true);
        $user_id = wp_create_user($username, $randomPassword, $email);
    
        if (is_wp_error($user_id)) {
            wp_die('Failed to auto-create user: ' . $user_id->get_error_message());
        }
    
        wp_update_user([
            'ID' => $user_id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => $firstName . ' ' . $lastName,
            'role' => 'subscriber', // Change to 'editor' or 'administrator' as needed
        ]);
    
        $user = get_user_by('id', $user_id);
    }

    wp_set_auth_cookie($user->ID, true);
    wp_set_current_user($user->ID);
    do_action('wp_login', $user->user_login, $user);

    wp_redirect(admin_url());
    exit;
});

add_action('login_form', 'saml_add_login_button');
function saml_add_login_button() {
    // Only show if not already redirecting via SAML
    if (isset($_GET['sso'])) return;

    $saml_url = esc_url(wp_login_url() . '?sso=1');

    echo '<p style="text-align:center">';
    echo '<a href="' . $saml_url . '" class="button button-primary button-large" style="margin-top: 20px; margin-bottom: 15px; width: 100%">';
    echo 'Login with SAML';
    echo '</a>';
    echo '</p>';
}

add_action('admin_menu', function() {
    add_options_page(
        'SAML Settings',
        'SAML Settings',
        'manage_options',
        'saml-settings',
        'saml_render_settings_page'
    );
});

add_action('admin_init', function() {
    register_setting('saml_settings_group', 'saml_settings');

    add_settings_section(
        'saml_settings_section',
        'OneLogin SAML Configuration',
        function() {
            echo '<p>Enter your SAML settings below.</p>';
        },
        'saml-settings'
    );

    add_settings_field(
        'idp_entity_id',
        'IdP Entity ID',
        'saml_field_text',
        'saml-settings',
        'saml_settings_section',
        ['label_for' => 'idp_entity_id']
    );

    add_settings_field(
        'idp_entity_binding',
        'IdP Entity Binding',
        'saml_field_text',
        'saml-settings',
        'saml_settings_section',
        ['label_for' => 'idp_entity_binding']
    );

    add_settings_field(
        'idp_sso_url',
        'IdP SSO URL',
        'saml_field_text',
        'saml-settings',
        'saml_settings_section',
        ['label_for' => 'idp_sso_url']
    );

    add_settings_field(
        'idp_sso_url',
        'IdP SSO URL',
        'saml_field_text',
        'saml-settings',
        'saml_settings_section',
        ['label_for' => 'idp_sso_url']
    );

    add_settings_field(
        'idp_x509_cert',
        'IdP X.509 Certificate',
        'saml_field_textarea',
        'saml-settings',
        'saml_settings_section',
        ['label_for' => 'idp_x509_cert']
    );

    add_settings_field(
        'sp_entity_id',
        'SP Entity ID',
        'saml_field_text',
        'saml-settings',
        'saml_settings_section',
        ['label_for' => 'sp_entity_id']
    );

    add_settings_field(
        'sp_entity_binding',
        'Sp Entity Binding',
        'saml_field_text',
        'saml-settings',
        'saml_settings_section',
        ['label_for' => 'sp_entity_binding']
    );

    add_settings_field(
        'sp_acs_url',
        'SP ACS URL',
        'saml_field_text',
        'saml-settings',
        'saml_settings_section',
        ['label_for' => 'sp_acs_url']
    );
});

function saml_field_text($args) {
    $options = get_option('saml_settings');
    $value = isset($options[$args['label_for']]) ? esc_attr($options[$args['label_for']]) : '';
    printf('<input type="text" id="%1$s" name="saml_settings[%1$s]" value="%2$s" style="width: 100%%;" />', esc_attr($args['label_for']), $value);
}

function saml_field_textarea($args) {
    $options = get_option('saml_settings');
    $value = isset($options[$args['label_for']]) ? esc_textarea($options[$args['label_for']]) : '';
    printf('<textarea id="%1$s" name="saml_settings[%1$s]" rows="7" style="width: 100%%;">%2$s</textarea>', esc_attr($args['label_for']), $value);
}

function saml_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>OneLogin SAML Settings</h1>
        <form method="post" action="options.php">
            <?php
                settings_fields('saml_settings_group');
                do_settings_sections('saml-settings');
                submit_button();
            ?>
        </form>
    </div>
    <?php
}