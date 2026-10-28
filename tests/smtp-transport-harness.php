<?php
/** Minimal WordPress compatibility harness for the Membership Suite SMTP adapter. */

define('ABSPATH', __DIR__ . '/');

$GLOBALS['smtp_options'] = [];
$GLOBALS['smtp_blog_id'] = 8;
$GLOBALS['smtp_actions'] = [];

function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
    $GLOBALS['smtp_actions'][] = compact('hook', 'callback', 'priority', 'accepted_args');
}
function get_option($key, $default = false) { return $GLOBALS['smtp_options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['smtp_options'][$key] = $value; return true; }
function get_current_blog_id() { return $GLOBALS['smtp_blog_id']; }
function wp_salt($scheme = 'auth') { return 'fixture-' . $scheme; }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_email($value) { return filter_var((string) $value, FILTER_SANITIZE_EMAIL); }
function is_email($value) { return filter_var((string) $value, FILTER_VALIDATE_EMAIL) !== false; }
function current_user_can($capability) { return $capability === 'manage_options'; }
function wp_unslash($value) { return $value; }
function __($value) { return $value; }
function esc_html__($value) { return $value; }
function esc_html_e($value) { echo esc_html($value); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function esc_url($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function admin_url($path = '') { return 'https://example.test/wp-admin/' . ltrim($path, '/'); }
function wp_nonce_field($action) { echo '<input type="hidden" name="nonce" value="fixture">'; }
function selected($value, $expected) { if ((string) $value === (string) $expected) echo 'selected'; }
function submit_button($text, $type = 'primary', $name = 'submit', $wrap = true) { echo '<button name="' . esc_attr($name) . '">' . esc_html($text) . '</button>'; }

class WP_User {}

class Fixture_Mailer {
    public $Mailer = 'mail';
    public $Host = '';
    public $Port = 0;
    public $SMTPAuth = false;
    public $SMTPSecure = '';
    public $SMTPAutoTLS = false;
    public $Username = '';
    public $Password = '';
    public $From = '';
    public $FromName = '';
    public $Sender = '';

    public function isSMTP() { $this->Mailer = 'smtp'; }
    public function setFrom($email, $name, $auto = true) { $this->From = $email; $this->FromName = $name; }
}

function smtp_assert($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

require_once dirname(__DIR__) . '/includes/Integrations/SMTP.php';

use wg\membership\Integrations\SMTP;

$valid = SMTP::sanitize_config([
    'enabled' => true,
    'host' => 'KUNDEN.TANZSCHULSERVICE.DE',
    'port' => '587',
    'encryption' => 'tls',
    'username' => 'oliver.fleidl@tanzschule-muenchen-dt.de',
    'from_email' => 'oliver.fleidl@tanzschule-muenchen-dt.de',
    'from_name' => 'Tanzlehrer Recruiting',
]);
smtp_assert($valid['host'] === 'kunden.tanzschulservice.de', 'SMTP host is normalized.');
smtp_assert($valid['port'] === 587 && $valid['encryption'] === 'tls', 'Port and STARTTLS are preserved.');

$invalid = SMTP::sanitize_config(['enabled' => true, 'host' => 'https://bad host', 'port' => 99999, 'encryption' => 'none']);
smtp_assert($invalid['host'] === '' && $invalid['port'] === 587 && $invalid['encryption'] === 'tls', 'Invalid transport values fail closed.');

$plaintext = 'fixture-smtp-password';
$encrypted = SMTP::encrypt_secret($plaintext);
smtp_assert($encrypted !== '' && strpos($encrypted, $plaintext) === false, 'Password is encrypted and absent from the stored payload.');
$GLOBALS['smtp_options'][SMTP::SECRET_OPTION] = $encrypted;
smtp_assert(SMTP::decrypt_secret() === $plaintext, 'Encrypted password decrypts on the same site.');
$GLOBALS['smtp_blog_id'] = 9;
smtp_assert(SMTP::decrypt_secret() === '', 'Encrypted password is bound to the originating site.');
$GLOBALS['smtp_blog_id'] = 8;

$GLOBALS['smtp_options'][SMTP::CONFIG_OPTION] = $valid;
smtp_assert(SMTP::is_configuration_complete(), 'Complete authenticated SMTP configuration is recognized.');
$mailer = new Fixture_Mailer();
SMTP::configure_phpmailer($mailer);
smtp_assert($mailer->Mailer === 'smtp', 'PHPMailer switches to SMTP.');
smtp_assert($mailer->Host === 'kunden.tanzschulservice.de' && $mailer->Port === 587, 'PHPMailer receives host and port.');
smtp_assert($mailer->SMTPAuth === true && $mailer->SMTPSecure === 'tls', 'PHPMailer requires authenticated STARTTLS.');
smtp_assert($mailer->Username === 'oliver.fleidl@tanzschule-muenchen-dt.de' && $mailer->Password === $plaintext, 'PHPMailer receives the configured credentials at runtime.');
smtp_assert($mailer->From === 'oliver.fleidl@tanzschule-muenchen-dt.de' && $mailer->Sender === 'oliver.fleidl@tanzschule-muenchen-dt.de', 'Header and envelope sender are aligned.');

ob_start();
SMTP::render_settings_panel();
$html = ob_get_clean();
smtp_assert(strpos($html, 'type="password"') !== false && strpos($html, 'value=""') !== false, 'Admin form renders an empty password field.');
smtp_assert(strpos($html, $plaintext) === false && strpos($html, $encrypted) === false, 'Admin form never reads the secret back.');

SMTP::register();
$registered_hooks = array_column($GLOBALS['smtp_actions'], 'hook');
smtp_assert(in_array('phpmailer_init', $registered_hooks, true), 'PHPMailer hook is registered.');
smtp_assert(in_array('admin_post_' . SMTP::ADMIN_ACTION, $registered_hooks, true), 'Protected admin save action is registered.');

echo "SMTP transport harness passed: 12 assertions.\n";
