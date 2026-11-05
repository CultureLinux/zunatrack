import { Controller } from '@hotwired/stimulus';
import { gsap } from 'gsap';

export default class extends Controller {
    connect() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        this.context = gsap.context(() => {
            const counters = this.element.querySelectorAll('[data-stat="watched-episodes"], [data-stat="catch-up"]');
            counters.forEach((counter) => this.countUp(counter));
            gsap.from(this.element.querySelectorAll('.statistics-bar span'), { scaleY: 0, transformOrigin: 'bottom', duration: 0.38, ease: 'power1.out', stagger: 0.035 });
        }, this.element);
    }

    disconnect() {
        this.context?.revert();
    }

    countUp(counter) {
        const value = Number.parseInt(counter.textContent.trim(), 10);
        if (!Number.isFinite(value)) return;

        const state = { value: 0 };
        gsap.to(state, { value, duration: 0.45, ease: 'power1.out', onUpdate: () => { counter.textContent = String(Math.round(state.value)); } });
    }
}
