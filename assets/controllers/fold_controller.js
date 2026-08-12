import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['state'];

    connect() {
        this.refresh();
    }

    refresh() {
        const filled = this.controls().some((control) => this.entered(control));

        this.stateTarget.textContent = filled ? this.stateTarget.dataset.filled : this.stateTarget.dataset.empty;
        this.stateTarget.classList.toggle('fold-state-set', filled);
    }

    controls() {
        return Array.from(this.element.querySelectorAll('input, select, textarea'));
    }

    entered(control) {
        if (control.type === 'radio' || control.type === 'checkbox') {
            return control.checked;
        }

        return control.value.trim() !== '';
    }
}
