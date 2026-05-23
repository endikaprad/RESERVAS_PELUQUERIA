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

// ===== STEP 1: SERVICE =====
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

// ===== STEP 2: BARBER =====
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

        const selDate    = booking.date;
        const isSelected = selDate &&
            selDate.getDate()     === d     &&
            selDate.getMonth()    === month &&
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
        const res  = await fetch(`${window.API_BASE}/slots.php?fecha=${fecha}&barbero=${barbero}`);
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
    const isToday     = booking.date &&
        booking.date.toDateString() === now.toDateString();
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

            const dataAttr = disabled ? '' : `data-time="${t}"`;
            return `<div class="${cls}" ${dataAttr}>${label}</div>`;
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
const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

function getPhoneCountryData() {
    const sel = document.getElementById('phone-country');
    if (!sel) return { prefix: '', pattern: /^\+?\d{6,15}$/, example: '' };
    const opt = sel.options[sel.selectedIndex];
    return {
        prefix:  opt.dataset.prefix  || '',
        pattern: new RegExp(opt.dataset.pattern),
        example: opt.dataset.example || '',
    };
}

function normalizePhone(raw) {
    return raw.replace(/[\s\-().]/g, '');
}

function validatePhone(showError = false) {
    const input   = document.getElementById('client-phone');
    const errorEl = document.getElementById('phone-error');
    const hintEl  = document.getElementById('phone-hint');
    const wrapper = document.getElementById('phone-wrapper');
    if (!input) return false;

    const { prefix, pattern, example } = getPhoneCountryData();
    const raw  = input.value.trim();
    const norm = normalizePhone(raw);

    if (hintEl && example) {
        hintEl.textContent = `Ej: ${example}`;
    } else if (hintEl) {
        hintEl.textContent = '';
    }

    if (!norm) {
        if (errorEl) errorEl.style.display = 'none';
        if (wrapper) {
            wrapper.style.borderColor = 'var(--color-border)';
            wrapper.style.boxShadow   = 'none';
        }
        return false;
    }

    const valid = pattern.test(norm);

    if (errorEl) errorEl.style.display = (showError && !valid) ? 'block' : 'none';
    if (wrapper) {
        wrapper.style.borderColor = valid
            ? 'var(--red)'
            : (showError ? 'rgba(212,43,43,0.5)' : 'var(--color-border)');
        wrapper.style.boxShadow = valid ? '0 0 0 3px rgba(212,43,43,0.1)' : 'none';
    }

    booking.client.phone = prefix ? `${prefix} ${raw}` : raw;
    return valid;
}

function validateEmail(showError = false) {
    const input   = document.getElementById('client-email');
    const errorEl = document.getElementById('email-error');
    if (!input) return false;

    const val   = input.value.trim();
    const valid = EMAIL_REGEX.test(val);

    if (!val) {
        if (errorEl) errorEl.style.display = 'none';
        input.style.borderColor = 'var(--color-border)';
        input.style.boxShadow   = 'none';
        return false;
    }

    if (errorEl) errorEl.style.display = (showError && !valid) ? 'block' : 'none';
    input.style.borderColor = valid
        ? 'var(--red)'
        : (showError ? 'rgba(212,43,43,0.5)' : 'var(--color-border)');
    input.style.boxShadow = valid ? '0 0 0 3px rgba(212,43,43,0.1)' : 'none';

    return valid;
}

function validateName(showError = false) {
    const input   = document.getElementById('client-name');
    const errorEl = document.getElementById('name-error');
    if (!input) return false;

    const val   = input.value.trim();
    const valid = val.length > 1;

    if (!val) {
        if (errorEl && showError) errorEl.style.display = 'block';
        input.style.borderColor = showError ? 'rgba(212,43,43,0.5)' : 'var(--color-border)';
        input.style.boxShadow   = 'none';
        return false;
    }

    if (errorEl) errorEl.style.display = (showError && !valid) ? 'block' : 'none';
    input.style.borderColor = valid
        ? 'var(--red)'
        : (showError ? 'rgba(212,43,43,0.5)' : 'var(--color-border)');
    input.style.boxShadow = valid ? '0 0 0 3px rgba(212,43,43,0.1)' : 'none';

    return valid;
}

function validateClientForm() {
    const name  = document.getElementById('client-name')?.value.trim()  || '';
    const email = document.getElementById('client-email')?.value.trim() || '';
    const phone = document.getElementById('client-phone')?.value.trim() || '';

    const nameOk  = name.length > 1;
    const emailOk = EMAIL_REGEX.test(email);
    const phoneOk = phone.length > 8;

    booking.client.name  = name;
    booking.client.email = email;

    const btn = document.getElementById('btn-confirm');
    if (btn) btn.disabled = !(nameOk && emailOk && phoneOk);
    return nameOk && emailOk && phoneOk;
}

function syncClientForm() {
    const nameEl = document.getElementById('client-name');
    if (nameEl) {
        nameEl.value = booking.client.name;

        if (!document.getElementById('name-error')) {
            const err = document.createElement('span');
            err.id = 'name-error';
            err.textContent = 'El nombre es obligatorio';
            err.style.cssText = 'font-size:0.72rem; color:var(--red); margin-top:0.25rem; display:none;';
            nameEl.parentNode.appendChild(err);
        }

        nameEl.addEventListener('input', () => {
            booking.client.name = nameEl.value.trim();
            validateClientForm();
        });

        nameEl.addEventListener('blur', () => validateName(true));
    }

    const notesEl = document.getElementById('client-notes');
    if (notesEl) {
        notesEl.value = booking.client.notes;
        notesEl.addEventListener('input', () => {
            booking.client.notes = notesEl.value;
        });
    }

    const emailEl = document.getElementById('client-email');
    if (emailEl) {
        emailEl.value = booking.client.email;
        emailEl.addEventListener('input', () => {
            booking.client.email = emailEl.value.trim();
            validateClientForm();
        });
        emailEl.addEventListener('blur', () => validateEmail(true));
    }

    const phoneEl = document.getElementById('client-phone');
    if (phoneEl) {
        phoneEl.addEventListener('input', () => {
            validatePhone(false);
            validateClientForm();
        });
        phoneEl.addEventListener('blur', () => validatePhone(true));
    }

    const countryEl = document.getElementById('phone-country');
    if (countryEl) {
        countryEl.addEventListener('change', () => {
            const { example } = getPhoneCountryData();
            if (phoneEl) phoneEl.placeholder = example || '000 000 000';
            if (phoneEl && phoneEl.value.trim()) validatePhone(true);
            validateClientForm();
        });
    }
}

// ===== CONFIRM =====
async function confirmBooking() {
    const nameEl  = document.getElementById('client-name');
    const emailEl = document.getElementById('client-email');
    const phoneEl = document.getElementById('client-phone');

    const name  = nameEl?.value.trim()  || '';
    const email = emailEl?.value.trim() || '';
    const phone = phoneEl?.value.trim() || '';

    const nameOk  = name.length > 1;
    const emailOk = EMAIL_REGEX.test(email);
    const phoneOk = phone.length > 8;

    // Forzar validación visual en todos los campos
    validateName(true);
    validateEmail(true);
    validatePhone(true);

    if (!nameOk || !emailOk || !phoneOk) {
        // Scroll al primer campo con error
        const firstError = !nameOk ? nameEl : (!emailOk ? emailEl : phoneEl);
        if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });

        if (window.showToast) showToast('Por favor, revisa los campos marcados en rojo', '⚠');
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
        nombre:   name,
        telefono: booking.client.phone || phone,
        email:    email,
        notas:    booking.client.notes,
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
            if (res.status === 409) {
                booking.time = null;
                await loadTakenSlots();
            }
        }
    } catch (e) {
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
function initBooking() {
    const grid = document.getElementById('service-grid');
    if (!grid) return;

    renderServices();
    renderBarbers();
    renderCalendar();
    renderTimeSlots();
    syncClientForm();

    const serviceGrid = document.getElementById('service-grid');
    if (serviceGrid) {
        serviceGrid.addEventListener('click', (e) => {
            const option = e.target.closest('[data-service-id]');
            if (option) selectService(option.dataset.serviceId);
        });
    }

    const barberGrid = document.getElementById('barber-grid');
    if (barberGrid) {
        barberGrid.addEventListener('click', (e) => {
            const option = e.target.closest('[data-barber-id]');
            if (option) selectBarber(option.dataset.barberId);
        });
    }

    const calGrid = document.getElementById('cal-grid');
    if (calGrid) {
        calGrid.addEventListener('click', (e) => {
            const cell = e.target.closest('[data-cal-day]');
            if (cell) {
                selectDate(
                    parseInt(cell.dataset.calYear),
                    parseInt(cell.dataset.calMonth),
                    parseInt(cell.dataset.calDay)
                );
            }
        });
    }

    const timeSlots = document.getElementById('time-slots');
    if (timeSlots) {
        timeSlots.addEventListener('click', (e) => {
            const slot = e.target.closest('[data-time]');
            if (slot) selectTime(slot.dataset.time);
        });
    }

    const calPrev = document.querySelector('[data-cal-nav="-1"]');
    const calNext = document.querySelector('[data-cal-nav="1"]');
    if (calPrev) calPrev.addEventListener('click', () => calNav(-1));
    if (calNext) calNext.addEventListener('click', () => calNav(1));

    const btn1       = document.getElementById('btn-next-1');
    const btn2       = document.getElementById('btn-next-2');
    const btn3       = document.getElementById('btn-next-3');
    const btnBack2   = document.getElementById('btn-back-2');
    const btnBack3   = document.getElementById('btn-back-3');
    const btnBack4   = document.getElementById('btn-back-4');
    const btnConfirm = document.getElementById('btn-confirm');

    if (btn1) btn1.addEventListener('click', () => { if (booking.service) goToStep(2); });
    if (btn2) btn2.addEventListener('click', () => { if (booking.barber)  goToStep(3); });
    if (btn3) btn3.addEventListener('click', () => {
        if (booking.date && booking.time) { renderSummary(); goToStep(4); syncClientForm(); }
    });

    if (btnBack2)   btnBack2.addEventListener('click',  () => goToStep(1));
    if (btnBack3)   btnBack3.addEventListener('click',  () => goToStep(2));
    if (btnBack4)   btnBack4.addEventListener('click',  () => goToStep(3));
    if (btnConfirm) btnConfirm.addEventListener('click', confirmBooking);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBooking);
} else {
    initBooking();
}