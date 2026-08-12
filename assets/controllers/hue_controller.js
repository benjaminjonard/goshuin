import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'preview', 'value'];

    connect() {
        this.paint();
    }

    paint() {
        const hue = this.inputTarget.value;

        this.previewTarget.style.setProperty('--hue', hue);
        if (this.hasValueTarget) {
            this.valueTarget.textContent = `${hue}°`;
        }
    }
}
