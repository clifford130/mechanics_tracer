const MT_UX = {
    // ... existing initProgress, initButtonLoaders, hideSplash ...
};

/**
 * Unified Loader Controller
 */
const MT_Loader = {
    // Global Splash (Gmail-style)
    showGlobal(message = 'Loading Dashboard...') {
        let loader = document.getElementById('system-initial-loader');
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'system-initial-loader';
            loader.innerHTML = `
                <div class="loader-logo"><i class="fas fa-wrench" style="margin-right:10px;"></i>MechanicTracer</div>
                <div class="loader-bar-container"><div class="loader-bar-fill"></div></div>
                <div id="loader-msg" style="margin-top:15px; color:#64748b; font-size:0.9rem;">${message}</div>
            `;
            document.body.appendChild(loader);
        } else {
            document.getElementById('loader-msg').textContent = message;
            loader.style.opacity = '1';
            loader.style.visibility = 'visible';
        }
    },

    hideGlobal() {
        const loader = document.getElementById('system-initial-loader');
        if (loader) {
            loader.style.opacity = '0';
            loader.style.visibility = 'hidden';
            setTimeout(() => loader.remove(), 500);
        }
    },

    // Button Spinners
    showButton(btn) {
        if (!btn) return;
        btn.classList.add('btn-loading');
        btn.disabled = true;
    },

    hideButton(btn) {
        if (!btn) return;
        btn.classList.remove('btn-loading');
        btn.disabled = false;
    },

    // Section Loaders
    showSection(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        container.classList.add('loading-container');
        let loader = container.querySelector('.section-loader');
        if (!loader) {
            loader = document.createElement('div');
            loader.className = 'section-loader';
            loader.innerHTML = '<div class="spinner-ring"></div>';
            container.appendChild(loader);
        }
        setTimeout(() => loader.classList.add('active'), 10);
    },

    hideSection(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;
        const loader = container.querySelector('.section-loader');
        if (loader) {
            loader.classList.remove('active');
            setTimeout(() => {
                loader.remove();
                container.classList.remove('loading-container');
            }, 300);
        }
    }
};

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    MT_UX.initProgress();
    // Only auto-init if not already handling manually
    if (!window.LOADER_MANUAL_INIT) {
        MT_UX.initButtonLoaders();
        MT_UX.hideSplash();
    }
});
