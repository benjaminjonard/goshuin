import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['frame', 'image'];

    open(event) {
        event.preventDefault();

        const source = event.currentTarget;

        this.imageTarget.src = source.href;
        this.imageTarget.alt = source.querySelector('img')?.alt ?? '';
        this.frameTarget.showModal();
    }

    close() {
        this.frameTarget.close();
    }

    dismiss(event) {
        if (event.target === this.frameTarget) {
            this.close();
        }
    }

    cleared() {
        this.imageTarget.removeAttribute('src');
    }
}
