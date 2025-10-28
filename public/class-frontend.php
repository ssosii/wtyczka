<?php

if (!defined('ABSPATH')) {
    exit;
}

class Rezerwacje_Frontend
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
        add_shortcode('rezerwacje_kalendarz', array($this, 'render_calendar'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_rezerwacje_get_therapists', array($this, 'ajax_get_therapists'));
        add_action('wp_ajax_nopriv_rezerwacje_get_therapists', array($this, 'ajax_get_therapists'));
        add_action('wp_ajax_rezerwacje_get_services', array($this, 'ajax_get_services'));
        add_action('wp_ajax_nopriv_rezerwacje_get_services', array($this, 'ajax_get_services'));
        add_action('wp_ajax_rezerwacje_get_available_slots', array($this, 'ajax_get_available_slots'));
        add_action('wp_ajax_nopriv_rezerwacje_get_available_slots', array($this, 'ajax_get_available_slots'));

        // NOWA AKCJA
        add_action('wp_ajax_rezerwacje_get_monthly_availability', array($this, 'ajax_get_monthly_availability'));
        add_action('wp_ajax_nopriv_rezerwacje_get_monthly_availability', array($this, 'ajax_get_monthly_availability'));

        add_action('wp_ajax_rezerwacje_create_booking', array($this, 'ajax_create_booking'));
        add_action('wp_ajax_nopriv_rezerwacje_create_booking', array($this, 'ajax_create_booking'));
    }

    public function enqueue_scripts()
    {
        if (has_shortcode(get_post()->post_content ?? '', 'rezerwacje_kalendarz')) {
            // Dodajemy Google Font dla lepszej estetyki
            wp_enqueue_style('rezerwacje-google-font', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap', array(), null);

            wp_enqueue_style('rezerwacje-frontend', REZERWACJE_PLUGIN_URL . 'public/css/frontend.css', array('rezerwacje-google-font'), REZERWACJE_VERSION);
            wp_enqueue_script('rezerwacje-frontend', REZERWACJE_PLUGIN_URL . 'public/js/frontend.js', array('jquery'), REZERWACJE_VERSION, true);

            wp_localize_script('rezerwacje-frontend', 'rezerwacjeFrontend', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rezerwacje_frontend_nonce')
            ));
        }
    }

    public function render_calendar($atts)
    {
        ob_start();
?>
        <div class="rezerwacje-calendar-wrapper">
            <div class="rezerwacje-step rezerwacje-step-1 active">
                <h3>Krok 1: Wybierz terapeutę</h3>
                <div id="rezerwacje-therapists-list">
                    <div class="rezerwacje-loading"></div>
                </div>
            </div>

            <div class="rezerwacje-step rezerwacje-step-2">
                <h3>Krok 2: Wybierz usługę</h3>
                <div id="rezerwacje-services-list"></div>
            </div>

            <div class="rezerwacje-step rezerwacje-step-3">
                <h3>Krok 3: Wybierz datę</h3>
                <div class="rezerwacje-calendar">
                    <div class="rezerwacje-calendar-header">
                        <button id="rezerwacje-prev-month" class="rezerwacje-btn rezerwacje-btn-nav">&laquo;</button>
                        <span id="rezerwacje-current-month"></span>
                        <button id="rezerwacje-next-month" class="rezerwacje-btn rezerwacje-btn-nav">&raquo;</button>
                    </div>
                    <div id="rezerwacje-calendar-grid"></div>
                </div>
            </div>

            <div class="rezerwacje-step rezerwacje-step-4">
                <h3>Krok 4: Wybierz godzinę</h3>
                <div id="rezerwacje-time-slots"></div>
            </div>

            <div class="rezerwacje-step rezerwacje-step-5">
                <h3>Krok 5: Twoje dane</h3>
                <form id="rezerwacje-booking-form">
                    <div class="rezerwacje-summary">
                        <h4>Podsumowanie:</h4>
                        <p><strong>Terapeuta:</strong> <span id="summary-therapist"></span></p>
                        <p><strong>Usługa:</strong> <span id="summary-service"></span></p>
                        <p><strong>Data:</strong> <span id="summary-date"></span></p>
                        <p><strong>Godzina:</strong> <span id="summary-time"></span></p>
                        <p><strong>Cena:</strong> <span id="summary-price"></span> zł</p>
                    </div>
                    <div class="rezerwacje-form-group">
                        <label for="patient_name">Imię i nazwisko *</label>
                        <input type="text" name="patient_name" id="patient_name" required>
                    </div>
                    <div class="rezerwacje-form-group">
                        <label for="patient_email">Email *</label>
                        <input type="email" name="patient_email" id="patient_email" required>
                    </div>
                    <div class="rezerwacje-form-group">
                        <label for="patient_phone">Telefon</label>
                        <input type="tel" name="patient_phone" id="patient_phone">
                    </div>
                    <div class="rezerwacje-form-group">
                        <label for="notes">Dodatkowe informacje</label>
                        <textarea name="notes" id="notes" rows="3"></textarea>
                    </div>

                    <button type="submit" class="rezerwacje-btn rezerwacje-btn-primary">Zarezerwuj</button>
                </form>
            </div>

            <div class="rezerwacje-step rezerwacje-step-6">
                <h3>Potwierdzenie</h3>
                <div class="rezerwacje-success">
                    <p>Dziękujemy! Twoja rezerwacja została przyjęta i oczekuje na potwierdzenie.</p>
                    <p>Wiadomość email z potwierdzeniem została wysłana na podany adres.</p>
                </div>
            </div>

            <div class="rezerwacje-navigation">
                <button id="rezerwacje-btn-back" class="rezerwacje-btn" style="display: none;">« Wstecz</button>
            </div>
        </div>
<?php
        return ob_get_clean();
    }

    public function ajax_get_therapists()
    {
        check_ajax_referer('rezerwacje_frontend_nonce', 'nonce');

        $therapists = Rezerwacje_Therapist::get_all(array('active' => 1));

        $result = array();
        foreach ($therapists as $therapist) {

            // NOWOŚĆ: Pobranie URL zdjęcia
            $photo_url = '';
            if (!empty($therapist->photo_id)) {
                // Używamy rozmiaru 'thumbnail' (domyślnie 150x150), co jest idealne na awatar
                $photo_url = wp_get_attachment_image_url($therapist->photo_id, 'thumbnail');
            }

            $result[] = array(
                'id' => $therapist->id,
                'name' => $therapist->name,
                'bio' => $therapist->bio,
                'photo_url' => $photo_url ? $photo_url : '' // NOWOŚĆ: Przekazanie URL do JS
            );
        }

        wp_send_json_success($result);
    }

    public function ajax_get_services()
    {
        check_ajax_referer('rezerwacje_frontend_nonce', 'nonce');

        $therapist_id = intval($_POST['therapist_id']);
        $services = Rezerwacje_Therapist::get_assigned_services($therapist_id);

        $result = array();
        foreach ($services as $service) {
            $price = $service->custom_price ? $service->custom_price : $service->default_price;
            $result[] = array(
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'duration' => $service->duration,
                'price' => number_format($price, 2, '.', '')
            );
        }

        wp_send_json_success($result);
    }

    public function ajax_get_available_slots()
    {
        check_ajax_referer('rezerwacje_frontend_nonce', 'nonce');

        $therapist_id = intval($_POST['therapist_id']);
        $service_id = intval($_POST['service_id']);
        $date = sanitize_text_field($_POST['date']);

        $service = Rezerwacje_Service::get($service_id);
        if (!$service) {
            wp_send_json_error('Usługa nie istnieje');
        }

        $slots = Rezerwacje_Availability::get_available_slots($therapist_id, $date, $service->duration);

        wp_send_json_success($slots);
    }

    // NOWA FUNKCJA
    public function ajax_get_monthly_availability()
    {
        check_ajax_referer('rezerwacje_frontend_nonce', 'nonce');

        $therapist_id = intval($_POST['therapist_id']);
        $service_id = intval($_POST['service_id']);
        $month = intval($_POST['month']) + 1; // JS month is 0-11, PHP needs 1-12
        $year = intval($_POST['year']);

        $service = Rezerwacje_Service::get($service_id);
        if (!$service) {
            wp_send_json_error('Usługa nie istnieje');
        }

        $available_dates = Rezerwacje_Availability::get_available_dates_for_month($therapist_id, $service->duration, $month, $year);

        wp_send_json_success($available_dates);
    }

    public function ajax_create_booking()
    {
        check_ajax_referer('rezerwacje_frontend_nonce', 'nonce');

        $therapist_id = intval($_POST['therapist_id']);
        $service_id = intval($_POST['service_id']);
        $booking_date = sanitize_text_field($_POST['booking_date']);
        $start_time = sanitize_text_field($_POST['start_time']);
        $end_time = sanitize_text_field($_POST['end_time']);

        if (Rezerwacje_Booking::is_slot_booked($therapist_id, $booking_date, $start_time, $end_time)) {
            wp_send_json_error('Ten termin jest już zajęty');
        }

        if (Rezerwacje_Availability::is_slot_blocked($therapist_id, $booking_date, $start_time, $end_time)) {
            wp_send_json_error('Ten termin jest zablokowany');
        }

        $price = Rezerwacje_Therapist::get_service_price($therapist_id, $service_id);

        $data = array(
            'therapist_id' => $therapist_id,
            'service_id' => $service_id,
            'patient_name' => sanitize_text_field($_POST['patient_name']),
            'patient_email' => sanitize_email($_POST['patient_email']),
            'patient_phone' => sanitize_text_field($_POST['patient_phone']),
            'patient_user_id' => is_user_logged_in() ? get_current_user_id() : null,
            'booking_date' => $booking_date,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'price' => $price,
            'status' => 'pending',
            'notes' => sanitize_textarea_field($_POST['notes'])
        );

        $booking_id = Rezerwacje_Booking::create($data);

        if ($booking_id) {
            Rezerwacje_Email::send_booking_confirmation($booking_id);
            Rezerwacje_Email::notify_therapist_new_booking($booking_id);
            wp_send_json_success();
        } else {
            wp_send_json_error('Nie udało się utworzyć rezerwacji');
        }
    }
}
