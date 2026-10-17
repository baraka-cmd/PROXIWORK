document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('a[data-nav-link]').forEach((link) => {
        const current = new URL(window.location.href);
        const target = new URL(link.href, window.location.origin);

        if (target.pathname === current.pathname && target.search === current.search) {
            link.setAttribute('aria-current', 'page');
        }
    });

    const fileInput = document.querySelector('[data-delivery-files]');

    if (fileInput instanceof HTMLInputElement) {
        const fileList = document.querySelector('[data-delivery-file-list]');
        const errorMessage = document.querySelector('[data-delivery-file-error]');
        const allowedExtensions = new Set(['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'zip']);
        const maxFiles = 5;
        const maxFileSize = 10 * 1024 * 1024;

        const renderFiles = () => {
            const files = Array.from(fileInput.files ?? []);
            const issues = [];

            if (fileList) fileList.replaceChildren();

            if (files.length > maxFiles) {
                issues.push('Sélectionnez au maximum 5 fichiers.');
            }

            files.forEach((file) => {
                const extension = file.name.includes('.') ? file.name.split('.').pop().toLowerCase() : '';

                if (!allowedExtensions.has(extension)) {
                    issues.push(`« ${file.name} » : format non accepté.`);
                }

                if (file.size > maxFileSize) {
                    issues.push(`« ${file.name} » dépasse la limite de 10 Mo.`);
                }

                if (fileList) {
                    const item = document.createElement('li');
                    const details = document.createElement('div');
                    const name = document.createElement('strong');
                    const size = document.createElement('small');

                    name.textContent = file.name;
                    size.textContent = `${(file.size / (1024 * 1024)).toLocaleString('fr-FR', { maximumFractionDigits: 2 })} Mo`;
                    details.append(name, size);
                    item.append(details);

                    if (!allowedExtensions.has(extension) || file.size > maxFileSize) {
                        item.dataset.fileError = 'true';
                        const warning = document.createElement('small');
                        warning.textContent = !allowedExtensions.has(extension) && file.size > maxFileSize
                            ? 'Format non accepté et fichier trop volumineux.'
                            : !allowedExtensions.has(extension)
                                ? 'Format non accepté.'
                                : 'Fichier supérieur à 10 Mo.';
                        item.append(warning);
                    }

                    fileList.append(item);
                }
            });

            const message = [...new Set(issues)].join(' ');
            fileInput.setCustomValidity(message);

            if (errorMessage instanceof HTMLElement) {
                errorMessage.textContent = message;
                errorMessage.hidden = message.length === 0;
            }
        };

        fileInput.addEventListener('change', renderFiles);
        renderFiles();
    }

    document.querySelectorAll('[data-char-count-for]').forEach((counter) => {
        const fieldId = counter.getAttribute('data-char-count-for');
        const field = fieldId ? document.getElementById(fieldId) : null;

        if (!(field instanceof HTMLTextAreaElement)) return;

        const updateCount = () => {
            const max = field.maxLength > 0 ? field.maxLength : null;
            counter.textContent = max === null
                ? `${field.value.length} caractères`
                : `${field.value.length} / ${max} caractères`;
        };

        field.addEventListener('input', updateCount);
        updateCount();
    });
});
