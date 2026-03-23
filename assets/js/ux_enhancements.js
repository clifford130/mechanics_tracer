const MT_UX = {
    initProgress() { /* Disabled */ },
    initButtonLoaders() { /* Disabled */ },
    hideSplash() { 
        const loader = document.getElementById('system-initial-loader');
        if (loader) loader.style.display = 'none';
    }
};

/**
 * Unified Loader Controller - DISABLED
 */
const MT_Loader = {
    showGlobal(message = '') { /* Disabled */ },
    hideGlobal() { 
        const loader = document.getElementById('system-initial-loader');
        if (loader) loader.remove();
    },
    showButton(btn) { /* Disabled */ },
    hideButton(btn) { /* Disabled */ },
    showSection(containerId) { /* Disabled */ },
    hideSection(containerId) { /* Disabled */ }
};

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    // Hidden by CSS anyway, but let's ensure it's handled
    MT_UX.hideSplash();
});

