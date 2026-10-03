// Customer rejection form — show the "Other" reason input when selected
const otherRadio = document.getElementById('reason-other-radio');
const otherInput = document.getElementById('reason-other-input');

document.querySelectorAll('input[name="rejection_reason"]').forEach(radio => {
    radio.addEventListener('change', () => {
        otherInput.classList.toggle('hidden', !otherRadio.checked);
        otherInput.required = otherRadio.checked;
    });
});
