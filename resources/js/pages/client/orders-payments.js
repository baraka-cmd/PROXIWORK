const submittingForms = new WeakSet();

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || event.defaultPrevented || !form.checkValidity()) {
        return;
    }

    if (submittingForms.has(form)) {
        event.preventDefault();
        return;
    }

    submittingForms.add(form);

    const button = event.submitter;

    if (!(button instanceof HTMLButtonElement)) {
        return;
    }

    button.disabled = true;
    button.setAttribute('aria-busy', 'true');

    const spinner = document.createElement('i');
    spinner.className = 'fa-solid fa-spinner fa-spin';
    spinner.setAttribute('aria-hidden', 'true');
    button.replaceChildren(spinner, document.createTextNode(' Traitement…'));
});
document.addEventListener('click',(event)=>{const button=event.target instanceof Element?event.target.closest('[data-print-receipt]'):null;if(button)window.print();});
