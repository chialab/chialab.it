import { Component, customElement, listen, observe, property, state, Template } from '@chialab/dna';
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

    @listen('click', '.mapboxgl-marker')
    private onMarkerClick(event: MouseEvent) {
        const markerElement = (event.target as HTMLElement).closest('.mapboxgl-marker');
        const marker = this.mapElement.markers.find((marker) => marker.getElement() === markerElement);
        const markerCoords = marker?.getLngLat();
        if (markerCoords) {
            Array.from(this.querySelectorAll('dna-map-scroller-step'))
                .find(
                    (step) =>
                        Math.abs(step.center.lng - markerCoords.lng) <= 1e-6 &&
                        Math.abs(step.center.lat - markerCoords.lat) <= 1e-6
                )
                ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
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

            this.data.features.forEach((feature, index) => {
                feature.properties!['marker-class'] =
                    feature.properties?.id == this.currentStep?.dataset.id ? 'current' : '';
            });
            this.data = { ...this.data };
        }
    }

    @observe('currentStep')
    private onCurrentStepChange() {
        if (this.currentStep) {
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
}
