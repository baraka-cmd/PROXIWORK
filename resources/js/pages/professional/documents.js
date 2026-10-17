const documentForms = document.querySelectorAll('[data-professional-document-form]');
const fileInput = document.querySelector('#professional-document-file');
const fileStatus = document.querySelector('[data-document-file-status]');

if (fileInput && fileStatus) {
    fileInput.addEventListener('change', () => {
        const file = fileInput.files?.[0];
        fileStatus.classList.remove('is-error');
        if (!file) {
            fileStatus.textContent = 'Aucun fichier sélectionné.';
            return;
        }

        const sizeMb = (file.size / (1024 * 1024)).toLocaleString(document.documentElement.lang || 'fr-FR', { maximumFractionDigits: 2 });
        const allowed = /\.(pdf|jpe?g|png|webp)$/i.test(file.name);
        const tooLarge = file.size > 10 * 1024 * 1024;
        if (!allowed || tooLarge) {
            fileStatus.classList.add('is-error');
            fileStatus.textContent = tooLarge
                ? `Le fichier fait ${sizeMb} Mo. La limite est de 10 Mo.`
                : 'Le nom du fichier doit se terminer par PDF, JPG, JPEG, PNG ou WebP. Le serveur vérifiera également le type réel.';
            return;
        }
        fileStatus.textContent = `${file.name} · ${sizeMb} Mo. Le document reste privé et sera transmis après validation du formulaire.`;
    });
}

documentForms.forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (form.dataset.confirmDelete === 'true' && !window.confirm('Supprimer ce document ? Cette action retire le justificatif de votre dossier.')) {
            event.preventDefault();
            return;
        }

        const button = form.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            const label = button.querySelector('[data-submit-label]');
            if (label) label.textContent = 'Transmission en cours…';
        }
    });
});
