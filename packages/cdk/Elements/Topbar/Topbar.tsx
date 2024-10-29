import { Component, customElement, listen, property, state } from '@chialab/dna';
import { ButtonVariant } from '@chialab/dna-button';

@customElement('cl-topbar')
export class Topbar extends Component {
    @property({
        type: String,
    })
    url: string = '/';

    @property({
        type: String,
    })
    tooltip: string = 'Back to home';

    @property({
        type: String,
    })
    openTooltip: string = 'Open menu';

    @property({
        type: String,
    })
    closeTooltip: string = 'Close menu';

    @state({
        type: Boolean,
        attribute: ':open',
    })
    open: boolean = false;

    @state({
        type: Boolean,
        attribute: ':fixed',
    })
    fixed: boolean = false;

    /**
     * @inheritdoc
     */
    connectedCallback() {
        super.connectedCallback();
        window.addEventListener('scroll', this.onWindowScroll);
    }

    /**
     * @inheritdoc
     */
    disconnectedCallback() {
        super.disconnectedCallback();
        window.removeEventListener('scroll', this.onWindowScroll);
    }

    /**
     * @inheritdoc
     */
    render() {
        return (
            <div class="viewport column gap-xs py-3">
                <a
                    href={this.url}
                    title={this.tooltip}
                    class="mono bold f-2 lower">
                    <slot name="title">chialab</slot>
                </a>
                <div class="topbar__container">
                    <span
                        class="f-2 mr-1 mono bold text-accent"
                        aria-hidden="true">
                        ẞ
                    </span>
                    <nav class="topbar__main-nav">
                        <slot />
                    </nav>
                    <div class="topbar__group">
                        <span
                            class="f-2 mr-1 mono"
                            aria-hidden="true">
                            ß
                        </span>
                        <nav class="topbar__lang-nav">
                            <slot name="locale" />
                        </nav>
                    </div>
                </div>
                <button
                    is="dna-button"
                    variant={ButtonVariant.action}
                    class="topbar__toggle"
                    role="switch"
                    aria-checked={this.open ? 'true' : 'false'}
                    aria-label={this.open ? this.closeTooltip : this.openTooltip}>
                    <svg viewBox="0 0 256 256">
                        <rect
                            y="47.5"
                            width="256"
                            height="14.6"
                        />
                        <rect
                            y="120.7"
                            width="256"
                            height="14.6"
                            class="topbar__toggle-middle-1"
                        />
                        <rect
                            y="120.7"
                            width="256"
                            height="14.6"
                            class="topbar__toggle-middle-2"
                        />
                        <rect
                            y="193.8"
                            width="256"
                            height="14.6"
                        />
                    </svg>
                </button>
            </div>
        );
    }

    protected onWindowScroll = () => {
        this.fixed = window.scrollY > 0;
    };

    @listen('click', '.topbar__toggle')
    protected onToggleClick() {
        this.open = !this.open;
    }
}
