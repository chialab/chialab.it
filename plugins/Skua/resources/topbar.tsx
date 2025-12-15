import { Component, customElement, listen, state } from '@chialab/dna';

@customElement('skua-topbar')
export class SkuaTopbar extends Component {
    @state({
        type: Boolean,
        attribute: ':expanded',
    })
    expanded = false;

    @state({
        type: Boolean,
        attribute: ':sticky',
    })
    sticky: boolean = false;

    /**
     * The list of items of the menu.
     * @returns The list of slotted items.
     */
    get items(): HTMLElement[] {
        return this.childNodesBySlot().filter((node) => node.nodeType === Node.ELEMENT_NODE) as HTMLElement[];
    }

    @listen('click', '.trigger-btn')
    toggle() {
        this.expanded = !this.expanded;
    }

    render() {
        return (
            <header>
                <nav class="mono">
                    <a
                        class="logo"
                        href="/">
                        <svg
                            width="70"
                            height="57"
                            viewBox="0 0 70 57"
                            fill="black"
                            xmlns="http://www.w3.org/2000/svg">
                            <path d="M49.9991 43.6916C49.9991 41.8594 48.5063 40.3737 46.6653 40.3737C44.8244 40.3737 43.3316 41.8594 43.3316 43.6916C43.3316 45.5238 44.8244 47.0094 46.6653 47.0094C48.5063 47.0094 49.9991 45.5238 49.9991 43.6916ZM53.1679 43.7471C53.1679 47.3759 50.2117 50.318 46.5656 50.318C42.9196 50.318 39.9634 47.3759 39.9634 43.7471C39.9634 40.1184 42.9196 37.1763 46.5656 37.1763C50.2117 37.1763 53.1679 40.1184 53.1679 43.7471ZM69.9987 46.9142V40.5814H59.5775C58.1698 34.8202 53.0616 30.5274 46.8355 30.5274C46.8089 30.5274 30.2267 31.6677 22.2726 19.0009C17.9114 12.057 20.9208 1.18797 20.9208 1.18797L14.3185 0C14.3185 0 9.78447 15.6751 18.8525 33.5661C18.8525 33.5661 8.83009 30.6372 6.68205 14.8033L0 16.15C1.59109 27.9464 15.0336 57 46.6933 57C52.9951 57 58.1845 52.6992 59.5895 46.9142H70H69.9987Z" />
                        </svg>
                        <strong>SKUA</strong>
                        <span>1852</span>
                    </a>
                    <div class="menu">
                        <button
                            class="trigger-btn"
                            type="button"
                            aria-label={this.expanded ? 'Chiudi menu' : 'Apri menu'}
                            title={this.expanded ? 'Chiudi menu' : 'Apri menu'}
                            aria-haspopup="menu">
                            <dna-icon name="menu" />
                        </button>
                        <div
                            class="menu-items"
                            role="menu"
                            aria-hidden={this.expanded ? 'false' : 'true'}>
                            {this.items.map((node) => (
                                <div
                                    ref={node}
                                    role="menuitem"
                                />
                            ))}
                        </div>
                    </div>
                </nav>
            </header>
        );
    }
}
