/**
 * Reveals the "+" (`.card-more`) next to a clamped `.card-description` when its text has
 * actually been visually truncated by the CSS line-clamp — there's no reliable way to know this
 * server-side (it depends on the real rendered width/wrapping), so it's checked here at runtime
 * via `scrollHeight` vs `clientHeight`. `.card-more` is always rendered (so its layout footprint
 * stays reserved either way) with the glyph itself hidden until this adds `card-more--visible`.
 *
 * Call again after appending new cards to the DOM (e.g. content fetched by the carousel).
 */
export function markOverflowingCards(root: ParentNode = document): void {
    root.querySelectorAll<HTMLElement>('.card-description.clamp-5').forEach((description) => {
        const more = description.nextElementSibling;
        if (!more?.classList.contains('card-more')) {
            return;
        }

        if (description.scrollHeight > description.clientHeight + 1) {
            more.classList.add('card-more--visible');
        }
    });
}
