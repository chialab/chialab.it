import { type AsyncEvent, delegateEventListener } from '@chialab/dna';
import './exhibitions/carousel';
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
