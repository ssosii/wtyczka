<?php

if (!defined('ABSPATH')) {
    exit;
}

class Rezerwacje_Service {

    public static function create($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_services';

        $result = $wpdb->insert(
            $table,
            array(
                'name' => $data['name'],
                'description' => isset($data['description']) ? $data['description'] : null,
                'duration' => isset($data['duration']) ? $data['duration'] : 60,
                'default_price' => $data['default_price'],
                'active' => isset($data['active']) ? $data['active'] : 1
            ),
            array('%s', '%s', '%d', '%s', '%d')
        );

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    public static function update($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_services';

        $update_data = array();
        $format = array();

        if (isset($data['name'])) {
            $update_data['name'] = $data['name'];
            $format[] = '%s';
        }
        if (isset($data['description'])) {
            $update_data['description'] = $data['description'];
            $format[] = '%s';
        }
        if (isset($data['duration'])) {
            $update_data['duration'] = $data['duration'];
            $format[] = '%d';
        }
        if (isset($data['default_price'])) {
            $update_data['default_price'] = $data['default_price'];
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
        $table = $wpdb->prefix . 'rezerwacje_services';

        return $wpdb->delete($table, array('id' => $id), array('%d'));
    }

    public static function get($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_services';

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id)
        );
    }

    public static function get_all($args = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_services';

        $where = array('1=1');

        if (isset($args['active'])) {
            $where[] = $wpdb->prepare('active = %d', $args['active']);
        }

        $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . " ORDER BY name ASC";

        return $wpdb->get_results($sql);
    }
}
