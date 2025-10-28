<?php

/**
 * Plugin Name: Rezerwacje
 * Plugin URI: https://example.com
 * Description: System rezerwacji dla terapeutów - zarządzanie terapeutami, usługami, dostępnością i rezerwacjami
 * Version: 1.0.2
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * Text Domain: rezerwacje
 * Domain Path: /languages
 */

// Zmieniono wersję na 1.0.2
if (!defined('ABSPATH')) {
    exit;
}

define('REZERWACJE_VERSION', '1.0.2');
define('REZERWACJE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('REZERWACJE_PLUGIN_URL', plugin_dir_url(__FILE__));

class Rezerwacje
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->includes();
        $this->init_hooks();
    }

    private function includes()
    {
        require_once REZERWACJE_PLUGIN_DIR . 'includes/class-database.php';
        require_once REZERWACJE_PLUGIN_DIR . 'includes/class-therapist.php';
        require_once REZERWACJE_PLUGIN_DIR . 'includes/class-service.php';
        require_once REZERWACJE_PLUGIN_DIR . 'includes/class-availability.php';
        require_once REZERWACJE_PLUGIN_DIR . 'includes/class-booking.php';
        require_once REZERWACJE_PLUGIN_DIR . 'includes/class-email.php';

        if (is_admin()) {
            require_once REZERWACJE_PLUGIN_DIR . 'admin/class-admin.php';
            require_once REZERWACJE_PLUGIN_DIR . 'admin/class-admin-therapists.php';
            require_once REZERWACJE_PLUGIN_DIR . 'admin/class-admin-services.php';
            require_once REZERWACJE_PLUGIN_DIR . 'admin/class-admin-bookings.php';
        }

        require_once REZERWACJE_PLUGIN_DIR . 'public/class-frontend.php';
    }

    private function init_hooks()
    {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init'));

        // --- NOWY KOD NAPRAWCZY (Wersja 2: "Na twardo") ---
        add_action('admin_init', array($this, 'run_manual_db_fix_102'));
        // --- KONIEC KODU NAPRAWCZEGO ---
    }

    // --- NOWA FUNKCJA NAPRAWCZA ---
    public function run_manual_db_fix_102()
    {
        // Uruchom ten kod tylko raz
        if (get_transient('_rezerwacje_fix_102_applied')) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'rezerwacje_therapists';

        // 1. Sprawdź, czy istnieje kolumna 'photo_id'
        $photo_id_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME,
            $table_name,
            'photo_id'
        ));

        // Jeśli nie istnieje, dodaj ją
        if ($photo_id_exists == 0) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN photo_id bigint(20) DEFAULT NULL AFTER bio");
        }

        // 2. Sprawdź, czy istnieje kolumna 'calendar_color'
        $calendar_color_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME,
            $table_name,
            'calendar_color'
        ));

        // Jeśli nie istnieje, dodaj ją
        if ($calendar_color_exists == 0) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN calendar_color varchar(20) DEFAULT NULL AFTER photo_id");
        }

        // Oznacz jako wykonane, aby nie uruchamiać tego ponownie
        set_transient('_rezerwacje_fix_102_applied', 'true', DAY_IN_SECONDS);
        update_option('rezerwacje_db_version', REZERWACJE_VERSION);
    }
    // --- KONIEC NOWEJ FUNKCJI ---

    public function activate()
    {
        Rezerwacje_Database::create_tables();
        flush_rewrite_rules();
    }

    public function deactivate()
    {
        flush_rewrite_rules();
    }

    public function load_textdomain()
    {
        load_plugin_textdomain('rezerwacje', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function init()
    {
        if (is_admin()) {
            Rezerwacje_Admin::get_instance();
        }
        Rezerwacje_Frontend::get_instance();
    }
}

function rezerwacje()
{
    return Rezerwacje::get_instance();
}

rezerwacje();
