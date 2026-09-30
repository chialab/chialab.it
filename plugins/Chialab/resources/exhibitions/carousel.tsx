import {
    Component,
    customElement,
    dispatchAsyncEvent,
    listen,
    observe,
    property,
    state,
    type Template,
} from '@chialab/dna';
import '@chialab/dna-button';
import '@chialab/dna-spinner';
import { getState, setState, unsetState } from './state';

/**
 * Horizontal carousel used for exhibition detail pages: prev/next buttons that only show up
 * when there's actually more to scroll to, and lazy-loads further pages of items as the
 * visitor reaches the end (some exhibitions have a lot of items). Ported from BCBF galleries'
 * own `dna-carousel` (`carousel.js`), adapted to this project's current DNA API.
 */
@customElement('exhibition-carousel')
export class Carousel extends Component {
    @property({ type: Boolean, attribute: 'snap' })
    snap = false;

    // Neither of these is a decorated `@property`: they're plain, read once from their HTML
    // attribute in `connectedCallback`. `page`/`pages` used to be declared via `@property`, but
    // that value kept resetting to its class field's default (1) rather than tracking updates —
    // likely `experimentalDecorators` + class fields (`target: esnext`) shadowing the decorator's
    // accessor with a plain own-property assignment. Reading them once as plain fields sidesteps
    // that ambiguity entirely, since `pages` never needs to change after the initial page load.
    private currentPage = 1;
    private totalPages = 1;

    @state()
    loading = false;

    @state()
    restoring = false;

    @state()
    canScrollLeft = false;

    @state()
    canScrollRight = false;

    private scrolling = false;
    private scrollTimeout?: ReturnType<typeof setTimeout>;
    private resizeObserver?: ResizeObserver;
    private onPageShow?: (event: PageTransitionEvent) => unknown;

    readonly scroller: HTMLDivElement = document.createElement('div');
    readonly container: HTMLDivElement = document.createElement('div');

    render(): Template {
        return (
            <>
                <button
                    is="dna-button"
                    type="button"
                    variant="action:primary"
                    class="carousel-button"
                    icon="chevron-left"
                    aria-hidden="true"
                    data-action="pagination-backward"
                    disabled={!this.canScrollLeft}
                />
                <div
                    class="carousel-scroller"
                    ref={this.scroller}>
                    <div
                        class="carousel-container"
                        ref={this.container}>
                        <slot />
                    </div>
                </div>
                <button
                    is="dna-button"
                    type="button"
                    variant="action:primary"
                    class="carousel-button"
                    aria-hidden="true"
                    aria-busy={this.loading}
                    data-action="pagination-forward"
                    disabled={!this.loading && !this.canScrollRight}>
                    {this.loading ? (
                        <dna-spinner slot="icon" />
                    ) : (
                        <dna-icon
                            slot="icon"
                            name="chevron-right"
                        />
                    )}
                </button>
            </>
        );
    }

    async connectedCallback(): Promise<void> {
        super.connectedCallback();

        this.currentPage = Number(this.getAttribute('page')) || 1;
        this.totalPages = Number(this.getAttribute('pages')) || 1;

        await this.restoreState();
        this.checkScrollArrows();
        this.saveState();

        // The `scroll` event doesn't bubble, so it can't be caught by the delegated `@listen`
        // mechanism (which relies on bubbling): bind it directly on the scroller instead.
        this.scroller.addEventListener('scroll', this.onScroll);

        this.resizeObserver?.disconnect();
        this.resizeObserver = new ResizeObserver(() => {
            // A resize can change whether there's room left to scroll.
            this.checkScrollArrows();
        });
        this.resizeObserver.observe(this);

        this.onPageShow = async (event: PageTransitionEvent) => {
            if (!event.persisted) {
                return;
            }
            await this.restoreState();
            this.checkScrollArrows();
            this.saveState();
        };
        window.addEventListener('pageshow', this.onPageShow);
    }

    disconnectedCallback(): void {
        super.disconnectedCallback();

        this.scroller.removeEventListener('scroll', this.onScroll);
        this.resizeObserver?.disconnect();
        if (this.onPageShow) {
            window.removeEventListener('pageshow', this.onPageShow);
        }
    }

    @listen('keydown')
    private onKeyDown(event: KeyboardEvent): void {
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            this.scrollBackward();
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            this.scrollForward();
        }
    }

    @listen('click', '[data-action="pagination-backward"]')
    private onBackwardClick(): void {
        this.scrollBackward();
    }

    @listen('click', '[data-action="pagination-forward"]')
    private onForwardClick(): void {
        this.scrollForward();
    }

    private onScroll = (): void => {
        clearTimeout(this.scrollTimeout);
        this.scrolling = true;
        this.scrollTimeout = setTimeout(() => {
            this.scrolling = false;
            this.saveState();
        }, 150);
        this.checkScrollArrows();
    };

    private scrollBackward(): void {
        if (this.scrolling) {
            return;
        }
        this.scroller.scrollBy({ left: -this.scroller.clientWidth / 2, behavior: 'smooth' });
    }

    private scrollForward(): void {
        if (this.scrolling) {
            return;
        }
        this.scroller.scrollBy({ left: this.scroller.clientWidth / 2, behavior: 'smooth' });
    }

    private checkScrollArrows(): void {
        const scroller = this.scroller;
        this.canScrollLeft = scroller.scrollLeft > 0;
        this.canScrollRight = !(scroller.scrollLeft + scroller.clientWidth >= scroller.scrollWidth - 50);
        if (scroller.scrollWidth <= scroller.clientWidth) {
            // Not enough content to fill the view yet: `canScrollRight` may already be `false`
            // (no-op assignment above, the observer below won't fire for it), so load more here too.
            void this.requestContent();
        }
    }

    // Whenever there's no more room to scroll forward (because the visitor reached the end),
    // try to load the next page of items.
    @observe('canScrollRight')
    private onCanScrollRightChange(): void {
        if (this.canScrollRight) {
            return;
        }
        void this.requestContent();
    }

    private async requestContent(): Promise<void> {
        if (this.loading || !this.totalPages || this.totalPages === this.currentPage) {
            return;
        }

        this.loading = true;
        const nextPage = this.currentPage + 1;
        const [response] = await dispatchAsyncEvent(this, 'fetch', nextPage);
        for (const node of (response as Node[] | undefined) ?? []) {
            this.container.appendChild(node);
        }
        this.currentPage = nextPage;
        this.loading = false;
        this.checkScrollArrows();
    }

    private async restoreState(): Promise<void> {
        if (!this.id) {
            return;
        }

        const [navigation] = performance.getEntriesByType('navigation') as PerformanceNavigationTiming[];
        if (!navigation || navigation.type !== 'back_forward') {
            // restore state only when navigating back
            return;
        }

        const savedState = getState(this.id);
        if (!savedState) {
            return;
        }

        this.restoring = true;
        this.currentPage = savedState.page || this.currentPage;

        // Replace whatever's currently in the container (just the freshly server-rendered first
        // page at this point) with the previously saved, possibly further-paginated, content.
        this.container.innerHTML = '';
        const wrapper = document.createElement('div');
        wrapper.innerHTML = savedState.content;
        while (wrapper.firstChild) {
            this.container.appendChild(wrapper.firstChild);
        }

        await new Promise<void>((resolve) => {
            requestAnimationFrame(() => {
                this.scroller.scrollLeft = savedState.scroll || 0;
                requestAnimationFrame(() => {
                    this.scroller.scrollLeft = savedState.scroll || 0;
                    resolve();
                });
            });
        });

        unsetState(this.id);
        this.restoring = false;
    }

    private saveState(): void {
        if (!this.id) {
            return;
        }
        setState(this.id, {
            page: this.currentPage,
            content: this.container.innerHTML,
            scroll: this.scroller.scrollLeft,
        });
    }
}
