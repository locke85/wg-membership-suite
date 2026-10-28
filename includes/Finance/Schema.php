<?php

namespace wg\membership\Finance;

if (!defined('ABSPATH')) {
    exit;
}

class Schema {
    public const VERSION = '1.0.0';
    public const VERSION_OPTION = 'wg_finance_archive_schema_version';
    public const UPGRADE_LOCK_OPTION = 'wg_finance_archive_schema_upgrade_lock';
    private const UPGRADE_LOCK_TTL = 300;

    public static function table($name) {
        global $wpdb;
        return $wpdb->prefix . 'wg_finance_' . sanitize_key($name);
    }

    public static function maybe_upgrade() {
        if (get_option(self::VERSION_OPTION) === self::VERSION || !self::acquire_upgrade_lock()) {
            return;
        }

        try {
            if (get_option(self::VERSION_OPTION) !== self::VERSION) {
                self::install();
            }
        } finally {
            delete_option(self::UPGRADE_LOCK_OPTION);
        }
    }

    private static function acquire_upgrade_lock() {
        $now = time();
        if (add_option(self::UPGRADE_LOCK_OPTION, $now, '', false)) {
            return true;
        }

        $locked_at = (int) get_option(self::UPGRADE_LOCK_OPTION, 0);
        if ($locked_at > 0 && ($now - $locked_at) < self::UPGRADE_LOCK_TTL) {
            return false;
        }

        delete_option(self::UPGRADE_LOCK_OPTION);
        return add_option(self::UPGRADE_LOCK_OPTION, $now, '', false);
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $batches = self::table('batches');
        $objects = self::table('objects');
        $relations = self::table('relations');
        $reviews = self::table('reviews');
        $audit = self::table('audit');

        dbDelta("CREATE TABLE {$batches} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            batch_uuid char(36) NOT NULL,
            source_system varchar(64) NOT NULL,
            manifest_hash char(64) NOT NULL,
            source_export_hash char(64) NOT NULL DEFAULT '',
            status varchar(32) NOT NULL,
            counts_json longtext NOT NULL,
            started_at datetime NOT NULL,
            completed_at datetime NULL,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY batch_uuid (batch_uuid),
            KEY source_manifest (source_system,manifest_hash)
        ) {$charset};");

        dbDelta("CREATE TABLE {$objects} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            logical_id char(36) NOT NULL,
            source_system varchar(64) NOT NULL,
            object_type varchar(32) NOT NULL,
            source_id varchar(191) NOT NULL,
            version int(10) unsigned NOT NULL,
            object_hash char(64) NOT NULL,
            batch_id bigint(20) unsigned NOT NULL,
            status varchar(64) NOT NULL DEFAULT '',
            occurred_at datetime NULL,
            customer_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            customer_ref_hash char(64) NOT NULL DEFAULT '',
            invoice_number varchar(191) NOT NULL DEFAULT '',
            amount_net decimal(20,6) NULL,
            amount_tax decimal(20,6) NULL,
            amount_gross decimal(20,6) NULL,
            currency char(3) NOT NULL DEFAULT 'EUR',
            document_class varchar(64) NOT NULL,
            retention_starts_on date NULL,
            earliest_disposal_on date NULL,
            legal_basis varchar(255) NOT NULL,
            hold_reason varchar(64) NOT NULL DEFAULT '',
            original_relpath varchar(255) NOT NULL DEFAULT '',
            original_sha256 char(64) NOT NULL DEFAULT '',
            snapshot_json longtext NOT NULL,
            imported_at datetime NOT NULL,
            imported_by bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY source_version (source_system,object_type,source_id,version),
            UNIQUE KEY source_hash (source_system,object_type,source_id,object_hash),
            KEY logical_id (logical_id),
            KEY invoice_number (invoice_number),
            KEY customer_date (customer_user_id,occurred_at),
            KEY batch_id (batch_id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$relations} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            batch_id bigint(20) unsigned NOT NULL,
            from_logical_id char(36) NOT NULL,
            relation_type varchar(64) NOT NULL,
            to_source_system varchar(64) NOT NULL,
            to_object_type varchar(32) NOT NULL,
            to_source_id varchar(191) NOT NULL,
            relation_hash char(64) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY relation_hash (relation_hash),
            KEY from_logical_id (from_logical_id),
            KEY target (to_source_system,to_object_type,to_source_id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$reviews} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            batch_id bigint(20) unsigned NOT NULL,
            source_system varchar(64) NOT NULL,
            object_type varchar(32) NOT NULL,
            source_id varchar(191) NOT NULL,
            reason_code varchar(64) NOT NULL,
            detail_json longtext NOT NULL,
            status varchar(32) NOT NULL DEFAULT 'open',
            created_at datetime NOT NULL,
            resolved_at datetime NULL,
            resolved_by bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY open_finding (source_system,object_type,source_id,reason_code,status),
            KEY batch_status (batch_id,status)
        ) {$charset};");

        dbDelta("CREATE TABLE {$audit} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_uuid char(36) NOT NULL,
            event_type varchar(64) NOT NULL,
            object_id bigint(20) unsigned NOT NULL DEFAULT 0,
            object_hash char(64) NOT NULL DEFAULT '',
            batch_id bigint(20) unsigned NOT NULL DEFAULT 0,
            actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            result varchar(32) NOT NULL,
            detail_json longtext NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY event_uuid (event_uuid),
            KEY object_id (object_id),
            KEY batch_id (batch_id),
            KEY created_at (created_at)
        ) {$charset};");

        $administrator = get_role('administrator');
        if ($administrator) {
            foreach ([
                \wg\membership\Config::VIEW_FINANCE_ARCHIVE_CAPABILITY,
                \wg\membership\Config::IMPORT_FINANCE_ARCHIVE_CAPABILITY,
                \wg\membership\Config::REPRINT_FINANCE_ARCHIVE_CAPABILITY,
            ] as $capability) {
                $administrator->add_cap($capability);
            }
        }

        update_option(self::VERSION_OPTION, self::VERSION, false);
    }
}
