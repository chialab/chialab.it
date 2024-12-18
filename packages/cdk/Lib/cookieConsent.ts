import * as CookieConsent from 'vanilla-cookieconsent';

// Enable dark mode
document.documentElement.classList.add('cc--darkmode');

CookieConsent.run({
    guiOptions: {
        consentModal: {
            layout: 'box',
            position: 'bottom left',
            equalWeightButtons: true,
            flipButtons: false,
        },
        preferencesModal: {
            layout: 'box',
            position: 'right',
            equalWeightButtons: true,
            flipButtons: false,
        },
    },
    categories: {
        necessary: {
            readOnly: true,
        },
        analytics: {},
    },
    language: {
        default: 'en',
        autoDetect: 'browser',
        translations: {
            en: {
                consentModal: {
                    title: 'We use cookies!',
                    description:
                        'This website uses essential cookies to ensure proper navigation and tracking cookies to understand how these pages are used. The latter are enabled only if you agree to their use.',
                    acceptAllBtn: 'Accept all',
                    acceptNecessaryBtn: 'Reject all',
                    showPreferencesBtn: 'Manage preferences',
                    footer: '<a href="https://www.chialab.it/privacy-policy">Privacy Policy</a>',
                },
                preferencesModal: {
                    title: 'Consent Preferences Center',
                    acceptAllBtn: 'Accept all',
                    acceptNecessaryBtn: 'Reject all',
                    savePreferencesBtn: 'Save preferences',
                    closeIconLabel: 'Close modal',
                    serviceCounterLabel: 'Service|Services',
                    sections: [
                        {
                            title: 'Cookie Usage',
                            description:
                                'Cookies are used to ensure the basic functionality of this website and to improve the browsing experience. You can choose which ones to enable or disable for each category. For more details on cookies and other sensitive data you can consult our <a href="https://www.chialab.it/privacy-policy">privacy policy</a>.',
                        },
                        {
                            title: 'Strictly Necessary Cookies <span class="pm__badge">Always Enabled</span>',
                            description:
                                'These cookies are essential for the proper functioning of the site. Without them the site would not work properly. But they do not track anything sensitive.',
                            linkedCategory: 'necessary',
                        },
                        {
                            title: 'Analytics Cookies',
                            description:
                                'These cookies allow the website to remember your previous visits and to anonymously analyze user behavior on the pages to understand what to improve and correct. Nothing is transmitted to third parties and nothing is used for advertising, promotional or profiling purposes.',
                            linkedCategory: 'analytics',
                        },
                        {
                            title: 'More information',
                            description:
                                'For any questions regarding our cookie policy you can <a class="cc-link" href="mailto:info@chialab.it">contact us here</a>.',
                        },
                    ],
                },
            },
            it: {
                consentModal: {
                    title: 'Usiamo dei cookie!',
                    description:
                        'Questo sito web utilizza dei cookie essenziali per assicurare il buon funzionamento della navigazione e dei cookie di tracciamento per capire come queste pagine sono utilizzate. Questi ultimi vengono abilitati solo nel caso in cui acconsenti al loro uso.',
                    acceptAllBtn: 'Accetta tutti',
                    acceptNecessaryBtn: 'Rifiuta tutti',
                    showPreferencesBtn: 'Gestisci preferenze',
                    footer: '<a href="https://www.chialab.it/privacy-policy">Informativa sulla privacy</a>',
                },
                preferencesModal: {
                    title: 'Centro preferenze per il consenso',
                    acceptAllBtn: 'Accetta tutto',
                    acceptNecessaryBtn: 'Rifiuta tutto',
                    savePreferencesBtn: 'Salva le preferenze',
                    closeIconLabel: 'Chiudi la finestra',
                    serviceCounterLabel: 'Servizi',
                    sections: [
                        {
                            title: 'Uso dei Cookie',
                            description:
                                'I cookie sono utilizzati per assicurare le funzionalità di base di questo sito web e per migliorare l\'esperienza di navigazione. Puoi scegliere per ogni categoria quali abilitare o disabilitare. Per maggiori dettagli relativi ai cookie e altri dati sensibili puoi consultare la nostra <a href="https://www.chialab.it/privacy-policy">privacy policy</a>.',
                        },
                        {
                            title: 'Cookie strettamente necessari <span class="pm__badge">Sempre Attivati</span>',
                            description:
                                'Questi cookie sono essenziali per il corretto funzionamento del sito. Senza di essi il sito non funzionerebbe a dovere. Ma non tracciano nulla di sensibile.',
                            linkedCategory: 'necessary',
                        },
                        {
                            title: 'Cookie di analisi e statistici',
                            description:
                                'Questi cookie permettono al sito web di ricordare le tue visite precedenti e di analizzzare in modo anonimo i comportamenti degli utenti sulle pagine per capire cosa migliorare e correggere. Nulla viene trasmesso a terzi e nulla è usato a fini pubblicitari, promozionali o di profilazione.',
                            linkedCategory: 'analytics',
                        },
                        {
                            title: 'Maggiori informazioni',
                            description:
                                'Per qualsiasi domanda riguardo alla nostra policy sui cookie puoi <a class="cc-link" href="mailto:info@chialab.it">contattarci qui</a>.',
                        },
                    ],
                },
            },
        },
    },
});
