jQuery(document).ready(function ($) {
    var currentStep = 1;
    var selectedTherapist = null;
    var selectedService = null;
    var selectedDate = null;
    var selectedTime = null;
    var currentMonth = new Date().getMonth();
    var currentYear = new Date().getFullYear();

    function showStep(step) {
        $('.rezerwacje-step').removeClass('active');
        $('.rezerwacje-step-' + step).addClass('active');
        currentStep = step;

        $('#rezerwacje-btn-back').toggle(step > 1 && step < 6);
    }

    function loadTherapists() {
        var $list = $('#rezerwacje-therapists-list');
        $list.html('<div class="rezerwacje-loading"></div>'); // Pokaż spinner

        $.post(rezerwacjeFrontend.ajax_url, {
            action: 'rezerwacje_get_therapists',
            nonce: rezerwacjeFrontend.nonce
        }, function (response) {
            if (response.success) {
                var html = '';
                if (response.data.length > 0) {
                    response.data.forEach(function (therapist) {
                        html += '<div class="rezerwacje-therapist-card" data-id="' + therapist.id + '">';
                        html += '<h4>' + therapist.name + '</h4>';
                        if (therapist.bio) {
                            html += '<p>' + therapist.bio + '</p>';
                        }
                        html += '</div>';
                    });
                } else {
                    html = '<p>Brak dostępnych terapeutów.</p>';
                }

                $list.html(html);
            } else {
                $list.html('<p class="rezerwacje-error">Nie udało się załadować terapeutów.</p>');
            }
        });
    }

    function loadServices(therapistId) {
        var $list = $('#rezerwacje-services-list');
        $list.html('<div class="rezerwacje-loading"></div>'); // Pokaż spinner

        $.post(rezerwacjeFrontend.ajax_url, {
            action: 'rezerwacje_get_services',
            nonce: rezerwacjeFrontend.nonce,
            therapist_id: therapistId
        }, function (response) {
            if (response.success) {
                var html = '';
                if (response.data.length > 0) {
                    response.data.forEach(function (service) {
                        html += '<div class="rezerwacje-service-card" data-id="' + service.id + '" data-duration="' + service.duration + '" data-price="' + service.price + '">';
                        html += '<h4>' + service.name + '</h4>';

                        html += '<div class="service-info">';
                        html += '<span>' + service.duration + ' minut</span>';
                        html += '<span class="service-price">' + service.price + ' zł</span>';
                        html += '</div>';
                        if (service.description) {
                            html += '<p>' + service.description + '</p>';
                        }
                        html += '</div>';
                    });
                } else {
                    html = '<p>Brak dostępnych usług dla tego terapeuty.</p>';
                }
                $list.html(html);
            } else {
                $list.html('<p class="rezerwacje-error">Nie udało się załadować usług.</p>');
            }
        });
    }

    function renderCalendar(month, year) {
        var firstDay = new Date(year, month, 1);
        var lastDay = new Date(year, month + 1, 0);
        var daysInMonth = lastDay.getDate();
        var startingDayOfWeek = firstDay.getDay(); // 0 (Niedziela) - 6 (Sobota)
        if (startingDayOfWeek === 0) startingDayOfWeek = 7; // 1 (Poniedziałek) - 7 (Niedziela)

        var monthNames = ['Styczeń', 'Luty', 'Marzec', 'Kwiecień', 'Maj', 'Czerwiec',
            'Lipiec', 'Sierpień', 'Wrzesień', 'Październik', 'Listopad', 'Grudzień'];
        $('#rezerwacje-current-month').text(monthNames[month] + ' ' + year);

        var html = '';
        var dayHeaders = ['Pon', 'Wt', 'Śr', 'Czw', 'Pt', 'Sob', 'Nie'];
        dayHeaders.forEach(function (day) {
            html += '<div class="rezerwacje-calendar-day header">' + day + '</div>';
        });

        for (var i = 1; i < startingDayOfWeek; i++) {
            html += '<div class="rezerwacje-calendar-day disabled"></div>';
        }

        var today = new Date();
        today.setHours(0, 0, 0, 0);

        for (var day = 1; day <= daysInMonth; day++) {
            var currentDate = new Date(year, month, day);
            var isDisabled = currentDate < today;
            var dateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');

            var classes = 'rezerwacje-calendar-day';
            if (isDisabled) classes += ' disabled';
            if (dateStr === selectedDate) classes += ' selected';

            html += '<div class="' + classes + '" data-date="' + dateStr + '">';
            html += day;
            html += '</div>';
        }

        $('#rezerwacje-calendar-grid').html(html);
    }

    function loadAvailableSlots(therapistId, serviceId, date) {
        var $list = $('#rezerwacje-time-slots');
        $list.html('<div class="rezerwacje-loading"></div>'); // Pokaż spinner

        $.post(rezerwacjeFrontend.ajax_url, {
            action: 'rezerwacje_get_available_slots',
            nonce: rezerwacjeFrontend.nonce,
            therapist_id: therapistId,
            service_id: serviceId,
            date: date
        }, function (response) {
            if (response.success) {
                var html = '';
                var availableSlots = response.data.filter(function (slot) {
                    return slot.available;
                });

                if (availableSlots.length === 0) {
                    html = '<p>Brak dostępnych terminów w wybranym dniu.</p>';
                } else {
                    availableSlots.forEach(function (slot) {
                        html += '<div class="rezerwacje-time-slot" data-start="' + slot.start_time + '" data-end="' + slot.end_time + '">';
                        html += slot.start_time.substring(0, 5); // + ' - ' + slot.end_time.substring(0, 5); // Tylko godzina rozpoczęcia dla zwięzłości
                        html += '</div>';
                    });
                }
                $list.html(html);
            } else {
                $list.html('<p class="rezerwacje-error">Nie udało się załadować terminów.</p>');
            }
        });
    }

    $(document).on('click', '.rezerwacje-therapist-card', function () {
        $('.rezerwacje-therapist-card').removeClass('selected');
        $(this).addClass('selected');
        selectedTherapist = {
            id: $(this).data('id'),
            name: $(this).find('h4').text()
        };
        loadServices(selectedTherapist.id);
        showStep(2);
    });

    $(document).on('click', '.rezerwacje-service-card', function () {
        $('.rezerwacje-service-card').removeClass('selected');
        $(this).addClass('selected');
        selectedService = {
            id: $(this).data('id'),
            name: $(this).find('h4').text(),
            duration: $(this).data('duration'),
            price: $(this).data('price')
        };
        renderCalendar(currentMonth, currentYear);
        showStep(3);
    });

    $('#rezerwacje-prev-month').on('click', function () {
        currentMonth--;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        renderCalendar(currentMonth, currentYear);
    });

    $('#rezerwacje-next-month').on('click', function () {
        currentMonth++;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        renderCalendar(currentMonth, currentYear);
    });

    $(document).on('click', '.rezerwacje-calendar-day:not(.disabled):not(.header)', function () {
        $('.rezerwacje-calendar-day').removeClass('selected');
        $(this).addClass('selected');
        selectedDate = $(this).data('date');
        loadAvailableSlots(selectedTherapist.id, selectedService.id, selectedDate);
        showStep(4);
    });

    $(document).on('click', '.rezerwacje-time-slot:not(.disabled)', function () {
        $('.rezerwacje-time-slot').removeClass('selected');
        $(this).addClass('selected');
        selectedTime = {
            start: $(this).data('start'),
            end: $(this).data('end'),
            display: $(this).text().trim()
        };

        $('#summary-therapist').text(selectedTherapist.name);
        $('#summary-service').text(selectedService.name);
        $('#summary-date').text(formatDate(selectedDate));
        $('#summary-time').text(selectedTime.display);
        $('#summary-price').text(selectedService.price);

        showStep(5);
    });

    $('#rezerwacje-booking-form').on('submit', function (e) {
        e.preventDefault();

        var $submitButton = $(this).find('button[type="submit"]');
        $submitButton.prop('disabled', true).text('Rezerwowanie...');


        var formData = {
            action: 'rezerwacje_create_booking',
            nonce: rezerwacjeFrontend.nonce,
            therapist_id: selectedTherapist.id,
            service_id: selectedService.id,
            booking_date: selectedDate,
            start_time: selectedTime.start,
            end_time: selectedTime.end,
            patient_name: $('#patient_name').val(),
            patient_email: $('#patient_email').val(),
            patient_phone: $('#patient_phone').val(),
            notes: $('#notes').val()
        };

        $.post(rezerwacjeFrontend.ajax_url, formData, function (response) {
            if (response.success) {
                showStep(6);
            } else {
                alert('Błąd: ' + response.data);
                $submitButton.prop('disabled', false).text('Zarezerwuj');
            }
        }).fail(function () {
            alert('Wystąpił błąd serwera. Spróbuj ponownie.');
            $submitButton.prop('disabled', false).text('Zarezerwuj');
        });
    });

    $('#rezerwacje-btn-back').on('click', function () {
        if (currentStep > 1) {
            if (currentStep === 4) { // Powrót z wyboru godziny do kalendarza
                selectedDate = null; // Resetuj datę
                renderCalendar(currentMonth, currentYear); // Odśwież kalendarz bez zaznaczenia
            }
            showStep(currentStep - 1);
        }
    });

    function formatDate(dateStr) {
        var date = new Date(dateStr);
        var day = String(date.getDate()).padStart(2, '0');
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var year = date.getFullYear();
        return day + '.' + month + '.' + year;
    }

    loadTherapists();
});
