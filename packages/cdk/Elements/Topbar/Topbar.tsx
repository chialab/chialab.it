import { Component, customElement, property, state } from '@chialab/dna';

@customElement('cl-topbar')
export class Topbar extends Component {
    @property({
        type: String,
    })
    url: string = '/';

    @property({
        type: String,
    })
    title: string = 'chialab';

    @property({
        type: String,
    })
    tooltip: string = 'Back to home';

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
                    class="mono bold f-2">
                    {this.title}
                </a>
                <div class="row topbar__links">
                    <nav class="row gap-0 align-center">
                        <span
                            class="f-2 mr-1 mono bold text-accent"
                            aria-hidden="true">
                            ẞ
                        </span>
                        <slot />
                    </nav>
                    <div class="row gap-0 align-center">
                        <span
                            class="f-2 mr-1 mono"
                            aria-hidden="true">
                            ß
                        </span>
                        <slot name="locale" />
                    </div>
                </div>
            </div>
        );
    }

    protected onWindowScroll = () => {
        this.fixed = window.scrollY > 0;
    };
}
