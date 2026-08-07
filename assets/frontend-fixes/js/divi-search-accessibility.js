document.addEventListener('DOMContentLoaded', function () {
    const searchForms = document.querySelectorAll('form.et_pb_searchform');

    searchForms.forEach(function (form, index) {
        const searchInput = form.querySelector('input.et_pb_s[type="text"]');

        if (!searchInput) {
            return;
        }

        const inputId = 'divi-search-input-' + (index + 1);
        searchInput.id = inputId;

        let label = form.querySelector('label.screen-reader-text');

        if (!label) {
            label = document.createElement('label');
            label.className = 'screen-reader-text';
            label.textContent = 'Search the library website:';
            searchInput.parentNode.insertBefore(label, searchInput);
        }

        label.setAttribute('for', inputId);
    });
});