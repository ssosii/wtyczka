<?php

if (!defined('ABSPATH')) {
    exit;
}

class Rezerwacje_Admin
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
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        Rezerwacje_Admin_Therapists::get_instance();
        Rezerwacje_Admin_Services::get_instance();
        Rezerwacje_Admin_Bookings::get_instance();
    }

    public function add_menu()
    {
        add_menu_page(
            'Rezerwacje',
            'Rezerwacje',
            'manage_options',
            'rezerwacje',
            array($this, 'dashboard_page'),
            'dashicons-calendar-alt',
            30
        );

        add_submenu_page(
            'rezerwacje',
            'Panel główny',
            'Panel główny',
            'manage_options',
            'rezerwacje',
            array($this, 'dashboard_page')
        );

        add_submenu_page(
            'rezerwacje',
            'Terapeuci',
            'Terapeuci',
            'manage_options',
            'rezerwacje-therapists',
            array('Rezerwacje_Admin_Therapists', 'render_page')
        );

        add_submenu_page(
            'rezerwacje',
            'Usługi',
            'Usługi',
            'manage_options',
            'rezerwacje-services',
            array('Rezerwacje_Admin_Services', 'render_page')
        );

        add_submenu_page(
            'rezerwacje',
            'Rezerwacje',
            'Rezerwacje',
            'edit_posts',
            'rezerwacje-bookings',
            array('Rezerwacje_Admin_Bookings', 'render_page')
        );

        if (current_user_can('edit_posts') && !current_user_can('manage_options')) {
            $therapist = Rezerwacje_Therapist::get_by_user_id(get_current_user_id());
            if ($therapist) {
                remove_menu_page('rezerwacje');
                add_menu_page(
                    'Moje Rezerwacje',
                    'Moje Rezerwacje',
                    'edit_posts',
                    'rezerwacje-bookings',
                    array('Rezerwacje_Admin_Bookings', 'render_page'),
                    'dashicons-calendar-alt',
                    30
                );
            }
        }
    }

    public function enqueue_scripts($hook)
    {
        if (strpos($hook, 'rezerwacje') === false) {
            return;
        }

        wp_enqueue_style('rezerwacje-admin', REZERWACJE_PLUGIN_URL . 'admin/css/admin.css', array(), REZERWACJE_VERSION);
        wp_enqueue_script('rezerwacje-admin', REZERWACJE_PLUGIN_URL . 'admin/js/admin.js', array('jquery'), REZERWACJE_VERSION, true);

        // Załaduj FullCalendar tylko na stronie rezerwacji w adminie
        if ($hook === 'rezerwacje_page_rezerwacje-bookings' || $hook === 'toplevel_page_rezerwacje-bookings') {
            wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js', array(), '6.1.15', true);
        }

        // NOWOŚĆ: Dodaj skrypty dla edytora terapeutów (Media Uploader i Color Picker)
        if ($hook === 'rezerwacje_page_rezerwacje-therapists') {
            // Włączamy skrypty do obsługi biblioteki mediów
            wp_enqueue_media();

            // Włączamy skrypty i style dla Color Pickera
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
        }

        wp_localize_script('rezerwacje-admin', 'rezerwacjeAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rezerwacje_admin_nonce')
        ));
    }

    public function dashboard_page()
    {
        $total_therapists = count(Rezerwacje_Therapist::get_all(array('active' => 1)));
        $total_services = count(Rezerwacje_Service::get_all(array('active' => 1)));
        $pending_bookings = count(Rezerwacje_Booking::get_all(array('status' => 'pending')));
        $today_bookings = count(Rezerwacje_Booking::get_all(array(
            'status' => 'approved',
            'date_from' => date('Y-m-d'),
            'date_to' => date('Y-m-d')
        )));

?>
        <div class="wrap">
            <h1>Panel Rezerwacji</h1>

            <div class="rezerwacje-dashboard">
                <div class="rezerwacje-stats">
                    <div class="rezerwacje-stat-box">
                        <h3>Terapeuci</h3>
                        <div class="stat-number"><?php echo $total_therapists; ?></div>
                        <a href="<?php echo admin_url('admin.php?page=rezerwacje-therapists'); ?>">Zarządzaj</a>
                    </div>

                    <div class="rezerwacje-stat-box">
                        <h3>Usługi</h3>
                        <div class="stat-number"><?php echo $total_services; ?></div>
                        <a href="<?php echo admin_url('admin.php?page=rezerwacje-services'); ?>">Zarządzaj</a>
                    </div>

                    <div class="rezerwacje-stat-box">
                        <h3>Oczekujące rezerwacje</h3>
                        <div class="stat-number"><?php echo $pending_bookings; ?></div>
                        <a href="<?php echo admin_url('admin.php?page=rezerwacje-bookings&status=pending'); ?>">Zobacz</a>
                    </div>

                    <div class="rezerwacje-stat-box">
                        <h3>Dzisiejsze rezerwacje</h3>
                        <div class="stat-number"><?php echo $today_bookings; ?></div>
                        <a href="<?php echo admin_url('admin.php?page=rezerwacje-bookings'); ?>">Zobacz</a>
                    </div>
                </div>

                <div class="rezerwacje-calendar-link-box">
                    <h2>Kalendarz</h2>
                    <p>Przeglądaj wszystkie rezerwacje w widoku kalendarza.</p>
                    <a href="<?php echo admin_url('admin.php?page=rezerwacje-bookings&action=calendar'); ?>" class="button button-primary">Otwórz kalendarz</a>
                </div>
            </div>
        </div>
<?php
    }
}
