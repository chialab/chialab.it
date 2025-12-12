import { Component, customElement, listen } from '@chialab/dna';
import { Dialog } from '@chialab/dna-dialog';

@customElement('app-dialog')
export class AppDialog extends Component {
    readonly dialog: Dialog = new Dialog();

    @listen('click', '.close-btn')
    hide() {
        this.dialog?.hide();
        this.dispatchEvent('close');
    }

    show() {
        this.dialog?.show();
    }

    /** Close the dialog on content click. */
    @listen('click', '.dialog__content')
    onContentClick(event: MouseEvent) {
        event.stopPropagation();
        event.preventDefault();
        this.hide();
    }

    render() {
        return (
            <dna-dialog ref={this.dialog}>
                <slot />
            </dna-dialog>
        );
    }
}
