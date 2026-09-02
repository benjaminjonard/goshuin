/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { feature } from 'topojson-client';

export default class extends Controller {
    static targets = ['canvas', 'latitude', 'longitude', 'switch', 'held'];
    static values = {
        mode: { type: String, default: 'picker' },
        tiles: String,
        attribution: String,
        latitude: Number,
        longitude: Number,
        markers: Array,
        layers: Array,
        zoom: { type: Number, default: 5 },
    };

    connect() {
        this.map = L.map(this.canvasTarget, { preferCanvas: true }).setView(this.centre(), this.zoom());

        L.tileLayer(this.tilesValue, {
            attribution: this.attributionValue,
            maxZoom: 19,
            referrerPolicy: 'strict-origin-when-cross-origin',
        }).addTo(this.map);

        if (this.modeValue === 'picker') {
            this.startPicking();

            return;
        }

        if (this.modeValue === 'regions') {
            this.shade();

            return;
        }

        this.drop();
    }

    disconnect() {
        this.map.remove();
        this.map = null;
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

    shade() {
        this.drawn = [];
        this.scales = [];
        this.pending = [];
        this.turn = 0;

        this.layersValue.forEach((layer, index) => {
            if (layer.frames) {
                this.regions(index);
            }
        });

        this.reveal(0);
    }

    show(event) {
        this.reveal(Number(event.params.level));
    }

    async reveal(index) {
        const turn = ++this.turn;
        const regions = await this.regions(index);

        if (!this.map || turn !== this.turn) {
            return;
        }

        this.drawn.forEach((group, at) => {
            if (group && at !== index) {
                this.map.removeLayer(group);
            }
        });

        regions.addTo(this.map);

        if (this.hasHeldTarget) {
            this.heldTarget.textContent = this.layersValue[index].count;
        }

        this.switchTargets.forEach((button, at) => button.setAttribute('aria-pressed', String(at === index)));

        if (!this.fitted && await this.open(regions, index, turn)) {
            this.fitted = true;
        }
    }

    regions(index) {
        if (!this.pending[index]) {
            this.pending[index] = this.build(index);
        }

        return this.pending[index];
    }

    async build(index) {
        const layer = this.layersValue[index];
        const topology = await this.boundaries(layer.url);

        if (!topology) {
            this.pending[index] = null;

            return L.geoJSON();
        }

        try {
            const [collection] = Object.keys(topology.objects);
            const units = topology.objects[collection].geometries.length;
            const visited = this.visited(index);
            const palette = getComputedStyle(this.element);
            const accent = palette.getPropertyValue('--color-accent').trim();
            const line = palette.getPropertyValue('--color-fg-2').trim();
            const hairline = this.dense(units);

            this.drawn[index] = L.geoJSON(feature(topology, topology.objects[collection]), {
                style: (region) => (visited.has(region.properties.code)
                    ? { color: accent, weight: hairline ? 1 : 1.8, opacity: 1, fillColor: accent, fillOpacity: 0.6 }
                    : { color: line, weight: hairline ? 0.7 : 1.4, opacity: 1, fill: false }),
            });

            this.drawn[index].bindTooltip((region) => this.reads(region.feature.properties, visited), {
                sticky: true,
                direction: 'top',
                className: 'map-label',
            });

            this.scales[index] = hairline ? 10 : 8;

            return this.drawn[index];
        } catch (failure) {
            console.error('The boundary layer could not be drawn.', failure);
            this.pending[index] = null;

            return L.geoJSON();
        }
    }

    async boundaries(url) {
        try {
            const answer = await fetch(url);

            if (!answer.ok) {
                throw new Error(`${url} answered ${answer.status}`);
            }

            const topology = await answer.json();

            if (!topology?.objects || Object.keys(topology.objects).length === 0) {
                throw new Error(`${url} carries no boundary object`);
            }

            return topology;
        } catch (failure) {
            console.error('The boundary layer could not be drawn.', failure);

            return null;
        }
    }

    async open(shown, index, turn) {
        if (this.scales[index] === undefined) {
            return false;
        }

        const from = this.layersValue.findIndex((layer) => layer.frames);
        const source = from === -1 ? shown : await this.regions(from);

        if (!this.map || turn !== this.turn) {
            return false;
        }

        const held = this.held(source, from === -1 ? index : from);
        const bounds = held.isValid() ? held : shown.getBounds();

        if (!bounds.isValid()) {
            return false;
        }

        this.map.fitBounds(bounds, {
            padding: [24, 24],
            maxZoom: this.scales[index],
            animate: false,
        });

        return true;
    }

    held(regions, index) {
        const visited = this.visited(index);
        const bounds = L.latLngBounds([]);

        regions.eachLayer((region) => {
            if (visited.has(region.feature.properties.code)) {
                bounds.extend(region.getBounds());
            }
        });

        return bounds;
    }

    dense(units) {
        return units > 500;
    }

    visited(index) {
        return new Map(this.layersValue[index].zones.map((zone) => [zone.code, zone.held]));
    }

    reads(properties, visited) {
        const held = visited.get(properties.code);
        const name = this.named(properties);

        return held ? `${name} · ${held}` : name;
    }

    named(properties) {
        const chain = document.documentElement.lang === 'ja'
            ? [properties.name, properties.romanized]
            : [properties.romanized, properties.name];

        return chain.find((name) => name) ?? '';
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
