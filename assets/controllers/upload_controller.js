import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'zone', 'preview', 'placeholder', 'hint', 'replace'];

    chosen() {
        const file = this.inputTarget.files[0];

        if (!file) {
            return;
        }

        this.show(file);
    }

    show(file) {
        if (!this.hasPreviewTarget || !file.type.startsWith('image/')) {
            return;
        }

        this.release();
        this.url = URL.createObjectURL(file);
        this.previewTarget.src = this.url;
        this.previewTarget.classList.remove('hidden');

        if (this.hasPlaceholderTarget) {
            this.placeholderTarget.classList.add('hidden');
        }

        if (this.hasHintTarget) {
            this.hintTarget.classList.add('hidden');
        }

        if (this.hasReplaceTarget) {
            this.replaceTarget.classList.remove('hidden');
        }

        if (this.hasZoneTarget) {
            this.zoneTarget.classList.replace('border-dashed', 'border-solid');
        }
    }

    disconnect() {
        this.release();
    }

    release() {
        if (this.url) {
            URL.revokeObjectURL(this.url);
            this.url = null;
        }
    }
}
