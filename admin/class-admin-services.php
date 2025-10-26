<?php

if (!defined('ABSPATH')) {
    exit;
}

class Rezerwacje_Admin_Services {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_rezerwacje_save_service', array($this, 'ajax_save_service'));
        add_action('wp_ajax_rezerwacje_delete_service', array($this, 'ajax_delete_service'));
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Brak uprawnień');
        }

        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $service_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        switch ($action) {
            case 'edit':
            case 'new':
                self::render_edit_form($service_id);
                break;
            default:
                self::render_list();
                break;
        }
    }

    private static function render_list() {
        $services = Rezerwacje_Service::get_all();

        ?>
        <div class="wrap">
            <h1>
                Usługi
                <a href="<?php echo admin_url('admin.php?page=rezerwacje-services&action=new'); ?>" class="page-title-action">Dodaj nową</a>
            </h1>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nazwa</th>
                        <th>Czas trwania</th>
                        <th>Cena domyślna</th>
                        <th>Status</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($services)): ?>
                        <tr>
                            <td colspan="6">Brak usług</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($services as $service): ?>
                            <tr>
                                <td><?php echo $service->id; ?></td>
                                <td><strong><?php echo esc_html($service->name); ?></strong></td>
                                <td><?php echo $service->duration; ?> min</td>
                                <td><?php echo number_format($service->default_price, 2); ?> zł</td>
                                <td><?php echo $service->active ? 'Aktywna' : 'Nieaktywna'; ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=rezerwacje-services&action=edit&id=' . $service->id); ?>">Edytuj</a> |
                                    <a href="#" class="delete-service" data-id="<?php echo $service->id; ?>">Usuń</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.delete-service').on('click', function(e) {
                e.preventDefault();

                if (!confirm('Czy na pewno usunąć tę usługę?')) {
                    return;
                }

                var id = $(this).data('id');

                $.post(rezerwacjeAdmin.ajax_url, {
                    action: 'rezerwacje_delete_service',
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

    private static function render_edit_form($service_id) {
        $service = $service_id ? Rezerwacje_Service::get($service_id) : null;

        ?>
        <div class="wrap">
            <h1><?php echo $service_id ? 'Edytuj usługę' : 'Dodaj usługę'; ?></h1>

            <form method="post" id="service-form">
                <input type="hidden" name="service_id" value="<?php echo $service_id; ?>">

                <table class="form-table">
                    <tr>
                        <th><label for="name">Nazwa *</label></th>
                        <td>
                            <input type="text" name="name" id="name" class="regular-text" required
                                   value="<?php echo $service ? esc_attr($service->name) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="description">Opis</label></th>
                        <td>
                            <textarea name="description" id="description" rows="5" class="large-text"><?php echo $service ? esc_textarea($service->description) : ''; ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="duration">Czas trwania (minuty) *</label></th>
                        <td>
                            <input type="number" name="duration" id="duration" min="1" step="1" required
                                   value="<?php echo $service ? esc_attr($service->duration) : '60'; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="default_price">Cena domyślna (zł) *</label></th>
                        <td>
                            <input type="number" name="default_price" id="default_price" min="0" step="0.01" required
                                   value="<?php echo $service ? esc_attr($service->default_price) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="active">Status</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="active" id="active" value="1"
                                       <?php checked($service ? $service->active : 1, 1); ?>>
                                Aktywna
                            </label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">Zapisz</button>
                    <a href="<?php echo admin_url('admin.php?page=rezerwacje-services'); ?>" class="button">Anuluj</a>
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#service-form').on('submit', function(e) {
                e.preventDefault();

                var formData = {
                    action: 'rezerwacje_save_service',
                    nonce: rezerwacjeAdmin.nonce,
                    service_id: $('[name="service_id"]').val(),
                    name: $('[name="name"]').val(),
                    description: $('[name="description"]').val(),
                    duration: $('[name="duration"]').val(),
                    default_price: $('[name="default_price"]').val(),
                    active: $('[name="active"]').is(':checked') ? 1 : 0
                };

                $.post(rezerwacjeAdmin.ajax_url, formData, function(response) {
                    if (response.success) {
                        alert('Zapisano pomyślnie');
                        window.location.href = '<?php echo admin_url('admin.php?page=rezerwacje-services'); ?>';
                    } else {
                        alert('Błąd: ' + response.data);
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function ajax_save_service() {
        check_ajax_referer('rezerwacje_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }

        $service_id = intval($_POST['service_id']);
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'duration' => intval($_POST['duration']),
            'default_price' => floatval($_POST['default_price']),
            'active' => intval($_POST['active'])
        );

        if ($service_id) {
            $result = Rezerwacje_Service::update($service_id, $data);
        } else {
            $result = Rezerwacje_Service::create($data);
        }

        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Nie udało się zapisać');
        }
    }

    public function ajax_delete_service() {
        check_ajax_referer('rezerwacje_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Brak uprawnień');
        }

        $id = intval($_POST['id']);
        $result = Rezerwacje_Service::delete($id);

        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Nie udało się usunąć');
        }
    }
}
