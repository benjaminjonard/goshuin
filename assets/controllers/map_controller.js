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
        }
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
            this.marker = L.circleMarker(rounded, { radius: 9, weight: 2, className: 'map-pin' }).addTo(this.map);
        }

        if (write && this.hasLatitudeTarget && this.hasLongitudeTarget) {
            this.latitudeTarget.value = String(rounded[0]);
            this.longitudeTarget.value = String(rounded[1]);
            this.latitudeTarget.dispatchEvent(new Event('input', { bubbles: true }));
            this.longitudeTarget.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    centre() {
        return this.hasCoordinates() ? [this.latitudeValue, this.longitudeValue] : [36.2048, 138.2529];
    }

    zoom() {
        return this.hasCoordinates() ? 17 : this.zoomValue;
    }

    hasCoordinates() {
        return this.latitudeValue !== 0 || this.longitudeValue !== 0;
    }
}
