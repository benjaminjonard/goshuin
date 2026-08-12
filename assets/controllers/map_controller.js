/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const MINIMUM = 30;
const FURTHEST = 60;
const TARGET = MINIMUM * 1.3;
const LEASH = 0.02;
const PASSES = 160;

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
        this.map = L.map(this.canvasTarget, { scrollWheelZoom: this.modeValue === 'picker' })
            .setView(this.centre(), this.zoom());

        L.tileLayer(this.tilesValue, {
            attribution: this.attributionValue,
            maxZoom: 19,
            referrerPolicy: 'strict-origin-when-cross-origin',
        }).addTo(this.map);

        if (this.modeValue === 'picker') {
            this.startPicking();

            return;
        }

        this.number();
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

    number() {
        this.pins = L.layerGroup().addTo(this.map);
        this.map.on('moveend zoomend resize', () => this.spread());

        if (this.markersValue.length > 1) {
            this.map.fitBounds(this.markersValue.map((marker) => [marker.latitude, marker.longitude]), {
                padding: [34, 34],
                animate: false,
            });
        }

        this.map.whenReady(() => this.spread());
    }

    spread() {
        this.pins.clearLayers();

        this.grouped(this.spaced()).forEach((placed) => {
            L.marker(this.map.containerPointToLatLng(L.point(placed.x, placed.y)), {
                icon: L.divIcon({
                    className: 'map-pin',
                    iconSize: [30, 38],
                    iconAnchor: [15, 38],
                    html: this.pin(placed),
                }),
                keyboard: false,
            }).addTo(this.pins);
        });
    }

    spaced() {
        const points = this.markersValue.map((marker) => {
            const at = this.map.latLngToContainerPoint(L.latLng(marker.latitude, marker.longitude));

            return { marker, ax: at.x, ay: at.y, x: at.x, y: at.y };
        });

        for (let pass = 0; pass < PASSES; pass += 1) {
            for (let i = 0; i < points.length; i += 1) {
                for (let j = i + 1; j < points.length; j += 1) {
                    const a = points[i];
                    const b = points[j];
                    let dx = b.x - a.x;
                    let dy = b.y - a.y;
                    let d = Math.sqrt(dx * dx + dy * dy);

                    if (d < 0.001) {
                        dx = 0.6;
                        dy = 0.4;
                        d = 0.72;
                    }

                    if (d < TARGET) {
                        const push = ((TARGET - d) / d) * 0.4;

                        a.x -= dx * push;
                        a.y -= dy * push;
                        b.x += dx * push;
                        b.y += dy * push;
                    }
                }
            }

            points.forEach((point) => {
                point.x += (point.ax - point.x) * LEASH;
                point.y += (point.ay - point.y) * LEASH;
                this.leash(point);
            });
        }

        return points;
    }

    leash(point) {
        const dx = point.x - point.ax;
        const dy = point.y - point.ay;
        const away = Math.sqrt(dx * dx + dy * dy);

        if (away > FURTHEST) {
            point.x = point.ax + (dx / away) * FURTHEST;
            point.y = point.ay + (dy / away) * FURTHEST;
        }
    }

    grouped(points) {
        const placed = [];

        points.forEach((point) => {
            const near = placed.find((other) => {
                const dx = other.x - point.x;
                const dy = other.y - point.y;

                return Math.sqrt(dx * dx + dy * dy) < MINIMUM;
            });

            if (near) {
                near.held.push(point.marker);

                return;
            }

            placed.push({ x: point.x, y: point.y, held: [point.marker] });
        });

        return placed;
    }

    pin(placed) {
        const first = placed.held[0];
        const many = placed.held.length > 1;
        const shown = many ? placed.held.length : first.number;
        const label = many
            ? placed.held.map((marker) => marker.number).join(', ')
            : `${first.number}. ${first.label ?? ''}`;

        const body = '<svg viewBox="0 0 30 38" aria-hidden="true">'
            + '<path class="body" d="M15 1.5c-6.9 0-12.5 5.5-12.5 12.3 0 8.4 9.9 18.6 11.6 20.3a1.3 1.3 0 0 0 1.8 0c1.7-1.7 11.6-11.9 11.6-20.3C27.5 7 21.9 1.5 15 1.5z"/>'
            + (shown ? `<text class="num" x="15" y="18.6">${Number(shown)}</text>` : '')
            + '</svg>';

        if (many || !first.href) {
            return `<span role="img" aria-label="${label}">${body}</span>`;
        }

        return `<a href="${first.href}" aria-label="${label}" data-index="${first.index ?? ''}">${body}</a>`;
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
