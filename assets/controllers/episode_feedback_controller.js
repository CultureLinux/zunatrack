import { Controller } from '@hotwired/stimulus';
import { gsap } from 'gsap';

const storageKey = 'zunatrack:episode-feedback';

export default class extends Controller {
    connect() {
        this.element.addEventListener('click', this.rememberEpisode, true);
        const feedback = this.consumeEpisode();
        const url = new URL(window.location.href);
        const seasonCompleted = url.searchParams.get('season_completed') === '1';
        if (seasonCompleted) {
            url.searchParams.delete('season_completed');
            history.replaceState({}, '', url);
        }
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            if (seasonCompleted) window.scrollTo(0, 0);
            else this.focusNextUnwatchedEpisode(true);
            return;
        }

        const episode = feedback?.episodeId && document.getElementById(feedback.episodeId);
        if (seasonCompleted) {
            this.scrollToSeasonCompletion();
            return;
        }
        if (!episode) {
            this.focusNextUnwatchedEpisode();
            return;
        }

        this.animation = gsap.fromTo(episode, { opacity: 0.6, scale: 0.985, y: 8 }, { opacity: 1, scale: 1, y: 0, duration: 0.32, ease: 'power2.out', clearProps: 'transform,opacity' });
    }

    disconnect() {
        this.element.removeEventListener('click', this.rememberEpisode, true);
        this.animation?.kill();
    }

    rememberEpisode = (event) => {
        const button = event.target.closest('.episode-toggle button');
        const episodeId = button?.closest('.episode-card')?.id;
        if (!episodeId) return;
        sessionStorage.setItem(storageKey, JSON.stringify({ episodeId }));
    };

    consumeEpisode() {
        const feedback = sessionStorage.getItem(storageKey);
        sessionStorage.removeItem(storageKey);
        return feedback ? JSON.parse(feedback) : null;
    }

    scrollToSeasonCompletion() {
        const completion = this.element.querySelector('.season-completion, .series-completion');
        if (!completion) return;

        const scroll = { y: window.scrollY };
        this.animation = gsap.timeline()
            .to(scroll, { y: 0, duration: 0.96, ease: 'power2.out', onUpdate: () => window.scrollTo(0, scroll.y) })
            .fromTo(completion, { opacity: 0, scale: 0.65 }, { opacity: 1, scale: 1, duration: 0.56, ease: 'back.out(2)', clearProps: 'transform,opacity' });
    }

    focusNextUnwatchedEpisode(immediate = false) {
        const episode = this.element.querySelector('[data-next-unwatched]');
        if (!episode) return;

        const scroll = { y: window.scrollY };
        const target = episode.getBoundingClientRect().top + window.scrollY - (window.innerHeight - episode.offsetHeight) / 2;
        if (immediate) {
            window.scrollTo(0, Math.max(0, target));
            return;
        }
        this.animation = gsap.to(scroll, { y: Math.max(0, target), duration: 0.5, ease: 'power2.out', onUpdate: () => window.scrollTo(0, scroll.y) });
    }
}
