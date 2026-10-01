import Alpine from 'alpinejs';

declare global {
    interface Window {
        Alpine: typeof Alpine;
    }
}

window.Alpine = Alpine;
Alpine.start();

/**
 * Révélation des sections au défilement. Le masquage n'est posé qu'ici :
 * sans JavaScript, ou si l'invité demande moins de mouvement, la page
 * s'affiche entière et immobile — jamais de contenu invisible en attente
 * d'un script.
 */
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

if (!reducedMotion && 'IntersectionObserver' in window) {
    const sections = document.querySelectorAll<HTMLElement>('.itaza-section');

    if (sections.length > 0) {
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('itaza-seen');
                        observer.unobserve(entry.target);
                    }
                });
            },
            { rootMargin: '0px 0px -12% 0px', threshold: 0.08 },
        );

        sections.forEach((section) => {
            section.classList.add('itaza-watch');
            observer.observe(section);
        });
    }
}
