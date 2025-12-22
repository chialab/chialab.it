import { globalIconset, IconAddLarge, IconCloseLarge, IconSubtractLarge } from '@chialab/dna-icons';

globalIconset.registerIcons({
    'marker-skua': `<svg width="70" height="57" viewBox="0 0 70 57" fill="none" xmlns="http://www.w3.org/2000/svg">
        <text class="marker-index" x="50%" y="0">1</text>
        <path d="M49.9991 43.6916C49.9991 41.8594 48.5063 40.3737 46.6653 40.3737C44.8244 40.3737 43.3316 41.8594 43.3316 43.6916C43.3316 45.5238 44.8244 47.0094 46.6653 47.0094C48.5063 47.0094 49.9991 45.5238 49.9991 43.6916ZM53.1679 43.7471C53.1679 47.3759 50.2117 50.318 46.5656 50.318C42.9196 50.318 39.9634 47.3759 39.9634 43.7471C39.9634 40.1184 42.9196 37.1763 46.5656 37.1763C50.2117 37.1763 53.1679 40.1184 53.1679 43.7471ZM69.9987 46.9142V40.5814H59.5775C58.1698 34.8202 53.0616 30.5274 46.8355 30.5274C46.8089 30.5274 30.2267 31.6677 22.2726 19.0009C17.9114 12.057 20.9208 1.18797 20.9208 1.18797L14.3185 0C14.3185 0 9.78447 15.6751 18.8525 33.5661C18.8525 33.5661 8.83009 30.6372 6.68205 14.8033L0 16.15C1.59109 27.9464 15.0336 57 46.6933 57C52.9951 57 58.1845 52.6992 59.5895 46.9142H70H69.9987Z" />
    </svg>`,
    'marker-point': `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <text class="marker-index" x="25%" y="100%">1</text>
        <circle cx="12" cy="12" r="12" fill="var(--marker-primary-fill, black)"/>
        <circle cx="12" cy="12" r="12" fill="var(--marker-primary-fill, black)"/>
        <circle cx="12" cy="12" r="7" fill="white"/>
        <circle cx="12" cy="12" r="3" fill="var(--marker-primary-fill, black)"/>
    </svg>`,
    'Map.zoomIn': IconAddLarge,
    'Map.zoomOut': IconSubtractLarge,
    'close-large': IconCloseLarge,
    'Slides.arrowNext': `<svg width="18" height="33" viewBox="0 0 18 33" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M1 32L16 16.5L0.999997 1" stroke="white" stroke-width="2" stroke-linecap="round"/>
    </svg>`,
    'Slides.arrowPrevious': `<svg width="18" height="33" viewBox="0 0 18 33" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M16.3916 1L1.39159 16.5L16.3916 32" stroke="white" stroke-width="2" stroke-linecap="round"/>
    </svg>`,
    'arrow-previous-full': `<svg width="29" height="45" viewBox="0 0 29 45" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M1.37286 19.2031L22.2825 0.991447C24.8708 -1.26283 28.9097 0.575454 28.9097 4.00777V40.4311C28.9097 43.8634 24.8708 45.7017 22.2825 43.4474L1.37286 25.2358C-0.457645 23.6415 -0.457645 20.7974 1.37286 19.2031Z" fill="black"/>
    </svg>`,
    'arrow-next-full': `<svg width="29" height="45" viewBox="0 0 29 45" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M27.5368 25.2358L6.62712 43.4474C4.03888 45.7017 -4.68238e-07 43.8634 -7.68301e-07 40.4311L-3.95253e-06 4.00778C-4.25259e-06 0.575457 4.03887 -1.26283 6.62712 0.99145L27.5368 19.2031C29.3673 20.7974 29.3673 23.6415 27.5368 25.2358Z" fill="white"/>
    </svg>`,
    'menu': `<svg width="63" height="34" viewBox="0 0 63 34" fill="none" xmlns="http://www.w3.org/2000/svg">
        <g filter="url(#filter0_d_33_19)">
            <path d="M57.0727 1H4.22168L58.2217 13.2791H4.22168L58.2217 25H5.37062" stroke="black" stroke-width="2" stroke-linecap="round"/>
        </g>
        <defs>
            <filter id="filter0_d_33_19" x="0" y="0" width="62.4434" height="34" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
                <feFlood flood-opacity="0" result="BackgroundImageFix"/>
                <feColorMatrix in="SourceAlpha" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 127 0" result="hardAlpha"/>
                <feOffset dy="4"/>
                <feGaussianBlur stdDeviation="2"/>
                <feComposite in2="hardAlpha" operator="out"/>
                <feColorMatrix type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0.25 0"/>
                <feBlend mode="normal" in2="BackgroundImageFix" result="effect1_dropShadow_33_19"/>
                <feBlend mode="normal" in="SourceGraphic" in2="effect1_dropShadow_33_19" result="shape"/>
            </filter>
        </defs>
    </svg>`,
});
