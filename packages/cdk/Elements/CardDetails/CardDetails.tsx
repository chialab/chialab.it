import { Component, customElement, listen, state } from '@chialab/dna';
import { ButtonVariant } from '@chialab/dna-button';
import { __ } from '@chialab/dna-i18n';

@customElement('cl-card-details')
export class CardDetails extends Component {
    @state({
        type: Boolean,
        attribute: ':expanded',
    })
    expanded: boolean = false;

    get cardDescriptionElement() {
        const details = this.realm.childNodes.find(
            (node) => node.nodeType === Node.ELEMENT_NODE && (node as HTMLElement).getAttribute('slot') === 'details'
        ) as HTMLElement;
        return details?.querySelector('.card__description');
    }

    render() {
        return (
            <>
                <slot name="cover"></slot>
                <div class="column w-full p-2">
                    <slot name="details"></slot>
                    {this.expanded && <slot name="extra"></slot>}
                    <div class="row w-full no-wrap justify align-end">
                        <div class="pb-1">
                            <slot name="footer" />
                        </div>
                        {this.cardDescriptionElement && (
                            <div class="card-details__commands">
                                <button
                                    is="dna-button"
                                    class="card-details__toggle"
                                    variant={ButtonVariant.action}
                                    icon={this.expanded ? 'close' : 'plus'}
                                    aria-label={this.expanded ? __('collapse') : __('expand')}
                                />
                            </div>
                        )}
                    </div>
                </div>
            </>
        );
    }

    forceUpdate() {
        super.forceUpdate();

        const element = this.cardDescriptionElement;
        if (!element) {
            return;
        }
        Array.from(element.querySelectorAll('a, button')).forEach((child) => {
            if (this.expanded) {
                child.removeAttribute('tabindex');
            } else {
                child.setAttribute('tabindex', '-1');
            }
        });
        if (this.expanded) {
            element.classList.remove('clamp-4');
        } else {
            element.classList.add('clamp-4');
        }
    }

    @listen('click', '.card__cover, .card__title, .card__description .description, .card-details__toggle')
    protected onToggleClick(event: MouseEvent) {
        if ((event.target as HTMLElement).closest('a')) {
            return;
        }
        this.expanded = !this.expanded;
    }
}
