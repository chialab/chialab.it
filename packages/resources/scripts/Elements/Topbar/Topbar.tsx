import { customElement, Component } from '@chialab/dna';

@customElement('cl-topbar')
export class Topbar extends Component {
    render() {
        return <div class="viewport column py-2">
            <span class="mono f-2">chialab</span>
            <nav class="row">
                <slot></slot>
            </nav>
        </div>;
    }
}
