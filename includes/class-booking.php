<?php

if (!defined('ABSPATH')) {
    exit;
}

class Rezerwacje_Booking
{

    public static function create($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_bookings';

        $result = $wpdb->insert(
            $table,
            array(
                'therapist_id' => $data['therapist_id'],
                'service_id' => $data['service_id'],
                'patient_name' => $data['patient_name'],
                'patient_email' => $data['patient_email'],
                'patient_phone' => isset($data['patient_phone']) ? $data['patient_phone'] : null,
                'patient_user_id' => isset($data['patient_user_id']) ? $data['patient_user_id'] : null,
                'booking_date' => $data['booking_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'price' => $data['price'],
                'status' => isset($data['status']) ? $data['status'] : 'pending',
                'notes' => isset($data['notes']) ? $data['notes'] : null
            ),
            array('%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    public static function update($id, $data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_bookings';

        $update_data = array();
        $format = array();

        if (isset($data['status'])) {
            $update_data['status'] = $data['status'];
            $format[] = '%s';
        }
        if (isset($data['notes'])) {
            $update_data['notes'] = $data['notes'];
            $format[] = '%s';
        }
        if (isset($data['booking_date'])) {
            $update_data['booking_date'] = $data['booking_date'];
            $format[] = '%s';
        }
        if (isset($data['start_time'])) {
            $update_data['start_time'] = $data['start_time'];
            $format[] = '%s';
        }
        if (isset($data['end_time'])) {
            $update_data['end_time'] = $data['end_time'];
            $format[] = '%s';
        }
        if (isset($data['price'])) {
            $update_data['price'] = $data['price'];
            $format[] = '%s';
        }

        return $wpdb->update(
            $table,
            $update_data,
            array('id' => $id),
            $format,
            array('%d')
        );
    }

    public static function delete($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_bookings';

        return $wpdb->delete($table, array('id' => $id), array('%d'));
    }

    public static function get($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_bookings';

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id)
        );
    }

    public static function get_all($args = array())
    {
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'rezerwacje_bookings';
        $therapists_table = $wpdb->prefix . 'rezerwacje_therapists';
        $services_table = $wpdb->prefix . 'rezerwacje_services';

        $where = array('1=1');

        if (isset($args['therapist_id'])) {
            $where[] = $wpdb->prepare('b.therapist_id = %d', $args['therapist_id']);
        }

        if (isset($args['status'])) {
            $where[] = $wpdb->prepare('b.status = %s', $args['status']);
        }

        if (isset($args['date_from'])) {
            $where[] = $wpdb->prepare('b.booking_date >= %s', $args['date_from']);
        }

        if (isset($args['date_to'])) {
            $where[] = $wpdb->prepare('b.booking_date <= %s', $args['date_to']);
        }

        $order = isset($args['order']) ? $args['order'] : 'DESC';
        $order_by = isset($args['order_by']) ? $args['order_by'] : 'b.booking_date, b.start_time';

        // ***** POPRAWKA: Dodano `t.calendar_color` do zapytania SELECT *****
        $sql = "SELECT b.*, t.name as therapist_name, s.name as service_name, t.calendar_color
                FROM $bookings_table b
                LEFT JOIN $therapists_table t ON b.therapist_id = t.id
                LEFT JOIN $services_table s ON b.service_id = s.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY $order_by $order";

        return $wpdb->get_results($sql);
    }

    public static function get_by_therapist($therapist_id, $status = null)
    {
        $args = array('therapist_id' => $therapist_id);

        if ($status) {
            $args['status'] = $status;
        }

        return self::get_all($args);
    }

    public static function is_slot_booked($therapist_id, $date, $start_time, $end_time, $exclude_booking_id = 0)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_bookings';

        // Logika kolizji: (SlotStart < BookEnd) AND (SlotEnd > BookStart)
        $sql = "SELECT COUNT(*) FROM $table
                WHERE therapist_id = %d
                AND booking_date = %s
                AND status IN ('pending', 'approved')
                AND (start_time < %s AND end_time > %s)
                AND id != %d";

        $count = $wpdb->get_var($wpdb->prepare(
            $sql,
            $therapist_id,
            $date,
            $end_time,   // SlotEnd
            $start_time,  // SlotStart
            $exclude_booking_id
        ));

        return $count > 0;
    }

    public static function approve($id)
    {
        return self::update($id, array('status' => 'approved'));
    }

    public static function reject($id)
    {
        return self::update($id, array('status' => 'rejected'));
    }

    public static function cancel($id)
    {
        return self::update($id, array('status' => 'cancelled'));
    }

    public static function add_blocked_slot($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_blocked_slots';

        $result = $wpdb->insert(
            $table,
            array(
                'therapist_id' => $data['therapist_id'],
                'patient_name' => $data['patient_name'],
                'start_date' => $data['start_date'],
                'end_date' => isset($data['end_date']) ? $data['end_date'] : null,
                'day_of_week' => isset($data['day_of_week']) ? $data['day_of_week'] : null,
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'is_recurring' => isset($data['is_recurring']) ? $data['is_recurring'] : 0,
                'recurrence_end_date' => isset($data['recurrence_end_date']) ? $data['recurrence_end_date'] : null,
                'notes' => isset($data['notes']) ? $data['notes'] : null,
                'created_by' => get_current_user_id()
            ),
            array('%d', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%d')
        );

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    public static function remove_blocked_slot($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_blocked_slots';

        return $wpdb->delete($table, array('id' => $id), array('%d'));
    }

    public static function get_blocked_slots($therapist_id = null, $date_from = null, $date_to = null)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_blocked_slots';

        $where = array('1=1');

        if ($therapist_id) {
            $where[] = $wpdb->prepare('therapist_id = %d', $therapist_id);
        }

        if ($date_from) {
            $where[] = $wpdb->prepare('( (is_recurring = 0 AND start_date >= %s) OR (is_recurring = 1 AND (recurrence_end_date IS NULL OR recurrence_end_date >= %s)) )', $date_from, $date_from);
        }

        if ($date_to) {
            $where[] = $wpdb->prepare('( (is_recurring = 0 AND start_date <= %s) OR (is_recurring = 1 AND start_date <= %s) )', $date_to, $date_to);
        }

        $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . " ORDER BY start_date, start_time";

        return $wpdb->get_results($sql);
    }
}
