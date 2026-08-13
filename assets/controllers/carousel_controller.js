import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['track', 'nav', 'previous', 'next'];

    connect() {
        this.watcher = new ResizeObserver(() => this.update());
        this.watcher.observe(this.trackTarget);
        this.update();
    }

    disconnect() {
        this.watcher.disconnect();
    }

    previous() {
        this.trackTarget.scrollBy({ left: -this.step() });
    }

    next() {
        this.trackTarget.scrollBy({ left: this.step() });
    }

    update() {
        const room = this.trackTarget.scrollWidth - this.trackTarget.clientWidth;
        const at = this.trackTarget.scrollLeft;

        this.navTarget.style.display = room < 2 ? 'none' : '';
        this.previousTarget.disabled = at < 2;
        this.nextTarget.disabled = at > room - 2;
    }

    step() {
        const held = this.trackTarget.firstElementChild;

        if (!held) {
            return this.trackTarget.clientWidth;
        }

        const width = held.getBoundingClientRect().width + this.gap();

        return Math.max(width, Math.floor(this.trackTarget.clientWidth / width) * width);
    }

    gap() {
        return parseFloat(getComputedStyle(this.trackTarget).columnGap) || 0;
    }
}
