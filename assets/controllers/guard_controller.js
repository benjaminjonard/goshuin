import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['field'];

    check(event) {
        const missing = this.fieldTargets.filter((field) => !this.filled(field));

        this.fieldTargets.forEach((field) => this.mark(field, missing.includes(field)));

        if (missing.length === 0) {
            return;
        }

        event.preventDefault();
        missing[0].scrollIntoView({ block: 'center', behavior: 'smooth' });
        missing[0].querySelector('input:not([type="hidden"]), textarea')?.focus();
    }

    release() {
        this.fieldTargets.forEach((field) => this.mark(field, false));
    }

    filled(field) {
        return field.querySelector('input[type="hidden"]').value.trim() !== '';
    }

    mark(field, missing) {
        field.querySelector('[data-guard-message]').classList.toggle('hidden', !missing);
    }
}
