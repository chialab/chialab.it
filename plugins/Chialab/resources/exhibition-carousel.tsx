import { Component, customElement, listen, property, state, type Template } from '@chialab/dna';

/**
 * Horizontally scrolling exhibition carousel with prev/next buttons (no visible scrollbar),
 * that lazy-loads further pages of items as the visitor reaches the end, since some
 * exhibitions have a lot of items.
 */
@customElement('exhibition-carousel')
export class ExhibitionCarousel extends Component {
    /** URL to fetch further pages of items from. */
    @property({ type: String, attribute: 'data-url' })
    url = '';

    /** The currently loaded page of items. */
    @property({ type: Number, attribute: 'data-page' })
    page = 1;

    /** The total number of pages of items available. */
    @property({ type: Number, attribute: 'data-pages' })
    pages = 1;

    @state()
    loading = false;

    @state()
    canScrollPrev = false;

    @state()
    canScrollNext = false;

    /** The scrollable viewport. */
    readonly scroller: HTMLDivElement = document.createElement('div');

    private resizeObserver?: ResizeObserver;

    render(): Template {
        return (
            <>
                <button
                    type="button"
                    class="exhibition-carousel__nav exhibition-carousel__nav--prev"
                    aria-label="Precedente"
                    disabled={!this.canScrollPrev}
                />
                <div
                    class="exhibition-carousel__scroller"
                    ref={this.scroller}>
                    <div class="exhibition-carousel__track">
                        <slot />
                    </div>
                </div>
                <button
                    type="button"
                    class="exhibition-carousel__nav exhibition-carousel__nav--next"
                    aria-label="Successivo"
                    aria-busy={this.loading}
                    disabled={!this.loading && !this.canScrollNext}
                />
            </>
        );
    }

    connectedCallback(): void {
        super.connectedCallback();

        this.resizeObserver = new ResizeObserver(() => this.checkScrollState());
        this.resizeObserver.observe(this);
        requestAnimationFrame(() => this.checkScrollState());
    }

    disconnectedCallback(): void {
        super.disconnectedCallback();

        this.resizeObserver?.disconnect();
    }

    @listen('click', '.exhibition-carousel__nav--prev')
    private onPrevClick(): void {
        this.scrollByPage(-1);
    }

    @listen('click', '.exhibition-carousel__nav--next')
    private onNextClick(): void {
        this.scrollByPage(1);
    }

    @listen('scroll', '.exhibition-carousel__scroller')
    private onScroll(): void {
        this.checkScrollState();
    }

    @listen('keydown')
    private onKeyDown(event: KeyboardEvent): void {
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            this.scrollByPage(-1);
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            this.scrollByPage(1);
        }
    }

    private scrollByPage(direction: 1 | -1): void {
        this.scroller.scrollBy({ left: direction * this.scroller.clientWidth, behavior: 'smooth' });
    }

    private checkScrollState(): void {
        const { scrollLeft, scrollWidth, clientWidth } = this.scroller;
        this.canScrollPrev = scrollLeft > 0;
        this.canScrollNext = scrollLeft + clientWidth < scrollWidth - 1;

        if (!this.canScrollNext) {
            void this.loadMore();
        }
    }

    private async loadMore(): Promise<void> {
        if (this.loading || this.page >= this.pages || !this.url) {
            return;
        }

        this.loading = true;
        try {
            const response = await fetch(`${this.url}?page=${this.page + 1}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) {
                return;
            }

            this.scroller
                .querySelector('.exhibition-carousel__track')
                ?.insertAdjacentHTML('beforeend', await response.text());
            this.page += 1;
            this.checkScrollState();
        } finally {
            this.loading = false;
        }
    }
}
