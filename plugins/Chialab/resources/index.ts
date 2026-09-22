import { openModal } from '@chialab/cdk';
import { delegateEventListener, type AsyncEvent } from '@chialab/dna';
import './exhibitions/carousel';

delegateEventListener(document.body, 'click', 'img[data-modal]', (event, target) => {
    const parent = (target as HTMLElement).parentElement as HTMLElement;
    const group = (target as HTMLElement).dataset.modal;
    const sources = Array.from(parent.querySelectorAll(`img[data-modal="${group}"]`)).map(
        (element) => (element as HTMLImageElement).src
    );

    openModal(sources, (target as HTMLImageElement).src);
});

// Serves further pages of items requested by a `dna-carousel` (see `exhibitions/carousel.tsx`).
window.addEventListener('fetch', (event) => {
    const asyncEvent = event as AsyncEvent & CustomEvent<number>;
    const carousel = asyncEvent.target as HTMLElement;
    const dataUrl = carousel.getAttribute('data');
    if (!dataUrl) {
        return;
    }

    const url = `${dataUrl}?page=${asyncEvent.detail}`;
    asyncEvent.respondWith(async () => {
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) {
            return [];
        }

        const text = await response.text();
        if (!text) {
            return [];
        }

        const wrapper = document.createElement('div');
        wrapper.innerHTML = text;

        return Array.from(wrapper.children);
    });
});
