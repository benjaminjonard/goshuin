import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['label'];
    static values = { url: String, token: String };

    connect() {
        this.media = window.matchMedia('(prefers-color-scheme: dark)');
        this.onSystemChange = () => {
            if (!document.documentElement.hasAttribute('data-theme')) {
                this.paint();
            }
        };
        this.media.addEventListener('change', this.onSystemChange);
        this.paint();
    }

    disconnect() {
        this.media.removeEventListener('change', this.onSystemChange);
    }

    toggle() {
        const next = this.isDark() ? 'light' : 'dark';

        document.documentElement.setAttribute('data-theme', next);
        this.paint();
        this.persist(next);
    }

    persist(theme) {
        const body = new FormData();
        body.append('theme', theme);
        body.append('_token', this.tokenValue);

        fetch(this.urlValue, { method: 'POST', body, credentials: 'same-origin' });
    }

    isDark() {
        const chosen = document.documentElement.getAttribute('data-theme');

        return chosen ? chosen === 'dark' : this.media.matches;
    }

    paint() {
        const dark = this.isDark();
        this.element.setAttribute('aria-pressed', dark ? 'true' : 'false');
        if (this.hasLabelTarget) {
            this.labelTarget.textContent = this.labelTarget.dataset[dark ? 'light' : 'dark'];
        }
    }
}
