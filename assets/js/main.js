// ============================================================
// SecondChance Mart - Main JavaScript
// Handles: Cart AJAX, quantity controls, toast notifications
// ============================================================

'use strict';

/* ── Auth Modal ─────────────────────────────────────────── */
let _pendingProductId = null; // remember cart add attempted before login

function openAuthModal(tab) {
    const modal = document.getElementById('authModal');
    if (!modal) { window.location.href = '/login.php'; return; }
    // Clear previous messages
    ['auth-login-msg','auth-reg-msg'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.classList.add('d-none'); el.textContent = ''; }
    });
    switchAuthTab(tab || 'login');
    bootstrap.Modal.getOrCreateInstance(modal).show();
}

function switchAuthTab(tab) {
    const loginPane = document.getElementById('tab-login');
    const regPane   = document.getElementById('tab-register');
    const loginBtn  = document.getElementById('tab-login-btn');
    const regBtn    = document.getElementById('tab-register-btn');
    if (!loginPane) return;
    if (tab === 'register') {
        loginPane.classList.add('d-none');    loginPane.classList.remove('active');
        regPane.classList.remove('d-none');   regPane.classList.add('active');
        loginBtn.classList.remove('active');  regBtn.classList.add('active');
    } else {
        regPane.classList.add('d-none');      regPane.classList.remove('active');
        loginPane.classList.remove('d-none'); loginPane.classList.add('active');
        regBtn.classList.remove('active');    loginBtn.classList.add('active');
    }
}

function toggleModalPwd(inputId, iconId) {
    const f = document.getElementById(inputId);
    const i = document.getElementById(iconId);
    if (!f) return;
    if (f.type === 'password') { f.type = 'text';     i.classList.replace('fa-eye','fa-eye-slash'); }
    else                       { f.type = 'password'; i.classList.replace('fa-eye-slash','fa-eye'); }
}

function _setAuthMsg(elId, msg, type) {
    const el = document.getElementById(elId);
    if (!el) return;
    el.className = 'alert alert-' + type + ' py-2 small mb-3';
    el.textContent = msg;
    el.classList.remove('d-none');
}

function modalLogin(e) {
    e.preventDefault();
    const btn = document.getElementById('loginBtn');
    const email = document.getElementById('login-email').value;
    const pwd   = document.getElementById('login-password').value;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Signing in…';

    fetch('/api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'login', email, password: pwd })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('authModal'))?.hide();
            showToast('Welcome back, ' + data.name + '! 👋', 'success');
            if (_pendingProductId) {
                const pid = _pendingProductId; _pendingProductId = null;
                setTimeout(() => addToCart(pid, null), 500);
            } else {
                setTimeout(() => location.reload(), 700);
            }
        } else {
            _setAuthMsg('auth-login-msg', data.message || 'Login failed.', 'danger');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i>Sign In';
        }
    })
    .catch(() => {
        _setAuthMsg('auth-login-msg', 'Connection error. Please try again.', 'danger');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i>Sign In';
    });
}

function modalRegister(e) {
    e.preventDefault();
    const btn = document.getElementById('registerBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Creating account…';

    fetch('/api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action:           'register',
            first_name:       document.getElementById('reg-first').value,
            last_name:        document.getElementById('reg-last').value,
            email:            document.getElementById('reg-email').value,
            password:         document.getElementById('reg-password').value,
            confirm_password: document.getElementById('reg-confirm').value,
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const regEmail = document.getElementById('reg-email').value;
            setTimeout(() => {
                const loginEmail = document.getElementById('login-email');
                if (loginEmail) loginEmail.value = regEmail;
                switchAuthTab('login');
                _setAuthMsg('auth-login-msg', '✅ Account created! Sign in to continue.', 'success');
            }, 800);
        } else {
            _setAuthMsg('auth-reg-msg', data.message || 'Registration failed.', 'danger');
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-user-plus me-2"></i>Create Account';
    })
    .catch(() => {
        _setAuthMsg('auth-reg-msg', 'Connection error. Please try again.', 'danger');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-user-plus me-2"></i>Create Account';
    });
}

/* ── Toast Notification ─────────────────────────────────── */
/**
 * Show a Bootstrap toast message at the bottom-right.
 * @param {string} message - Message to display
 * @param {string} type - 'success', 'danger', 'warning', 'info'
 */
function showToast(message, type = 'success') {
    // Create toast container if it doesn't exist
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const id = 'toast-' + Date.now();
    const icons = { success: '✅', danger: '❌', warning: '⚠️', info: 'ℹ️' };

    const toastHtml = `
        <div id="${id}" class="toast align-items-center text-white bg-${type} border-0 mb-2" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    ${icons[type] || ''} ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>`;

    container.insertAdjacentHTML('beforeend', toastHtml);
    const toastEl = document.getElementById(id);
    const bsToast = new bootstrap.Toast(toastEl, { delay: 3500 });
    bsToast.show();

    // Remove from DOM after it hides
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}

/* ── Add to Cart (AJAX) ─────────────────────────────────── */
/**
 * Send a request to the cart API to add a product.
 * Updates the cart badge in the navbar without page reload.
 */
function addToCart(productId, btn) {
    if (!productId) return;

    const originalHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    }

    fetch('/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'add', product_id: productId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Added to cart!', 'success');
            updateCartBadge(data.cart_count);
        } else if (data.redirect) {
            // Not logged in — store product and open auth modal
            _pendingProductId = productId;
            openAuthModal('login');
        } else {
            showToast(data.message || 'Could not add to cart.', 'danger');
        }
    })
    .catch(() => showToast('Connection error. Please try again.', 'danger'))
    .finally(() => {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    });
}

/* ── Update Cart Badge ──────────────────────────────────── */
function updateCartBadge(count) {
    const badge = document.querySelector('.cart-badge');
    if (count > 0) {
        if (badge) {
            badge.textContent = count;
            badge.style.display = 'flex';
        } else {
            // Create badge if it doesn't exist
            const cartLink = document.querySelector('.cart-link');
            if (cartLink) {
                const newBadge = document.createElement('span');
                newBadge.className = 'cart-badge badge bg-warning text-dark position-absolute top-0 start-100 translate-middle';
                newBadge.textContent = count;
                cartLink.style.position = 'relative';
                cartLink.appendChild(newBadge);
            }
        }
    } else if (badge) {
        badge.style.display = 'none';
    }
}

/* ── Cart Quantity Controls ─────────────────────────────── */
/**
 * Update the quantity of a cart item via AJAX.
 * @param {number} cartId - The cart row ID
 * @param {number} quantity - New quantity value
 */
function updateCartQty(cartId, quantity) {
    if (quantity < 1) {
        removeFromCart(cartId);
        return;
    }
    fetch('/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update', cart_id: cartId, quantity: quantity })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Update line total display
            const lineEl = document.querySelector(`[data-line="${cartId}"]`);
            if (lineEl) lineEl.textContent = data.line_total;
            // Update order summary
            if (data.subtotal) {
                const subEl = document.getElementById('cart-subtotal');
                if (subEl) subEl.textContent = data.subtotal;
            }
            updateCartBadge(data.cart_count);
        } else {
            showToast(data.message || 'Update failed.', 'warning');
        }
    })
    .catch(() => showToast('Connection error.', 'danger'));
}

/**
 * Remove an item from the cart entirely.
 */
function removeFromCart(cartId) {
    fetch('/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'remove', cart_id: cartId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Remove the table row with animation
            const row = document.querySelector(`tr[data-cart="${cartId}"]`);
            if (row) {
                row.style.transition = 'opacity 0.3s, height 0.3s';
                row.style.opacity = '0';
                setTimeout(() => {
                    row.remove();
                    checkEmptyCart();
                    // Reload to recalculate totals accurately
                    location.reload();
                }, 300);
            }
            updateCartBadge(data.cart_count);
            showToast('Item removed from cart.', 'info');
        }
    })
    .catch(() => showToast('Connection error.', 'danger'));
}

/** Show empty cart message if all items removed */
function checkEmptyCart() {
    const tbody = document.querySelector('.cart-table tbody');
    if (tbody && tbody.children.length === 0) {
        const emptyMsg = document.getElementById('cart-empty');
        const cartContent = document.getElementById('cart-content');
        if (emptyMsg) emptyMsg.style.display = 'block';
        if (cartContent) cartContent.style.display = 'none';
    }
}

/* ── Quantity Input Buttons ─────────────────────────────── */
// Handle + / - buttons for quantity inputs
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.qty-btn');
    if (!btn) return;

    const input = btn.parentElement.querySelector('.qty-input');
    if (!input) return;

    const step    = btn.dataset.action === 'plus' ? 1 : -1;
    const newVal  = Math.max(1, parseInt(input.value || 1) + step);
    const cartId  = input.dataset.cartId;
    const maxQty  = parseInt(input.max || 999);

    if (step > 0 && newVal > maxQty) {
        showToast('Not enough stock available.', 'warning');
        return;
    }

    input.value = newVal;
    if (cartId) updateCartQty(parseInt(cartId), newVal);
});

// Handle direct quantity input change
document.addEventListener('change', function(e) {
    if (!e.target.classList.contains('qty-input')) return;
    const val    = Math.max(1, parseInt(e.target.value) || 1);
    const cartId = e.target.dataset.cartId;
    e.target.value = val;
    if (cartId) updateCartQty(parseInt(cartId), val);
});

/* ── Payment Method Selection ───────────────────────────── */
document.addEventListener('click', function(e) {
    const option = e.target.closest('.payment-option');
    if (!option) return;

    // Deselect all
    document.querySelectorAll('.payment-option').forEach(o => {
        o.classList.remove('selected');
        const radio = o.querySelector('input[type="radio"]');
        if (radio) radio.checked = false;
    });

    // Select clicked
    option.classList.add('selected');
    const radio = option.querySelector('input[type="radio"]');
    if (radio) radio.checked = true;
});

/* ── Auto-dismiss Flash Alerts ──────────────────────────── */
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 5000); // Auto-close after 5 seconds
    });
});

/* ── Countdown for Near Expiry Products ─────────────────── */
document.querySelectorAll('[data-expiry]').forEach(el => {
    const expiry = new Date(el.dataset.expiry);
    const today  = new Date();
    const days   = Math.ceil((expiry - today) / (1000 * 60 * 60 * 24));
    if (days <= 7 && days >= 0) {
        el.textContent = days === 0 ? 'Expires today!' : `Expires in ${days} day${days === 1 ? '' : 's'}`;
        el.style.color = days <= 3 ? '#e74c3c' : '#e67e22';
    }
});

/* ── Admin Sidebar Toggle (Mobile) ─────────────────────── */
const sidebarToggle = document.getElementById('sidebarToggle');
if (sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
        document.getElementById('adminSidebar')?.classList.toggle('show');
    });
}

/* ── Product Search (Live) ──────────────────────────────── */
// Highlight matching text in search results
const searchInput = document.querySelector('input[name="search"]');
if (searchInput && searchInput.value) {
    const term = searchInput.value.toLowerCase();
    document.querySelectorAll('.product-name').forEach(el => {
        const text = el.textContent;
        const re = new RegExp(`(${term})`, 'gi');
        el.innerHTML = text.replace(re, '<mark>$1</mark>');
    });
}

/* ── Smooth Scroll for Category Anchors ─────────────────── */
document.querySelectorAll('a[href^="#"]').forEach(link => {
    link.addEventListener('click', e => {
        const target = document.querySelector(link.getAttribute('href'));
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

/* ── Image Error Fallback ───────────────────────────────── */
document.querySelectorAll('img.product-img, .product-img-wrap img').forEach(img => {
    img.addEventListener('error', function() {
        this.src = 'https://placehold.co/400x300/27ae60/ffffff?text=No+Image';
    });
});
