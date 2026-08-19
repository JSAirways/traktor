/**
 * Shared PIN input behavior for modal-based 4-digit flows.
 */

export function initializePinInput({
    input,
    onComplete,
    onReset = null,
    pinLength = 4,
}) {
    if (!input || typeof onComplete !== 'function') {
        return {
            reset() {},
        };
    }

    const reset = () => {
        input.value = '';
        input.disabled = false;
        input.classList.remove('is-invalid');
        if (typeof onReset === 'function') {
            onReset();
        }
    };

    input.addEventListener('input', (event) => {
        const nextValue = event.target.value.replace(/[^0-9]/g, '').slice(0, pinLength);
        event.target.value = nextValue;
        event.target.classList.remove('is-invalid');

        if (nextValue.length === pinLength) {
            onComplete(nextValue);
        }
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            const currentValue = input.value.replace(/[^0-9]/g, '').slice(0, pinLength);
            if (currentValue.length === pinLength) {
                onComplete(currentValue);
            }
        }
    });

    return { reset };
}
