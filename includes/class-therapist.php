<?php

if (!defined('ABSPATH')) {
    exit;
}

class Rezerwacje_Therapist {

    public static function create($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_therapists';

        $result = $wpdb->insert(
            $table,
            array(
                'user_id' => $data['user_id'],
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => isset($data['phone']) ? $data['phone'] : null,
                'bio' => isset($data['bio']) ? $data['bio'] : null,
                'active' => isset($data['active']) ? $data['active'] : 1
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d')
        );

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    public static function update($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_therapists';

        $update_data = array();
        $format = array();

        if (isset($data['user_id'])) {
            $update_data['user_id'] = $data['user_id'];
            $format[] = '%d';
        }
        if (isset($data['name'])) {
            $update_data['name'] = $data['name'];
            $format[] = '%s';
        }
        if (isset($data['email'])) {
            $update_data['email'] = $data['email'];
            $format[] = '%s';
        }
        if (isset($data['phone'])) {
            $update_data['phone'] = $data['phone'];
            $format[] = '%s';
        }
        if (isset($data['bio'])) {
            $update_data['bio'] = $data['bio'];
            $format[] = '%s';
        }
        if (isset($data['active'])) {
            $update_data['active'] = $data['active'];
            $format[] = '%d';
        }

        return $wpdb->update(
            $table,
            $update_data,
            array('id' => $id),
            $format,
            array('%d')
        );
    }

    public static function delete($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_therapists';

        return $wpdb->delete($table, array('id' => $id), array('%d'));
    }

    public static function get($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_therapists';

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id)
        );
    }

    public static function get_by_user_id($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_therapists';

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE user_id = %d", $user_id)
        );
    }

    public static function get_all($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_therapists';

        $where = array('1=1');

        if (isset($args['active'])) {
            $where[] = $wpdb->prepare('active = %d', $args['active']);
        }

        $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . " ORDER BY name ASC";

        return $wpdb->get_results($sql);
    }

    public static function add_service($therapist_id, $service_id, $custom_price = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_therapist_services';

        $result = $wpdb->insert(
            $table,
            array(
                'therapist_id' => $therapist_id,
                'service_id' => $service_id,
                'custom_price' => $custom_price
            ),
            array('%d', '%d', '%s')
        );

        return $result !== false;
    }

    public static function remove_service($therapist_id, $service_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_therapist_services';

        return $wpdb->delete(
            $table,
            array(
                'therapist_id' => $therapist_id,
                'service_id' => $service_id
            ),
            array('%d', '%d')
        );
    }

    public static function update_service_price($therapist_id, $service_id, $custom_price) {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_therapist_services';

        return $wpdb->update(
            $table,
            array('custom_price' => $custom_price),
            array(
                'therapist_id' => $therapist_id,
                'service_id' => $service_id
            ),
            array('%s'),
            array('%d', '%d')
        );
    }

    public static function get_services($therapist_id) {
        global $wpdb;
        $services_table = $wpdb->prefix . 'rezerwacje_services';
        $ts_table = $wpdb->prefix . 'rezerwacje_therapist_services';

        $sql = "SELECT s.*, ts.custom_price
                FROM $services_table s
                LEFT JOIN $ts_table ts ON s.id = ts.service_id AND ts.therapist_id = %d
                WHERE s.active = 1
                ORDER BY s.name ASC";

        return $wpdb->get_results($wpdb->prepare($sql, $therapist_id));
    }

    public static function get_assigned_services($therapist_id) {
        global $wpdb;
        $services_table = $wpdb->prefix . 'rezerwacje_services';
        $ts_table = $wpdb->prefix . 'rezerwacje_therapist_services';

        $sql = "SELECT s.*, ts.custom_price
                FROM $ts_table ts
                INNER JOIN $services_table s ON s.id = ts.service_id
                WHERE ts.therapist_id = %d AND s.active = 1
                ORDER BY s.name ASC";

        return $wpdb->get_results($wpdb->prepare($sql, $therapist_id));
    }

    public static function get_service_price($therapist_id, $service_id) {
        global $wpdb;
        $services_table = $wpdb->prefix . 'rezerwacje_services';
        $ts_table = $wpdb->prefix . 'rezerwacje_therapist_services';

        $sql = "SELECT COALESCE(ts.custom_price, s.default_price) as price
                FROM $services_table s
                LEFT JOIN $ts_table ts ON s.id = ts.service_id AND ts.therapist_id = %d
                WHERE s.id = %d";

        $result = $wpdb->get_var($wpdb->prepare($sql, $therapist_id, $service_id));

        return $result ? floatval($result) : 0;
    }
}
