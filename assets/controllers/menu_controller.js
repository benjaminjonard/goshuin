import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'panel'];

    connect() {
        this.onDocument = (event) => {
            if (!this.element.contains(event.target)) {
                this.close();
            }
        };
        this.onKey = (event) => {
            if (event.key === 'Escape') {
                this.close();
                this.buttonTarget.focus();
            }
        };
        document.addEventListener('click', this.onDocument);
        document.addEventListener('keydown', this.onKey);
    }

    disconnect() {
        document.removeEventListener('click', this.onDocument);
        document.removeEventListener('keydown', this.onKey);
    }

    toggle() {
        this.panelTarget.classList.contains('hidden') ? this.open() : this.close();
    }

    open() {
        this.panelTarget.classList.remove('hidden');
        this.buttonTarget.setAttribute('aria-expanded', 'true');
    }

    close() {
        this.panelTarget.classList.add('hidden');
        this.buttonTarget.setAttribute('aria-expanded', 'false');
    }
}
