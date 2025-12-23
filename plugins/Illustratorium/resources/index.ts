import { openModal } from '@chialab/cdk';
import { delegateEventListener } from '@chialab/dna';

delegateEventListener(document.body, 'click', 'img[data-modal]', (event, target) => {
    const parent = (target as HTMLElement).closest('.illustrator-gallery') as HTMLElement;
    const group = (target as HTMLElement).dataset.modal;
    const sources = Array.from(parent.querySelectorAll(`img[data-modal="${group}"]`)).map(
        (element) => (element as HTMLImageElement).src
    );

    openModal(sources, (target as HTMLImageElement).src);
});
