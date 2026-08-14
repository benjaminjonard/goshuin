import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['panel'];

    connect() {
        this.onDocument = (event) => {
            if (!this.element.contains(event.target)) {
                this.close();
            }
        };
        this.onKey = (event) => {
            if (event.key === 'Escape') {
                this.close();
            }
        };
        this.onInput = () => this.open();

        document.addEventListener('click', this.onDocument);
        document.addEventListener('keydown', this.onKey);
        this.element.addEventListener('input', this.onInput);
    }

    disconnect() {
        document.removeEventListener('click', this.onDocument);
        document.removeEventListener('keydown', this.onKey);
        this.element.removeEventListener('input', this.onInput);
    }

    close() {
        this.panelTargets.forEach((panel) => panel.classList.add('hidden'));
    }

    open() {
        this.panelTargets.forEach((panel) => panel.classList.remove('hidden'));
    }
}
