import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['select', 'swatch'];

    connect() { this.preview(); }

    preview() {
        const accent = this.selectTarget.value;
        this.swatchTarget.dataset.accent = accent;
        document.documentElement.dataset.accent = accent;
    }
}
