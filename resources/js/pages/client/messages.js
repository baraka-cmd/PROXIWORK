const thread = document.querySelector('[data-message-thread][data-updates-url]');

if (thread) {
    let lastMessageId = Number.parseInt(thread.dataset.lastMessageId || '0', 10) || 0;
    const currentUserId = String(thread.dataset.userId || '');
    const pollingEnabled = thread.dataset.pollingEnabled !== 'false';
    const syncStatus = document.querySelector('[data-message-sync-status]');
    const scrollLatestButton = thread.querySelector('[data-scroll-latest]');
    const messageAnnouncement = document.querySelector('[data-message-announcement]');
    let pollInProgress = false;
    let pollingStopped = false;
    let pollInterval = null;

    const updateStatus = (message) => {
        if (syncStatus) {
            syncStatus.textContent = message;
        }
    };

    const updateReadReceipts = (readMarker) => {
        if (readMarker === null || readMarker === undefined) {
            return;
        }

        thread.querySelectorAll('.message-bubble.is-mine[data-message-id]').forEach((article) => {
            if (Number(article.dataset.messageId) > Number(readMarker)) {
                return;
            }

            const state = article.querySelector('.message-delivery-state');

            if (!state || state.textContent.includes('Lu')) {
                return;
            }

            const icon = document.createElement('i');
            icon.className = 'fa-solid fa-check-double';
            icon.setAttribute('aria-hidden', 'true');
            state.replaceChildren(icon, document.createTextNode(' Lu'));
        });
    };

    const formatDate = (value) => {
        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return '';
        }

        return new Intl.DateTimeFormat('fr-FR', {
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
    };

    const appendMessage = (message) => {
        if (thread.querySelector(`[data-message-id="${message.id}"]`)) {
            return;
        }

        thread.querySelector('[data-empty-thread]')?.remove();

        const article = document.createElement('article');
        const mine = String(message.sender?.id ?? '') === currentUserId;
        const messageDate = new Date(message.created_at);
        if (!Number.isNaN(messageDate.getTime())) {
            const dayKey = `${messageDate.getFullYear()}-${String(messageDate.getMonth() + 1).padStart(2, '0')}-${String(messageDate.getDate()).padStart(2, '0')}`;
            const dayDividers = Array.from(thread.querySelectorAll('[data-message-day]'));
            const lastDayKey = dayDividers.length > 0 ? dayDividers[dayDividers.length - 1].dataset.messageDay : null;

            if (lastDayKey !== dayKey) {
                const divider = document.createElement('div');
                divider.className = 'message-day-divider';
                divider.dataset.messageDay = dayKey;
                const label = document.createElement('span');
                const today = new Date();
                const yesterday = new Date(today);
                yesterday.setDate(today.getDate() - 1);
                const isToday = today.getFullYear() === messageDate.getFullYear()
                    && today.getMonth() === messageDate.getMonth()
                    && today.getDate() === messageDate.getDate();
                const isYesterday = yesterday.getFullYear() === messageDate.getFullYear()
                    && yesterday.getMonth() === messageDate.getMonth()
                    && yesterday.getDate() === messageDate.getDate();
                label.textContent = isToday
                    ? 'Aujourd’hui'
                    : (isYesterday ? 'Hier' : messageDate.toLocaleDateString('fr-FR', {
                        weekday: 'long',
                        day: '2-digit',
                        month: 'long',
                        year: 'numeric',
                    }));
                divider.append(label);
                thread.insertBefore(divider, syncStatus);
            }
        }
        article.className = `message-bubble ${mine ? 'is-mine' : ''}`;
        article.dataset.messageId = String(message.id);

        const meta = document.createElement('div');
        meta.className = 'message-bubble__meta';

        const sender = document.createElement('strong');
        sender.textContent = mine ? 'Vous' : (message.sender?.name || 'Participant');

        const time = document.createElement('time');
        time.dateTime = message.created_at || '';
        time.textContent = formatDate(message.created_at);

        meta.append(sender, time);
        article.append(meta);

        if (message.reply_to) {
            const reply = document.createElement('blockquote');
            reply.className = 'message-reply-preview';
            const replyTitle = document.createElement('strong');
            replyTitle.textContent = `Réponse à ${message.reply_to.sender_name || 'Participant'}`;
            const replyBody = document.createElement('p');
            replyBody.textContent = message.reply_to.body || 'Pièce jointe';
            reply.append(replyTitle, replyBody);
            article.append(reply);
        }

        if (typeof message.body === 'string' && message.body.trim() !== '') {
            const body = document.createElement('p');
            body.className = 'message-bubble__body';
            body.textContent = message.body;
            article.append(body);
        }

        if (Array.isArray(message.attachments) && message.attachments.length > 0) {
            const attachments = document.createElement('div');
            attachments.className = 'message-attachments';

            message.attachments.forEach((attachment) => {
                if (typeof attachment.download_url !== 'string') {
                    return;
                }

                const target = new URL(attachment.download_url, window.location.origin);

                if (target.origin !== window.location.origin) {
                    return;
                }

                const link = document.createElement('a');
                link.className = 'message-attachment';
                link.href = target.href;

                const icon = document.createElement('i');
                icon.className = 'fa-solid fa-paperclip';
                icon.setAttribute('aria-hidden', 'true');

                const name = document.createElement('span');
                name.textContent = attachment.name || 'Pièce jointe';

                const size = document.createElement('small');
                size.textContent = `${Math.ceil((Number(attachment.size) || 0) / 1024)} Ko`;

                link.append(icon, name, size);
                attachments.append(link);
            });

            if (attachments.childElementCount > 0) {
                article.append(attachments);
            }
        }

        const replyButton = document.createElement('button');
        replyButton.className = 'message-reply-button';
        replyButton.type = 'button';
        replyButton.dataset.replyTo = String(message.id);
        replyButton.dataset.replyBody = (message.body || 'Pièce jointe').slice(0, 160);
        replyButton.dataset.replySender = message.sender?.name || (mine ? 'Vous' : 'Participant');
        const replyIcon = document.createElement('i');
        replyIcon.className = 'fa-solid fa-reply';
        replyIcon.setAttribute('aria-hidden', 'true');
        replyButton.append(replyIcon, document.createTextNode(' Répondre'));
        article.append(replyButton);

        if (!mine && typeof message.report_url === 'string') {
            const reportTarget = new URL(message.report_url, window.location.origin);
            if (reportTarget.origin === window.location.origin) {
                const details = document.createElement('details');
                details.className = 'message-report-control';
                const summary = document.createElement('summary');
                summary.textContent = 'Signaler';
                const reportForm = document.createElement('form');
                reportForm.method = 'POST';
                reportForm.action = reportTarget.href;
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
                if (csrf) {
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_token';
                    csrfInput.value = csrf;
                    reportForm.append(csrfInput);
                }
                const reasonLabel = document.createElement('label');
                reasonLabel.textContent = 'Motif du signalement';
                const reason = document.createElement('select');
                reason.name = 'reason_code';
                reason.required = true;
                reason.id = 'message-report-reason-' + message.id;
                reasonLabel.htmlFor = reason.id;
                [
                    ['SPAM', 'Spam'],
                    ['FRAUD', 'Fraude'],
                    ['HARASSMENT', 'Harcèlement'],
                    ['ABUSIVE_CONTENT', 'Contenu abusif'],
                    ['INAPPROPRIATE_CONTENT', 'Contenu inapproprié'],
                    ['FAKE_PROFILE', 'Faux profil'],
                    ['FAKE_SERVICE', 'Faux service'],
                    ['SCAM', 'Arnaque'],
                    ['COPYRIGHT', 'Droits d’auteur'],
                    ['OTHER', 'Autre'],
                ].forEach(([value, label]) => {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = label;
                    reason.append(option);
                });
                const descriptionLabel = document.createElement('label');
                descriptionLabel.textContent = 'Détails (facultatif)';
                const description = document.createElement('textarea');
                description.name = 'description';
                description.id = 'message-report-description-' + message.id;
                descriptionLabel.htmlFor = description.id;
                description.rows = 2;
                description.maxLength = 5000;
                description.placeholder = 'Décrivez le problème sans données personnelles inutiles.';
                const submit = document.createElement('button');
                submit.className = 'btn btn-secondary';
                submit.type = 'submit';
                submit.textContent = 'Envoyer le signalement';
                reportForm.append(reasonLabel, reason, descriptionLabel, description, submit);
                details.append(summary, reportForm);
                article.append(details);
            }
        }

        if (mine) {
            const state = document.createElement('span');
            state.className = 'message-delivery-state';
            const icon = document.createElement('i');
            icon.className = 'fa-solid fa-check';
            icon.setAttribute('aria-hidden', 'true');
            state.append(icon, document.createTextNode(' Envoyé'));
            article.append(state);
        }

        thread.insertBefore(article, syncStatus);
        lastMessageId = Math.max(lastMessageId, Number(message.id) || 0);
        thread.dataset.lastMessageId = String(lastMessageId);
    };

    const poll = async () => {
        if (pollInProgress || pollingStopped || document.visibilityState !== 'visible') {
            return;
        }

        pollInProgress = true;

        try {
            const url = new URL(thread.dataset.updatesUrl, window.location.origin);
            url.searchParams.set('after_message_id', String(lastMessageId));
            url.searchParams.set('visible', '1');

            const response = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });

            if (response.status === 401 || response.status === 419) {
                pollingStopped = true;
                if (pollInterval !== null) window.clearInterval(pollInterval);
                updateStatus('Votre session a expiré. Reconnectez-vous pour continuer.');
                return;
            }

            if (!response.ok) {
                updateStatus('Synchronisation momentanément indisponible. Nouvelle tentative automatique…');
                return;
            }

            const payload = await response.json();

            if (!Array.isArray(payload.data)) {
                updateStatus('Réponse de synchronisation invalide.');
                return;
            }

            const wasNearBottom = thread.scrollHeight - thread.scrollTop - thread.clientHeight < 100;
            const newMessages = payload.data.filter((message) => !thread.querySelector('[data-message-id="' + message.id + '"]'));
            const incomingCount = newMessages.filter((message) => String(message.sender?.id ?? '') !== currentUserId).length;

            payload.data.forEach(appendMessage);
            updateReadReceipts(payload.meta?.recipient_last_read_message_id);
            lastMessageId = Math.max(lastMessageId, Number(payload.meta?.last_message_id) || 0);
            thread.dataset.lastMessageId = String(lastMessageId);
            updateStatus(`Synchronisé à ${new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' }).format(new Date())}`);

            if (newMessages.length > 0) {
                if (wasNearBottom) {
                    thread.scrollTop = thread.scrollHeight;
                    if (scrollLatestButton) scrollLatestButton.hidden = true;
                } else if (scrollLatestButton) {
                    scrollLatestButton.hidden = false;
                }

                if (incomingCount > 0 && messageAnnouncement) {
                    messageAnnouncement.textContent = incomingCount === 1
                        ? 'Nouveau message reçu.'
                        : incomingCount + ' nouveaux messages reçus.';
                }
            }
        } catch {
            updateStatus('Connexion interrompue. Les messages enregistrés restent conservés; nouvelle tentative automatique…');
        } finally {
            pollInProgress = false;
        }
    };

    thread.scrollTop = thread.scrollHeight;

    scrollLatestButton?.addEventListener('click', () => {
        thread.scrollTop = thread.scrollHeight;
        scrollLatestButton.hidden = true;
    });

    if (pollingEnabled) {
        updateStatus('Synchronisation active');
        pollInterval = window.setInterval(poll, 8000);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                void poll();
            }
        });
    } else {
        updateStatus('Historique ancien affiché. Revenez aux messages récents pour reprendre la synchronisation.');
    }
}

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    const replyButton = event.target.closest('[data-reply-to]');
    const clearButton = event.target.closest('[data-clear-reply]');
    const replyInput = document.querySelector('[data-reply-input]');
    const replyPreview = document.querySelector('[data-reply-preview]');
    const replyTitle = document.querySelector('[data-reply-preview-title]');
    const replyBody = document.querySelector('[data-reply-preview-body]');

    if (clearButton) {
        if (replyInput) {
            replyInput.value = '';
        }
        if (replyPreview) {
            replyPreview.hidden = true;
        }
        return;
    }

    if (!replyButton || !replyInput || !replyPreview || !replyTitle || !replyBody) {
        return;
    }

    replyInput.value = replyButton.dataset.replyTo || '';
    replyTitle.textContent = `Réponse à ${replyButton.dataset.replySender || 'Participant'}`;
    replyBody.textContent = replyButton.dataset.replyBody || 'Pièce jointe';
    replyPreview.hidden = false;
    document.querySelector('#message-body')?.focus();
});

document.querySelectorAll('[data-message-form]').forEach((form) => {
    const textarea = form.querySelector('textarea[name="body"]');
    const characterCount = form.querySelector('[data-character-count]');
    const attachmentInput = form.querySelector('[data-attachment-input]');
    const attachmentNames = form.querySelector('[data-attachment-names]');
    const sendButton = form.querySelector('[data-send-button], button[type="submit"]');

    const updateCharacterCount = () => {
        if (textarea && characterCount) {
            characterCount.textContent = `${textarea.value.length.toLocaleString('fr-FR')} / 5 000`;
        }
    };

    const updateAttachmentNames = () => {
        if (!attachmentInput || !attachmentNames) {
            return;
        }

        const files = Array.from(attachmentInput.files || []);
        const allowedExtensions = new Set(['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx']);
        const invalidType = files.some((file) => {
            const extension = file.name.includes('.') ? file.name.split('.').pop().toLowerCase() : '';
            return !allowedExtensions.has(extension);
        });

        attachmentNames.textContent = files.length === 0
            ? 'Formats : JPG, PNG, WebP, PDF, DOC ou DOCX · 5 fichiers maximum · 10 Mo par fichier'
            : files.length + ' fichier' + (files.length > 1 ? 's sélectionnés' : ' sélectionné') + ' · ' + files.map((file) => file.name).join(' · ');

        const attachmentList = form.querySelector('[data-attachment-list]');
        if (attachmentList) {
            attachmentList.replaceChildren();
            files.forEach((file, index) => {
                const chip = document.createElement('span');
                chip.className = 'message-attachment-chip';
                const name = document.createElement('span');
                name.className = 'message-attachment-chip__name';
                name.textContent = file.name + ' · ' + (file.size / (1024 * 1024)).toFixed(2).replace('.', ',') + ' Mo';
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.setAttribute('aria-label', 'Retirer le fichier ' + file.name);
                remove.textContent = '×';
                remove.addEventListener('click', () => {
                    if (typeof DataTransfer === 'undefined') return;
                    const transfer = new DataTransfer();
                    Array.from(attachmentInput.files || []).forEach((selectedFile, selectedIndex) => {
                        if (selectedIndex !== index) transfer.items.add(selectedFile);
                    });
                    attachmentInput.files = transfer.files;
                    updateAttachmentNames();
                });
                chip.append(name, remove);
                attachmentList.append(chip);
            });
        }

        attachmentInput.setCustomValidity(
            files.length > 5
                ? 'Vous pouvez joindre au maximum cinq fichiers.'
                : (files.some((file) => file.size > 10 * 1024 * 1024)
                    ? 'Chaque fichier doit faire au maximum 10 Mo.'
                    : (invalidType ? 'Choisissez un fichier JPG, PNG, WebP, PDF, DOC ou DOCX.' : '')),
        );
    };

    textarea?.addEventListener('input', updateCharacterCount);
    attachmentInput?.addEventListener('change', updateAttachmentNames);
    updateCharacterCount();
    updateAttachmentNames();

    form.addEventListener('submit', (event) => {
        const hasText = Boolean(textarea?.value.trim());
        const hasFiles = Boolean(attachmentInput?.files?.length);

        if (!hasText && !hasFiles) {
            event.preventDefault();
            textarea?.setCustomValidity('Écrivez un message ou joignez au moins un fichier.');
            textarea?.reportValidity();
            textarea?.addEventListener('input', () => textarea.setCustomValidity(''), { once: true });
            return;
        }

        if (sendButton) {
            sendButton.disabled = true;
            sendButton.setAttribute('aria-busy', 'true');
            sendButton.dataset.originalText = sendButton.textContent || '';
        }
    });
});
