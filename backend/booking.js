// ============================================================
//  PRADO BARBER CO. — booking.js
// ============================================================

window.API_BASE = window.API_BASE || './backend/api';

// ===== DATA =====
const SERVICES = [
    { id: 'corte',       name: 'Corte Clásico',    duration: '30 min', price: 18 },
    { id: 'barba',       name: 'Arreglo de Barba', duration: '20 min', price: 12 },
    { id: 'corte-barba', name: 'Corte + Barba',    duration: '50 min', price: 26 },
    { id: 'degradado',   name: 'Degradado',         duration: '40 min', price: 22 },
    { id: 'afeitado',    name: 'Afeitado Navaja',   duration: '30 min', price: 20 },
    { id: 'premium',     name: 'Sesión Premium',    duration: '75 min', price: 45 },
];

const BARBERS = [
    { id: 'endika', name: 'Endika Prado', spec: 'Fundador · Especialista en degradados', initials: 'EP' },
    { id: 'marcos', name: 'Marcos Vila',  spec: 'Barba & Navaja',                        initials: 'MV' },
    { id: 'alex',   name: 'Alex Ramos',   spec: 'Corte clásico & Fade',                  initials: 'AR' },
];

const TIME_SLOTS = [
    '09:00','09:30','10:00','10:30','11:00','11:30',
    '12:00','12:30','13:00','13:30',
    '16:00','16:30','17:00','17:30','18:00','18:30','19:00','19:30',
];

// ===== STATE =====
const booking = {
    step: 1,
    service: null,
    barber: null,
    date: null,
    time: null,
    client: { name: '', phone: '', email: '', notes: '' },
};

let calendarDate = new Date();
let takenSlots   = [];
let loadingSlots = false;

// ===== VALIDACIÓN =====
// Reglas por campo
const RULES = {
    name: {
        // Solo letras, tildes, ñ/Ñ y espacios, mínimo 2 caracteres
        blockKey:   /^[0-9!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?`~]$/,
        cleanPaste: v => v.replace(/[^a-zA-ZÀ-ÿ\u00f1\u00d1\s]/g, ''),
        validate:   v => {
            if (!v.trim()) return { ok: false, msg: 'El nombre es obligatorio.' };
            if (!/^[a-zA-ZÀ-ÿ\u00f1\u00d1\s]{2,}$/.test(v.trim()))
                return { ok: false, msg: 'El nombre solo puede contener letras.' };
            return { ok: true };
        },
    },
    phone: {
        // Solo dígitos, +, guión y espacios
        blockKey:   /^[a-zA-ZÀ-ÿ!@#$%^&*()_=\[\]{};':"\\|,.<>\/?`~]$/,
        cleanPaste: v => v.replace(/[^\d+\-\s]/g, ''),
        validate:   v => {
            if (!v.trim()) return { ok: false, msg: 'El teléfono es obligatorio.' };
            const digits = v.replace(/\D/g, '');
            if (digits.length < 9)
                return { ok: false, msg: 'El teléfono debe tener al menos 9 dígitos.' };
            return { ok: true };
        },
    },
    email: {
        blockKey:   null,
        cleanPaste: null,
        validate:   v => {
            if (!v.trim()) return { ok: false, msg: 'El email es obligatorio.' };
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.trim()))
                return { ok: false, msg: 'Introduce un email válido (ej: nombre@dominio.com).' };
            return { ok: true };
        },
    },
};

// ===== HELPERS VISUALES =====
// El mensaje de error va en un div FUERA del input, justo debajo del form-group
function getErrorEl(fieldKey) {
    // Buscamos el contenedor hermano con id "error-{key}"
    return document.getElementById(`error-${fieldKey}`);
}

function showError(fieldKey, msg) {
    const input = document.getElementById(`client-${fieldKey}`);
    const errEl = getErrorEl(fieldKey);
    if (input) {
        input.style.borderColor = 'var(--red)';
        input.style.boxShadow   = '0 0 0 3px rgba(212,43,43,0.2)';
    }
    if (errEl) {
        errEl.textContent = '⚠ ' + msg;
        errEl.style.display = 'block';
    }
}

function showOk(fieldKey) {
    const input = document.getElementById(`client-${fieldKey}`);
    const errEl = getErrorEl(fieldKey);
    if (input) {
        input.style.borderColor = 'rgba(34,197,94,0.55)';
        input.style.boxShadow   = '0 0 0 3px rgba(34,197,94,0.12)';
    }
    if (errEl) {
        errEl.textContent = '';
        errEl.style.display = 'none';
    }
}

function clearState(fieldKey) {
    const input = document.getElementById(`client-${fieldKey}`);
    const errEl = getErrorEl(fieldKey);
    if (input) {
        input.style.borderColor = '';
        input.style.boxShadow   = '';
    }
    if (errEl) {
        errEl.textContent = '';
        errEl.style.display = 'none';
    }
}

// ===== VALIDAR UN CAMPO =====
// mode: 'silent' → solo actualiza botón sin pintar
//       'blur'   → pinta error si hay contenido incorrecto, limpia si vacío
//       'submit' → pinta siempre, incluso si vacío
function validateField(key, mode) {
    const input = document.getElementById(`client-${key}`);
    if (!input) return false;
    const val    = input.value;
    const result = RULES[key].validate(val);

    booking.client[key] = val;

    if (mode === 'silent') {
        if (result.ok) clearState(key);
        return result.ok;
    }
    if (mode === 'blur') {
        if (!val.trim()) { clearState(key); return false; }
        if (result.ok)   showOk(key);
        else             showError(key, result.msg);
        return result.ok;
    }
    if (mode === 'submit') {
        if (result.ok) showOk(key);
        else           showError(key, result.msg);
        return result.ok;
    }
    return result.ok;
}

function validateAll(mode) {
    const keys = ['name', 'phone', 'email'];
    const results = keys.map(k => validateField(k, mode));
    const allOk = results.every(Boolean);
    const btn = document.getElementById('btn-confirm');
    if (btn) btn.disabled = !allOk;
    return allOk;
}

// ===== INYECTAR DIVS DE ERROR EN EL DOM =====
// Los insertamos una sola vez al entrar al step 4
function injectErrorDivs() {
    Object.keys(RULES).forEach(key => {
        if (document.getElementById(`error-${key}`)) return; // ya existe
        const input = document.getElementById(`client-${key}`);
        if (!input) return;
        const div = document.createElement('div');
        div.id = `error-${key}`;
        div.style.cssText = [
            'display:none',
            'color:var(--red)',
            'font-size:.75rem',
            'margin-top:.4rem',
            'padding:.35rem .6rem',
            'background:rgba(212,43,43,0.07)',
            'border-left:2px solid var(--red)',
            'border-radius:0 4px 4px 0',
            'line-height:1.4',
        ].join(';');
        // Insertar después del form-group que contiene el input
        const group = input.closest('.form-group') || input.parentElement;
        group.parentNode.insertBefore(div, group.nextSibling);
    });
}

// ===== SETUP LISTENERS FORMULARIO =====
function syncClientForm() {
    injectErrorDivs();

    // Restaurar valores y limpiar estado visual
    Object.keys(RULES).forEach(key => {
        const input = document.getElementById(`client-${key}`);
        if (!input) return;
        input.value = booking.client[key] || '';
        clearState(key);

        // Eliminar listeners previos clonando
        const fresh = input.cloneNode(true);
        input.parentNode.replaceChild(fresh, input);
        fresh.value = booking.client[key] || '';

        // --- keydown: bloquear caracteres no permitidos ---
        if (RULES[key].blockKey) {
            fresh.addEventListener('keydown', function(e) {
                if (RULES[key].blockKey.test(e.key)) {
                    e.preventDefault();
                }
            });
        }

        // --- paste: limpiar texto pegado ---
        if (RULES[key].cleanPaste) {
            fresh.addEventListener('paste', function() {
                setTimeout(() => {
                    fresh.value = RULES[key].cleanPaste(fresh.value);
                    booking.client[key] = fresh.value;
                    validateAll('silent');
                }, 0);
            });
        }

        // --- input: validar en silencio para habilitar botón ---
        fresh.addEventListener('input', function() {
            booking.client[key] = fresh.value;
            // Si ya había error visible, revalidar en blur-mode para actualizar
            const errEl = getErrorEl(key);
            if (errEl && errEl.style.display !== 'none') {
                validateField(key, 'blur');
            }
            validateAll('silent');
        });

        // --- blur: mostrar error si el campo tiene contenido incorrecto ---
        fresh.addEventListener('blur', function() {
            booking.client[key] = fresh.value;
            validateField(key, 'blur');
            validateAll('silent');
        });
    });

    // Notas (sin validación)
    const notesEl = document.getElementById('client-notes');
    if (notesEl) {
        const freshNotes = notesEl.cloneNode(true);
        notesEl.parentNode.replaceChild(freshNotes, notesEl);
        freshNotes.value = booking.client.notes || '';
        freshNotes.addEventListener('input', () => { booking.client.notes = freshNotes.value; });
    }

    // Estado inicial del botón (campos vacíos → disabled)
    validateAll('silent');
}

// ===== STEP NAV =====
function goToStep(n) {
    if (n < 1 || n > 5) return;
    booking.step = n;
    document.querySelectorAll('.step-panel').forEach((p, i) => {
        p.classList.toggle('active', i + 1 === n);
    });
    updateStepsBar();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function updateStepsBar() {
    document.querySelectorAll('.step-circle').forEach((el, i) => {
        const stepN = i + 1;
        el.classList.remove('active', 'done');
        if (stepN < booking.step)        { el.classList.add('done');   el.textContent = '✓'; }
        else if (stepN === booking.step) { el.classList.add('active'); el.textContent = stepN; }
        else                             { el.textContent = stepN; }
    });
    document.querySelectorAll('.step-line').forEach((el, i) => {
        el.classList.toggle('done', i + 1 < booking.step);
    });
}

// ===== STEP 1 =====
function renderServices() {
    const grid = document.getElementById('service-grid');
    if (!grid) return;
    grid.innerHTML = SERVICES.map(s => `
        <div class="service-option ${booking.service?.id === s.id ? 'selected' : ''}"
             data-service-id="${s.id}">
          <div class="svc-name">${s.name}</div>
          <div class="svc-meta">${s.duration}</div>
          <div class="svc-price">${s.price} €</div>
        </div>`).join('');
}

function selectService(id) {
    booking.service = SERVICES.find(s => s.id === id);
    renderServices();
    const btn = document.getElementById('btn-next-1');
    if (btn) btn.disabled = false;
}

// ===== STEP 2 =====
function renderBarbers() {
    const grid = document.getElementById('barber-grid');
    if (!grid) return;
    grid.innerHTML = BARBERS.map(b => `
        <div class="barber-option ${booking.barber?.id === b.id ? 'selected' : ''}"
             data-barber-id="${b.id}">
          <div class="barber-avatar-book">${b.initials}</div>
          <div class="b-name">${b.name}</div>
          <div class="b-spec">${b.spec}</div>
        </div>`).join('');
}

function selectBarber(id) {
    booking.barber = BARBERS.find(b => b.id === id);
    renderBarbers();
    const btn = document.getElementById('btn-next-2');
    if (btn) btn.disabled = false;
    if (booking.date) loadTakenSlots();
}

// ===== STEP 3 =====
function renderCalendar() {
    const grid  = document.getElementById('cal-grid');
    const title = document.getElementById('cal-title');
    if (!grid) return;

    const year  = calendarDate.getFullYear();
    const month = calendarDate.getMonth();
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const MONTHS = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                    'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    if (title) title.textContent = `${MONTHS[month]} ${year}`;

    const firstDay    = new Date(year, month, 1).getDay();
    const offset      = (firstDay + 6) % 7;
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    let html = '';
    for (let i = 0; i < offset; i++) html += `<div class="cal-cell empty"></div>`;
    for (let d = 1; d <= daysInMonth; d++) {
        const date     = new Date(year, month, d);
        const isToday  = date.getTime() === today.getTime();
        const isPast   = date < today;
        const isSunday = date.getDay() === 0;
        const disabled = isPast || isSunday;
        const selDate  = booking.date;
        const isSelected = selDate &&
            selDate.getDate() === d &&
            selDate.getMonth() === month &&
            selDate.getFullYear() === year;

        let cls = 'cal-cell';
        if (disabled)   cls += ' disabled';
        if (isToday)    cls += ' today';
        if (isSelected) cls += ' selected';

        const dataAttr = disabled ? '' : `data-cal-day="${d}" data-cal-month="${month}" data-cal-year="${year}"`;
        html += `<div class="${cls}" ${dataAttr}>${d}</div>`;
    }
    grid.innerHTML = html;
}

function selectDate(y, m, d) {
    booking.date = new Date(y, m, d);
    booking.time = null;
    renderCalendar();
    loadTakenSlots();
}

async function loadTakenSlots() {
    if (!booking.date || !booking.barber) { takenSlots = []; renderTimeSlots(); return; }
    loadingSlots = true;
    renderTimeSlotsLoading();
    try {
        const res  = await fetch(`${window.API_BASE}/slots.php?fecha=${formatDate(booking.date)}&barbero=${booking.barber.id}`);
        const json = await res.json();
        takenSlots = json.ok ? json.data.ocupadas : [];
    } catch(e) {
        console.warn('slots error', e);
        takenSlots = [];
    } finally {
        loadingSlots = false;
        renderTimeSlots();
    }
}

function renderTimeSlotsLoading() {
    const wrap = document.getElementById('time-slots');
    if (!wrap) return;
    wrap.innerHTML = `<div style="grid-column:1/-1;text-align:center;color:var(--color-muted);font-size:.82rem;padding:1.5rem 0;">Cargando horarios…</div>`;
}

function renderTimeSlots() {
    const wrap = document.getElementById('time-slots');
    if (!wrap) return;
    const now  = new Date();
    const isToday  = booking.date && booking.date.toDateString() === now.toDateString();
    const currentHHMM = `${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}`;
    const esSabado = booking.date && booking.date.getDay() === 6;

    wrap.innerHTML = TIME_SLOTS
        .filter(t => !esSabado || t < '14:00')
        .map(t => {
            const taken    = takenSlots.includes(t);
            const pastTime = isToday && t <= currentHHMM;
            const disabled = taken || pastTime;
            const selected = booking.time === t;
            let cls   = 'time-slot' + (disabled ? ' taken' : '') + (selected ? ' selected' : '');
            let label = taken ? t + ' <small>●</small>' : pastTime ? `<s>${t}</s>` : t;
            return `<div class="${cls}" ${disabled ? '' : `data-time="${t}"`}>${label}</div>`;
        }).join('');

    const next = document.getElementById('btn-next-3');
    if (next) next.disabled = !(booking.date && booking.time);
}

function selectTime(t) {
    booking.time = t;
    renderTimeSlots();
    const next = document.getElementById('btn-next-3');
    if (next) next.disabled = false;
}

function calNav(dir) {
    calendarDate.setMonth(calendarDate.getMonth() + dir);
    renderCalendar();
}

// ===== STEP 4: SUMMARY =====
function renderSummary() {
    const DAYS   = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    const MONTHS = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    const dateStr = booking.date
        ? `${DAYS[booking.date.getDay()]}, ${booking.date.getDate()} ${MONTHS[booking.date.getMonth()]}`
        : '—';
    document.getElementById('sum-service').textContent  = booking.service?.name     || '—';
    document.getElementById('sum-barber').textContent   = booking.barber?.name      || '—';
    document.getElementById('sum-date').textContent     = dateStr;
    document.getElementById('sum-time').textContent     = booking.time              || '—';
    document.getElementById('sum-duration').textContent = booking.service?.duration || '—';
    document.getElementById('sum-price').textContent    = booking.service ? `${booking.service.price} €` : '—';
}

// ===== CONFIRM =====
async function confirmBooking() {
    // Leer siempre del DOM antes de validar
    Object.keys(RULES).forEach(key => {
        const el = document.getElementById(`client-${key}`);
        if (el) booking.client[key] = el.value;
    });
    const notesEl = document.getElementById('client-notes');
    if (notesEl) booking.client.notes = notesEl.value;

    // Validar todos en modo submit → pinta todos los errores
    const isValid = validateAll('submit');

    if (!isValid) {
        // Construir mensaje descriptivo
        const errores = Object.keys(RULES)
            .filter(k => !RULES[k].validate(booking.client[k] || '').ok)
            .map(k => ({ name: { name: 'nombre', phone: 'teléfono', email: 'email' }[k] }))
            .map(o => o.name);

        const msg = errores.length === 1
            ? `El campo "${errores[0]}" está vacío o es incorrecto.`
            : `Campos incorrectos: ${errores.join(', ')}.`;

        if (window.showToast) showToast(msg, '⚠');

        // Scroll al primer error
        setTimeout(() => {
            const firstErr = document.querySelector('[id^="error-"][style*="block"]');
            if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 50);

        return;
    }

    const btn = document.getElementById('btn-confirm');
    btn.disabled    = true;
    btn.textContent = 'Procesando…';

    const payload = {
        servicio: booking.service.id,
        barbero:  booking.barber.id,
        fecha:    formatDate(booking.date),
        hora:     booking.time,
        nombre:   booking.client.name.trim(),
        telefono: booking.client.phone.trim(),
        email:    booking.client.email.trim(),
        notas:    booking.client.notes || '',
    };

    try {
        const res  = await fetch(`${window.API_BASE}/booking.php`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload),
        });
        const json = await res.json();
        if (json.ok) {
            goToStep(5);
            renderConfirmation();
        } else {
            if (window.showToast) showToast(json.error || 'Error al reservar', '⚠');
            btn.disabled    = false;
            btn.textContent = 'Confirmar reserva ✦';
            if (res.status === 409) { booking.time = null; await loadTakenSlots(); }
        }
    } catch(e) {
        if (window.showToast) showToast('Sin conexión al servidor', '⚠');
        btn.disabled    = false;
        btn.textContent = 'Confirmar reserva ✦';
    }
}

function renderConfirmation() {
    const MONTHS = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    const dateStr = booking.date
        ? `${booking.date.getDate()} ${MONTHS[booking.date.getMonth()]} ${booking.date.getFullYear()}`
        : '—';
    const box = document.getElementById('confirmation-detail');
    if (box) {
        box.innerHTML = `
          <p><strong>${booking.service?.name}</strong> con <strong>${booking.barber?.name}</strong></p>
          <p>${dateStr} · ${booking.time}</p>
          <p style="color:var(--color-muted);font-size:.875rem;margin-top:.5rem">
            Confirmación enviada a ${booking.client.email}</p>`;
    }
    if (window.showToast) showToast('¡Reserva confirmada! Te esperamos.');
}

// ===== UTIL =====
function formatDate(date) {
    return `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`;
}

// ===== INIT =====
function initBooking() {
    if (!document.getElementById('service-grid')) return;

    renderServices();
    renderBarbers();
    renderCalendar();
    renderTimeSlots();

    document.getElementById('service-grid')?.addEventListener('click', e => {
        const o = e.target.closest('[data-service-id]');
        if (o) selectService(o.dataset.serviceId);
    });
    document.getElementById('barber-grid')?.addEventListener('click', e => {
        const o = e.target.closest('[data-barber-id]');
        if (o) selectBarber(o.dataset.barberId);
    });
    document.getElementById('cal-grid')?.addEventListener('click', e => {
        const c = e.target.closest('[data-cal-day]');
        if (c) selectDate(+c.dataset.calYear, +c.dataset.calMonth, +c.dataset.calDay);
    });
    document.getElementById('time-slots')?.addEventListener('click', e => {
        const s = e.target.closest('[data-time]');
        if (s) selectTime(s.dataset.time);
    });
    document.querySelector('[data-cal-nav="-1"]')?.addEventListener('click', () => calNav(-1));
    document.querySelector('[data-cal-nav="1"]')?.addEventListener('click',  () => calNav(1));

    document.getElementById('btn-next-1')?.addEventListener('click', () => { if (booking.service) goToStep(2); });
    document.getElementById('btn-next-2')?.addEventListener('click', () => { if (booking.barber)  goToStep(3); });
    document.getElementById('btn-next-3')?.addEventListener('click', () => {
        if (booking.date && booking.time) { renderSummary(); goToStep(4); syncClientForm(); }
    });
    document.getElementById('btn-back-2')?.addEventListener('click', () => goToStep(1));
    document.getElementById('btn-back-3')?.addEventListener('click', () => goToStep(2));
    document.getElementById('btn-back-4')?.addEventListener('click', () => goToStep(3));

    // btn-confirm: listener permanente, NO se clona nunca
    document.getElementById('btn-confirm')?.addEventListener('click', confirmBooking);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBooking);
} else {
    initBooking();
}