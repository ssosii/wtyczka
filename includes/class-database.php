<?php

if (!defined('ABSPATH')) {
    exit;
}

class Rezerwacje_Database {

    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = array();

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rezerwacje_therapists (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(50) DEFAULT NULL,
            bio text DEFAULT NULL,
            active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rezerwacje_services (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            duration int(11) NOT NULL DEFAULT 60,
            default_price decimal(10,2) NOT NULL DEFAULT 0.00,
            active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rezerwacje_therapist_services (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            therapist_id bigint(20) NOT NULL,
            service_id bigint(20) NOT NULL,
            custom_price decimal(10,2) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY therapist_service (therapist_id, service_id),
            KEY therapist_id (therapist_id),
            KEY service_id (service_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rezerwacje_availability (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            therapist_id bigint(20) NOT NULL,
            day_of_week tinyint(1) NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY therapist_id (therapist_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rezerwacje_bookings (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            therapist_id bigint(20) NOT NULL,
            service_id bigint(20) NOT NULL,
            patient_name varchar(255) NOT NULL,
            patient_email varchar(255) NOT NULL,
            patient_phone varchar(50) DEFAULT NULL,
            patient_user_id bigint(20) DEFAULT NULL,
            booking_date date NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            price decimal(10,2) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY therapist_id (therapist_id),
            KEY service_id (service_id),
            KEY booking_date (booking_date),
            KEY status (status)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}rezerwacje_blocked_slots (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            therapist_id bigint(20) NOT NULL,
            patient_name varchar(255) NOT NULL,
            start_date date NOT NULL,
            end_date date DEFAULT NULL,
            day_of_week tinyint(1) DEFAULT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            is_recurring tinyint(1) DEFAULT 0,
            recurrence_end_date date DEFAULT NULL,
            notes text DEFAULT NULL,
            created_by bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY therapist_id (therapist_id),
            KEY start_date (start_date)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        foreach ($sql as $query) {
            dbDelta($query);
        }

        update_option('rezerwacje_db_version', REZERWACJE_VERSION);
    }

    public static function drop_tables() {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'rezerwacje_blocked_slots',
            $wpdb->prefix . 'rezerwacje_bookings',
            $wpdb->prefix . 'rezerwacje_availability',
            $wpdb->prefix . 'rezerwacje_therapist_services',
            $wpdb->prefix . 'rezerwacje_services',
            $wpdb->prefix . 'rezerwacje_therapists'
        );

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }

        delete_option('rezerwacje_db_version');
    }
}
