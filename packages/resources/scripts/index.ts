import '@chialab/dna-masonry';
import './Elements/Topbar/Topbar';

const updateViewportSize = () => {
    document.documentElement.style.setProperty('--window-width', `${document.body.clientWidth}px`);
};

updateViewportSize();
window.addEventListener('resize', updateViewportSize);
