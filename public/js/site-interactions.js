document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('.header-wrapper');
    // Existing page spacing allows for a 112px fixed header. Add only its growth.
    if (header) {
        const resizeHeader = () => document.body.style.setProperty('--nav-extra-height', `${Math.max(0, header.getBoundingClientRect().height - 112)}px`);
        resizeHeader();
        if ('ResizeObserver' in window) new ResizeObserver(resizeHeader).observe(header);
        else window.addEventListener('resize', resizeHeader);
    }
    const updateHeader = () => header?.classList.toggle('is-scrolled', window.scrollY > 12);
    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });

    const targets = document.querySelectorAll('.dashboard-section, .story-section, .culture-section, .culture-band, .highlights, .travel-essentials, .about-cta, .transport-grid, .transport-note');
    targets.forEach((element) => element.classList.add('reveal-on-scroll'));

    if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        targets.forEach((element) => element.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });
    targets.forEach((element) => observer.observe(element));
});
