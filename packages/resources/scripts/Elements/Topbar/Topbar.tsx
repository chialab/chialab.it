import { window, customElement, Component, property, state, listen } from '@chialab/dna';

@customElement('cl-topbar')
export class Topbar extends Component {
    @property({
        type: String,
    }) url: string = '/';

    @property({
        type: String,
    }) title: string = 'chialab';

    @property({
        type: String,
    }) tooltip: string = 'Back to home';

    @state({
        type: Boolean,
        attribute: ':fixed',
    }) fixed: boolean = false;

    render() {
        return <div class="viewport column gap-xs py-3">
            <a href={this.url} title={this.tooltip} class="mono bold f-2">{this.title}</a>
            <div class="row topbar__links">
                <nav class="row gap-0 align-center">
                    <span class="f-2 mr-1 mono bold text-accent" aria-hidden="true">ẞ</span>
                    <slot></slot>
                </nav>
                <div class="row gap-0 align-center">
                    <span class="f-2 mr-1 mono" aria-hidden="true">ß</span>
                    <slot name="locale"></slot>
                </div>
            </div>
        </div>;
    }

    @listen('scroll', window)
    protected onWindowScroll() {
        this.fixed = window.scrollY > 0;
    }
}
