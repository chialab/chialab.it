import { customElement, Component } from '@chialab/dna';

@customElement('cl-topbar')
export class Topbar extends Component {
    render() {
        return <div class="viewport column gap-xs py-2">
            <span class="mono bold f-2">chialab</span>
            <nav class="row gap-0 align-center">
                <span class="f-2 mr-1 mono bold text-accent" aria-hidden="true">ẞ</span>
                <slot></slot>
            </nav>
        </div>;
    }
}
