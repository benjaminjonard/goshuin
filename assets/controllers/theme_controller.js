import { Controller } from '@hotwired/stimulus';

const KEY = 'goshuin.theme';

export default class extends Controller {
    static targets = ['label'];

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
        document.documentElement.setAttribute('data-theme', this.isDark() ? 'light' : 'dark');
        window.localStorage.setItem(KEY, document.documentElement.getAttribute('data-theme'));
        this.paint();
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
