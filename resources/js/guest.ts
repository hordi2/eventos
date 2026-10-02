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

/**
 * Lecture en feuillets : la barre de progression en haut de l'écran, et le
 * repère du feuillet qu'on est en train de lire. Tout est facultatif — sans
 * JavaScript, la page se lit exactement pareil, feuillet après feuillet.
 */
const sheets = document.querySelectorAll<HTMLElement>('[id^="feuillet-"]');
const progress = document.querySelector<HTMLElement>('.itaza-progress');
const marks = document.querySelectorAll<HTMLAnchorElement>('.itaza-nav a');

if (sheets.length > 0 && (progress !== null || marks.length > 0)) {
    const updateProgress = () => {
        if (progress === null) {
            return;
        }

        const scrollable = document.documentElement.scrollHeight - window.innerHeight;
        const ratio = scrollable > 0 ? window.scrollY / scrollable : 0;
        progress.style.setProperty('--itaza-progress', String(Math.min(1, Math.max(0, ratio))));
    };

    updateProgress();
    window.addEventListener('scroll', updateProgress, { passive: true });
    window.addEventListener('resize', updateProgress, { passive: true });

    if (marks.length > 0 && 'IntersectionObserver' in window) {
        const current = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) {
                        return;
                    }

                    const folio = Number(entry.target.id.replace('feuillet-', ''));
                    marks.forEach((mark, index) => mark.classList.toggle('itaza-nav-on', index + 1 === folio));
                });
            },
            { threshold: 0.5 },
        );

        sheets.forEach((sheet) => current.observe(sheet));
    }
}
