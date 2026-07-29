/* Configuração centralizada dos serviços de flashcards */
(function() {
    'use strict';

    window.FlashcardsConfig = {
        // URL do serviço FSRS Node.js
        FSRS_SERVICE_URL: 'https://flashcards.olavoadriel.com.br',

        // Token compartilhado (deve ser o mesmo configurado no servidor)
        FSRS_SERVICE_TOKEN: '' // Deixe vazio, será setado pelo PHP via meta tag se necessário
    };

    // Se existir meta tag com a URL, use dela (para permitir override do PHP)
    const fsrsUrlMeta = document.querySelector('meta[name="flashcards:fsrs-url"]');
    if (fsrsUrlMeta) {
        window.FlashcardsConfig.FSRS_SERVICE_URL = fsrsUrlMeta.getAttribute('content');
    }

    // Se existir meta tag com o token, use dela (raramente necessário no frontend)
    const fsrsTokenMeta = document.querySelector('meta[name="flashcards:fsrs-token"]');
    if (fsrsTokenMeta) {
        window.FlashcardsConfig.FSRS_SERVICE_TOKEN = fsrsTokenMeta.getAttribute('content');
    }

    console.log('[Flashcards] Configurado para:', window.FlashcardsConfig.FSRS_SERVICE_URL);
})();
