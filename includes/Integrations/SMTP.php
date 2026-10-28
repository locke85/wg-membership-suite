<?php

namespace wg\membership\Integrations;

use Throwable;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

class SMTP {
    const CONFIG_OPTION = 'wg_membership_suite_smtp';
    const SECRET_OPTION = 'wg_membership_suite_smtp_secret';
    const LAST_TEST_OPTION = 'wg_membership_suite_smtp_last_test';
    const ADMIN_ACTION = 'wg_membership_smtp_save';

    public static function register() {
        add_action('phpmailer_init', [static::class, 'configure_phpmailer'], 999, 1);
        add_action('admin_post_' . self::ADMIN_ACTION, [static::class, 'handle_admin_save']);
    }

    public static function defaults() {
        return [
            'enabled' => false,
            'host' => '',
            'port' => 587,
            'encryption' => 'tls',
            'username' => '',
            'from_email' => '',
            'from_name' => '',
        ];
    }

    public static function sanitize_config($input) {
        $input = is_array($input) ? $input : [];
        $host = strtolower(trim((string) ($input['host'] ?? '')));
        if (!preg_match('/^(?=.{1,253}$)(?!-)[a-z0-9-]+(?:\.[a-z0-9-]+)+(?!-)$/', $host)) {
            $host = '';
        }

        $port = absint($input['port'] ?? 587);
        if ($port < 1 || $port > 65535) {
            $port = 587;
        }

        $encryption = sanitize_key((string) ($input['encryption'] ?? 'tls'));
        if (!in_array($encryption, ['tls', 'ssl'], true)) {
            $encryption = 'tls';
        }

        $username = sanitize_text_field((string) ($input['username'] ?? ''));
        $from_email = sanitize_email((string) ($input['from_email'] ?? ''));
        $from_name = sanitize_text_field((string) ($input['from_name'] ?? ''));

        return [
            'enabled' => !empty($input['enabled']),
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'username' => substr($username, 0, 254),
            'from_email' => $from_email,
            'from_name' => substr($from_name, 0, 160),
        ];
    }

    public static function get_config() {
        return self::sanitize_config(array_replace(self::defaults(), (array) get_option(self::CONFIG_OPTION, [])));
    }

    private static function encryption_key() {
        $material = wp_salt('auth') . '|' . wp_salt('secure_auth') . '|' . get_current_blog_id() . '|wg-membership-suite-smtp';
        return hash('sha256', $material, true);
    }

    public static function encrypt_secret($password) {
        $password = (string) $password;
        if ($password === '') {
            return '';
        }

        try {
            if (function_exists('sodium_crypto_secretbox') && function_exists('sodium_crypto_secretbox_keygen')) {
                $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                $ciphertext = sodium_crypto_secretbox($password, $nonce, self::encryption_key());
                return 'sodium-v1:' . base64_encode($nonce . $ciphertext);
            }

            if (function_exists('openssl_encrypt')) {
                $iv = random_bytes(12);
                $tag = '';
                $ciphertext = openssl_encrypt($password, 'aes-256-gcm', self::encryption_key(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
                if ($ciphertext !== false && strlen($tag) === 16) {
                    return 'aesgcm-v1:' . base64_encode($iv . $tag . $ciphertext);
                }
            }
        } catch (Throwable $error) {
            return '';
        }

        return '';
    }

    public static function decrypt_secret($stored = null) {
        $stored = $stored === null ? get_option(self::SECRET_OPTION, '') : $stored;
        $stored = (string) $stored;
        if ($stored === '' || strpos($stored, ':') === false) {
            return '';
        }

        [$version, $encoded] = explode(':', $stored, 2);
        $payload = base64_decode($encoded, true);
        if ($payload === false) {
            return '';
        }

        try {
            if ($version === 'sodium-v1' && function_exists('sodium_crypto_secretbox_open')) {
                $nonce_length = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
                if (strlen($payload) <= $nonce_length) {
                    return '';
                }
                $plaintext = sodium_crypto_secretbox_open(substr($payload, $nonce_length), substr($payload, 0, $nonce_length), self::encryption_key());
                return $plaintext === false ? '' : (string) $plaintext;
            }

            if ($version === 'aesgcm-v1' && function_exists('openssl_decrypt') && strlen($payload) > 28) {
                $plaintext = openssl_decrypt(substr($payload, 28), 'aes-256-gcm', self::encryption_key(), OPENSSL_RAW_DATA, substr($payload, 0, 12), substr($payload, 12, 16));
                return $plaintext === false ? '' : (string) $plaintext;
            }
        } catch (Throwable $error) {
            return '';
        }

        return '';
    }

    public static function has_secret() {
        return self::decrypt_secret() !== '';
    }

    public static function is_configuration_complete($config = null, $password = null) {
        $config = $config === null ? self::get_config() : self::sanitize_config($config);
        $password = $password === null ? self::decrypt_secret() : (string) $password;
        return !empty($config['enabled'])
            && $config['host'] !== ''
            && $config['port'] > 0
            && $config['username'] !== ''
            && $config['from_email'] !== ''
            && is_email($config['from_email'])
            && $password !== '';
    }

    public static function configure_phpmailer($phpmailer) {
        $config = self::get_config();
        $password = self::decrypt_secret();
        if (!is_object($phpmailer) || !self::is_configuration_complete($config, $password)) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = $config['host'];
        $phpmailer->Port = $config['port'];
        $phpmailer->SMTPAuth = true;
        $phpmailer->SMTPSecure = $config['encryption'];
        $phpmailer->SMTPAutoTLS = true;
        $phpmailer->Username = $config['username'];
        $phpmailer->Password = $password;
        $phpmailer->setFrom($config['from_email'], $config['from_name'], false);
        $phpmailer->Sender = $config['from_email'];
    }

    private static function redirect_with_status($status) {
        $url = add_query_arg('wg_smtp_status', sanitize_key((string) $status), admin_url('options-general.php?page=wg-membership-suite#smtp'));
        wp_safe_redirect($url);
        exit;
    }

    public static function handle_admin_save() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Sie dürfen die SMTP-Einstellungen nicht ändern.', 'wg-membership-suite'), '', ['response' => 403]);
        }
        check_admin_referer(self::ADMIN_ACTION);

        $input = isset($_POST['wg_membership_smtp']) && is_array($_POST['wg_membership_smtp'])
            ? wp_unslash($_POST['wg_membership_smtp'])
            : [];
        $password = isset($input['password']) ? (string) $input['password'] : '';
        unset($input['password']);
        unset($_POST['wg_membership_smtp']['password']);
        $input['enabled'] = true;
        $config = self::sanitize_config($input);

        if ($config['host'] === '' || $config['username'] === '' || $config['from_email'] === '' || !is_email($config['from_email'])) {
            self::redirect_with_status('config_error');
        }

        if ($password !== '') {
            $encrypted = self::encrypt_secret($password);
            $password = '';
            if ($encrypted === '') {
                self::redirect_with_status('encryption_error');
            }
            update_option(self::SECRET_OPTION, $encrypted, false);
            unset($encrypted);
        } elseif (!self::has_secret()) {
            self::redirect_with_status('password_required');
        }

        update_option(self::CONFIG_OPTION, $config, false);

        $user = wp_get_current_user();
        $recipient = $user instanceof WP_User ? sanitize_email((string) $user->user_email) : '';
        $wp_mail_result = $recipient !== '' && is_email($recipient)
            ? wp_mail(
                $recipient,
                __('SMTP-Test: Tanzlehrer Recruiting', 'wg-membership-suite'),
                __('Der authentifizierte E-Mail-Versand der Membership Suite wurde eingerichtet. Bitte bestätigen Sie Jan kurz, dass diese Testmail angekommen ist.', 'wg-membership-suite')
            )
            : false;
        global $phpmailer;
        $accepted = $wp_mail_result
            && is_object($phpmailer)
            && strtolower((string) ($phpmailer->Mailer ?? '')) === 'smtp'
            && !empty($phpmailer->SMTPAuth);
        update_option(self::LAST_TEST_OPTION, [
            'status' => $accepted ? 'accepted_by_smtp' : 'failed',
            'tested_at' => current_time('mysql'),
            'tested_by' => get_current_user_id(),
        ], false);

        self::redirect_with_status($accepted ? 'accepted_by_smtp' : 'test_failed');
    }

    public static function render_settings_panel() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $config = self::get_config();
        $has_secret = self::has_secret();
        $last_test = (array) get_option(self::LAST_TEST_OPTION, []);
        $status = isset($_GET['wg_smtp_status']) ? sanitize_key((string) wp_unslash($_GET['wg_smtp_status'])) : '';
        $messages = [
            'accepted_by_smtp' => ['notice notice-success', __('Die Testmail wurde vom SMTP-Server angenommen. Bitte prüfen Sie jetzt den Eingang und bestätigen Sie Jan kurz.', 'wg-membership-suite')],
            'test_failed' => ['notice notice-error', __('Der SMTP-Test ist fehlgeschlagen. Prüfen Sie Passwort und Serverdaten und versuchen Sie es erneut.', 'wg-membership-suite')],
            'password_required' => ['notice notice-error', __('Bitte geben Sie das SMTP-Passwort einmalig ein.', 'wg-membership-suite')],
            'config_error' => ['notice notice-error', __('Die SMTP-Angaben sind unvollständig oder ungültig.', 'wg-membership-suite')],
            'encryption_error' => ['notice notice-error', __('Das SMTP-Passwort konnte nicht sicher gespeichert werden.', 'wg-membership-suite')],
        ];
        ?>
        <hr>
        <section id="smtp">
            <h2><?php esc_html_e('E-Mail-Versand (SMTP)', 'wg-membership-suite'); ?></h2>
            <p><?php esc_html_e('Das Passwort wird einmalig verschlüsselt gespeichert und danach nie wieder angezeigt. Es gehört nicht in E-Mails, Chats oder Änderungswünsche.', 'wg-membership-suite'); ?></p>
            <?php if (isset($messages[$status])) : ?>
                <div class="<?php echo esc_attr($messages[$status][0]); ?>"><p><?php echo esc_html($messages[$status][1]); ?></p></div>
            <?php endif; ?>
            <p><strong><?php esc_html_e('Passwortstatus:', 'wg-membership-suite'); ?></strong> <?php echo esc_html($has_secret ? __('sicher hinterlegt', 'wg-membership-suite') : __('noch nicht hinterlegt', 'wg-membership-suite')); ?></p>
            <?php if (!empty($last_test['status'])) : ?>
                <p><strong><?php esc_html_e('Letzter SMTP-Test:', 'wg-membership-suite'); ?></strong> <?php echo esc_html((string) $last_test['status']); ?><?php if (!empty($last_test['tested_at'])) : ?> · <?php echo esc_html((string) $last_test['tested_at']); ?><?php endif; ?></p>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ADMIN_ACTION); ?>">
                <?php wp_nonce_field(self::ADMIN_ACTION); ?>
                <table class="form-table" role="presentation">
                    <tr><th><label for="wg_smtp_host"><?php esc_html_e('SMTP-Server', 'wg-membership-suite'); ?></label></th><td><input class="regular-text" id="wg_smtp_host" name="wg_membership_smtp[host]" value="<?php echo esc_attr($config['host']); ?>" required></td></tr>
                    <tr><th><label for="wg_smtp_port"><?php esc_html_e('Port', 'wg-membership-suite'); ?></label></th><td><input class="small-text" type="number" min="1" max="65535" id="wg_smtp_port" name="wg_membership_smtp[port]" value="<?php echo esc_attr($config['port']); ?>" required></td></tr>
                    <tr><th><label for="wg_smtp_encryption"><?php esc_html_e('Verschlüsselung', 'wg-membership-suite'); ?></label></th><td><select id="wg_smtp_encryption" name="wg_membership_smtp[encryption]"><option value="tls" <?php selected($config['encryption'], 'tls'); ?>>STARTTLS</option><option value="ssl" <?php selected($config['encryption'], 'ssl'); ?>>SSL/TLS</option></select></td></tr>
                    <tr><th><label for="wg_smtp_username"><?php esc_html_e('Benutzername', 'wg-membership-suite'); ?></label></th><td><input class="regular-text" id="wg_smtp_username" name="wg_membership_smtp[username]" value="<?php echo esc_attr($config['username']); ?>" autocomplete="username" required></td></tr>
                    <tr><th><label for="wg_smtp_from_email"><?php esc_html_e('Absenderadresse', 'wg-membership-suite'); ?></label></th><td><input class="regular-text" type="email" id="wg_smtp_from_email" name="wg_membership_smtp[from_email]" value="<?php echo esc_attr($config['from_email']); ?>" required></td></tr>
                    <tr><th><label for="wg_smtp_from_name"><?php esc_html_e('Absendername', 'wg-membership-suite'); ?></label></th><td><input class="regular-text" id="wg_smtp_from_name" name="wg_membership_smtp[from_name]" value="<?php echo esc_attr($config['from_name']); ?>" required></td></tr>
                    <tr><th><label for="wg_smtp_password"><?php esc_html_e('SMTP-Passwort', 'wg-membership-suite'); ?></label></th><td><input class="regular-text" type="password" id="wg_smtp_password" name="wg_membership_smtp[password]" value="" autocomplete="new-password" <?php echo $has_secret ? '' : 'required'; ?>><p class="description"><?php echo esc_html($has_secret ? __('Leer lassen, um das sicher hinterlegte Passwort beizubehalten. Eine Eingabe ersetzt es.', 'wg-membership-suite') : __('Einmalig das Passwort des E-Mail-Postfachs eingeben. Es wird nicht wieder angezeigt.', 'wg-membership-suite')); ?></p></td></tr>
                </table>
                <?php submit_button(__('Speichern und Testmail senden', 'wg-membership-suite'), 'primary', 'submit', false); ?>
            </form>
        </section>
        <?php
    }
}
