import { Component, customElement, dispatchAsyncEvent, listen, property, state, type Template } from '@chialab/dna';
import { getState, setState, unsetState } from './state';

/**
 * Horizontal carousel used for exhibition detail pages: prev/next buttons that only show up
 * when there's actually more to scroll to, and lazy-loads further pages of items as the
 * visitor reaches the end (some exhibitions have a lot of items). Ported from BCBF galleries'
 * own `dna-carousel` (`carousel.js`), adapted to this project's current DNA API.
 */
@customElement('dna-carousel', { extends: 'section' })
export class Carousel extends Component {
    @property({ type: Boolean, attribute: 'snap' })
    snap = false;

    @property({ type: Number, attribute: 'page' })
    page = 1;

    @property({ type: Number, attribute: 'pages' })
    pages = 1;

    @state()
    loading = false;

    @state()
    restoring = false;

    @state()
    canScrollLeft = false;

    @state()
    canScrollRight = false;

    @state()
    content: Node[] = [];

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
                    type="button"
                    class="carousel-button"
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
                        {this.content}
                    </div>
                </div>
                <button
                    type="button"
                    class="carousel-button"
                    aria-hidden="true"
                    data-action="pagination-forward"
                    disabled={!this.loading && !this.canScrollRight}
                />
            </>
        );
    }

    async connectedCallback(): Promise<void> {
        super.connectedCallback();

        await this.restoreState();
        this.checkScrollArrows();
        this.saveState();

        // The `scroll` event doesn't bubble, so it can't be caught by the delegated `@listen`
        // mechanism (which relies on bubbling): bind it directly on the scroller instead.
        this.scroller.addEventListener('scroll', this.onScroll);

        this.resizeObserver?.disconnect();
        this.resizeObserver = new ResizeObserver(() => this.checkScrollArrows());
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
            void this.requestContent();
        }
    }

    private async requestContent(): Promise<void> {
        if (this.loading || !this.pages || this.pages === this.page) {
            return;
        }

        this.loading = true;
        const page = this.page;
        const [response] = await dispatchAsyncEvent(this, 'fetch', page + 1);
        this.content = [...this.content, ...((response as Node[] | undefined) ?? [])];
        this.page = page + 1;
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
        this.page = savedState.page || this.page;

        const wrapper = document.createElement('div');
        wrapper.innerHTML = savedState.content;
        this.content = Array.from(wrapper.childNodes);

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
            page: this.page,
            content: this.container.innerHTML,
            scroll: this.scroller.scrollLeft,
        });
    }
}
