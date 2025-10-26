<?php

if (!defined('ABSPATH')) {
    exit;
}

class Rezerwacje_Email
{

    public static function send_booking_confirmation($booking_id)
    {
        $booking = Rezerwacje_Booking::get($booking_id);

        if (!$booking) {
            return false;
        }

        $therapist = Rezerwacje_Therapist::get($booking->therapist_id);
        $service = Rezerwacje_Service::get($booking->service_id);

        $to = $booking->patient_email;
        $subject = 'Rezerwacja oczekuje na potwierdzenie - ' . get_bloginfo('name');

        $date_formatted = date_i18n('d.m.Y', strtotime($booking->booking_date));
        $start_time_formatted = date('H:i', strtotime($booking->start_time));
        $end_time_formatted = date('H:i', strtotime($booking->end_time));

        $message = "Dzień dobry,\n\n";
        $message .= "Twoja rezerwacja została przyjęta i oczekuje na potwierdzenie.\n\n";
        $message .= "Szczegóły rezerwacji:\n";
        $message .= "- Terapeuta: {$therapist->name}\n";
        $message .= "- Usługa: {$service->name}\n";
        $message .= "- Data: {$date_formatted}\n";
        $message .= "- Godzina: {$start_time_formatted} - {$end_time_formatted}\n";
        $message .= "- Cena: {$booking->price} zł\n\n";
        $message .= "Otrzymasz wiadomość email z potwierdzeniem lub odrzuceniem rezerwacji.\n\n";
        $message .= "Pozdrawiamy,\n";
        $message .= get_bloginfo('name');

        $headers = array('Content-Type: text/plain; charset=UTF-8');

        return wp_mail($to, $subject, $message, $headers);
    }

    public static function send_booking_approved($booking_id)
    {
        $booking = Rezerwacje_Booking::get($booking_id);

        if (!$booking) {
            return false;
        }

        $therapist = Rezerwacje_Therapist::get($booking->therapist_id);
        $service = Rezerwacje_Service::get($booking->service_id);

        $to = $booking->patient_email;
        $subject = 'Rezerwacja potwierdzona - ' . get_bloginfo('name');

        $date_formatted = date_i18n('d.m.Y', strtotime($booking->booking_date));
        $start_time_formatted = date('H:i', strtotime($booking->start_time));
        $end_time_formatted = date('H:i', strtotime($booking->end_time));

        $message = "Dzień dobry,\n\n";
        $message .= "Twoja rezerwacja została potwierdzona!\n\n";
        $message .= "Szczegóły rezerwacji:\n";
        $message .= "- Terapeuta: {$therapist->name}\n";
        $message .= "- Usługa: {$service->name}\n";
        $message .= "- Data: {$date_formatted}\n";
        $message .= "- Godzina: {$start_time_formatted} - {$end_time_formatted}\n";
        $message .= "- Cena: {$booking->price} zł\n\n";
        $message .= "Czekamy na Ciebie!\n\n";
        $message .= "Pozdrawiamy,\n";
        $message .= get_bloginfo('name');

        $headers = array('Content-Type: text/plain; charset=UTF-8');

        return wp_mail($to, $subject, $message, $headers);
    }

    public static function send_booking_rejected($booking_id)
    {
        $booking = Rezerwacje_Booking::get($booking_id);

        if (!$booking) {
            return false;
        }

        $therapist = Rezerwacje_Therapist::get($booking->therapist_id);
        $service = Rezerwacje_Service::get($booking->service_id);

        $to = $booking->patient_email;
        $subject = 'Rezerwacja odrzucona - ' . get_bloginfo('name');

        $date_formatted = date_i18n('d.m.Y', strtotime($booking->booking_date));
        $start_time_formatted = date('H:i', strtotime($booking->start_time));
        $end_time_formatted = date('H:i', strtotime($booking->end_time));

        $message = "Dzień dobry,\n\n";
        $message .= "Niestety Twoja rezerwacja została odrzucona.\n\n";
        $message .= "Szczegóły rezerwacji:\n";
        $message .= "- Terapeuta: {$therapist->name}\n";
        $message .= "- Usługa: {$service->name}\n";
        $message .= "- Data: {$date_formatted}\n";
        $message .= "- Godzina: {$start_time_formatted} - {$end_time_formatted}\n\n";
        $message .= "Prosimy o wybranie innego terminu lub skontaktowanie się z nami.\n\n";
        $message .= "Pozdrawiamy,\n";
        $message .= get_bloginfo('name');

        $headers = array('Content-Type: text/plain; charset=UTF-8');

        return wp_mail($to, $subject, $message, $headers);
    }

    public static function notify_therapist_new_booking($booking_id)
    {
        $booking = Rezerwacje_Booking::get($booking_id);

        if (!$booking) {
            return false;
        }

        $therapist = Rezerwacje_Therapist::get($booking->therapist_id);
        $service = Rezerwacje_Service::get($booking->service_id);

        $to = $therapist->email;
        $subject = 'Nowa rezerwacja do akceptacji - ' . get_bloginfo('name');

        $date_formatted = date_i18n('d.m.Y', strtotime($booking->booking_date));
        $start_time_formatted = date('H:i', strtotime($booking->start_time));
        $end_time_formatted = date('H:i', strtotime($booking->end_time));

        $admin_url = admin_url('admin.php?page=rezerwacje-bookings');

        $message = "Dzień dobry,\n\n";
        $message .= "Masz nową rezerwację do zaakceptowania.\n\n";
        $message .= "Szczegóły rezerwacji:\n";
        $message .= "- Pacjent: {$booking->patient_name}\n";
        $message .= "- Email: {$booking->patient_email}\n";
        $message .= "- Telefon: {$booking->patient_phone}\n";
        $message .= "- Usługa: {$service->name}\n";
        $message .= "- Data: {$date_formatted}\n";
        $message .= "- Godzina: {$start_time_formatted} - {$end_time_formatted}\n";
        $message .= "- Cena: {$booking->price} zł\n\n";
        $message .= "Zaloguj się do panelu, aby zaakceptować lub odrzucić rezerwację:\n";
        $message .= $admin_url . "\n\n";
        $message .= "Pozdrawiamy,\n";
        $message .= get_bloginfo('name');

        $headers = array('Content-Type: text/plain; charset=UTF-8');

        return wp_mail($to, $subject, $message, $headers);
    }
}
