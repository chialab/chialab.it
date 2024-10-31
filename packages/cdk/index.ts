import './theme';
import '@chialab/dna-code';
import '@chialab/dna-masonry';
import '@chialab/dna-qrcode';
import '@chialab/dna-slideshow';
import './Elements/Topbar/Topbar';
import './Elements/CardDetails/CardDetails';

const updateViewportSize = () => {
    document.documentElement.style.setProperty('--window-width', `${document.body.clientWidth}px`);
};

updateViewportSize();
window.addEventListener('resize', updateViewportSize);

export * from './Lib/openModal';
