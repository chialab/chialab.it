import { Component, customElement, listen, observe, property, render, state, type Template } from '@chialab/dna';
import { Map as MapElement, type Area } from '@chialab/dna-map';
import type { MapScrollerStep } from '@chialab/dna-map-scroller';
import { ControlsList, Slideshow } from '@chialab/dna-slideshow';
import { StoryScroller, type ChangeEvent } from '@chialab/dna-story-scroller';
import type { AppDialog } from './app-dialog';

@customElement('skua-map-scroller')
export class SkuaMapScroller extends Component {
    /** The tile configuration for the map. */
    @property({
        type: String,
    })
    tile = 'mapbox://styles/mapbox/streets-v9';

    /** Mapbox access token. */
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

    /** The current map step. */
    @state()
    currentStep: MapScrollerStep | null = null;

    /** Whether the viewport is mobile-sized. */
    @state()
    isMobile = false;

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

    /** The map element. */
    readonly mapElement: MapElement = new MapElement();

    /** The story scroller element. */
    readonly storyScrollerElement: StoryScroller = new StoryScroller();

    /** The resize handle element. */
    readonly resizeHandle: HTMLButtonElement = document.createElement('button');

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
                        minZoom={5}
                    />
                </div>
                <dna-story-scroller
                    ref={this.storyScrollerElement}
                    class="map-scroller__stories">
                    <slot />
                </dna-story-scroller>
                <button
                    class="resize-handle"
                    aria-label="Clicca e trascina per ridimensionare il pannello di testo"
                    ref={this.resizeHandle}>
                    <dna-icon
                        name="arrow-previous-full"
                        class="resize-handle__bar"></dna-icon>
                    <dna-icon
                        name="arrow-next-full"
                        class="resize-handle__bar"></dna-icon>
                </button>
            </>
        );
    }

    /**
     * @inheritdoc
     * @internal
     */
    connectedCallback(): void {
        super.connectedCallback();

        const mql = window.matchMedia('(width < 768px)');
        this.isMobile = mql.matches;
        mql.addEventListener('change', (e) => {
            this.isMobile = e.matches;
        });

        // scroll allo step indicato nell'hash dell'url
        const hash = window.location.hash.slice(1);
        if (hash) {
            const step = this.querySelector(`dna-map-scroller-step[data-uname="${hash}"]`);
            if (step) {
                setTimeout(() => {
                    step.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 0);
            }
        }
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
            const markerElement = marker.getElement() as HTMLElement;
            const markerCoords = marker.getLngLat();
            const index = points.findIndex((feature) => {
                const [lng, lat] = feature.geometry.coordinates;
                return this.isSamePoint([lng, lat], [markerCoords.lng, markerCoords.lat]);
            });
            if (index && index >= 0) {
                const indexElement = markerElement.querySelector('.marker-index');
                if (!indexElement) {
                    return;
                }

                indexElement.textContent = (index + 1).toString();
                processedMarkersCount++;
            }
        });

        if (processedMarkersCount === points.length) {
            this._allMarkersIndexed = true;
        }
    }

    @listen('load', 'dna-map')
    private onMapLoad() {
        this.mapElement.map?.setProjection('mercator');
        this.setupArea();
        this.mapElement.map?.on('render', () => {
            if (this._allMarkersIndexed) {
                return;
            }

            this.updateMarkersIndex();
        });
    }

    /**
     * Handles clicks on map markers to scroll to the corresponding step.
     * @internal
     * @param event The click event.
     */
    @listen('click', '[marker-symbol]')
    private onMarkerClick(event: MouseEvent) {
        const markerElement = (event.target as HTMLElement).closest('[marker-symbol]');
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
                    feature.properties?.uname == this.currentStep?.dataset.uname ? 'current' : '';
            });
            this.data = { ...this.data };
        }
    }

    @observe('currentStep')
    private onCurrentStepChange() {
        if (!this.currentStep) {
            return;
        }

        // aggiorno l'hash dell'url
        const stepUname = this.currentStep.dataset.uname;
        if (stepUname) {
            history.replaceState(null, '', `#${stepUname}`);
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

    /** Opens a full screen dialog when an image inside a step's slideshow is clicked. */
    @listen('click', '.map-scroller-item img.clickable')
    private onMediaItemClick(event: MouseEvent) {
        const mediaItem = event.target as HTMLImageElement;
        const dialog = this.ownerDocument.createElement('app-dialog') as AppDialog;
        const imgClone = mediaItem.cloneNode(true) as HTMLImageElement;
        let dialogContent: HTMLElement = imgClone;

        const slideshow = mediaItem.closest('dna-slideshow') as Slideshow | null;
        if (slideshow) {
            // se l'immagine fa parte di uno slideshow, lo mostro nella dialog
            const slideshowMedias = slideshow.querySelectorAll('img, video');
            const clonedSlideshow = (
                <dna-slideshow
                    carousel
                    controls
                    cover
                    current={slideshow.current}
                    controlsList={[ControlsList.nodots, ControlsList.noplayback, ControlsList.nocounter]}>
                    {[...slideshowMedias].map((media) => media.cloneNode(true))}
                </dna-slideshow>
            );
            dialogContent = clonedSlideshow;
        }

        this.ownerDocument.body.appendChild(render(<app-dialog ref={dialog}>{dialogContent}</app-dialog>) as Node);
        dialog.show();
        dialog.addEventListener('close', () => {
            dialog.remove();
        });
    }

    /** Set the correct area based on current viewport size. */
    @observe('isMobile')
    private setupArea() {
        if (this.isMobile) {
            // the height of the story scroller in mobile view is 75vh so we want the interactive area to start from there
            const top = this.clientHeight * 0.75;
            this.mapElement.area = { top, right: 0, bottom: 0, left: 0 };
            return;
        }
        // the width of the story scroller in desktop view is 40vw so we want the interactive area to end before that
        const left = this.clientWidth * 0.4;
        this.mapElement.area = { top: 0, right: 0, bottom: 0, left };
    }

    @listen('mousedown', '.resize-handle')
    private onResizeHandleMouseDown(event: MouseEvent) {
        event.preventDefault();
        event.stopPropagation();
        // consento il ridimensionamento solo con il tasto principale (sinistro) del mouse
        if (event.button != 0) {
            return;
        }

        document.addEventListener('mousemove', this.onResizeHandleMove);
        document.addEventListener('mouseup', this.onResizeHandleRelease);
    }

    /**
     * Gestisce il movimento del mouse durante il ridimensionamento del pannello degli step.
     * In base alla larghezza della viewport, ridimensiona in altezza (mobile) o in larghezza (desktop).
     * Imposta una dimensione minima e massima per evitare che il pannello diventi più piccolo della dimensione iniziale o più grande della finestra.
     */
    private onResizeHandleMove = (event: MouseEvent) => {
        if (this.isMobile) {
            this.style.setProperty('--steps-resized-height', `min(max(20vh, ${event.pageY}px), 100vh)`);
            return;
        }

        this.style.setProperty(
            '--steps-resized-width',
            `min(max(var(--map-scroller-steps-width), calc(${event.pageX}px - var(--map-scroller-left-offset))), var(--steps-max-width))`
        );
    };

    private onResizeHandleRelease = () => {
        document.removeEventListener('mousemove', this.onResizeHandleMove);
        document.removeEventListener('mouseup', this.onResizeHandleRelease);
    };
}
