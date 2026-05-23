// ============================================================
//  PRADO BARBER CO. — booking.js  (versión con backend MySQL)
// ============================================================

const API_BASE = './backend/api';

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

// ===== VALIDATION HELPERS =====

function isValidName(v) {
    return /^[a-zA-ZÀ-ÿ\u00f1\u00d1\s'\-]{2,}$/.test(v.trim());
}

function isValidPhone(v) {
    const digits = v.replace(/[\s\-().+]/g, '');
    return /^\d{9,}$/.test(digits);
}

function isValidEmail(v) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim());
}

function setFieldError(fieldId, message) {
    const el = document.getElementById(fieldId);
    if (!el) return;
    const wrap = el.closest('.form-group');
    if (!wrap) return;

    let errEl = wrap.querySelector('.field-error');
    if (!errEl) {
        errEl = document.createElement('div');
        errEl.className = 'field-error';
        wrap.appendChild(errEl);
    }
    errEl.textContent = message;
    errEl.style.display = message ? 'flex' : 'none';
    el.classList.toggle('input-error', !!message);
}

function clearFieldError(fieldId) {
    setFieldError(fieldId, '');
}

function enforceNameInput(e) {
    const el = e.target;
    const clean = el.value.replace(/[^a-zA-ZÀ-ÿ\u00f1\u00d1\s'\-]/g, '');
    if (clean !== el.value) el.value = clean;
    booking.client.name = el.value;
    validateClientForm(false);
}

function enforcePhoneInput(e) {
    const el = e.target;
    const clean = el.value.replace(/[^0-9+\s\-().]/g, '');
    if (clean !== el.value) el.value = clean;
    booking.client.phone = el.value;
    validateClientForm(false);
}

function validateField(fieldId) {
    const el = document.getElementById(fieldId);
    if (!el) return true;
    const val = el.value;

    if (fieldId === 'client-name') {
        if (!val.trim()) {
            setFieldError(fieldId, '⚠ El nombre no puede estar vacío.');
            return false;
        }
        if (!isValidName(val)) {
            setFieldError(fieldId, '⚠ Solo se permiten letras y espacios.');
            return false;
        }
        clearFieldError(fieldId);
        return true;
    }

    if (fieldId === 'client-phone') {
        if (!val.trim()) {
            setFieldError(fieldId, '⚠ El teléfono no puede estar vacío.');
            return false;
        }
        if (!isValidPhone(val)) {
            setFieldError(fieldId, '⚠ El teléfono debe tener al menos 9 dígitos.');
            return false;
        }
        clearFieldError(fieldId);
        return true;
    }

    if (fieldId === 'client-email') {
        if (!val.trim()) {
            setFieldError(fieldId, '⚠ El email no puede estar vacío.');
            return false;
        }
        if (!isValidEmail(val)) {
            setFieldError(fieldId, '⚠ Introduce un email válido (ej: tu@email.com).');
            return false;
        }
        clearFieldError(fieldId);
        return true;
    }

    return true;
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
        if (stepN < booking.step)       { el.classList.add('done');   el.textContent = '✓'; }
        else if (stepN === booking.step) { el.classList.add('active'); el.textContent = stepN; }
        else                             { el.textContent = stepN; }
    });
    document.querySelectorAll('.step-line').forEach((el, i) => {
        el.classList.toggle('done', i + 1 < booking.step);
    });
}

// ===== STEP 1: SERVICE =====
function renderServices() {
    const grid = document.getElementById('service-grid');
    if (!grid) return;
    grid.innerHTML = SERVICES.map(s => `
        <div class="service-option ${booking.service?.id === s.id ? 'selected' : ''}"
             onclick="selectService('${s.id}')">
          <div class="svc-name">${s.name}</div>
          <div class="svc-meta">${s.duration}</div>
          <div class="svc-price">${s.price} €</div>
        </div>`).join('');
}

function selectService(id) {
    booking.service = SERVICES.find(s => s.id === id);
    renderServices();
    document.getElementById('btn-next-1').disabled = false;
}

// ===== STEP 2: BARBER =====
function renderBarbers() {
    const grid = document.getElementById('barber-grid');
    if (!grid) return;
    grid.innerHTML = BARBERS.map(b => `
        <div class="barber-option ${booking.barber?.id === b.id ? 'selected' : ''}"
             onclick="selectBarber('${b.id}')">
          <div class="barber-avatar-book">${b.initials}</div>
          <div class="b-name">${b.name}</div>
          <div class="b-spec">${b.spec}</div>
        </div>`).join('');
}

function selectBarber(id) {
    booking.barber = BARBERS.find(b => b.id === id);
    renderBarbers();
    document.getElementById('btn-next-2').disabled = false;
    if (booking.date) loadTakenSlots();
}

// ===== STEP 3: DATE & TIME =====
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
    title.textContent = `${MONTHS[month]} ${year}`;

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

        const selDate    = booking.date;
        const isSelected = selDate &&
            selDate.getDate()     === d     &&
            selDate.getMonth()    === month &&
            selDate.getFullYear() === year;

        let cls = 'cal-cell';
        if (disabled)   cls += ' disabled';
        if (isToday)    cls += ' today';
        if (isSelected) cls += ' selected';

        const onclick = disabled ? '' : `onclick="selectDate(${year},${month},${d})"`;
        html += `<div class="${cls}" ${onclick}>${d}</div>`;
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
    if (!booking.date || !booking.barber) {
        takenSlots = [];
        renderTimeSlots();
        return;
    }
    loadingSlots = true;
    renderTimeSlotsLoading();
    const fecha   = formatDate(booking.date);
    const barbero = booking.barber.id;
    try {
        const res  = await fetch(`${API_BASE}/slots.php?fecha=${fecha}&barbero=${barbero}`);
        const json = await res.json();
        takenSlots = json.ok ? json.data.ocupadas : [];
    } catch (e) {
        console.warn('No se pudo conectar a la API:', e);
        takenSlots = [];
    } finally {
        loadingSlots = false;
        renderTimeSlots();
    }
}

function renderTimeSlotsLoading() {
    const wrap = document.getElementById('time-slots');
    if (!wrap) return;
    wrap.innerHTML = `<div style="grid-column:1/-1;text-align:center;color:var(--color-muted);
        font-size:.82rem;padding:1.5rem 0;">Cargando horarios…</div>`;
}

function renderTimeSlots() {
    const wrap = document.getElementById('time-slots');
    if (!wrap) return;

    const now         = new Date();
    const isToday     = booking.date && booking.date.toDateString() === now.toDateString();
    const currentHHMM = `${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}`;
    const esSabado    = booking.date && booking.date.getDay() === 6;

    wrap.innerHTML = TIME_SLOTS
        .filter(t => !esSabado || t < '14:00')
        .map(t => {
            const taken    = takenSlots.includes(t);
            const pastTime = isToday && t <= currentHHMM;
            const disabled = taken || pastTime;
            const selected = booking.time === t;

            let cls = 'time-slot';
            if (disabled) cls += ' taken';
            if (selected) cls += ' selected';

            let label = t;
            if (taken)    label += ' <small>●</small>';
            if (pastTime) label = `<s>${t}</s>`;

            const onclick = disabled ? '' : `onclick="selectTime('${t}')"`;
            return `<div class="${cls}" ${onclick}>${label}</div>`;
        }).join('');

    const next = document.getElementById('btn-next-3');
    if (next) next.disabled = !(booking.date && booking.time);
}

function selectTime(t) {
    booking.time = t;
    renderTimeSlots();
    const next = document.getElementById('btn-next-3');
    if (next) next.disabled = !(booking.date && booking.time);
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

    document.getElementById('sum-service').textContent  = booking.service?.name || '—';
    document.getElementById('sum-barber').textContent   = booking.barber?.name  || '—';
    document.getElementById('sum-date').textContent     = dateStr;
    document.getElementById('sum-time').textContent     = booking.time           || '—';
    document.getElementById('sum-duration').textContent = booking.service?.duration || '—';
    document.getElementById('sum-price').textContent    = booking.service ? `${booking.service.price} €` : '—';
}

// ===== CLIENT FORM =====
function syncClientForm() {
    const nameEl  = document.getElementById('client-name');
    const phoneEl = document.getElementById('client-phone');
    const emailEl = document.getElementById('client-email');
    const notesEl = document.getElementById('client-notes');

    if (nameEl) {
        nameEl.value = booking.client.name;
        nameEl.addEventListener('input', enforceNameInput);
        nameEl.addEventListener('blur',  () => validateField('client-name'));
    }
    if (phoneEl) {
        phoneEl.value = booking.client.phone;
        phoneEl.addEventListener('input', enforcePhoneInput);
        phoneEl.addEventListener('blur',  () => validateField('client-phone'));
    }
    if (emailEl) {
        emailEl.value = booking.client.email;
        emailEl.addEventListener('input', () => {
            booking.client.email = emailEl.value;
        });
        emailEl.addEventListener('blur', () => validateField('client-email'));
    }
    if (notesEl) {
        notesEl.value = booking.client.notes;
        notesEl.addEventListener('input', () => { booking.client.notes = notesEl.value; });
    }
}

function validateClientForm() {
    const nameEl  = document.getElementById('client-name');
    const phoneEl = document.getElementById('client-phone');
    const emailEl = document.getElementById('client-email');
    if (nameEl)  booking.client.name  = nameEl.value;
    if (phoneEl) booking.client.phone = phoneEl.value;
    if (emailEl) booking.client.email = emailEl.value;

    const allOk = isValidName(booking.client.name)
               && isValidPhone(booking.client.phone)
               && isValidEmail(booking.client.email);

    // Visual hint only — button is never truly disabled so click always fires
    const btn = document.getElementById('btn-confirm');

    return allOk;
}

function validateAllFields() {
    const nameEl  = document.getElementById('client-name');
    const phoneEl = document.getElementById('client-phone');
    const emailEl = document.getElementById('client-email');
    if (nameEl)  booking.client.name  = nameEl.value;
    if (phoneEl) booking.client.phone = phoneEl.value;
    if (emailEl) booking.client.email = emailEl.value;

    const nameOk  = validateField('client-name');
    const phoneOk = validateField('client-phone');
    const emailOk = validateField('client-email');

    if (!nameOk || !phoneOk || !emailOk) {
        const firstErr = document.querySelector('.input-error');
        if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    return nameOk && phoneOk && emailOk;
}

// ===== CONFIRM =====
async function confirmBooking() {
    const valid = validateAllFields();
    if (!valid) return;

    const btn = document.getElementById('btn-confirm');
    btn.disabled    = true;
    btn.textContent = 'Procesando…';

    const payload = {
        servicio: booking.service.id,
        barbero:  booking.barber.id,
        fecha:    formatDate(booking.date),
        hora:     booking.time,
        nombre:   booking.client.name,
        telefono: booking.client.phone,
        email:    booking.client.email,
        notas:    booking.client.notes,
    };

    try {
        const res  = await fetch(`${API_BASE}/booking.php`, {
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
            btn.disabled     = false;
            btn.textContent  = 'Confirmar reserva ✦';
            if (res.status === 409) {
                booking.time = null;
                await loadTakenSlots();
            }
        }
    } catch (e) {
        if (window.showToast) showToast('Sin conexión al servidor', '⚠');
        btn.disabled     = false;
        btn.textContent  = 'Confirmar reserva ✦';
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
            Confirmación enviada a ${booking.client.email}
          </p>`;
    }
    if (window.showToast) showToast('¡Reserva confirmada! Te esperamos.');
}

// ===== UTIL =====
function formatDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

// ===== INIT =====
document.addEventListener('DOMContentLoaded', () => {
    renderServices();
    renderBarbers();
    renderCalendar();
    renderTimeSlots();
    syncClientForm();

    const btn1      = document.getElementById('btn-next-1');
    const btn2      = document.getElementById('btn-next-2');
    const btn3      = document.getElementById('btn-next-3');
    const btnBack2  = document.getElementById('btn-back-2');
    const btnBack3  = document.getElementById('btn-back-3');
    const btnBack4  = document.getElementById('btn-back-4');
    const btnConfirm= document.getElementById('btn-confirm');

    // btn-confirm NUNCA debe tener disabled: los botones disabled no disparan click,
    // por lo que la validación con errores nunca se mostraría.
    // Usamos opacidad como indicador visual en su lugar.
    if (btnConfirm) {
        btnConfirm.disabled      = false;
btnConfirm.addEventListener('click', confirmBooking);
    }

    if (btn1) btn1.addEventListener('click', () => { if (booking.service) goToStep(2); });
    if (btn2) btn2.addEventListener('click', () => { if (booking.barber)  goToStep(3); });
    if (btn3) btn3.addEventListener('click', () => {
        if (booking.date && booking.time) { renderSummary(); goToStep(4); syncClientForm(); }
    });
    if (btnBack2) btnBack2.addEventListener('click', () => goToStep(1));
    if (btnBack3) btnBack3.addEventListener('click', () => goToStep(2));
    if (btnBack4) btnBack4.addEventListener('click', () => goToStep(3));
});

window.selectService = selectService;
window.selectBarber  = selectBarber;
window.selectDate    = selectDate;
window.selectTime    = selectTime;
window.calNav        = calNav;