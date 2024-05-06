import { render } from '@chialab/dna';
import { Backdrop } from '@chialab/dna-backdrop';
import { ControlsList, Slideshow } from '@chialab/dna-slideshow';

export function openModal(sources: string[], currentSrc: string = sources[0]) {
    const backdrop = new Backdrop();
    const slideshow = new Slideshow();
    document.body.appendChild(backdrop);

    render(
        <dna-slideshow
            ref={slideshow}
            class="mono"
            controls
            current={sources.indexOf(currentSrc)}
            controlsList={[ControlsList.noarrows, ControlsList.noplayback]}>
            {sources.map((src) => (
                <img
                    src={src}
                    alt=""
                />
            ))}
        </dna-slideshow>,
        backdrop
    );

    backdrop.show();
    backdrop.addEventListener('close', () => {
        // wait animation end
        setTimeout(() => {
            backdrop.remove();
        }, 500);
    });

    setTimeout(() => {
        slideshow.focus();
    });
}
