/**
 * Set the current marker based on the current step ID
 * @param currentID Current step ID
 */
function setCurrentMarker(currentID: string) {
    const markers = Array.from(document.querySelectorAll('[marker-symbol="marker-skua"]') ?? []) as HTMLElement[];
    markers.forEach((marker) => {
        const isCurrentMarker = marker.classList.contains(`marker-${currentID}`);
        marker.toggleAttribute('data-current', isCurrentMarker);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const map = document.querySelector('dna-map');
    if (!map) {
        return;
    }

    map.controls = true;
    map.addEventListener('load', () => {
        const firstStep = document.querySelector('dna-map-scroller-step');
        if (firstStep) {
            setCurrentMarker(firstStep.dataset.id || '');
        }
        const storyScroller = document.querySelector('dna-story-scroller');
        // @ts-ignore
        storyScroller?.addEventListener('change', (ev: CustomEvent) => {
            // aspetta che la mappa abbia finito di muoversi, altrimenti il marker potrebbe non essere ancora trovabile dal querySelector
            map.addEventListener('moveend', () => {
                setCurrentMarker(ev.detail.dataset.id || '');
            });
        });
    });
});
