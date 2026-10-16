const cover = document.querySelector('[data-service-media]');
const mainImage = document.querySelector('[data-service-gallery-main]');
const placeholder = document.querySelector('[data-service-image-placeholder]');
const thumbnails = [...document.querySelectorAll('[data-service-gallery-thumb]')];

if (cover && mainImage) {
    const showImage = () => {
        if (mainImage.naturalWidth > 0) {
            mainImage.hidden = false;
            cover.classList.add('has-image');
        } else {
            mainImage.hidden = true;
            cover.classList.remove('has-image');
        }
    };

    mainImage.addEventListener('load', showImage);
    mainImage.addEventListener('error', showImage);

    if (mainImage.complete) {
        showImage();
    }

    thumbnails.forEach((button) => {
        const thumbnailImage = button.querySelector('img');

        if (thumbnailImage) {
            thumbnailImage.addEventListener('error', () => {
                button.hidden = true;
            }, { once: true });
        }

        button.addEventListener('click', () => {
            const source = button.dataset.imageSrc;
            const alt = button.dataset.imageAlt || '';

            if (!source || source === mainImage.getAttribute('src')) {
                return;
            }

            mainImage.hidden = true;
            cover.classList.remove('has-image');
            mainImage.src = source;
            mainImage.alt = alt;

            thumbnails.forEach((item) => {
                item.setAttribute('aria-pressed', item === button ? 'true' : 'false');
            });
        });
    });
}

document.querySelectorAll('.public-service-page__realization img').forEach((image) => {
    image.addEventListener('error', () => {
        const card = image.closest('.public-service-page__realization');
        const section = image.closest('[aria-labelledby="service-realizations-title"]');

        if (card) {
            card.hidden = true;
        }

        if (section && !section.querySelector('.public-service-page__realization:not([hidden])')) {
            section.hidden = true;
        }
    }, { once: true });
});

document.querySelectorAll('[data-service-avatar-image]').forEach((image) => {
    const avatar = image.closest('[data-service-avatar]');

    if (!avatar) {
        return;
    }

    const showAvatar = () => {
        if (image.naturalWidth > 0) {
            image.hidden = false;
            avatar.classList.add('has-image');
        } else {
            image.hidden = true;
            avatar.classList.remove('has-image');
        }
    };

    image.addEventListener('load', showAvatar, { once: true });
    image.addEventListener('error', showAvatar, { once: true });

    if (image.complete) {
        showAvatar();
    }
});
