document.addEventListener('DOMContentLoaded', function () {
    const selects = document.querySelectorAll('select[name^="mapping"]');
    if (selects.length === 0) return;

    const form = selects[0].closest('form');

    function updateDropdowns() {
        const selectedValues = Array.from(selects)
            .map(s => s.value)
            .filter(v => v !== '');

        selects.forEach(select => {
            const currentValue = select.value;
            Array.from(select.options).forEach(option => {
                if (option.value !== '' && option.value !== currentValue) {
                    option.disabled = selectedValues.includes(option.value);
                } else {
                    option.disabled = false;
                }
            });
        });
    }

    selects.forEach(select => {
        select.addEventListener('change', updateDropdowns);
    });

    if (form) {
        form.addEventListener('submit', function (e) {
            const selectedValues = Array.from(selects).map(s => s.value);
            if (!selectedValues.includes('lastName')) {
                e.preventDefault();
                alert('Error: The "Last Name" field is required by Kylas CRM and must be mapped.');
            }
        });
    }

    updateDropdowns(); // Initial run
});
