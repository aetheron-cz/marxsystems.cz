(() => {
    const form = document.querySelector('[data-contact-form]');
    if (!form) return;
    const status = form.querySelector('[data-form-status]');
    const button = form.querySelector('button[type="submit"]');
    let sending = false;
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (sending || !form.reportValidity()) return;
        sending = true;
        button.disabled = true;
        form.setAttribute('aria-busy', 'true');
        status.textContent = 'Odesílání zprávy…';
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const result = await response.json();
            if (!response.ok || result.ok !== true) {
                throw new Error(result.message || 'Zprávu se nepodařilo odeslat. Zkuste to prosím později.');
            }
            status.textContent = result.message;
            form.reset();
        } catch (error) {
            status.textContent = error instanceof SyntaxError || error instanceof TypeError
                ? 'Odeslání se nepodařilo potvrdit. Zkuste to později nebo nás kontaktujte e-mailem.'
                : error.message;
        } finally {
            sending = false;
            button.disabled = false;
            form.removeAttribute('aria-busy');
        }
    });
})();
