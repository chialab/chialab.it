import { Component, customElement, listen, observe, state } from '@chialab/dna';

@customElement('skua-menu')
export class SkuaMenu extends Component {
    @state({
        type: Boolean,
        attribute: ':open',
    })
    protected _open = false;

    /**
     * The list of items of the menu.
     * @returns The list of slotted items.
     */
    get items(): HTMLElement[] {
        return this.childNodesBySlot('item').filter((node) => node.nodeType === Node.ELEMENT_NODE) as HTMLElement[];
    }

    open() {
        this._open = true;
        this.dispatchEvent('open');
    }

    close() {
        this._open = false;
        this.dispatchEvent('close');
    }

    /** Close the dialog on content click. */
    @listen('click', '.trigger-btn')
    toggle() {
        this._open = !this._open;
    }

    @observe('_open')
    protected onOpenChange(open: boolean) {
        if (open) {
            this.dispatchEvent('open');
        } else {
            this.dispatchEvent('close');
        }
    }

    render() {
        return (
            <>
                <button
                    class="trigger-btn"
                    type="button"
                    aria-label={this._open ? 'Chiudi menu' : 'Apri menu'}
                    aria-haspopup="menu">
                    <dna-icon name="menu" />
                </button>
                <div
                    class="menu-items"
                    role="menu"
                    aria-hidden={this._open ? 'false' : 'true'}>
                    {this.items.map((node) => (
                        <div
                            ref={node}
                            role="menuitem"
                        />
                    ))}
                </div>
            </>
        );
    }
}
