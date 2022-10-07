import { customElement, Component, state, listen } from '@chialab/dna';
import { ButtonVariant } from '@chialab/dna-button';
import { __ } from '@chialab/dna-theming';

@customElement('cl-card-details')
export class CardDetails extends Component {
    @state({
        type: Boolean,
    }) expanded: boolean = false;

    get cardDescriptionElement() {
        return this.querySelector('.card__description');
    }

    render() {
        return <>
            <slot name="cover"></slot>
            <div class="column w-full p-2">
                <slot name="details"></slot>
                {this.expanded && <slot name="extra"></slot>}
                {this.cardDescriptionElement && <div class="card-details__commands w-full row end">
                    <button is="dna-button"
                        class="card-details__toggle"
                        variant={ButtonVariant.action}
                        icon={this.expanded ? 'close' : 'plus'}
                        aria-label={this.expanded ? __('collapse') : __('expand')}
                    />
                </div>}
            </div>
        </>;
    }

    forceUpdate() {
        super.forceUpdate();

        const element = this.cardDescriptionElement;
        if (!element) {
            return;
        }
        if (this.expanded) {
            element.classList.remove('clamp-4');
        } else {
            element.classList.add('clamp-4');
        }
    }

    @listen('click', '.card__cover, .card__title, .card__description .body, .card-details__toggle')
    protected onToggleClick() {
        this.expanded = !this.expanded;
    }
}
