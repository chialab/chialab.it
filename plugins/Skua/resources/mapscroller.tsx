import { Component, customElement, listen, observe, property, state, type Template } from '@chialab/dna';
import { Dialog } from '@chialab/dna-dialog';
import { Map as MapElement, type Area } from '@chialab/dna-map';
import type { MapScrollerStep } from '@chialab/dna-map-scroller';
import { StoryScroller, type ChangeEvent } from '@chialab/dna-story-scroller';

@customElement('skua-map-scroller')
export class SkuaMapScroller extends Component {
    /**
     * The tile configuration for the map.
     */
    @property({
        type: String,
    })
    tile = 'mapbox://styles/mapbox/streets-v9';

    /**
     * Mapbox access token.
     */
    @property({
        type: String,
        attribute: 'access-token',
    })
    accessToken?: string;

    @property({
        type: Object,
        attribute: 'data',
    })
    data?: GeoJSON.FeatureCollection<GeoJSON.Geometry>;

    /**
     * The current map step.
     */
    @state()
    currentStep: MapScrollerStep | null = null;

    /** Whether all markers have been processed to put the index number above the icon. */
    private _allMarkersIndexed = false;

    /**
     * The interactive area of the map.
     * @returns The current area value.
     */
    @property({
        type: Object,
        defaultValue: { top: 0, left: 0, right: 0, bottom: 0 },
        fromAttribute(value) {
            if (!value) {
                return undefined;
            }
            const [top, right, bottom, left] = value.trim().split(/\s*,\s*/);
            return {
                top: Number.parseFloat(top),
                right: Number.parseFloat(right),
                bottom: Number.parseFloat(bottom),
                left: Number.parseFloat(left),
            };
        },
        toAttribute(value: Area) {
            return [value.top, value.right, value.bottom, value.left].join(', ');
        },
    })
    get area() {
        return this.getInnerPropertyValue('area');
    }
    set area(area: Area) {
        const oldArea = this.area || { top: 0, left: 0, right: 0, bottom: 0 };
        const newArea = area || { top: 0, left: 0, right: 0, bottom: 0 };
        if (
            oldArea.top === newArea.top &&
            oldArea.left === newArea.left &&
            oldArea.right === newArea.right &&
            oldArea.bottom === newArea.bottom
        ) {
            return;
        }

        this.setInnerPropertyValue('area', newArea);
    }

    /**
     * The map element.
     */
    readonly mapElement: MapElement = new MapElement();

    /**
     * The story scroller element.
     */
    readonly storyScrollerElement: StoryScroller = new StoryScroller();

    /**
     * @inheritdoc
     * @internal
     */
    render(): Template {
        return (
            <>
                <div class="map-scroller__map">
                    <dna-map
                        ref={this.mapElement}
                        tile={this.tile}
                        accessToken={this.accessToken}
                        area={this.area}
                        data={this.data}
                        controls
                    />
                </div>
                <dna-story-scroller
                    ref={this.storyScrollerElement}
                    class="map-scroller__stories">
                    <slot />
                </dna-story-scroller>
            </>
        );
    }

    /** Whether two points have the same coordinates within a small tolerance (1e-6). */
    private isSamePoint(a: GeoJSON.Point['coordinates'], b: GeoJSON.Point['coordinates']): boolean {
        return Math.abs(a[0] - b[0]) <= 1e-6 && Math.abs(a[1] - b[1]) <= 1e-6;
    }

    /** Add index numbers to markers based on their position in the data. */
    private updateMarkersIndex() {
        /*
            `mapElement.markers` non dà l'elenco di tutti i marker ma solo quelli attualmente visibili sulla mappa quindi non si può
            fare un semplice ciclo con indice.
            Deduciamo l'indice dai dati passati a dna-map in `data` confrontando i `Point` con le coordinate del marker
        */
        const points = this.data?.features.filter(
            (f): f is GeoJSON.Feature<GeoJSON.Point> => f.geometry.type === 'Point'
        );
        if (!points) {
            return;
        }

        let processedMarkersCount = 0;
        this.mapElement.markers.forEach((marker) => {
            const markerElement = marker.getElement();
            const markerCoords = marker.getLngLat();
            const index = points.findIndex((feature) => {
                const [lng, lat] = feature.geometry.coordinates;
                return this.isSamePoint([lng, lat], [markerCoords.lng, markerCoords.lat]);
            });
            if (index && index >= 0) {
                markerElement.querySelector('.marker-index')!.textContent = (index + 1).toString();
                processedMarkersCount++;
            }
        });

        if (processedMarkersCount === points.length) {
            this._allMarkersIndexed = true;
        }
    }

    @listen('load', 'dna-map')
    private onMapLoad() {
        this.mapElement.map.on('render', () => {
            if (this._allMarkersIndexed) {
                return;
            }

            this.updateMarkersIndex();
        });
        this.mapElement.area = { top: 0, right: 0, bottom: 0, left: '40%' };
    }

    @listen('click', '[marker-symbol="marker-skua"]')
    private onMarkerClick(event: MouseEvent) {
        const markerElement = (event.target as HTMLElement).closest('[marker-symbol="marker-skua"]');
        const marker = this.mapElement.markers.find((marker) => marker.getElement() === markerElement);
        const markerCoords = marker?.getLngLat();
        if (markerCoords) {
            Array.from(this.querySelectorAll('dna-map-scroller-step'))
                .find((step) =>
                    this.isSamePoint([step.center.lng, step.center.lat], [markerCoords.lng, markerCoords.lat])
                )
                ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    /**
     * The enter callback for map steps.
     * @internal
     * @param event The change event.
     */
    @listen('change', '.map-scroller__stories')
    private onStoryScrollerChange(event: ChangeEvent<MapScrollerStep>) {
        if (event.target === this.storyScrollerElement && event.detail) {
            this.currentStep = event.detail;
            if (!this.data) {
                return;
            }

            this.data.features.forEach((feature) => {
                feature.properties!['marker-class'] =
                    feature.properties?.id == this.currentStep?.dataset.id ? 'current' : '';
            });
            this.data = { ...this.data };
        }
    }

    /**
     * Opens the related dialog when an image inside a step's slideshow is clicked.
     */
    @listen('click', '.map-scroller-item > dna-slideshow img')
    private onMediaItemClick(event: MouseEvent) {
        const mediaItem = event.target as HTMLImageElement;
        const mapScrollerItem = mediaItem?.closest('.map-scroller-item');
        if (!mapScrollerItem) {
            return;
        }

        const mediaItemParent = mediaItem.getAttribute('data-parent');
        const dialog = document.querySelector(`dna-dialog[data-for="${mediaItemParent}"]`) as Dialog;
        if (!dialog) {
            return;
        }

        const dialogSlideshow = dialog.querySelector('dna-slideshow');
        const stepSlideshow = mapScrollerItem.querySelector('dna-slideshow');
        if (!stepSlideshow || !dialogSlideshow) {
            return;
        }

        dialogSlideshow.current = stepSlideshow.current;
        dialog?.show();
    }

    @observe('currentStep')
    private onCurrentStepChange() {
        if (!this.currentStep) {
            return;
        }

        if (this.mapElement?.loaded) {
            this.mapElement.updateCamera(this.currentStep.center, this.currentStep.zoom, this.currentStep.pitch);
        } else {
            this.mapElement.center = this.currentStep.center;
            if (this.currentStep.zoom) {
                this.mapElement.zoom = this.currentStep.zoom;
            }
            if (this.currentStep.pitch) {
                this.mapElement.pitch = this.currentStep.pitch;
            }
        }
    }
}
