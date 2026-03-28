document.addEventListener('DOMContentLoaded', function () {
    var senderSearch = document.querySelector('.smsviewer-sender-search input[name="sender_search"]');
    var messageSearch = document.querySelector('.smsviewer-message-filters input[name="q"]');
    var deleteForm = document.getElementById('smsviewer-delete-form');

    if (senderSearch) {
        senderSearch.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.target.form.submit();
            }
        });
    }

    if (messageSearch) {
        messageSearch.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.target.form.submit();
            }
        });
    }

    if (deleteForm) {
        deleteForm.addEventListener('submit', function (event) {
            var checked = deleteForm.querySelectorAll('input[name="delete_ids[]"]:checked');

            if (!checked.length) {
                event.preventDefault();
                window.alert('Select at least one message to delete.');
            }
        });
    }
});