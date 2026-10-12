document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-home-carousel]').forEach((carousel) => {
        const slides = [...carousel.querySelectorAll('[data-home-slide]')];
        const dots = [...carousel.querySelectorAll('[data-home-dot]')];
        const previous = carousel.querySelector('[data-home-prev]');
        const next = carousel.querySelector('[data-home-next]');
        const pause = carousel.querySelector('[data-home-pause]');

        if (slides.length < 2) return;

        let current = 0;
        let timer;
        let paused = false;

        const show = (index) => {
            current = (index + slides.length) % slides.length;
            slides.forEach((slide, slideIndex) => {
                const active = slideIndex === current;
                slide.classList.toggle('is-active', active);
                slide.setAttribute('aria-hidden', String(!active));
            });
            dots.forEach((dot, dotIndex) => dot.classList.toggle('is-active', dotIndex === current));
        };

        const schedule = () => {
            window.clearInterval(timer);
            if (!paused && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                timer = window.setInterval(() => show(current + 1), 7000);
            }
        };

        previous?.addEventListener('click', () => { show(current - 1); schedule(); });
        next?.addEventListener('click', () => { show(current + 1); schedule(); });
        dots.forEach((dot) => dot.addEventListener('click', () => { show(Number(dot.dataset.homeDot)); schedule(); }));
        pause?.addEventListener('click', () => {
            paused = !paused;
            pause.setAttribute('aria-label', paused ? 'Relancer le carrousel' : 'Mettre en pause le carrousel');
            pause.innerHTML = paused ? '<i class="fa-solid fa-play" aria-hidden="true"></i>' : '<i class="fa-solid fa-pause" aria-hidden="true"></i>';
            schedule();
        });
        show(0);
        schedule();
    });
});