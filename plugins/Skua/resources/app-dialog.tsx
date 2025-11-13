import { Component, customElement, listen } from '@chialab/dna';
import { Dialog } from '@chialab/dna-dialog';

@customElement('app-dialog')
export class AppDialog extends Component {
    readonly dialog: Dialog = new Dialog();

    @listen('click', '.close-btn')
    hide() {
        this.dialog?.hide();
    }

    show() {
        this.dialog?.show();
    }

    render() {
        return (
            <dna-dialog ref={this.dialog}>
                <button
                    class="close-btn"
                    aria-label="chiudi pannello">
                    <dna-icon name="close-filled" />
                </button>
                <slot />
            </dna-dialog>
        );
    }
}
