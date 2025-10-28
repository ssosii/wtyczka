<?php

if (!defined('ABSPATH')) {
    exit;
}

class Rezerwacje_Admin_Therapists
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
        add_action('wp_ajax_rezerwacje_save_therapist', array($this, 'ajax_save_therapist'));
        add_action('wp_ajax_rezerwacje_delete_therapist', array($this, 'ajax_delete_therapist'));
        add_action('wp_ajax_rezerwacje_save_therapist_services', array($this, 'ajax_save_therapist_services'));
        add_action('wp_ajax_rezerwacje_save_availability', array($this, 'ajax_save_availability'));
        add_action('wp_ajax_rezerwacje_delete_availability', array($this, 'ajax_delete_availability'));
    }

    public static function render_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $therapist_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        switch ($action) {
            case 'edit':
            case 'new':
                self::render_edit_form($therapist_id);
                break;
            case 'services':
                self::render_services_form($therapist_id);
                break;
            case 'availability':
                self::render_availability_form($therapist_id);
                break;
            default:
                self::render_list();
                break;
        }
    }

    private static function render_list()
    {
        $therapists = Rezerwacje_Therapist::get_all();

?>
        <div class="wrap">
            <h1>
                Terapeuci
                <a href="<?php echo admin_url('admin.php?page=rezerwacje-therapists&action=new'); ?>" class="page-title-action">Dodaj nowego</a>
            </h1>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Imię i nazwisko</th>
                        <th>Email</th>
                        <th>Telefon</th>
                        <th>Użytkownik WP</th>
                        <th>Status</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($therapists)): ?>
                        <tr>
                            <td colspan="7">Brak terapeutów</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($therapists as $therapist): ?>
                            <?php $user = get_user_by('id', $therapist->user_id); ?>
                            <tr>
                                <td><?php echo $therapist->id; ?></td>
                                <td><strong><?php echo esc_html($therapist->name); ?></strong></td>
                                <td><?php echo esc_html($therapist->email); ?></td>
                                <td><?php echo esc_html($therapist->phone); ?></td>
                                <td><?php echo $user ? $user->user_login : '-'; ?></td>
                                <td><?php echo $therapist->active ? 'Aktywny' : 'Nieaktywny'; ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=rezerwacje-therapists&action=edit&id=' . $therapist->id); ?>">Edytuj</a> |
                                    <a href="<?php echo admin_url('admin.php?page=rezerwacje-therapists&action=services&id=' . $therapist->id); ?>">Usługi</a> |
                                    <a href="<?php echo admin_url('admin.php?page=rezerwacje-therapists&action=availability&id=' . $therapist->id); ?>">Dostępność</a> |
                                    <a href="#" class="delete-therapist" data-id="<?php echo $therapist->id; ?>">Usuń</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php
    }

    private static function render_edit_form($therapist_id)
    {
        $therapist = $therapist_id ? Rezerwacje_Therapist::get($therapist_id) : null;
        $users = get_users(array('role__not_in' => array('administrator')));

        // Zabezpieczenie na wypadek, gdyby kolumny JESZCZE nie było
        $photo_id = isset($therapist->photo_id) ? $therapist->photo_id : 0;
        $photo_url = $photo_id ? wp_get_attachment_image_url($photo_id, 'thumbnail') : '';
        $calendar_color = isset($therapist->calendar_color) ? $therapist->calendar_color : '';


    ?>
        <div class="wrap">
            <h1><?php echo $therapist_id ? 'Edytuj terapeutę' : 'Dodaj terapeutę'; ?></h1>

            <form method="post" id="therapist-form">
                <input type="hidden" name="therapist_id" value="<?php echo $therapist_id; ?>">

                <table class="form-table">
                    <tr>
                        <th><label for="user_id">Użytkownik WordPress *</label></th>
                        <td>
                            <select name="user_id" id="user_id" required>
                                <option value="">Wybierz użytkownika</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user->ID; ?>" <?php selected($therapist ? $therapist->user_id : 0, $user->ID); ?>>
                                        <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="name">Imię i nazwisko *</label></th>
                        <td>
                            <input type="text" name="name" id="name" class="regular-text" required
                                value="<?php echo $therapist ? esc_attr($therapist->name) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label>Zdjęcie</label></th>
                        <td>
                            <div class="image-uploader-wrapper">
                                <div id="therapist-photo-preview" style="width: 100px; height: 100px; background: #eee; border: 1px solid #ccc; <?php echo $photo_url ? "background-image: url($photo_url); background-size: cover;" : ''; ?>">
                                </div>
                                <input type="hidden" name="photo_id" id="photo_id" value="<?php echo esc_attr($photo_id); ?>">
                                <button type="button" class="button" id="upload-photo-button">Wybierz / Zmień zdjęcie</button>
                                <button type="button" class="button" id="remove-photo-button" style="<?php echo $photo_id ? '' : 'display: none;'; ?>">Usuń zdjęcie</button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="calendar_color">Kolor w kalendarzu admina</label></th>
                        <td>
                            <input type="text" name="calendar_color" id="calendar_color" class="color-picker-field"
                                value="<?php echo esc_attr($calendar_color); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="email">Email *</label></th>
                        <td>
                            <input type="email" name="email" id="email" class="regular-text" required
                                value="<?php echo $therapist ? esc_attr($therapist->email) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="phone">Telefon</label></th>
                        <td>
                            <input type="tel" name="phone" id="phone" class="regular-text"
                                value="<?php echo $therapist ? esc_attr($therapist->phone) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="bio">Bio</label></th>
                        <td>
                            <textarea name="bio" id="bio" rows="5" class="large-text"><?php echo $therapist ? esc_textarea($therapist->bio) : ''; ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="active">Status</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="active" id="active" value="1"
                                    <?php checked($therapist ? $therapist->active : 1, 1); ?>>
                                Aktywny
                            </label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">Zapisz</button>
                    <a href="<?php echo admin_url('admin.php?page=rezerwacje-therapists'); ?>" class="button">Anuluj</a>
                </p>
            </form>
        </div>

        <script>
            jQuery(document).ready(function($) {

                $('.color-picker-field').wpColorPicker();

                var mediaUploader;
                $('#upload-photo-button').on('click', function(e) {
                    e.preventDefault();
                    if (mediaUploader) {
                        mediaUploader.open();
                        return;
                    }
                    mediaUploader = wp.media({
                        title: 'Wybierz zdjęcie terapeuty',
                        button: {
                            text: 'Wybierz zdjęcie'
                        },
                        multiple: false
                    });
                    mediaUploader.on('select', function() {
                        var attachment = mediaUploader.state().get('selection').first().toJSON();
                        $('#photo_id').val(attachment.id);
                        $('#therapist-photo-preview').css('background-image', 'url(' + attachment.sizes.thumbnail.url + ')').css('background-size', 'cover');
                        $('#remove-photo-button').show();
                    });
                    mediaUploader.open();
                });

                $('#remove-photo-button').on('click', function(e) {
                    e.preventDefault();
                    $('#photo_id').val('');
                    $('#therapist-photo-preview').css('background-image', 'none');
                    $(this).hide();
                });

                $('#therapist-form').on('submit', function(e) {
                    e.preventDefault();

                    var formData = {
                        action: 'rezerwacje_save_therapist',
                        nonce: rezerwacjeAdmin.nonce,
                        therapist_id: $('[name="therapist_id"]').val(),
                        user_id: $('[name="user_id"]').val(),
                        name: $('[name="name"]').val(),
                        email: $('[name="email"]').val(),
                        phone: $('[name="phone"]').val(),
                        bio: $('[name="bio"]').val(),
                        photo_id: $('[name="photo_id"]').val(),
                        calendar_color: $('[name="calendar_color"]').val(),
                        active: $('[name="active"]').is(':checked') ? 1 : 0
                    };

                    $.post(rezerwacjeAdmin.ajax_url, formData, function(response) {
                        if (response.success) {
                            alert('Zapisano pomyślnie');
                            window.location.href = '<?php echo admin_url('admin.php?page=rezerwacje-therapists'); ?>';
                        } else {
                            alert('Błąd: ' + response.data);
                        }
                    });
                });
            });
        </script>
    <?php
    }

    private static function render_services_form($therapist_id)
    {
        $therapist = Rezerwacje_Therapist::get($therapist_id);
        if (!$therapist) {
            wp_die('Terapeuta nie istnieje');
        }

        $all_services = Rezerwacje_Service::get_all(array('active' => 1));
        $assigned_services = Rezerwacje_Therapist::get_assigned_services($therapist_id);

        $assigned_ids = array();
        $custom_prices = array();
        foreach ($assigned_services as $service) {
            $assigned_ids[] = $service->id;
            $custom_prices[$service->id] = $service->custom_price;
        }

    ?>
        <div class="wrap">
            <h1>Usługi: <?php echo esc_html($therapist->name); ?></h1>

            <form method="post" id="therapist-services-form">
                <input type="hidden" name="therapist_id" value="<?php echo $therapist_id; ?>">

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="50">Przypisz</th>
                            <th>Usługa</th>
                            <th>Czas trwania</th>
                            <th>Cena domyślna</th>
                            <th>Cena własna</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_services as $service): ?>
                            <?php $is_assigned = in_array($service->id, $assigned_ids); ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="services[]" value="<?php echo $service->id; ?>"
                                        <?php checked($is_assigned); ?>>
                                </td>
                                <td><?php echo esc_html($service->name); ?></td>
                                <td><?php echo $service->duration; ?> min</td>
                                <td><?php echo number_format($service->default_price, 2); ?> zł</td>
                                <td>
                                    <input type="number" name="custom_price[<?php echo $service->id; ?>]"
                                        step="0.01" min="0" class="small-text"
                                        value="<?php echo isset($custom_prices[$service->id]) ? esc_attr($custom_prices[$service->id]) : ''; ?>"
                                        placeholder="<?php echo number_format($service->default_price, 2); ?>">
                                    zł
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">Zapisz</button>
                    <a href="<?php echo admin_url('admin.php?page=rezerwacje-therapists'); ?>" class="button">Powrót</a>
                </p>
            </form>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $('#therapist-services-form').on('submit', function(e) {
                    e.preventDefault();

                    var services = [];
                    $('[name="services[]"]:checked').each(function() {
                        var serviceId = $(this).val();
                        var customPrice = $('[name="custom_price[' + serviceId + ']"]').val();
                        services.push({
                            id: serviceId,
                            custom_price: customPrice || null
                        });
                    });

                    var formData = {
                        action: 'rezerwacje_save_therapist_services',
                        nonce: rezerwacjeAdmin.nonce,
                        therapist_id: $('[name="therapist_id"]').val(),
                        services: services
                    };

                    $.post(rezerwacjeAdmin.ajax_url, formData, function(response) {
                        if (response.success) {
                            alert('Zapisano pomyślnie');
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

    private static function render_availability_form($therapist_id)
    {
        $therapist = Rezerwacje_Therapist::get($therapist_id);
        if (!$therapist) {
            wp_die('Terapeuta nie istnieje');
        }

        $current_user = wp_get_current_user();
        $is_admin = current_user_can('manage_options');
        $is_owner = $therapist->user_id == $current_user->ID;

        if (!$is_admin && !$is_owner) {
            wp_die('Brak uprawnień');
        }

        $availability = Rezerwacje_Availability::get_by_therapist($therapist_id, false);

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
            <h1>Dostępność: <?php echo esc_html($therapist->name); ?></h1>

            <h2>Aktualna dostępność</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Dzień tygodnia</th>
                        <th>Godzina od</th>
                        <th>Godzina do</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($availability)): ?>
                        <tr>
                            <td colspan="4">Brak dostępności</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($availability as $slot): ?>
                            <tr>
                                <td><?php echo $days[$slot->day_of_week]; ?></td>
                                <td><?php echo date('H:i', strtotime($slot->start_time)); ?></td>
                                <td><?php echo date('H:i', strtotime($slot->end_time)); ?></td>
                                <td>
                                    <a href="#" class="delete-availability" data-id="<?php echo $slot->id; ?>">Usuń</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2>Dodaj nową dostępność</h2>
            <form method="post" id="availability-form">
                <input type="hidden" name="therapist_id" value="<?php echo $therapist_id; ?>">

                <table class="form-table">
                    <tr>
                        <th><label for="day_of_week">Dzień tygodnia *</label></th>
                        <td>
                            <select name="day_of_week" id="day_of_week" required>
                                <option value="">Wybierz dzień</option>
                                <?php foreach ($days as $num => $name): ?>
                                    <option value="<?php echo $num; ?>"><?php echo $name; ?></option>
                                <?php endforeach; ?>
                            </select>
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
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">Dodaj</button>
                    <a href="<?php echo admin_url('admin.php?page=rezerwacje-therapists'); ?>" class="button">Powrót</a>
                </p>
            </form>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $('#availability-form').on('submit', function(e) {
                    e.preventDefault();

                    var formData = {
                        action: 'rezerwacje_save_availability',
                        nonce: rezerwacjeAdmin.nonce,
                        therapist_id: $('[name="therapist_id"]').val(),
                        day_of_week: $('[name="day_of_week"]').val(),
                        start_time: $('[name="start_time"]').val(),
                        end_time: $('[name="end_time"]').val()
                    };

                    $.post(rezerwacjeAdmin.ajax_url, formData, function(response) {
                        if (response.success) {
                            alert('Zapisano pomyślnie');
                            location.reload();
                        } else {
                            alert('Błąd: ' + response.data);
                        }
                    });
                });

                $('.delete-availability').on('click', function(e) {
                    e.preventDefault();

                    if (!confirm('Czy na pewno usunąć tę dostępność?')) {
                        return;
                    }

                    var id = $(this).data('id');

                    $.post(rezerwacjeAdmin.ajax_url, {
                        action: 'rezerwacje_delete_availability',
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

    public function ajax_save_therapist()
    {
        check_ajax_referer('rezerwacje_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }

        $therapist_id = intval($_POST['therapist_id']);

        $data = array(
            'user_id' => intval($_POST['user_id']),
            'name' => sanitize_text_field($_POST['name']),
            'email' => sanitize_email($_POST['email']),
            'phone' => sanitize_text_field($_POST['phone']),
            'bio' => sanitize_textarea_field($_POST['bio']),
            'photo_id' => !empty($_POST['photo_id']) ? intval($_POST['photo_id']) : null,
            'calendar_color' => sanitize_text_field($_POST['calendar_color']),
            'active' => intval($_POST['active'])
        );

        if ($therapist_id) {
            $result = Rezerwacje_Therapist::update($therapist_id, $data);
        } else {
            $result = Rezerwacje_Therapist::create($data);
        }

        if ($result !== false) { // Musi być !== false, bo update zwraca 0 jeśli nic się nie zmieniło
            wp_send_json_success();
        } else {
            // Zostawiamy logowanie błędów
            global $wpdb;
            wp_send_json_error('Nie udało się zapisać. Błąd bazy danych: ' . $wpdb->last_error);
        }
    }

    public function ajax_delete_therapist()
    {
        check_ajax_referer('rezerwacje_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }

        $id = intval($_POST['id']);
        $result = Rezerwacje_Therapist::delete($id);

        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Nie udało się usunąć');
        }
    }

    public function ajax_save_therapist_services()
    {
        check_ajax_referer('rezerwacje_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }

        $therapist_id = intval($_POST['therapist_id']);
        $services = isset($_POST['services']) ? $_POST['services'] : array();

        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_therapist_services';
        $wpdb->delete($table, array('therapist_id' => $therapist_id), array('%d'));

        foreach ($services as $service_data) {
            $service_id = intval($service_data['id']);
            $custom_price = !empty($service_data['custom_price']) ? floatval($service_data['custom_price']) : null;

            Rezerwacje_Therapist::add_service($therapist_id, $service_id, $custom_price);
        }

        wp_send_json_success();
    }

    public function ajax_save_availability()
    {
        check_ajax_referer('rezerwacje_admin_nonce', 'nonce');

        $therapist_id = intval($_POST['therapist_id']);
        $therapist = Rezerwacje_Therapist::get($therapist_id);

        if (!$therapist) {
            wp_send_json_error('Terapeuta nie istnieje');
        }

        $current_user = wp_get_current_user();
        $is_admin = current_user_can('manage_options');
        $is_owner = $therapist->user_id == $current_user->ID;

        if (!$is_admin && !$is_owner) {
            wp_send_json_error('Brak uprawnień');
        }

        $data = array(
            'therapist_id' => $therapist_id,
            'day_of_week' => intval($_POST['day_of_week']),
            'start_time' => sanitize_text_field($_POST['start_time']),
            'end_time' => sanitize_text_field($_POST['end_time'])
        );

        $result = Rezerwacje_Availability::add($data);

        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Nie udało się zapisać');
        }
    }

    public function ajax_delete_availability()
    {
        check_ajax_referer('rezerwacje_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Brak uprawnień');
        }

        $id = intval($_POST['id']);
        $result = Rezerwacje_Availability::delete($id);

        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Nie udało się usunąć');
        }
    }
}
