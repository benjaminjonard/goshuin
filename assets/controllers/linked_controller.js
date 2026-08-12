import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.enter = (event) => this.mark(event, true);
        this.leave = (event) => this.mark(event, false);

        ['mouseover', 'focusin'].forEach((name) => this.element.addEventListener(name, this.enter));
        ['mouseout', 'focusout'].forEach((name) => this.element.addEventListener(name, this.leave));
    }

    disconnect() {
        ['mouseover', 'focusin'].forEach((name) => this.element.removeEventListener(name, this.enter));
        ['mouseout', 'focusout'].forEach((name) => this.element.removeEventListener(name, this.leave));
    }

    mark(event, on) {
        const source = event.target.closest?.('[data-index]');

        if (!source) {
            return;
        }

        this.element.querySelectorAll(`[data-index="${source.dataset.index}"]`).forEach((node) => {
            node.classList.toggle('lit', on);
        });
    }
}
