import { Controller } from '@hotwired/stimulus';

const THRESHOLD = 2 * 1024 * 1024;

export default class extends Controller {
    static targets = ['input', 'track', 'bar', 'zone', 'preview', 'placeholder', 'hint', 'replace'];

    chosen() {
        const file = this.inputTarget.files[0];

        if (!file) {
            return;
        }

        this.show(file);

        if (file.size > THRESHOLD) {
            this.watch();
        }
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

    watch() {
        const form = this.element.closest('form');

        if (!form || form.dataset.uploadWatched) {
            return;
        }

        form.dataset.uploadWatched = 'true';
        form.addEventListener('submit', (event) => this.send(event, form));
    }

    send(event, form) {
        event.preventDefault();

        const request = new XMLHttpRequest();
        this.trackTarget.classList.remove('hidden');

        request.upload.addEventListener('progress', (progress) => {
            if (progress.lengthComputable) {
                this.barTarget.style.width = `${Math.round((progress.loaded / progress.total) * 100)}%`;
            }
        });

        request.addEventListener('load', () => {
            if (request.responseURL && request.status < 400) {
                window.location.assign(request.responseURL);
                return;
            }

            document.open();
            document.write(request.responseText);
            document.close();
        });

        request.open('POST', form.action || window.location.href);
        request.send(new FormData(form));
    }
}
