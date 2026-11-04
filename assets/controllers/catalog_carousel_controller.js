import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['track'];

    connect() {
        this.paused = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.timeout = window.setTimeout(() => this.start(), 5000);
    }

    disconnect() { window.clearTimeout(this.timeout); window.clearInterval(this.interval); }
    next() { this.scroll(1); }
    previous() { this.scroll(-1); }
    pause() { this.paused = true; }
    resume() { this.paused = false; }
    start() { this.interval = window.setInterval(() => { if (!this.paused) this.scroll(1); }, 4500); }
    scroll(direction) {
        const track = this.trackTarget;
        const amount = Math.max(track.clientWidth * .65, 220);
        if (direction > 0 && track.scrollLeft + track.clientWidth >= track.scrollWidth - 2) track.scrollTo({ left: 0, behavior: 'smooth' });
        else track.scrollBy({ left: direction * amount, behavior: 'smooth' });
    }
}
