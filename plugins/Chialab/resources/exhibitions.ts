import { type AsyncEvent, delegateEventListener } from '@chialab/dna';
import { globalIconset } from '@chialab/dna-icons';
import { Carousel } from './exhibitions/carousel';
import './exhibitions/card-description';

// Serves further pages of items requested by a `exhibition-carousel` (see `exhibitions/carousel.tsx`).
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

delegateEventListener(document.body, 'click', '.back', (event) => {
    if (history.length) {
        event.preventDefault();
        history.back();
    }
});

// Not part of the iconset: two copies of `chevron-left`, side by side.
const chevronLeftPath = 'M10 16L20 6 21.4 7.4 12.8 16 21.4 24.6 20 26z';
globalIconset.registerIcon(
    'chevron-double-left',
    `<svg viewBox="0 0 32 32"><path transform="translate(-4 0)" d="${chevronLeftPath}"></path><path transform="translate(4 0)" d="${chevronLeftPath}"></path></svg>`
);

// Brings the exhibition carousel back to its first item, without reloading the page.
delegateEventListener(document.body, 'click', 'a.start', (event) => {
    const carousel = document.querySelector('exhibition-carousel');
    if (carousel instanceof Carousel) {
        event.preventDefault();
        carousel.scrollToStart();
    }
});
