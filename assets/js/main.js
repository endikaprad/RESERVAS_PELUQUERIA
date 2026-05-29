// ============================================================
//  PRADO BARBER CO. — main.js
// ============================================================

// API_BASE se declara en booking.js para evitar conflictos de scope.
// En otras páginas (sin booking.js) se define aquí como fallback:
if (typeof API_BASE === 'undefined') {
    window.API_BASE = './backend/api';
}

// ===== NAVBAR =====
const navbar    = document.querySelector('.navbar');
const hamburger = document.querySelector('.hamburger');
const navLinks  = document.querySelector('.nav-links');

if (navbar) {
    window.addEventListener('scroll', () => {
        navbar.classList.toggle('scrolled', window.scrollY > 60);
    });
}

if (hamburger && navLinks) {
    hamburger.addEventListener('click', () => {
        const isOpen = navLinks.classList.toggle('open');

        hamburger.querySelectorAll('span')[0].style.transform = isOpen ? 'rotate(45deg) translate(4px, 4px)'   : '';
        hamburger.querySelectorAll('span')[1].style.opacity   = isOpen ? '0' : '';
        hamburger.querySelectorAll('span')[2].style.transform = isOpen ? 'rotate(-45deg) translate(4px, -4px)' : '';
        document.body.style.overflow = isOpen ? 'hidden' : '';

        if (isOpen) {
            navLinks.querySelectorAll('a').forEach((a, i) => {
                a.style.opacity    = '0';
                a.style.transform  = 'translateY(18px)';
                a.style.transition = 'none';
                requestAnimationFrame(() => requestAnimationFrame(() => {
                    a.style.transition = `opacity 0.4s ease ${i * 0.07}s, transform 0.4s ease ${i * 0.07}s`;
                    a.style.opacity    = '1';
                    a.style.transform  = 'translateY(0)';
                }));
            });
        } else {
            navLinks.querySelectorAll('a').forEach(a => { a.style.cssText = ''; });
        }
    });

    navLinks.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            navLinks.classList.remove('open');
            navLinks.querySelectorAll('a').forEach(a => { a.style.cssText = ''; });
            hamburger.querySelectorAll('span').forEach(s => {
                s.style.transform = '';
                s.style.opacity   = '';
            });
            document.body.style.overflow = '';
        });
    });
}

// ===== ACTIVE NAV LINK =====
function setActiveNavLink() {
    const page = location.pathname.split('/').pop() || 'index.html';
    document.querySelectorAll('.nav-links a').forEach(a => {
        a.classList.toggle('active', a.getAttribute('href') === page);
    });
}
setActiveNavLink();

// ===== SCROLL REVEAL =====
const revealObserver = window._revealObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('revealed');
            revealObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.12 });
document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));

// ===== TOAST =====
function showToast(message, icon = '✦') {
    let toast = document.querySelector('.toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.className = 'toast';
        document.body.appendChild(toast);
    }
    toast.innerHTML = `<span class="toast-icon">${icon}</span><span>${message}</span>`;
    toast.classList.add('show');
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => toast.classList.remove('show'), 3500);
}

// ===== SMOOTH ANCHOR SCROLL =====
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', e => {
        const target = document.querySelector(anchor.getAttribute('href'));
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

// ===== COUNTER ANIMATION =====
function animateCounter(el) {
    const target   = parseFloat(el.dataset.target);
    const isDecimal= el.dataset.decimal === 'true';
    const suffix   = el.dataset.suffix || '';
    const duration = 1400;
    const start    = performance.now();
    function update(now) {
        const progress = Math.min((now - start) / duration, 1);
        const eased    = 1 - Math.pow(1 - progress, 3);
        const value    = eased * target;
        el.textContent = (isDecimal ? value.toFixed(1) : Math.floor(value)) + suffix;
        if (progress < 1) requestAnimationFrame(update);
    }
    requestAnimationFrame(update);
}
const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            animateCounter(entry.target);
            counterObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.5 });
document.querySelectorAll('[data-target]').forEach(el => counterObserver.observe(el));

// ===== PRÓXIMA DISPONIBILIDAD (widget del index) =====
async function loadNextAvailable() {
    const titleEl = document.getElementById('hv-next-title');
    const subEl   = document.getElementById('hv-next-sub');
    if (!titleEl) return;

    titleEl.textContent = 'Cargando…';
    subEl.textContent   = '';

    try {
        const res  = await fetch(`${window.API_BASE}/next-available.php`);
        const json = await res.json();

        if (json.ok && json.data.barbero) {
            const d = json.data;
            titleEl.textContent = d.etiqueta;
            subEl.textContent   = `${d.barbero} · Corte Clásico`;
        } else {
            titleEl.textContent = 'Consultar disponibilidad';
            subEl.textContent   = 'Llámanos o escríbenos';
        }
    } catch (e) {
        titleEl.textContent = 'Hoy · 17:00';
        subEl.textContent   = 'Endika Prado · Corte Clásico';
    }
}

document.addEventListener('DOMContentLoaded', loadNextAvailable);

window.showToast = showToast;

// ===== MAGNETIC BUTTONS (Emil Kowalski) =====
function initMagneticButtons() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    if (window.matchMedia('(hover: none)').matches) return;

    const STRENGTH = 0.35;

    document.querySelectorAll('.btn-primary, .btn-blue, .nav-cta').forEach(btn => {
        let raf = null;

        btn.addEventListener('mousemove', e => {
            cancelAnimationFrame(raf);
            raf = requestAnimationFrame(() => {
                const rect   = btn.getBoundingClientRect();
                const cx     = rect.left + rect.width  / 2;
                const cy     = rect.top  + rect.height / 2;
                const dx     = (e.clientX - cx) * STRENGTH;
                const dy     = (e.clientY - cy) * STRENGTH;
                btn.style.transform = `translate(${dx}px, ${dy}px)`;
                btn.style.transition = 'transform 0.15s ease';
            });
        });

        btn.addEventListener('mouseleave', () => {
            cancelAnimationFrame(raf);
            btn.style.transform = '';
            btn.style.transition = 'transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1)';
        });
    });
}

// ===== SERVICIOS DESTACADOS EN INICIO =====
(async function loadHomeServices() {
    const grid = document.getElementById('home-services-grid');
    if (!grid) return;

    const FEATURED = ['Corte Clásico', 'Arreglo de Barba', 'Corte + Barba', 'Sesión Premium'];
    const delays   = ['reveal-delay-1', 'reveal-delay-2', 'reveal-delay-3', 'reveal-delay-4'];

    try {
        const res  = await fetch(`${window.API_BASE}/servicios.php`);
        const json = await res.json();
        if (!json.ok) return;

        // Mantener el orden definido en FEATURED
        const map      = Object.fromEntries(json.data.map(s => [s.nombre, s]));
        const selected = FEATURED.map(n => map[n]).filter(Boolean);
        if (!selected.length) return;

        grid.innerHTML = selected.map((s, i) => {
            const precio   = Number.isInteger(s.precio) ? s.precio : s.precio.toFixed(2).replace('.', ',');
            const duracion = `${s.duracion} minutos`;
            return `
            <div class="service-card reveal ${delays[i] || ''}">
                <div class="service-card-body">
                    <div class="service-card-header">
                        <span class="service-name">${s.nombre}</span>
                        <span class="service-price">${precio} €</span>
                    </div>
                    <div class="service-duration">${duracion}</div>
                </div>
            </div>`;
        }).join('');

        // Re-aplicar observer de reveal a las nuevas tarjetas
        if (window._revealObserver) {
            grid.querySelectorAll('.reveal').forEach(el => window._revealObserver.observe(el));
        }
    } catch (_) { /* mantiene las tarjetas estáticas de fallback */ }
})();

document.addEventListener('DOMContentLoaded', initMagneticButtons);