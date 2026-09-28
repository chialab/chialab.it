/**
 * Sizes `--caption-height` (the extra room the carousel container leaves below the shared card
 * row for media captions, see `exhibitions/index.css`) to fit the tallest caption actually
 * present, since captions are left free to grow to whatever height they need and there's no way
 * to know that ahead of time from the server: it depends on the real rendered width/wrapping.
 *
 * Call again after appending new cards to the DOM (e.g. content fetched by the carousel) or after
 * anything that could change how the captions wrap (e.g. a resize).
 */
export function updateCaptionHeight(carousel: HTMLElement): void {
    let max = 0;
    carousel
        .querySelectorAll<HTMLElement>(
            ".card[data-type='images'] .card-description, .card[data-type='videos'] .card-description"
        )
        .forEach((description) => {
            max = Math.max(max, description.scrollHeight);
        });

    if (max > 0) {
        carousel.style.setProperty('--caption-height', `${max}px`);
    }
}
