import { Component, customElement, property, state, type Template } from '@chialab/dna';

/**
 * Description of an exhibition card, clamped to a few lines, followed by a "+" that is only
 * revealed when the text has actually been truncated. There's no reliable way to know this
 * server-side (it depends on the real rendered width/wrapping), so the clamped text is observed
 * at runtime. Add the `more` attribute to always reveal the "+" (e.g. the item has a body).
 */
@customElement('dna-card-description')
export class CardDescription extends Component {
    @property({ type: Boolean, attribute: 'more' })
    more = false;

    @state()
    truncated = false;

    readonly text: HTMLDivElement = document.createElement('div');
    private resizeObserver?: ResizeObserver;

    render(): Template {
        return (
            <>
                <div
                    class="card-text clamp-5 f-4"
                    ref={this.text}>
                    <slot />
                </div>
                <span
                    class={this.truncated || this.more ? 'card-more card-more--visible' : 'card-more'}
                    aria-hidden="true"
                />
            </>
        );
    }

    connectedCallback(): void {
        super.connectedCallback();

        this.resizeObserver?.disconnect();
        this.resizeObserver = new ResizeObserver(() => this.checkTruncated());
        this.resizeObserver.observe(this.text);
        // Fonts settling can shift how the text wraps without resizing the (fixed-height) box.
        document.fonts?.ready.then(() => this.checkTruncated());
    }

    disconnectedCallback(): void {
        super.disconnectedCallback();
        this.resizeObserver?.disconnect();
    }

    private checkTruncated(): void {
        this.truncated = this.text.scrollHeight > this.text.clientHeight + 1;
    }
}
