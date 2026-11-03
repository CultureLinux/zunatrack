import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['label'];

    connect() {
        this.apply(localStorage.getItem('zunatrack-theme') || 'system');
    }

    toggle() {
        const current = document.documentElement.dataset.theme || 'system';
        this.apply(current === 'dark' ? 'light' : current === 'light' ? 'system' : 'dark');
    }

    apply(theme) {
        if (theme === 'system') document.documentElement.removeAttribute('data-theme');
        else document.documentElement.dataset.theme = theme;
        localStorage.setItem('zunatrack-theme', theme);
        if (this.hasLabelTarget) this.labelTarget.textContent = theme === 'system' ? 'Système' : theme === 'dark' ? 'Sombre' : 'Clair';
    }
}
