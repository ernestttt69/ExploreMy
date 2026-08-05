document.addEventListener('DOMContentLoaded', () => {
    const options = document.querySelectorAll(
        '.preference-option'
    );

    options.forEach(option => {
        const radio = option.querySelector(
            'input[type="radio"]'
        );

        radio.addEventListener('change', () => {
            options.forEach(item => {
                item.classList.remove('selected');
            });

            option.classList.add('selected');
        });
    });
});