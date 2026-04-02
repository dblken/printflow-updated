/* ============================================================
   Premium Theme Modals (Alert & Confirm Override)
   PrintFlow — Clean, Async, Professional
   ============================================================ */

window.ThemeModal = {
    _modal: null,
    _resolve: null,

    init() {
        if (document.getElementById('pf-theme-modal')) return;

        const html = `
            <div id="pf-theme-modal" class="pf-modal-overlay" style="display:none;">
                <div class="pf-modal-container">
                    <div class="pf-modal-header">
                        <div id="pf-modal-icon" class="pf-modal-icon">!</div>
                        <h2 id="pf-modal-title" class="pf-modal-title">System Alert</h2>
                    </div>
                    <div id="pf-modal-content" class="pf-modal-content">
                        Message contents here...
                    </div>
                    <div class="pf-modal-actions">
                        <button id="pf-modal-btn-cancel" class="pf-modal-btn pf-modal-btn-cancel">Cancel</button>
                        <button id="pf-modal-btn-confirm" class="pf-modal-btn pf-modal-btn-confirm">OK</button>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', html);
        this._modal = document.getElementById('pf-theme-modal');
        
        document.getElementById('pf-modal-btn-confirm').onclick = () => this._handleAction(true);
        document.getElementById('pf-modal-btn-cancel').onclick = () => this._handleAction(false);
        this._modal.onclick = (e) => {
            if (e.target === this._modal) this._handleAction(false);
        };
    },

    show(msg, type = 'alert', title = 'System Alert') {
        this.init();
        const iconEl = document.getElementById('pf-modal-icon');
        const titleEl = document.getElementById('pf-modal-title');
        const contentEl = document.getElementById('pf-modal-content');
        const cancelBtn = document.getElementById('pf-modal-btn-cancel');
        const confirmBtn = document.getElementById('pf-modal-btn-confirm');

        contentEl.innerText = msg;
        titleEl.innerText = title;
        
        // Setup UI based on type
        iconEl.className = 'pf-modal-icon';
        if (type === 'confirm') {
            iconEl.classList.add('pf-modal-icon-help');
            iconEl.innerText = '?';
            cancelBtn.style.display = 'inline-flex';
            confirmBtn.innerText = 'Confirm';
        } else if (type === 'error') {
             iconEl.classList.add('pf-modal-icon-error');
             iconEl.innerText = '!';
             cancelBtn.style.display = 'none';
             confirmBtn.innerText = 'OK';
        } else {
            iconEl.innerText = '!';
            cancelBtn.style.display = 'none';
            confirmBtn.innerText = 'OK';
        }

        this._modal.style.display = 'flex';
        // Force reflow for transition
        this._modal.offsetHeight; 
        this._modal.classList.add('active');

        return new Promise((resolve) => {
            this._resolve = resolve;
        });
    },

    _handleAction(result) {
        this._modal.classList.remove('active');
        setTimeout(() => {
            if (!this._modal.classList.contains('active')) {
                this._modal.style.display = 'none';
            }
        }, 300);
        
        if (this._resolve) {
            const res = this._resolve;
            this._resolve = null;
            res(result);
        }
    }
};

/**
 * Global Replacement for Confirm (Safe for onclick="return pfConfirm(...)")
 * 1. Checks if the element already has a stored "confirmed" state
 * 2. If not, prevents default and shows custom modal
 * 3. On modal success, re-triggers the click
 */
window.pfConfirm = function(msg, element) {
    if (element && element.dataset.pfConfirmed === 'true') {
        delete element.dataset.pfConfirmed;
        return true;
    }

    ThemeModal.show(msg, 'confirm', 'Please Confirm').then(confirmed => {
        if (confirmed && element) {
            element.dataset.pfConfirmed = 'true';
            element.click(); // Re-trigger the click
        }
    });

    return false; // Blocks native navigation
};

/**
 * Standard replacements
 */
window.pfAlert = (msg, title) => ThemeModal.show(msg, 'alert', title);
window.pfError = (msg) => ThemeModal.show(msg, 'error', 'Error');

// Global Overrides to catch native calls automatically
window.alert = function(msg) {
    ThemeModal.show(msg, 'alert', 'System Alert');
};

// Note: confirm is still tricky due to being synchronous, 
// but we override it to point to pfConfirm for manual calls.
// For native confirm usage in 'onclick', pfConfirm(msg, this) must be used.
window.confirm = function(msg) {
    console.warn("Native confirm() called. Use pfConfirm(msg, this) for better theme integration.");
    return ThemeModal.show(msg, 'confirm', 'Please Confirm'); // Returns a Promise
};
