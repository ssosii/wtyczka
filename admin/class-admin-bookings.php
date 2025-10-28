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
        add_action('wp_ajax_rezerwacje_cancel_booking', array($this, 'ajax_cancel_booking'));
        add_action('wp_ajax_rezerwacje_update_booking_time', array($this, 'ajax_update_booking_time'));
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
            } else {
                $bookings = array(); // Jeśli nie jest adminem i nie jest terapeutą, pokaż pustą listę
            }
        }

        if (!isset($bookings)) {
            $bookings = Rezerwacje_Booking::get_all($args);
        }

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
                <li><a href="<?php echo admin_url('admin.php?page=rezerwacje-bookings&status=cancelled'); ?>" <?php echo $status_filter === 'cancelled' ? 'class="current"' : ''; ?>>Anulowane</a></li>
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
                            <td colspan="9">Brak rezerwacji spełniających kryteria.</td>
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
                                    <?php elseif ($booking->status === 'approved'): ?>
                                        <a href="#" class="cancel-booking" data-id="<?php echo $booking->id; ?>">Anuluj</a>
                                    <?php else: ?> - <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $('.approve-booking, .reject-booking, .cancel-booking').on('click', function(e) {
                    e.preventDefault();
                    var $link = $(this);
                    var id = $link.data('id');
                    var action = '';
                    var confirmMsg = '';

                    if ($link.hasClass('approve-booking')) {
                        action = 'rezerwacje_approve_booking';
                        confirmMsg = 'Czy na pewno zatwierdzić tę rezerwację?';
                    } else if ($link.hasClass('reject-booking')) {
                        action = 'rezerwacje_reject_booking';
                        confirmMsg = 'Czy na pewno odrzucić tę rezerwację?';
                    } else if ($link.hasClass('cancel-booking')) {
                        action = 'rezerwacje_cancel_booking';
                        confirmMsg = 'Czy na pewno ANULOWAĆ tę rezerwację? Pacjent zostanie powiadomiony.';
                    }

                    if (!action || !confirm(confirmMsg)) {
                        return;
                    }

                    $.post(rezerwacjeAdmin.ajax_url, {
                        action: action,
                        nonce: rezerwacjeAdmin.nonce,
                        id: id
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Błąd: ' + response.data);
                        }
                    }).fail(function() {
                        alert('Błąd serwera podczas przetwarzania żądania.');
                    });
                });
            });
        </script>
    <?php
    }

    private static function render_calendar()
    {
        $is_admin = current_user_can('manage_options');
        $therapists = Rezerwacje_Therapist::get_all(array('active' => 1)); // Pobierz wszystkich dla admina

        $current_therapist_id_for_view = null;
        if (!$is_admin) {
            $therapist_obj = Rezerwacje_Therapist::get_by_user_id(get_current_user_id());
            if ($therapist_obj) {
                $current_therapist_id_for_view = $therapist_obj->id;
            }
        }
    ?>
        <div class="wrap rezerwacje-calendar-admin-wrap">
            <h1>
                Kalendarz Rezerwacji
                <a href="<?php echo admin_url('admin.php?page=rezerwacje-bookings'); ?>" class="page-title-action">Widok listy</a>
            </h1>

            <?php if ($is_admin): ?>
                <div class="rezerwacje-calendar-filter" style="margin-bottom: 20px;">
                    <label for="therapist-filter" style="margin-right: 10px;">Pokaż kalendarz dla:</label>
                    <select id="therapist-filter" style="min-width: 200px;">
                        <option value="">Wszyscy terapeuci</option>
                        <?php foreach ($therapists as $therapist): ?>
                            <option value="<?php echo esc_attr($therapist->id); ?>">
                                <?php echo esc_html($therapist->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div id="rezerwacje-admin-calendar"></div>
        </div>

        <div id="rezerwacje-event-modal" class="rezerwacje-modal-overlay" style="display: none;">
            <div class="rezerwacje-modal-content">
                <div class="rezerwacje-modal-header">
                    <h3 id="modal-title">Szczegóły</h3> <button id="modal-btn-close" class="rezerwacje-modal-close">&times;</button>
                </div>
                <div class="rezerwacje-modal-body">
                    <div id="modal-details-view">
                        <p><strong>Pacjent:</strong> <span id="modal-patient-name"></span></p>
                        <p><strong>Terapeuta:</strong> <span id="modal-therapist-name"></span></p>
                        <p><strong>Usługa:</strong> <span id="modal-service-name"></span></p>
                        <p><strong>Status:</strong> <span id="modal-status"></span></p>
                        <p><strong>Notatki:</strong> <span id="modal-notes"></span></p>
                    </div>
                    <div id="modal-edit-view" style="display: none;">
                        <p>Zmieniasz dla: <strong id="modal-edit-patient-name"></strong></p>
                        <p class="form-row"><label for="modal-edit-date">Data:</label><input type="date" id="modal-edit-date" class="modal-input"></p>
                        <p class="form-row"><label for="modal-edit-start-time">Od:</label><input type="time" id="modal-edit-start-time" class="modal-input" step="600"></p>
                        <p class="form-row"><label for="modal-edit-end-time">Do:</label><input type="time" id="modal-edit-end-time" class="modal-input" step="600"></p>
                        <p id="modal-edit-error" class="rezerwacje-error" style="display: none;"></p>
                    </div>
                </div>
                <div class="rezerwacje-modal-footer"> <button id="modal-btn-approve" class="button button-primary" style="display: none;">Zatwierdź</button> <button id="modal-btn-reject" class="button button-secondary" style="display: none;">Odrzuć</button> <button id="modal-btn-cancel" class="button button-secondary" style="display: none;">Anuluj rez.</button> <button id="modal-btn-delete-blocked" class="button button-danger" style="display: none;">Usuń blokadę</button> <button id="modal-btn-edit" class="button button-secondary" style="display: none;">Edytuj termin</button> <button id="modal-btn-save" class="button button-primary" style="display: none;">Zapisz</button> <button id="modal-btn-back-to-details" class="button button-secondary" style="display: none;">Anuluj</button> </div>
            </div>
        </div>

        <div id="rezerwacje-block-modal" class="rezerwacje-modal-overlay" style="display: none;">
            <div class="rezerwacje-modal-content">
                <form id="rezerwacje-block-modal-form">
                    <div class="rezerwacje-modal-header">
                        <h3 id="block-modal-title">Zablokuj termin</h3>
                        <button id="block-modal-btn-close" type="button" class="rezerwacje-modal-close">&times;</button>
                    </div>
                    <div class="rezerwacje-modal-body">
                        <?php if ($is_admin): ?>
                            <p class="form-row">
                                <label for="block-modal-therapist">Terapeuta *</label>
                                <select id="block-modal-therapist" class="modal-input" required>
                                    <option value="">Wybierz...</option>
                                    <?php foreach ($therapists as $therapist): ?>
                                        <option value="<?php echo esc_attr($therapist->id); ?>">
                                            <?php echo esc_html($therapist->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </p>
                        <?php endif; ?>
                        <p class="form-row">
                            <label for="block-modal-name">Nazwa spotkania / Pacjent *</label>
                            <input type="text" id="block-modal-name" class="modal-input" required placeholder="np. Przerwa, Spotkanie, Jan Kowalski">
                        </p>
                        <p class="form-row">
                            <label for="block-modal-date">Data</label>
                            <input type="date" id="block-modal-date" class="modal-input" required readonly style="background-color: #f0f0f0;"> <?php // Readonly field 
                                                                                                                                                ?>
                        </p>
                        <p class="form-row">
                            <label for="block-modal-start-time">Godzina od</label>
                            <input type="time" id="block-modal-start-time" class="modal-input" required step="600"> <?php // step 600 = 10 minut 
                                                                                                                    ?>
                        </p>
                        <p class="form-row">
                            <label for="block-modal-end-time">Godzina do *</label>
                            <input type="time" id="block-modal-end-time" class="modal-input" required step="600">
                        </p>
                        <p class="form-row">
                            <label for="block-modal-notes">Notatki</label>
                            <textarea id="block-modal-notes" rows="2" class="modal-input"></textarea>
                        </p>
                        <p id="block-modal-error" class="rezerwacje-error" style="display: none;"></p>
                    </div>
                    <div class="rezerwacje-modal-footer">
                        <button type="submit" id="block-modal-btn-save" class="button button-primary">Zablokuj</button>
                        <button type="button" id="block-modal-btn-cancel" class="button button-secondary">Anuluj</button>
                    </div>
                </form>
            </div>
        </div>


        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var calendarEl = document.getElementById('rezerwacje-admin-calendar');
                var calendar;
                var selectedTherapistFilterId = '';

                // --- Referencje do modala SZCZEGÓŁÓW/EDYCJI ---
                const eventModal = document.getElementById('rezerwacje-event-modal');
                const eventModalTitle = document.getElementById('modal-title');
                const modalDetailsView = document.getElementById('modal-details-view');
                const modalPatientName = document.getElementById('modal-patient-name');
                const modalTherapistName = document.getElementById('modal-therapist-name');
                const modalServiceName = document.getElementById('modal-service-name');
                const modalStatus = document.getElementById('modal-status');
                const modalNotes = document.getElementById('modal-notes');
                const modalEditView = document.getElementById('modal-edit-view');
                const modalEditPatientName = document.getElementById('modal-edit-patient-name');
                const modalEditDate = document.getElementById('modal-edit-date');
                const modalEditStartTime = document.getElementById('modal-edit-start-time');
                const modalEditEndTime = document.getElementById('modal-edit-end-time');
                const modalEditError = document.getElementById('modal-edit-error');
                const btnEventClose = document.getElementById('modal-btn-close');
                const btnApprove = document.getElementById('modal-btn-approve');
                const btnReject = document.getElementById('modal-btn-reject');
                const btnCancel = document.getElementById('modal-btn-cancel');
                const btnDeleteBlocked = document.getElementById('modal-btn-delete-blocked');
                const btnEdit = document.getElementById('modal-btn-edit');
                const btnSave = document.getElementById('modal-btn-save');
                const btnBackToDetails = document.getElementById('modal-btn-back-to-details');

                // --- Referencje do modala BLOKOWANIA ---
                const blockModal = document.getElementById('rezerwacje-block-modal');
                const blockModalForm = document.getElementById('rezerwacje-block-modal-form');
                const blockModalTitle = document.getElementById('block-modal-title');
                const blockModalTherapist = document.getElementById('block-modal-therapist'); // Może nie istnieć dla nie-admina
                const blockModalName = document.getElementById('block-modal-name');
                const blockModalDate = document.getElementById('block-modal-date');
                const blockModalStartTime = document.getElementById('block-modal-start-time');
                const blockModalEndTime = document.getElementById('block-modal-end-time');
                const blockModalNotes = document.getElementById('block-modal-notes');
                const blockModalError = document.getElementById('block-modal-error');
                const btnBlockClose = document.getElementById('block-modal-btn-close');
                const btnBlockSave = document.getElementById('block-modal-btn-save');
                const btnBlockCancel = document.getElementById('block-modal-btn-cancel');


                let currentBookingId = null;
                let currentBlockedSlotId = null;
                let currentBookingDetails = {};

                // --- Funkcje dla modala SZCZEGÓŁÓW/EDYCJI ---
                function showEventModal() {
                    eventModal.style.display = 'flex';
                }

                function hideEventModal() {
                    eventModal.style.display = 'none';
                    currentBookingId = null;
                    currentBlockedSlotId = null;
                    currentBookingDetails = {};
                    btnApprove.style.display = 'none';
                    btnReject.style.display = 'none';
                    btnCancel.style.display = 'none';
                    btnDeleteBlocked.style.display = 'none';
                    btnEdit.style.display = 'none';
                    btnSave.style.display = 'none';
                    btnBackToDetails.style.display = 'none';
                    modalDetailsView.style.display = 'block';
                    modalEditView.style.display = 'none';
                    modalEditError.style.display = 'none';
                    modalPatientName.parentElement.style.display = 'block';
                    modalTherapistName.parentElement.style.display = 'block';
                    modalServiceName.parentElement.style.display = 'block';
                    modalStatus.parentElement.style.display = 'block';
                    modalNotes.parentElement.style.display = 'block';
                }
                btnEventClose.addEventListener('click', hideEventModal);
                eventModal.addEventListener('click', function(e) {
                    if (e.target === eventModal) {
                        hideEventModal();
                    }
                });
                // Akcje dla przycisków eventModal (approve, reject etc.)
                btnApprove.addEventListener('click', function() {
                    if (!currentBookingId || !confirm('Zatwierdzić?')) return;
                    jQuery.post(rezerwacjeAdmin.ajax_url, {
                        action: 'rezerwacje_approve_booking',
                        nonce: rezerwacjeAdmin.nonce,
                        id: currentBookingId
                    }).always(function(response) {
                        if (response.success) {
                            calendar.refetchEvents();
                            hideEventModal();
                        } else {
                            alert('Błąd: ' + (response.data || 'Nieznany'));
                        }
                    });
                });
                btnReject.addEventListener('click', function() {
                    if (!currentBookingId || !confirm('Odrzucić?')) return;
                    jQuery.post(rezerwacjeAdmin.ajax_url, {
                        action: 'rezerwacje_reject_booking',
                        nonce: rezerwacjeAdmin.nonce,
                        id: currentBookingId
                    }).always(function(response) {
                        if (response.success) {
                            calendar.refetchEvents();
                            hideEventModal();
                        } else {
                            alert('Błąd: ' + (response.data || 'Nieznany'));
                        }
                    });
                });
                btnDeleteBlocked.addEventListener('click', function() {
                    if (!currentBlockedSlotId || !confirm('Usunąć blokadę?')) return;
                    jQuery.post(rezerwacjeAdmin.ajax_url, {
                        action: 'rezerwacje_remove_blocked_slot',
                        nonce: rezerwacjeAdmin.nonce,
                        id: currentBlockedSlotId
                    }).always(function(response) {
                        if (response.success) {
                            calendar.refetchEvents();
                            hideEventModal();
                        } else {
                            alert('Błąd: ' + (response.data || 'Nieznany'));
                        }
                    });
                });
                btnCancel.addEventListener('click', function() {
                    if (!currentBookingId || !confirm('Anulować rezerwację? Pacjent zostanie powiadomiony.')) return;
                    jQuery.post(rezerwacjeAdmin.ajax_url, {
                        action: 'rezerwacje_cancel_booking',
                        nonce: rezerwacjeAdmin.nonce,
                        id: currentBookingId
                    }).always(function(response) {
                        if (response.success) {
                            calendar.refetchEvents();
                            hideEventModal();
                        } else {
                            alert('Błąd: ' + (response.data || 'Nieznany'));
                        }
                    });
                });
                btnEdit.addEventListener('click', function() {
                    modalDetailsView.style.display = 'none';
                    modalEditView.style.display = 'block';
                    modalTitle.innerText = 'Edytuj termin';
                    modalEditPatientName.innerText = modalPatientName.innerText;
                    modalEditDate.value = currentBookingDetails.date;
                    modalEditStartTime.value = currentBookingDetails.start;
                    modalEditEndTime.value = currentBookingDetails.end;
                    btnApprove.style.display = 'none';
                    btnReject.style.display = 'none';
                    btnCancel.style.display = 'none';
                    btnEdit.style.display = 'none';
                    btnSave.style.display = 'inline-block';
                    btnBackToDetails.style.display = 'inline-block';
                });
                btnBackToDetails.addEventListener('click', function() {
                    modalDetailsView.style.display = 'block';
                    modalEditView.style.display = 'none';
                    modalTitle.innerText = 'Szczegóły';
                    modalEditError.style.display = 'none';
                    if (currentBookingDetails.status === 'pending') {
                        btnApprove.style.display = 'inline-block';
                        btnReject.style.display = 'inline-block';
                    } else if (currentBookingDetails.status === 'approved') {
                        btnCancel.style.display = 'inline-block';
                    }
                    btnEdit.style.display = 'inline-block';
                    btnSave.style.display = 'none';
                    btnBackToDetails.style.display = 'none';
                });
                btnSave.addEventListener('click', function() {
                    const newDate = modalEditDate.value,
                        newStart = modalEditStartTime.value,
                        newEnd = modalEditEndTime.value;
                    if (!newDate || !newStart || !newEnd) {
                        modalEditError.innerText = 'Wszystkie pola są wymagane.';
                        modalEditError.style.display = 'block';
                        return;
                    }
                    btnSave.innerText = 'Zapisywanie...';
                    btnSave.disabled = true;
                    modalEditError.style.display = 'none';
                    jQuery.post(rezerwacjeAdmin.ajax_url, {
                        action: 'rezerwacje_update_booking_time',
                        nonce: rezerwacjeAdmin.nonce,
                        id: currentBookingId,
                        therapist_id: currentBookingDetails.therapist_id,
                        booking_date: newDate,
                        start_time: newStart,
                        end_time: newEnd
                    }).always(function(response) {
                        btnSave.innerText = 'Zapisz zmiany';
                        btnSave.disabled = false;
                        if (response.success) {
                            calendar.refetchEvents();
                            hideEventModal();
                        } else {
                            modalEditError.innerText = 'Błąd: ' + (response.data || 'Błąd serwera.');
                            modalEditError.style.display = 'block';
                        }
                    });
                });

                // --- Funkcje dla modala BLOKOWANIA ---
                function showBlockModal() {
                    blockModal.style.display = 'flex';
                }

                function hideBlockModal() {
                    blockModal.style.display = 'none';
                    blockModalForm.reset();
                    blockModalError.style.display = 'none';
                    btnBlockSave.disabled = false;
                    btnBlockSave.innerText = 'Zablokuj';
                }
                btnBlockClose.addEventListener('click', hideBlockModal);
                btnBlockCancel.addEventListener('click', hideBlockModal);
                blockModal.addEventListener('click', function(e) {
                    if (e.target === blockModal) {
                        hideBlockModal();
                    }
                });


                if (calendarEl && typeof FullCalendar !== 'undefined') {
                    calendar = new FullCalendar.Calendar(calendarEl, {
                        initialView: 'timeGridWeek',
                        locale: 'pl',
                        buttonText: {
                            today: 'Dzisiaj',
                            month: 'Miesiąc',
                            week: 'Tydzień',
                            day: 'Dzień',
                            list: 'Lista'
                        },
                        headerToolbar: {
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                        },
                        events: function(fetchInfo, successCallback, failureCallback) {
                            jQuery.ajax({
                                url: rezerwacjeAdmin.ajax_url,
                                type: 'POST',
                                dataType: 'json',
                                data: {
                                    action: 'rezerwacje_get_calendar_bookings',
                                    nonce: rezerwacjeAdmin.nonce,
                                    start: fetchInfo.startStr,
                                    end: fetchInfo.endStr,
                                    therapist_filter_id: selectedTherapistFilterId
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
                        expandRows: true,
                        selectable: true, // Włączenie zaznaczania

                        dateClick: function(info) {
                            hideEventModal();
                            blockModalForm.reset();
                            const clickedDate = info.date;
                            blockModalDate.value = clickedDate.toISOString().split('T')[0];
                            blockModalStartTime.value = clickedDate.toTimeString().substring(0, 5);
                            const defaultEndTime = new Date(clickedDate.getTime() + 60 * 60 * 1000);
                            blockModalEndTime.value = defaultEndTime.toTimeString().substring(0, 5);
                            const currentTherapistId = <?php echo $is_admin ? 'selectedTherapistFilterId || "null"' : json_encode($current_therapist_id_for_view); ?>;
                            if (blockModalTherapist && currentTherapistId) {
                                blockModalTherapist.value = currentTherapistId;
                            }
                            showBlockModal();
                        },

                        eventClick: function(info) {
                            info.jsEvent.preventDefault();
                            const props = info.event.extendedProps;
                            hideBlockModal(); // Zamknij modal blokowania
                            hideEventModal(); // Resetuj modal szczegółów

                            if (props.type === 'booking') {
                                eventModalTitle.innerText = 'Szczegóły rezerwacji';
                                modalPatientName.innerText = props.patient;
                                modalTherapistName.innerText = props.therapist;
                                modalServiceName.innerText = props.service;
                                modalStatus.innerText = props.status_display || props.status;
                                modalNotes.innerText = props.notes || '-';
                                currentBookingId = props.booking_id;
                                currentBookingDetails = {
                                    date: info.event.start.toISOString().split('T')[0],
                                    start: info.event.startStr.split('T')[1]?.substring(0, 5) || '00:00',
                                    end: info.event.endStr.split('T')[1]?.substring(0, 5) || '00:00',
                                    status: props.status,
                                    therapist_id: props.therapist_id
                                };
                                btnEdit.style.display = 'inline-block';
                                if (props.status === 'pending') {
                                    btnApprove.style.display = 'inline-block';
                                    btnReject.style.display = 'inline-block';
                                } else if (props.status === 'approved') {
                                    btnCancel.style.display = 'inline-block';
                                }
                            } else if (props.type === 'blocked') {
                                eventModalTitle.innerText = 'Zablokowany termin';
                                modalPatientName.innerText = props.patient_name;
                                modalNotes.innerText = props.notes || '-';
                                modalTherapistName.parentElement.style.display = 'none';
                                modalServiceName.parentElement.style.display = 'none';
                                modalStatus.parentElement.style.display = 'none';
                                currentBlockedSlotId = props.blocked_slot_id;
                                btnDeleteBlocked.style.display = 'inline-block';
                            }
                            showEventModal(); // Pokaż modal szczegółów/edycji
                        }
                    });
                    calendar.render();

                    // Listener dla filtra terapeutów
                    const therapistFilter = document.getElementById('therapist-filter');
                    if (therapistFilter) {
                        therapistFilter.addEventListener('change', function() {
                            selectedTherapistFilterId = this.value;
                            calendar.refetchEvents();
                        });
                    }

                    // Obsługa formularza blokowania
                    blockModalForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        btnBlockSave.disabled = true;
                        btnBlockSave.innerText = 'Zapisywanie...';
                        blockModalError.style.display = 'none';
                        let therapistIdToBlock = null;
                        if (blockModalTherapist) {
                            therapistIdToBlock = blockModalTherapist.value;
                        } else {
                            therapistIdToBlock = <?php echo json_encode($current_therapist_id_for_view); ?>;
                        }
                        if (!therapistIdToBlock) {
                            blockModalError.innerText = 'Musisz wybrać terapeutę.';
                            blockModalError.style.display = 'block';
                            btnBlockSave.disabled = false;
                            btnBlockSave.innerText = 'Zablokuj';
                            return;
                        }
                        const formData = {
                            action: 'rezerwacje_add_blocked_slot',
                            nonce: rezerwacjeAdmin.nonce,
                            therapist_id: therapistIdToBlock,
                            patient_name: blockModalName.value,
                            start_date: blockModalDate.value,
                            start_time: blockModalStartTime.value,
                            end_time: blockModalEndTime.value,
                            notes: blockModalNotes.value,
                            is_recurring: 0
                        };
                        jQuery.post(rezerwacjeAdmin.ajax_url, formData)
                            .done(function(response) {
                                if (response.success) {
                                    calendar.refetchEvents();
                                    hideBlockModal();
                                } else {
                                    blockModalError.innerText = 'Błąd: ' + (response.data || 'Nie udało się zablokować terminu.');
                                    blockModalError.style.display = 'block';
                                }
                            })
                            .fail(function() {
                                blockModalError.innerText = 'Błąd: Wystąpił błąd serwera.';
                                blockModalError.style.display = 'block';
                            })
                            .always(function() {
                                btnBlockSave.disabled = false;
                                btnBlockSave.innerText = 'Zablokuj';
                            });
                    });

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
        $days = array(1 => 'Poniedziałek', 2 => 'Wtorek', 3 => 'Środa', 4 => 'Czwartek', 5 => 'Piątek', 6 => 'Sobota', 7 => 'Niedziela');
    ?>
        <div class="wrap">
            <h1>Zablokuj termin</h1>
            <form method="post" id="block-form">
                <table class="form-table">
                    <tr>
                        <th><label for="therapist_id">Terapeuta *</label></th>
                        <td><select name="therapist_id" id="therapist_id" required>
                                <option value="">Wybierz...</option><?php foreach ($therapists as $therapist): ?><option value="<?php echo $therapist->id; ?>"><?php echo esc_html($therapist->name); ?></option><?php endforeach; ?>
                            </select></td>
                    </tr>
                    <tr>
                        <th><label for="patient_name">Nazwa *</label></th>
                        <td><input type="text" name="patient_name" id="patient_name" class="regular-text" required placeholder="np. Spotkanie"></td>
                    </tr>
                    <tr>
                        <th><label>Typ</label></th>
                        <td><label><input type="radio" name="is_recurring" value="0" checked> Pojedyncza</label> <label><input type="radio" name="is_recurring" value="1"> Powtarzająca</label></td>
                    </tr>
                    <tr class="single-date-row">
                        <th><label for="start_date">Data *</label></th>
                        <td><input type="date" name="start_date" id="start_date" required></td>
                    </tr>
                    <tr class="recurring-row" style="display: none;">
                        <th><label for="day_of_week">Dzień tyg. *</label></th>
                        <td><select name="day_of_week" id="day_of_week">
                                <option value="">Wybierz...</option><?php foreach ($days as $num => $name): ?><option value="<?php echo $num; ?>"><?php echo $name; ?></option><?php endforeach; ?>
                            </select></td>
                    </tr>
                    <tr class="recurring-row" style="display: none;">
                        <th><label for="recurrence_start_date">Start od *</label></th>
                        <td><input type="date" name="recurrence_start_date" id="recurrence_start_date"></td>
                    </tr>
                    <tr class="recurring-row" style="display: none;">
                        <th><label for="recurrence_end_date">Koniec do</label></th>
                        <td><input type="date" name="recurrence_end_date" id="recurrence_end_date"> <small>Puste = bez końca.</small></td>
                    </tr>
                    <tr>
                        <th><label for="start_time">Od *</label></th>
                        <td><input type="time" name="start_time" id="start_time" required></td>
                    </tr>
                    <tr>
                        <th><label for="end_time">Do *</label></th>
                        <td><input type="time" name="end_time" id="end_time" required></td>
                    </tr>
                    <tr>
                        <th><label for="notes">Notatki</label></th>
                        <td><textarea name="notes" id="notes" rows="3" class="large-text"></textarea></td>
                    </tr>
                </table>
                <p class="submit"><button type="submit" class="button button-primary">Zablokuj</button> <a href="<?php echo admin_url('admin.php?page=rezerwacje-bookings&action=calendar'); ?>" class="button">Anuluj</a></p>
            </form>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $('[name="is_recurring"]').on('change', function() {
                    var i = $(this).val() === '1';
                    $('.single-date-row').toggle(!i);
                    $('.recurring-row').toggle(i);
                    $('#start_date').prop('required', !i);
                    $('#day_of_week, #recurrence_start_date').prop('required', i);
                });
                $('#block-form').on('submit', function(e) {
                    e.preventDefault();
                    var i = $('[name="is_recurring"]:checked').val() === '1';
                    var d = {
                        action: 'rezerwacje_add_blocked_slot',
                        nonce: rezerwacjeAdmin.nonce,
                        therapist_id: $('[name="therapist_id"]').val(),
                        patient_name: $('[name="patient_name"]').val(),
                        is_recurring: i ? 1 : 0,
                        start_time: $('[name="start_time"]').val(),
                        end_time: $('[name="end_time"]').val(),
                        notes: $('[name="notes"]').val()
                    };
                    if (i) {
                        d.day_of_week = $('[name="day_of_week"]').val();
                        d.start_date = $('[name="recurrence_start_date"]').val();
                        d.recurrence_end_date = $('[name="recurrence_end_date"]').val();
                    } else {
                        d.start_date = $('[name="start_date"]').val();
                    }
                    $.post(rezerwacjeAdmin.ajax_url, d, function(r) {
                        if (r.success) {
                            alert('Zablokowano.');
                            window.location.href = '<?php echo admin_url('admin.php?page=rezerwacje-bookings&action=calendar'); ?>';
                        } else {
                            alert('Błąd: ' + r.data);
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
        $days = array(1 => 'Pon', 2 => 'Wt', 3 => 'Śr', 4 => 'Czw', 5 => 'Pt', 6 => 'Sob', 7 => 'Niedz');
    ?>
        <div class="wrap">
            <h1>Zablokowane <a href="<?php echo admin_url('admin.php?page=rezerwacje-bookings&action=block'); ?>" class="page-title-action">Zablokuj nowy</a></h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Terapeuta</th>
                        <th>Nazwa</th>
                        <th>Typ</th>
                        <th>Data/Dzień</th>
                        <th>Godzina</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody> <?php if (empty($blocked_slots)): ?> <tr>
                            <td colspan="7">Brak</td>
                        </tr> <?php else: ?> <?php foreach ($blocked_slots as $slot): ?> <?php $therapist = Rezerwacje_Therapist::get($slot->therapist_id); ?> <tr>
                                <td><?php echo $slot->id; ?></td>
                                <td><?php echo $therapist ? esc_html($therapist->name) : '-'; ?></td>
                                <td><?php echo esc_html($slot->patient_name); ?></td>
                                <td><?php echo $slot->is_recurring ? 'Powtarzająca' : 'Pojedyncza'; ?></td>
                                <td><?php if ($slot->is_recurring): ?><?php echo $days[$slot->day_of_week]; ?><br><small><?php echo date_i18n('d.m.Y', strtotime($slot->start_date)); ?> - <?php echo $slot->recurrence_end_date ? date_i18n('d.m.Y', strtotime($slot->recurrence_end_date)) : '∞'; ?></small><?php else: ?><?php echo date_i18n('d.m.Y', strtotime($slot->start_date)); ?><?php endif; ?></td>
                                <td><?php echo date('H:i', strtotime($slot->start_time)) . '-' . date('H:i', strtotime($slot->end_time)); ?></td>
                                <td><a href="#" class="remove-blocked-slot" data-id="<?php echo $slot->id; ?>">Usuń</a></td>
                            </tr> <?php endforeach; ?> <?php endif; ?> </tbody>
            </table>
            <p><a href="<?php echo admin_url('admin.php?page=rezerwacje-bookings'); ?>" class="button">Wróć</a></p>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $('.remove-blocked-slot').on('click', function(e) {
                    e.preventDefault();
                    if (!confirm('Usunąć?')) return;
                    var id = $(this).data('id');
                    $.post(rezerwacjeAdmin.ajax_url, {
                        action: 'rezerwacje_remove_blocked_slot',
                        nonce: rezerwacjeAdmin.nonce,
                        id: id
                    }, function(r) {
                        if (r.success) location.reload();
                        else alert('Błąd: ' + r.data);
                    });
                });
            });
        </script>
<?php
    }

    public function ajax_get_calendar_bookings()
    {
        if (!check_ajax_referer('rezerwacje_admin_nonce', 'nonce', false) || !current_user_can('edit_posts')) wp_send_json_error('Brak uprawnień');
        $current_user = wp_get_current_user();
        $is_admin = current_user_can('manage_options');
        $args = array();
        $filter_therapist_id = isset($_POST['therapist_filter_id']) ? intval($_POST['therapist_filter_id']) : null;
        if (!$is_admin) {
            $therapist = Rezerwacje_Therapist::get_by_user_id($current_user->ID);
            if ($therapist) {
                $args['therapist_id'] = $therapist->id;
            } else {
                wp_send_json_success(array());
                return;
            }
        } elseif ($filter_therapist_id) {
            $args['therapist_id'] = $filter_therapist_id;
        }
        $start_date = isset($_POST['start']) ? sanitize_text_field($_POST['start']) : date('Y-m-d', strtotime('-1 month'));
        $end_date = isset($_POST['end']) ? sanitize_text_field($_POST['end']) : date('Y-m-d', strtotime('+1 month'));
        $args['date_from'] = date('Y-m-d', strtotime($start_date));
        $args['date_to'] = date('Y-m-d', strtotime($end_date));
        $bookings = Rezerwacje_Booking::get_all($args);
        $blocked_slots_therapist_id = isset($args['therapist_id']) ? $args['therapist_id'] : null;
        $blocked_slots = Rezerwacje_Booking::get_blocked_slots($blocked_slots_therapist_id, $args['date_from'], $args['date_to']);
        $events = array();
        $status_labels = array('pending' => 'Oczekująca', 'approved' => 'Zatwierdzona', 'rejected' => 'Odrzucona', 'cancelled' => 'Anulowana');
        foreach ($bookings as $booking) {
            if ($booking->status === 'rejected' || $booking->status === 'cancelled') continue;
            $color = '#2196F3';
            if ($booking->status === 'pending') {
                $color = '#FF9800';
            }
            if (isset($booking->calendar_color) && !empty($booking->calendar_color)) {
                $color = $booking->calendar_color;
            }
            $event_title = $booking->patient_name . "\n" . $booking->service_name . "\n(" . $booking->therapist_name . ')';
            $events[] = array('title' => $event_title, 'start' => $booking->booking_date . 'T' . $booking->start_time, 'end' => $booking->booking_date . 'T' . $booking->end_time, 'backgroundColor' => $color, 'borderColor' => $color, 'extendedProps' => array('type' => 'booking', 'booking_id' => $booking->id, 'therapist_id' => $booking->therapist_id, 'status' => $booking->status, 'status_display' => isset($status_labels[$booking->status]) ? $status_labels[$booking->status] : $booking->status, 'patient' => $booking->patient_name, 'service' => $booking->service_name, 'therapist' => $booking->therapist_name, 'notes' => $booking->notes));
        }
        foreach ($blocked_slots as $slot) {
            $therapist = Rezerwacje_Therapist::get($slot->therapist_id);
            $therapist_name = $therapist ? $therapist->name : 'Brak';
            $title = 'Zablokowane: ' . $slot->patient_name . "\n(" . $therapist_name . ')';
            $color = '#E91E63';
            if ($therapist && isset($therapist->calendar_color) && !empty($therapist->calendar_color)) {
                $color = $therapist->calendar_color;
            }
            if ($slot->is_recurring) {
                $start = new DateTime($slot->start_date);
                $endDateStr = (!empty($slot->recurrence_end_date) && $slot->recurrence_end_date !== '0000-00-00') ? $slot->recurrence_end_date : $args['date_to'];
                if ($endDateStr < $args['date_from']) continue;
                $end = new DateTime($endDateStr);
                if ($end < $start) $end = clone $start;
                $interval = new DateInterval('P1W');
                try {
                    $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
                    foreach ($period as $date) {
                        if ($date->format('N') == $slot->day_of_week && $date->format('Y-m-d') >= $args['date_from'] && $date->format('Y-m-d') <= $args['date_to']) {
                            $events[] = array('title' => $title, 'start' => $date->format('Y-m-d') . 'T' . $slot->start_time, 'end' => $date->format('Y-m-d') . 'T' . $slot->end_time, 'backgroundColor' => $color, 'borderColor' => $color, 'extendedProps' => array('type' => 'blocked', 'blocked_slot_id' => $slot->id, 'patient_name' => $slot->patient_name, 'notes' => $slot->notes));
                        }
                    }
                } catch (Exception $e) {
                    error_log("Błąd DatePeriod: " . $e->getMessage());
                }
            } else {
                if ($slot->start_date >= $args['date_from'] && $slot->start_date <= $args['date_to']) {
                    $events[] = array('title' => $title, 'start' => $slot->start_date . 'T' . $slot->start_time, 'end' => $slot->start_date . 'T' . $slot->end_time, 'backgroundColor' => $color, 'borderColor' => $color, 'extendedProps' => array('type' => 'blocked', 'blocked_slot_id' => $slot->id, 'patient_name' => $slot->patient_name, 'notes' => $slot->notes));
                }
            }
        }
        wp_send_json($events);
    }

    public function ajax_approve_booking()
    {
        if (!check_ajax_referer('rezerwacje_admin_nonce', 'nonce', false) || !current_user_can('edit_posts')) wp_send_json_error('Brak uprawnień');
        $id = intval($_POST['id']);
        if (Rezerwacje_Booking::approve($id)) {
            Rezerwacje_Email::send_booking_approved($id);
            wp_send_json_success();
        } else {
            wp_send_json_error('Nie udało się zatwierdzić');
        }
    }
    public function ajax_reject_booking()
    {
        if (!check_ajax_referer('rezerwacje_admin_nonce', 'nonce', false) || !current_user_can('edit_posts')) wp_send_json_error('Brak uprawnień');
        $id = intval($_POST['id']);
        if (Rezerwacje_Booking::reject($id)) {
            Rezerwacje_Email::send_booking_rejected($id);
            wp_send_json_success();
        } else {
            wp_send_json_error('Nie udało się odrzucić');
        }
    }
    public function ajax_cancel_booking()
    {
        if (!check_ajax_referer('rezerwacje_admin_nonce', 'nonce', false) || !current_user_can('edit_posts')) wp_send_json_error('Brak uprawnień');
        $id = intval($_POST['id']);
        if (Rezerwacje_Booking::cancel($id)) {
            Rezerwacje_Email::send_booking_rejected($id);
            wp_send_json_success();
        } else {
            wp_send_json_error('Nie udało się anulować');
        }
    }
    public function ajax_update_booking_time()
    {
        if (!check_ajax_referer('rezerwacje_admin_nonce', 'nonce', false) || !current_user_can('edit_posts')) wp_send_json_error('Brak uprawnień');
        $id = intval($_POST['id']);
        $tid = intval($_POST['therapist_id']);
        $date = sanitize_text_field($_POST['booking_date']);
        $start = sanitize_text_field($_POST['start_time']) . ':00';
        $end = sanitize_text_field($_POST['end_time']) . ':00';
        if (empty($id) || empty($tid) || empty($date) || empty($start) || empty($end)) wp_send_json_error('Brak danych.');
        if (Rezerwacje_Booking::is_slot_booked($tid, $date, $start, $end, $id)) wp_send_json_error('Termin zajęty.');
        if (Rezerwacje_Availability::is_slot_blocked($tid, $date, $start, $end)) wp_send_json_error('Termin zablokowany.');
        $res = Rezerwacje_Booking::update($id, ['booking_date' => $date, 'start_time' => $start, 'end_time' => $end]);
        if ($res !== false) wp_send_json_success(['message' => 'Termin zaktualizowany.']);
        else wp_send_json_error('Błąd zapisu.');
    }
    public function ajax_add_blocked_slot()
    {
        if (!check_ajax_referer('rezerwacje_admin_nonce', 'nonce', false) || !current_user_can('manage_options') && !Rezerwacje_Therapist::get(intval($_POST['therapist_id']))?->user_id == get_current_user_id()) wp_send_json_error('Brak uprawnień');
        $tid = intval($_POST['therapist_id']);
        if (!$tid) wp_send_json_error('Nie wybrano terapeuty.');
        $data = ['therapist_id' => $tid, 'patient_name' => sanitize_text_field($_POST['patient_name']), 'start_date' => sanitize_text_field($_POST['start_date']), 'start_time' => sanitize_text_field($_POST['start_time']), 'end_time' => sanitize_text_field($_POST['end_time']), 'is_recurring' => intval($_POST['is_recurring']), 'notes' => sanitize_textarea_field($_POST['notes'])];
        if ($data['is_recurring']) {
            $data['day_of_week'] = intval($_POST['day_of_week']);
            $data['recurrence_end_date'] = !empty($_POST['recurrence_end_date']) ? sanitize_text_field($_POST['recurrence_end_date']) : null;
        }
        if (Rezerwacje_Booking::add_blocked_slot($data)) wp_send_json_success();
        else wp_send_json_error('Nie udało się zablokować.');
    }
    public function ajax_remove_blocked_slot()
    {
        if (!check_ajax_referer('rezerwacje_admin_nonce', 'nonce', false) || !current_user_can('manage_options')) wp_send_json_error('Brak uprawnień');
        $id = intval($_POST['id']);
        if (Rezerwacje_Booking::remove_blocked_slot($id)) wp_send_json_success();
        else wp_send_json_error('Nie udało się usunąć.');
    }
}
