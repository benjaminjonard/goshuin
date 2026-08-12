import { Controller } from '@hotwired/stimulus';

const THRESHOLD = 2 * 1024 * 1024;

export default class extends Controller {
    static targets = ['input', 'hint', 'track', 'bar'];

    chosen() {
        const file = this.inputTarget.files[0];

        if (!file) {
            return;
        }

        this.hintTarget.textContent = `${file.name} · ${this.size(file.size)}`;

        if (file.size > THRESHOLD) {
            this.watch();
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

    size(bytes) {
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }
}
