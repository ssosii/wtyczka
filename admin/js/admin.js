jQuery(document).ready(function($) {
    $('.delete-therapist').on('click', function(e) {
        e.preventDefault();

        if (!confirm('Czy na pewno usunąć tego terapeutę?')) {
            return;
        }

        var id = $(this).data('id');

        $.post(rezerwacjeAdmin.ajax_url, {
            action: 'rezerwacje_delete_therapist',
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
