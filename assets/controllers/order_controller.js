import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['row'];

    earlier(event) {
        const row = this.rowOf(event);

        if (row.previousElementSibling) {
            row.parentNode.insertBefore(row, row.previousElementSibling);
        }
    }

    later(event) {
        const row = this.rowOf(event);

        if (row.nextElementSibling) {
            row.parentNode.insertBefore(row.nextElementSibling, row);
        }
    }

    rowOf(event) {
        return event.currentTarget.closest('[data-order-target="row"]');
    }
}
