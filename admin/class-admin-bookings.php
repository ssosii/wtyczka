<?php

if (!defined('ABSPATH')) {
    exit;
}

class Rezerwacje_Admin_Bookings
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
        add_action('wp_ajax_rezerwacje_approve_booking', array($this, 'ajax_approve_booking'));
        add_action('wp_ajax_rezerwacje_reject_booking', array($this, 'ajax_reject_booking'));
        add_action('wp_ajax_rezerwacje_add_blocked_slot', array($this, 'ajax_add_blocked_slot'));
        add_action('wp_ajax_rezerwacje_remove_blocked_slot', array($this, 'ajax_remove_blocked_slot'));
        add_action('wp_ajax_rezerwacje_get_calendar_bookings', array($this, 'ajax_get_calendar_bookings'));
    }

    public static function render_page()
    {
        if (!current_user_can('edit_posts')) {
            wp_die('Brak uprawnień');
        }

        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';

        switch ($action) {
            case 'block':
                self::render_block_form();
                break;
            case 'blocked':
                self::render_blocked_slots();
                break;
            case 'calendar':
                self::render_calendar();
                break;
            default:
                self::render_list();
                break;
        }
    }

    private static function render_list()
    {
        $current_user = wp_get_current_user();
        $is_admin = current_user_can('manage_options');

        $args = array();
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

        if ($status_filter) {
            $args['status'] = $status_filter;
        }

        if (!$is_admin) {
            $therapist = Rezerwacje_Therapist::get_by_user_id($current_user->ID);
            if ($therapist) {
                $args['therapist_id'] = $therapist->id;
            }
        }

        $bookings = Rezerwacje_Booking::get_all($args);

?>
        <div class="wrap">
            <h1>
                Rezerwacje
                <?php if ($is_admin): ?>
                    <a href="<?php echo admin_url('admin.php?page=rezerwacje-bookings&action=block'); ?>" class="page-title-action">Zablokuj termin</a>
                    <a href="<?php echo admin_url('admin.php?page=rezerwacje-bookings&action=blocked'); ?>" class="page-title-action">Zablokowane terminy</a>
                <?php endif; ?>
                <a href="<?php echo admin_url('admin.php?page=rezerwacje-bookings&action=calendar'); ?>" class="page-title-action">Widok kalendarza</a>
            </h1>

            <ul class="subsubsub">
                <li><a href="<?php echo admin_url('admin.php?page=rezerwacje-bookings'); ?>" <?php echo empty($status_filter) ? 'class="current"' : ''; ?>>Wszystkie</a> |</li>
                <li><a href="<?php echo admin_url('admin.php?page=rezerwacje-bookings&status=pending'); ?>" <?php echo $status_filter === 'pending' ? 'class="current"' : ''; ?>>Oczekujące</a> |</li>
                <li><a href="<?php echo admin_url('admin.php?page=rezerwacje-bookings&status=approved'); ?>" <?php echo $status_filter === 'approved' ? 'class="current"' : ''; ?>>Zatwierdzone</a> |</li>
                <li><a href="<?php echo admin_url('admin.php?page=rezerwacje-bookings&status=rejected'); ?>" <?php echo $status_filter === 'rejected' ? 'class="current"' : ''; ?>>Odrzucone</a></li>
            </ul>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Terapeuta</th>
                        <th>Pacjent</th>
                        <th>Usługa</th>
                        <th>Data</th>
                        <th>Godzina</th>
                        <th>Cena</th>
                        <th>Status</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="9">Brak rezerwacji</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?php echo $booking->id; ?></td>
                                <td><?php echo esc_html($booking->therapist_name); ?></td>
                                <td>
                                    <strong><?php echo esc_html($booking->patient_name); ?></strong><br>
                                    <?php echo esc_html($booking->patient_email); ?><br>
                                    <?php echo esc_html($booking->patient_phone); ?>
                                </td>
                                <td><?php echo esc_html($booking->service_name); ?></td>
                                <td><?php echo date_i18n('d.m.Y', strtotime($booking->booking_date)); ?></td>
                                <td><?php echo date('H:i', strtotime($booking->start_time)) . ' - ' . date('H:i', strtotime($booking->end_time)); ?></td>
                                <td><?php echo number_format($booking->price, 2); ?> zł</td>
                                <td>
                                    <?php
                                    $status_labels = array(
                                        'pending' => 'Oczekująca',
                                        'approved' => 'Zatwierdzona',
                                        'rejected' => 'Odrzucona',
                                        'cancelled' => 'Anulowana'
                                    );
                                    echo isset($status_labels[$booking->status]) ? $status_labels[$booking->status] : $booking->status;
                                    ?>
                                </td>
                                <td>
                                    <?php if ($booking->status === 'pending'): ?>
                                        <a href="#" class="approve-booking" data-id="<?php echo $booking->id; ?>">Zatwierdź</a> |
                                        <a href="#" class="reject-booking" data-id="<?php echo $booking->id; ?>">Odrzuć</a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $('.approve-booking').on('click', function(e) {
                    e.preventDefault();

                    if (!confirm('Czy na pewno zatwierdzić tę rezerwację?')) {
                        return;
                    }

                    var id = $(this).data('id');

                    $.post(rezerwacjeAdmin.ajax_url, {
                        action: 'rezerwacje_approve_booking',
                        nonce: rezerwacjeAdmin.nonce,
                        id: id
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Błąd: ' + response.data);
                        }
                    });
                });

                $('.reject-booking').on('click', function(e) {
                    e.preventDefault();

                    if (!confirm('Czy na pewno odrzucić tę rezerwację?')) {
                        return;
                    }

                    var id = $(this).data('id');

                    $.post(rezerwacjeAdmin.ajax_url, {
                        action: 'rezerwacje_reject_booking',
                        nonce: rezerwacjeAdmin.nonce,
                        id: id
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Błąd: ' + response.data);
                        }
                    });
                });
            });
        </script>
    <?php
    }

    private static function render_calendar()
    {
    ?>
        <div class="wrap rezerwacje-calendar-admin-wrap">
            <h1>
                Kalendarz Rezerwacji
                <a href="<?php echo admin_url('admin.php?page=rezerwacje-bookings'); ?>" class="page-title-action">Widok listy</a>
            </h1>
            <div id="rezerwacje-admin-calendar"></div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var calendarEl = document.getElementById('rezerwacje-admin-calendar');
                if (calendarEl && typeof FullCalendar !== 'undefined') {
                    var calendar = new FullCalendar.Calendar(calendarEl, {
                        initialView: 'timeGridWeek',
                        locale: 'pl',
                        headerToolbar: {
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                        },
                        // POPRAWKA: Użyj funkcji do ładowania zdarzeń, aby mieć pewność, że nonce jest wysyłany w ciele POST
                        events: function(fetchInfo, successCallback, failureCallback) {
                            // Użyj jQuery.ajax, aby ręcznie wysłać dane
                            jQuery.ajax({
                                url: rezerwacjeAdmin.ajax_url,
                                type: 'POST',
                                dataType: 'json',
                                data: {
                                    action: 'rezerwacje_get_calendar_bookings',
                                    nonce: rezerwacjeAdmin.nonce,
                                    start: fetchInfo.startStr,
                                    end: fetchInfo.endStr
                                },
                                success: function(events) {
                                    successCallback(events);
                                },
                                error: function(jqXHR, textStatus, errorThrown) {
                                    console.error("Błąd AJAX:", textStatus, errorThrown, jqXHR.responseText);
                                    failureCallback();
                                }
                            });
                        },
                        failure: function() {
                            alert('Wystąpił błąd podczas ładowania rezerwacji!');
                        },
                        eventTimeFormat: {
                            hour: '2-digit',
                            minute: '2-digit',
                            meridiem: false,
                            hour12: false
                        },
                        slotLabelFormat: {
                            hour: '2-digit',
                            minute: '2-digit',
                            meridiem: false,
                            hour12: false
                        },
                        allDaySlot: false,
                        slotMinTime: '06:00:00',
                        slotMaxTime: '22:00:00',
                        height: 'auto',
                        contentHeight: 'auto',
                        stickyHeaderDates: true,
                        expandRows: true
                    });
                    calendar.render();
                } else {
                    console.error("FullCalendar nie jest załadowany lub element #rezerwacje-admin-calendar nie istnieje.");
                }
            });
        </script>
    <?php
    }

    private static function render_block_form()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        $therapists = Rezerwacje_Therapist::get_all(array('active' => 1));

        $days = array(
            1 => 'Poniedziałek',
            2 => 'Wtorek',
            3 => 'Środa',
            4 => 'Czwartek',
            5 => 'Piątek',
            6 => 'Sobota',
            7 => 'Niedziela'
        );

    ?>
        <div class="wrap">
            <h1>Zablokuj termin</h1>

            <form method="post" id="block-form">
                <table class="form-table">
                    <tr>
                        <th><label for="therapist_id">Terapeuta *</label></th>
                        <td>
                            <select name="therapist_id" id="therapist_id" required>
                                <option value="">Wybierz terapeutę</option>
                                <?php foreach ($therapists as $therapist): ?>
                                    <option value="<?php echo $therapist->id; ?>"><?php echo esc_html($therapist->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="patient_name">Nazwa spotkania / Pacjent *</label></th>
                        <td>
                            <input type="text" name="patient_name" id="patient_name" class="regular-text" required
                                placeholder="np. Michał Nowak lub Spotkanie wewnętrzne">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="is_recurring">Typ blokady</label></th>
                        <td>
                            <label>
                                <input type="radio" name="is_recurring" value="0" checked> Pojedyncza
                            </label>
                            <label>
                                <input type="radio" name="is_recurring" value="1"> Powtarzająca się
                            </label>
                        </td>
                    </tr>
                    <tr class="single-date-row">
                        <th><label for="start_date">Data *</label></th>
                        <td>
                            <input type="date" name="start_date" id="start_date" required>
                        </td>
                    </tr>
                    <tr class="recurring-row" style="display: none;">
                        <th><label for="day_of_week">Dzień tygodnia *</label></th>
                        <td>
                            <select name="day_of_week" id="day_of_week">
                                <option value="">Wybierz dzień</option>
                                <?php foreach ($days as $num => $name): ?>
                                    <option value="<?php echo $num; ?>"><?php echo $name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr class="recurring-row" style="display: none;">
                        <th><label for="recurrence_start_date">Data rozpoczęcia *</label></th>
                        <td>
                            <input type="date" name="recurrence_start_date" id="recurrence_start_date">
                        </td>
                    </tr>
                    <tr class="recurring-row" style="display: none;">
                        <th><label for="recurrence_end_date">Data zakończenia *</label></th>
                        <td>
                            <input type="date" name="recurrence_end_date" id="recurrence_end_date">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="start_time">Godzina od *</label></th>
                        <td>
                            <input type="time" name="start_time" id="start_time" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="end_time">Godzina do *</label></th>
                        <td>
                            <input type="time" name="end_time" id="end_time" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="notes">Notatki</label></th>
                        <td>
                            <textarea name="notes" id="notes" rows="3" class="large-text"></textarea>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">Zablokuj termin</button>
                    <a href="<?php echo admin_url('admin.php?page=rezerwacje-bookings'); ?>" class="button">Anuluj</a>
                </p>
            </form>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $('[name="is_recurring"]').on('change', function() {
                    var isRecurring = $(this).val() === '1';

                    if (isRecurring) {
                        $('.single-date-row').hide();
                        $('.recurring-row').show();
                        $('#start_date').prop('required', false);
                        $('#day_of_week').prop('required', true);
                        $('#recurrence_start_date').prop('required', true);
                        $('#recurrence_end_date').prop('required', true);
                    } else {
                        $('.single-date-row').show();
                        $('.recurring-row').hide();
                        $('#start_date').prop('required', true);
                        $('#day_of_week').prop('required', false);
                        $('#recurrence_start_date').prop('required', false);
                        $('#recurrence_end_date').prop('required', false);
                    }
                });

                $('#block-form').on('submit', function(e) {
                    e.preventDefault();

                    var isRecurring = $('[name="is_recurring"]:checked').val() === '1';

                    var formData = {
                        action: 'rezerwacje_add_blocked_slot',
                        nonce: rezerwacjeAdmin.nonce,
                        therapist_id: $('[name="therapist_id"]').val(),
                        patient_name: $('[name="patient_name"]').val(),
                        is_recurring: isRecurring ? 1 : 0,
                        start_time: $('[name="start_time"]').val(),
                        end_time: $('[name="end_time"]').val(),
                        notes: $('[name="notes"]').val()
                    };

                    if (isRecurring) {
                        formData.day_of_week = $('[name="day_of_week"]').val();
                        formData.start_date = $('[name="recurrence_start_date"]').val();
                        formData.recurrence_end_date = $('[name="recurrence_end_date"]').val();
                    } else {
                        formData.start_date = $('[name="start_date"]').val();
                    }

                    $.post(rezerwacjeAdmin.ajax_url, formData, function(response) {
                        if (response.success) {
                            alert('Termin zablokowany pomyślnie');
                            window.location.href = '<?php echo admin_url('admin.php?page=rezerwacje-bookings'); ?>';
                        } else {
                            alert('Błąd: ' + response.data);
                        }
                    });
                });
            });
        </script>
    <?php
    }

    private static function render_blocked_slots()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        $blocked_slots = Rezerwacje_Booking::get_blocked_slots();

        $days = array(
            1 => 'Poniedziałek',
            2 => 'Wtorek',
            3 => 'Środa',
            4 => 'Czwartek',
            5 => 'Piątek',
            6 => 'Sobota',
            7 => 'Niedziela'
        );

    ?>
        <div class="wrap">
            <h1>
                Zablokowane terminy
                <a href="<?php echo admin_url('admin.php?page=rezerwacje-bookings&action=block'); ?>" class="page-title-action">Zablokuj termin</a>
            </h1>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Terapeuta</th>
                        <th>Nazwa</th>
                        <th>Typ</th>
                        <th>Data / Dzień</th>
                        <th>Godzina</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($blocked_slots)): ?>
                        <tr>
                            <td colspan="7">Brak zablokowanych terminów</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($blocked_slots as $slot): ?>
                            <?php
                            $therapist = Rezerwacje_Therapist::get($slot->therapist_id);
                            ?>
                            <tr>
                                <td><?php echo $slot->id; ?></td>
                                <td><?php echo $therapist ? esc_html($therapist->name) : '-'; ?></td>
                                <td><?php echo esc_html($slot->patient_name); ?></td>
                                <td><?php echo $slot->is_recurring ? 'Powtarzająca się' : 'Pojedyncza'; ?></td>
                                <td>
                                    <?php if ($slot->is_recurring): ?>
                                        <?php echo $days[$slot->day_of_week]; ?><br>
                                        <small><?php echo date_i18n('d.m.Y', strtotime($slot->start_date)); ?> - <?php echo $slot->recurrence_end_date ? date_i18n('d.m.Y', strtotime($slot->recurrence_end_date)) : 'Bezterminowo'; ?></small>
                                    <?php else: ?>
                                        <?php echo date_i18n('d.m.Y', strtotime($slot->start_date)); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('H:i', strtotime($slot->start_time)) . ' - ' . date('H:i', strtotime($slot->end_time)); ?></td>
                                <td>
                                    <a href="#" class="remove-blocked-slot" data-id="<?php echo $slot->id; ?>">Usuń</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <p><a href="<?php echo admin_url('admin.php?page=rezerwacje-bookings'); ?>" class="button">Powrót do rezerwacji</a></p>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $('.remove-blocked-slot').on('click', function(e) {
                    e.preventDefault();

                    if (!confirm('Czy na pewno usunąć tę blokadę?')) {
                        return;
                    }

                    var id = $(this).data('id');

                    $.post(rezerwacjeAdmin.ajax_url, {
                        action: 'rezerwacje_remove_blocked_slot',
                        nonce: rezerwacjeAdmin.nonce,
                        id: id
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Błąd: ' + response.data);
                        }
                    });
                });
            });
        </script>
<?php
    }

    public function ajax_get_calendar_bookings()
    {
        check_ajax_referer('rezerwacje_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Brak uprawnień');
        }

        $current_user = wp_get_current_user();
        $is_admin = current_user_can('manage_options');

        $args = array();
        if (!$is_admin) {
            $therapist = Rezerwacje_Therapist::get_by_user_id($current_user->ID);
            if ($therapist) {
                $args['therapist_id'] = $therapist->id;
            } else {
                wp_send_json_success(array()); // Zwróć puste dane, jeśli terapeuta nie jest znaleziony
            }
        }

        // Pobieranie dat z FullCalendar (teraz wysyłane w $_POST)
        $start_date = isset($_POST['start']) ? sanitize_text_field($_POST['start']) : date('Y-m-d', strtotime('-1 month'));
        $end_date = isset($_POST['end']) ? sanitize_text_field($_POST['end']) : date('Y-m-d', strtotime('+1 month'));

        $args['date_from'] = date('Y-m-d', strtotime($start_date));
        $args['date_to'] = date('Y-m-d', strtotime($end_date));

        $bookings = Rezerwacje_Booking::get_all($args);
        $blocked_slots = Rezerwacje_Booking::get_blocked_slots(
            isset($args['therapist_id']) ? $args['therapist_id'] : null,
            $args['date_from'],
            $args['date_to']
        );

        $events = array();

        // Przetwarzanie rezerwacji
        foreach ($bookings as $booking) {
            if ($booking->status === 'rejected' || $booking->status === 'cancelled') {
                continue;
            }

            $color = '#2196F3'; // Domyślny (Zatwierdzona)
            if ($booking->status === 'pending') {
                $color = '#FF9800'; // Oczekująca
            }

            $events[] = array(
                'title' => $booking->patient_name . ' (' . $booking->service_name . ') - ' . $booking->therapist_name,
                'start' => $booking->booking_date . 'T' . $booking->start_time,
                'end' => $booking->booking_date . 'T' . $booking->end_time,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'extendedProps' => array(
                    'type' => 'booking',
                    'status' => $booking->status,
                    'patient' => $booking->patient_name,
                    'service' => $booking->service_name,
                    'therapist' => $booking->therapist_name
                )
            );
        }

        // Przetwarzanie zablokowanych terminów
        foreach ($blocked_slots as $slot) {
            $therapist = Rezerwacje_Therapist::get($slot->therapist_id);
            $therapist_name = $therapist ? $therapist->name : 'Brak';
            $title = 'Zablokowane: ' . $slot->patient_name . ' (' . $therapist_name . ')';
            $color = '#E91E63'; // Zablokowane

            if ($slot->is_recurring) {
                // Generowanie powtarzających się zdarzeń
                $start = new DateTime($slot->start_date);

                // POPRAWKA: Upewnij się, że pusta data lub '0000-00-00' jest obsługiwana poprawnie
                $endDateStr = (!empty($slot->recurrence_end_date) && $slot->recurrence_end_date !== '0000-00-00') ? $slot->recurrence_end_date : $args['date_to'];
                $end = new DateTime($endDateStr);

                $interval = new DateInterval('P1W'); // Co tydzień
                $period = new DatePeriod($start, $interval, $end->modify('+1 day')); // Modyfikator +1 dzień, aby uwzględnić ostatni dzień

                foreach ($period as $date) {
                    if ($date->format('N') == $slot->day_of_week && $date->format('Y-m-d') <= $args['date_to']) {
                        if ($date->format('Y-m-d') >= $args['date_from']) {
                            $events[] = array(
                                'title' => $title,
                                'start' => $date->format('Y-m-d') . 'T' . $slot->start_time,
                                'end' => $date->format('Y-m-d') . 'T' . $slot->end_time,
                                'backgroundColor' => $color,
                                'borderColor' => $color,
                                'extendedProps' => array(
                                    'type' => 'blocked',
                                    'notes' => $slot->notes
                                )
                            );
                        }
                    }
                }
            } else {
                // Pojedyncze zdarzenie
                $events[] = array(
                    'title' => $title,
                    'start' => $slot->start_date . 'T' . $slot->start_time,
                    'end' => $slot->start_date . 'T' . $slot->end_time,
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'extendedProps' => array(
                        'type' => 'blocked',
                        'notes' => $slot->notes
                    )
                );
            }
        }

        wp_send_json($events); // FullCalendar oczekuje tablicy JSON, nie obiektu {success: true, data: ...}
    }


    public function ajax_approve_booking()
    {
        check_ajax_referer('rezerwacje_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Brak uprawnień');
        }

        $booking_id = intval($_POST['id']);
        $result = Rezerwacje_Booking::approve($booking_id);

        if ($result) {
            Rezerwacje_Email::send_booking_approved($booking_id);
            wp_send_json_success();
        } else {
            wp_send_json_error('Nie udało się zatwierdzić');
        }
    }

    public function ajax_reject_booking()
    {
        check_ajax_referer('rezerwacje_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Brak uprawnień');
        }

        $booking_id = intval($_POST['id']);
        $result = Rezerwacje_Booking::reject($booking_id);

        if ($result) {
            Rezerwacje_Email::send_booking_rejected($booking_id);
            wp_send_json_success();
        } else {
            wp_send_json_error('Nie udało się odrzucić');
        }
    }

    public function ajax_add_blocked_slot()
    {
        check_ajax_referer('rezerwacje_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }

        $data = array(
            'therapist_id' => intval($_POST['therapist_id']),
            'patient_name' => sanitize_text_field($_POST['patient_name']),
            'start_date' => sanitize_text_field($_POST['start_date']),
            'start_time' => sanitize_text_field($_POST['start_time']),
            'end_time' => sanitize_text_field($_POST['end_time']),
            'is_recurring' => intval($_POST['is_recurring']),
            'notes' => sanitize_textarea_field($_POST['notes'])
        );

        if ($data['is_recurring']) {
            $data['day_of_week'] = intval($_POST['day_of_week']);
            // POPRAWKA: Zapisz NULL zamiast pustego stringa, jeśli data nie jest ustawiona
            $data['recurrence_end_date'] = !empty($_POST['recurrence_end_date']) ? sanitize_text_field($_POST['recurrence_end_date']) : null;
        }

        $result = Rezerwacje_Booking::add_blocked_slot($data);

        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Nie udało się zablokować terminu');
        }
    }

    public function ajax_remove_blocked_slot()
    {
        check_ajax_referer('rezerwacje_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }

        $id = intval($_POST['id']);
        $result = Rezerwacje_Booking::remove_blocked_slot($id);

        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Nie udało się usunąć');
        }
    }
}
