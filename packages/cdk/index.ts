import './theme';
import '@chialab/dna-masonry';
import './Elements/Topbar/Topbar';
import './Elements/CardDetails/CardDetails';

const updateViewportSize = () => {
    document.documentElement.style.setProperty('--window-width', `${document.body.clientWidth}px`);
};

updateViewportSize();
window.addEventListener('resize', updateViewportSize);

export * from './Lib/openModal';
