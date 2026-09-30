/**
 * Tiny per-id state store backed by `history.state`, used to restore a carousel's scroll
 * position and loaded content when navigating back to the page (bfcache/back-forward nav).
 */
export interface CarouselState {
    page: number;
    content: string;
    scroll: number;
}

export function getState(id: string): CarouselState | null {
    const state = history.state as Record<string, CarouselState> | null;
    if (!state?.[id]) {
        return null;
    }

    return state[id];
}

export function setState(id: string, data: CarouselState): void {
    const state = (history.state as Record<string, CarouselState> | null) ?? {};
    state[id] = data;
    history.replaceState(state, document.title);
}

export function unsetState(id: string): boolean {
    const state = history.state as Record<string, CarouselState> | null;
    if (!state?.[id]) {
        return false;
    }

    delete state[id];
    history.replaceState(state, document.title);

    return true;
}
