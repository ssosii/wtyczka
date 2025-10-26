<?php

if (!defined('ABSPATH')) {
    exit;
}

class Rezerwacje_Availability
{

    public static function add($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_availability';

        $result = $wpdb->insert(
            $table,
            array(
                'therapist_id' => $data['therapist_id'],
                'day_of_week' => $data['day_of_week'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'active' => isset($data['active']) ? $data['active'] : 1
            ),
            array('%d', '%d', '%s', '%s', '%d')
        );

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    public static function update($id, $data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_availability';

        $update_data = array();
        $format = array();

        if (isset($data['day_of_week'])) {
            $update_data['day_of_week'] = $data['day_of_week'];
            $format[] = '%d';
        }
        if (isset($data['start_time'])) {
            $update_data['start_time'] = $data['start_time'];
            $format[] = '%s';
        }
        if (isset($data['end_time'])) {
            $update_data['end_time'] = $data['end_time'];
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

    public static function delete($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_availability';

        return $wpdb->delete($table, array('id' => $id), array('%d'));
    }

    public static function get($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_availability';

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id)
        );
    }

    public static function get_by_therapist($therapist_id, $active_only = true)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_availability';

        $where = array($wpdb->prepare('therapist_id = %d', $therapist_id));

        if ($active_only) {
            $where[] = 'active = 1';
        }

        $sql = "SELECT * FROM $table WHERE " . implode(' AND ', $where) . " ORDER BY day_of_week, start_time";

        return $wpdb->get_results($sql);
    }

    public static function delete_by_therapist($therapist_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_availability';

        return $wpdb->delete($table, array('therapist_id' => $therapist_id), array('%d'));
    }

    public static function get_available_slots($therapist_id, $date, $service_duration)
    {
        global $wpdb;

        $day_of_week = date('w', strtotime($date));
        if ($day_of_week == 0) {
            $day_of_week = 7;
        }

        $availability = self::get_by_therapist($therapist_id, true);

        $slots = array();
        $slot_interval = 15; // Domyślny interwał slotów (np. co 15 minut)
        // Można by to pobrać z ustawień wtyczki, jeśli istnieją

        foreach ($availability as $avail) {
            if ($avail->day_of_week != $day_of_week) {
                continue;
            }

            $start = strtotime($avail->start_time);
            $end = strtotime($avail->end_time);

            while ($start + ($service_duration * 60) <= $end) {
                $slot_start = date('H:i:s', $start);
                // POPRAWKA: Usunięto błędny, dodatkowy argument z funkcji date()
                $slot_end = date('H:i:s', $start + ($service_duration * 60));

                $is_booked = Rezerwacje_Booking::is_slot_booked($therapist_id, $date, $slot_start, $slot_end);
                $is_blocked = self::is_slot_blocked($therapist_id, $date, $slot_start, $slot_end);

                // Sprawdzenie czy slot nie jest w przeszłości (dla dzisiejszego dnia)
                $is_past = false;
                if (date('Y-m-d') === $date) {
                    // Dodajemy bufor czasowy, np. 1 godzina przed
                    $buffer_time = 60 * 60;
                    if (strtotime(date('Y-m-d') . ' ' . $slot_start) < (time() + $buffer_time)) { // Poprawka: Pełna data i godzina
                        $is_past = true;
                    }
                }


                $slots[] = array(
                    'start_time' => $slot_start,
                    'end_time' => $slot_end,
                    'available' => !$is_booked && !$is_blocked && !$is_past
                );

                // Przesuń czas rozpoczęcia o zdefiniowany interwał, a nie czas trwania usługi
                // To pozwala na generowanie slotów np. co 15 minut (10:00, 10:15, 10:30...)
                // Jeśli usługa trwa 60 minut, rezerwacja o 10:00 zablokuje 10:00, 10:15, 10:30, 10:45
                $start += ($slot_interval * 60);
            }
        }

        return $slots;
    }

    // NOWA FUNKCJA
    public static function get_available_dates_for_month($therapist_id, $service_duration, $month, $year)
    {
        $available_dates = array();
        $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $today = date('Y-m-d');

        for ($day = 1; $day <= $days_in_month; $day++) {
            $date_str = sprintf('%04d-%02d-%02d', $year, $month, $day);

            // Nie sprawdzaj przeszłych dni
            if ($date_str < $today) {
                continue;
            }

            // Użyj istniejącej funkcji, aby pobrać sloty na dany dzień
            $slots = self::get_available_slots($therapist_id, $date_str, $service_duration);

            // Sprawdź, czy jest CHOCIAŻ JEDEN dostępny slot
            foreach ($slots as $slot) {
                if ($slot['available']) {
                    $available_dates[] = $date_str;
                    break; // Wystarczy jeden wolny slot, przejdź do następnego dnia
                }
            }
        }

        return $available_dates;
    }


    public static function is_slot_blocked($therapist_id, $date, $start_time, $end_time)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'rezerwacje_blocked_slots';

        $day_of_week = date('w', strtotime($date));
        if ($day_of_week == 0) {
            $day_of_week = 7;
        }

        // Sprawdza czy slot ($start_time, $end_time) nachodzi na JAKIKOLWIEK zablokowany termin
        $sql = "SELECT COUNT(*) FROM $table
                WHERE therapist_id = %d
                AND (
                    (
                        is_recurring = 0 AND start_date = %s
                        AND (
                            (start_time < %s AND end_time > %s) -- Zablokowany slot zawiera nowy slot
                            OR (start_time >= %s AND start_time < %s) -- Nowy slot zaczyna się w trakcie zablokowanego
                            OR (end_time > %s AND end_time <= %s) -- Nowy slot kończy się w trakcie zablokowanego
                        )
                    )
                    OR
                    (
                        is_recurring = 1 AND day_of_week = %d AND start_date <= %s
                        AND (recurrence_end_date IS NULL OR recurrence_end_date >= %s)
                        AND (
                            (start_time < %s AND end_time > %s) 
                            OR (start_time >= %s AND start_time < %s)
                            OR (end_time > %s AND end_time <= %s)
                        )
                    )
                )";

        $count = $wpdb->get_var($wpdb->prepare(
            $sql,
            $therapist_id,
            // Warunki dla is_recurring = 0
            $date,
            $end_time,
            $start_time, // (BlockStart < SlotEnd AND BlockEnd > SlotStart)
            $start_time,
            $end_time, // (SlotStart >= BlockStart AND SlotStart < BlockEnd)
            $start_time,
            $end_time, // (SlotEnd > BlockStart AND SlotEnd <= BlockEnd) - Ta logika jest trochę inna
            // Powyższe 3 linie można uprościć do: (start_time < %s AND end_time > %s)

            // Poprawiona logika nachodzenia na siebie terminów:
            // (SlotStart < BlockEnd) AND (SlotEnd > BlockStart)

            // is_recurring = 0
            $date,
            $end_time, // BlockEnd
            $start_time, // BlockStart

            // is_recurring = 1
            $day_of_week,
            $date,
            $date,
            $end_time, // BlockEnd
            $start_time // BlockStart
        ));

        // Poprawka logiki SQL - jest zbyt skomplikowana i błędna. Uproszczona:
        // Sprawdzamy, czy slot (S_start, S_end) koliduje z blokadą (B_start, B_end)
        // Kolizja występuje, gdy: (S_start < B_end) AND (S_end > B_start)

        $sql_simplified = "SELECT COUNT(*) FROM $table
            WHERE therapist_id = %d
            AND (
                (
                    is_recurring = 0 AND start_date = %s
                    AND (start_time < %s AND end_time > %s)
                )
                OR
                (
                    is_recurring = 1 AND day_of_week = %d AND start_date <= %s
                    AND (recurrence_end_date IS NULL OR recurrence_end_date >= %s)
                    AND (start_time < %s AND end_time > %s)
                )
            )";

        $count = $wpdb->get_var($wpdb->prepare(
            $sql_simplified,
            $therapist_id,
            // non-recurring
            $date,
            $end_time,   // S_end
            $start_time, // S_start
            // recurring
            $day_of_week,
            $date,
            $date,
            $end_time,   // S_end
            $start_time  // S_start
        ));


        return $count > 0;
    }
}
