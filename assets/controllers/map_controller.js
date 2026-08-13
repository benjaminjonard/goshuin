/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

export default class extends Controller {
    static targets = ['canvas', 'latitude', 'longitude'];
    static values = {
        mode: { type: String, default: 'picker' },
        tiles: String,
        attribution: String,
        latitude: Number,
        longitude: Number,
        markers: Array,
        zoom: { type: Number, default: 5 },
    };

    connect() {
        this.map = L.map(this.canvasTarget).setView(this.centre(), this.zoom());

        L.tileLayer(this.tilesValue, {
            attribution: this.attributionValue,
            maxZoom: 19,
            referrerPolicy: 'strict-origin-when-cross-origin',
        }).addTo(this.map);

        if (this.modeValue === 'picker') {
            this.startPicking();

            return;
        }

        this.drop();
    }

    disconnect() {
        this.map.remove();
    }

    latitudeValueChanged() {
        this.follow();
    }

    longitudeValueChanged() {
        this.follow();
    }

    follow() {
        if (!this.map || !this.hasCoordinates()) {
            return;
        }

        this.place(this.latitudeValue, this.longitudeValue);
        this.map.setView([this.latitudeValue, this.longitudeValue], Math.max(this.map.getZoom(), 17));
    }

    drop() {
        if (this.markersValue.length > 1) {
            this.map.fitBounds(this.markersValue.map((marker) => [marker.latitude, marker.longitude]), {
                padding: [40, 40],
                animate: false,
            });
        }

        const pins = L.layerGroup().addTo(this.map);

        this.markersValue.forEach((marker) => {
            L.marker([marker.latitude, marker.longitude], {
                icon: L.divIcon({
                    className: 'map-pin',
                    iconSize: [30, 38],
                    iconAnchor: [15, 38],
                    html: this.pin(marker),
                }),
                keyboard: false,
            }).addTo(pins);
        });
    }

    pin(marker) {
        const shown = Number(marker.number) || 0;
        const label = [marker.number, marker.label].filter((part) => part).join('. ');
        const tint = marker.hue === null || marker.hue === undefined
            ? ''
            : ` class="tinted" style="--hue: ${Number(marker.hue)}"`;

        const body = '<svg viewBox="0 0 30 38" aria-hidden="true">'
            + '<path class="body" d="M15 1.5c-6.9 0-12.5 5.5-12.5 12.3 0 8.4 9.9 18.6 11.6 20.3a1.3 1.3 0 0 0 1.8 0c1.7-1.7 11.6-11.9 11.6-20.3C27.5 7 21.9 1.5 15 1.5z"/>'
            + (shown ? `<text class="num" x="15" y="18.6">${shown}</text>` : '')
            + '</svg>';

        if (!marker.href) {
            return `<span${tint} role="img" aria-label="${label}">${body}</span>`;
        }

        const index = marker.index ? ` data-index="${marker.index}"` : '';

        return `<a href="${marker.href}"${tint}${index} aria-label="${label}">${body}</a>`;
    }

    startPicking() {
        if (this.hasCoordinates()) {
            this.place(this.latitudeValue, this.longitudeValue);
        }

        this.map.on('click', (event) => this.place(event.latlng.lat, event.latlng.lng, true));
    }

    place(latitude, longitude, write = false) {
        const rounded = [Number(latitude.toFixed(6)), Number(longitude.toFixed(6))];

        if (this.marker) {
            this.marker.setLatLng(rounded);
        } else {
            this.marker = L.circleMarker(rounded, { radius: 9, weight: 2, className: 'map-point' }).addTo(this.map);
        }

        if (write && this.hasLatitudeTarget && this.hasLongitudeTarget) {
            this.latitudeTarget.value = String(rounded[0]);
            this.longitudeTarget.value = String(rounded[1]);
            this.latitudeTarget.dispatchEvent(new Event('input', { bubbles: true }));
            this.longitudeTarget.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    centre() {
        if (this.hasCoordinates()) {
            return [this.latitudeValue, this.longitudeValue];
        }

        const [first] = this.markersValue;

        return first ? [first.latitude, first.longitude] : [36.2048, 138.2529];
    }

    zoom() {
        return this.hasCoordinates() || this.markersValue.length > 0 ? 17 : this.zoomValue;
    }

    hasCoordinates() {
        return this.latitudeValue !== 0 || this.longitudeValue !== 0;
    }
}
