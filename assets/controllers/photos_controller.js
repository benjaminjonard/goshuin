import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'queue', 'pending', 'template'];

    chosen() {
        Array.from(this.inputTarget.files).forEach((file) => this.queue(file));
        this.sync();
    }

    discard(event) {
        const pending = event.currentTarget.closest('[data-photos-target="pending"]');

        URL.revokeObjectURL(pending.querySelector('img').src);
        pending.remove();
        this.sync();
    }

    disconnect() {
        this.pendingTargets.forEach((pending) => URL.revokeObjectURL(pending.querySelector('img').src));
    }

    queue(file) {
        if (!file.type.startsWith('image/')) {
            return;
        }

        const pending = this.templateTarget.content.firstElementChild.cloneNode(true);

        pending.querySelector('img').src = URL.createObjectURL(file);
        pending.file = file;
        this.queueTarget.appendChild(pending);
    }

    sync() {
        const transfer = new DataTransfer();

        this.pendingTargets.forEach((pending) => transfer.items.add(pending.file));
        this.inputTarget.files = transfer.files;
    }
}
