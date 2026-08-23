document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('.header-wrapper');
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
