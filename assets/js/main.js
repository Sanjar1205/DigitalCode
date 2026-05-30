/**
 * CodeAcademy - Asosiy JavaScript
 */

// ═══════════════════════════════════════════════════════════
// THEME (DARK/LIGHT MODE)
// ═══════════════════════════════════════════════════════════
(function() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    
    document.addEventListener('DOMContentLoaded', () => {
        const toggle = document.querySelector('.theme-toggle');
        if (toggle) {
            updateThemeIcon(savedTheme);
            toggle.addEventListener('click', () => {
                const current = document.documentElement.getAttribute('data-theme');
                const newTheme = current === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                updateThemeIcon(newTheme);
            });
        }
    });
    
    function updateThemeIcon(theme) {
        const toggle = document.querySelector('.theme-toggle i');
        if (toggle) {
            toggle.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
    }
})();

// ═══════════════════════════════════════════════════════════
// MOBILE SIDEBAR
// ═══════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('show');
        });
        
        // Tashqarini bosishda yopish
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 992 && 
                !sidebar.contains(e.target) && 
                !menuToggle.contains(e.target) &&
                sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        });
    }
});

// ═══════════════════════════════════════════════════════════
// CONFIRM TUGMA
// ═══════════════════════════════════════════════════════════
document.addEventListener('click', (e) => {
    const confirmBtn = e.target.closest('[data-confirm]');
    if (confirmBtn) {
        const message = confirmBtn.dataset.confirm || 'Rostdan ham bajarmoqchimisiz?';
        if (!confirm(message)) {
            e.preventDefault();
            e.stopPropagation();
        }
    }
});

// ═══════════════════════════════════════════════════════════
// TOAST NOTIFICATION
// ═══════════════════════════════════════════════════════════
function showToast(message, type = 'info', duration = 3500) {
    const container = getToastContainer();
    const toast = document.createElement('div');
    toast.className = `toast-item toast-${type}`;
    toast.innerHTML = `
        <i class="fas ${getToastIcon(type)}"></i>
        <span>${message}</span>
        <button class="toast-close">&times;</button>
    `;
    container.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 10);
    
    const close = () => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    };
    
    toast.querySelector('.toast-close').addEventListener('click', close);
    setTimeout(close, duration);
}

function getToastContainer() {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = `
            position: fixed; top: 20px; right: 20px;
            z-index: 9999; display: flex; flex-direction: column;
            gap: 0.75rem; max-width: 400px;
        `;
        document.body.appendChild(container);
        
        // Toast stillari
        const style = document.createElement('style');
        style.textContent = `
            .toast-item {
                background: white; padding: 1rem 1.25rem;
                border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.15);
                display: flex; align-items: center; gap: 0.75rem;
                transform: translateX(110%); transition: transform 0.3s;
                border-left: 4px solid #6B7280;
            }
            .toast-item.show { transform: translateX(0); }
            .toast-item.toast-success { border-left-color: #10B981; }
            .toast-item.toast-error, .toast-item.toast-danger { border-left-color: #EF4444; }
            .toast-item.toast-warning { border-left-color: #F59E0B; }
            .toast-item.toast-info { border-left-color: #3B82F6; }
            .toast-item .toast-close {
                background: none; border: none; font-size: 1.25rem;
                cursor: pointer; color: #9CA3AF; margin-left: auto;
            }
            [data-theme="dark"] .toast-item { background: #1E293B; color: #F1F5F9; }
        `;
        document.head.appendChild(style);
    }
    return container;
}

function getToastIcon(type) {
    return {
        'success': 'fa-check-circle',
        'error': 'fa-exclamation-circle',
        'danger': 'fa-exclamation-circle',
        'warning': 'fa-exclamation-triangle',
        'info': 'fa-info-circle'
    }[type] || 'fa-info-circle';
}

// ═══════════════════════════════════════════════════════════
// AJAX YORDAMCHI
// ═══════════════════════════════════════════════════════════
async function apiPost(url, data = {}) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrfToken && !data.csrf_token) {
        data.csrf_token = csrfToken;
    }
    
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        return await response.json();
    } catch (e) {
        console.error('API xatolik:', e);
        return { success: false, message: 'Tarmoq xatosi' };
    }
}

async function apiGet(url) {
    try {
        const response = await fetch(url);
        return await response.json();
    } catch (e) {
        console.error('API xatolik:', e);
        return { success: false };
    }
}

// ═══════════════════════════════════════════════════════════
// FORMA YUBORISH
// ═══════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-ajax]').forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = form.querySelector('[type="submit"]');
            const originalText = submitBtn?.innerHTML;
            
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Yuborilmoqda...';
            }
            
            try {
                const formData = new FormData(form);
                const response = await fetch(form.action, {
                    method: form.method || 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    showToast(result.message || 'Muvaffaqiyatli', 'success');
                    if (result.redirect) {
                        setTimeout(() => location.href = result.redirect, 1000);
                    } else if (form.dataset.reload) {
                        setTimeout(() => location.reload(), 1000);
                    }
                } else {
                    showToast(result.message || 'Xatolik yuz berdi', 'error');
                }
            } catch (err) {
                showToast('Tarmoq xatosi', 'error');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            }
        });
    });
});

// ═══════════════════════════════════════════════════════════
// FORMAT - vaqt, raqamlar
// ═══════════════════════════════════════════════════════════
function formatTime(seconds) {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}:${s.toString().padStart(2, '0')}`;
}

function formatNumber(num) {
    return new Intl.NumberFormat('uz-UZ').format(num);
}
