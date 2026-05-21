// ===== DATA =====
const SERVICES = [
    { id: 'corte', name: 'Corte Clásico', duration: '30 min', price: 18 },
    { id: 'barba', name: 'Arreglo de Barba', duration: '20 min', price: 12 },
    { id: 'corte-barba', name: 'Corte + Barba', duration: '50 min', price: 26 },
    { id: 'degradado', name: 'Degradado', duration: '40 min', price: 22 },
    { id: 'afeitado', name: 'Afeitado Navaja', duration: '30 min', price: 20 },
    { id: 'premium', name: 'Sesión Premium', duration: '75 min', price: 45 },
];

const BARBERS = [
    { id: 'endika', name: 'Endika Prado', spec: 'Fundador · Especialista en degradados', initials: 'EP' },
    { id: 'marcos', name: 'Marcos Vila', spec: 'Barba & Navaja', initials: 'MV' },
    { id: 'alex', name: 'Alex Ramos', spec: 'Corte clásico & Fade', initials: 'AR' },
];

const TAKEN_SLOTS = ['09:00', '10:30', '14:00', '16:30'];

const TIME_SLOTS = [
    '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
    '12:00', '12:30', '13:00', '13:30',
    '16:00', '16:30', '17:00', '17:30', '18:00', '18:30', '19:00', '19:30',
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

// ===== STEP NAV =====
function goToStep(n) {
    if (n < 1 || n > 4) return;
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
        if (stepN < booking.step) el.classList.add('done'), el.textContent = '✓';
        else if (stepN === booking.step) el.classList.add('active'), el.textContent = stepN;
        else el.textContent = stepN;
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
    </div>
  `).join('');
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
    </div>
  `).join('');
}

function selectBarber(id) {
    booking.barber = BARBERS.find(b => b.id === id);
    renderBarbers();
    document.getElementById('btn-next-2').disabled = false;
}

// ===== STEP 3: DATE & TIME =====
function renderCalendar() {
    const grid = document.getElementById('cal-grid');
    const title = document.getElementById('cal-title');
    if (!grid) return;

    const year = calendarDate.getFullYear();
    const month = calendarDate.getMonth();
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const MONTHS = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    title.textContent = `${MONTHS[month]} ${year}`;

    const firstDay = new Date(year, month, 1).getDay();
    const offset = (firstDay + 6) % 7;
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    let html = '';
    for (let i = 0; i < offset; i++) html += `<div class="cal-cell empty"></div>`;

    for (let d = 1; d <= daysInMonth; d++) {
        const date = new Date(year, month, d);
        const isToday = date.getTime() === today.getTime();
        const isPast = date < today;
        const isSunday = date.getDay() === 0;
        const disabled = isPast || isSunday;

        const selDate = booking.date;
        const isSelected = selDate &&
            selDate.getDate() === d &&
            selDate.getMonth() === month &&
            selDate.getFullYear() === year;

        let cls = 'cal-cell';
        if (disabled) cls += ' disabled';
        if (isToday) cls += ' today';
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
    renderTimeSlots();
}

function renderTimeSlots() {
    const wrap = document.getElementById('time-slots');
    if (!wrap) return;
    wrap.innerHTML = TIME_SLOTS.map(t => {
        const taken = TAKEN_SLOTS.includes(t);
        const selected = booking.time === t;
        let cls = 'time-slot';
        if (taken) cls += ' taken';
        if (selected) cls += ' selected';
        const onclick = taken ? '' : `onclick="selectTime('${t}')"`;
        return `<div class="${cls}" ${onclick}>${t}</div>`;
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

// ===== STEP 4: CONFIRM =====
function renderSummary() {
    const DAYS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    const MONTHS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    const dateStr = booking.date
        ? `${DAYS[booking.date.getDay()]}, ${booking.date.getDate()} ${MONTHS[booking.date.getMonth()]}`
        : '—';

    document.getElementById('sum-service').textContent = booking.service?.name || '—';
    document.getElementById('sum-barber').textContent = booking.barber?.name || '—';
    document.getElementById('sum-date').textContent = dateStr;
    document.getElementById('sum-time').textContent = booking.time || '—';
    document.getElementById('sum-duration').textContent = booking.service?.duration || '—';
    document.getElementById('sum-price').textContent = booking.service ? `${booking.service.price} €` : '—';
}

// ===== CLIENT FORM =====
function syncClientForm() {
    ['name', 'phone', 'email', 'notes'].forEach(field => {
        const el = document.getElementById(`client-${field}`);
        if (el) {
            el.value = booking.client[field];
            el.addEventListener('input', () => {
                booking.client[field] = el.value;
                validateClientForm();
            });
        }
    });
}

function validateClientForm() {
    const { name, phone, email } = booking.client;
    const valid = name.trim().length > 1 && phone.trim().length > 8 && email.includes('@');
    const btn = document.getElementById('btn-confirm');
    if (btn) btn.disabled = !valid;
    return valid;
}

// ===== CONFIRM =====
function confirmBooking() {
    if (!validateClientForm()) return;

    const btn = document.getElementById('btn-confirm');
    btn.disabled = true;
    btn.textContent = 'Procesando…';

    setTimeout(() => {
        goToStep(5);
        renderConfirmation();
    }, 1200);
}

function renderConfirmation() {
    const MONTHS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
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
      </p>
    `;
    }
    if (window.showToast) showToast('¡Reserva confirmada! Te esperamos.');
}

// ===== INIT =====
document.addEventListener('DOMContentLoaded', () => {
    renderServices();
    renderBarbers();
    renderCalendar();
    renderTimeSlots();
    syncClientForm();

    const btn1 = document.getElementById('btn-next-1');
    const btn2 = document.getElementById('btn-next-2');
    const btn3 = document.getElementById('btn-next-3');
    const btnBack2 = document.getElementById('btn-back-2');
    const btnBack3 = document.getElementById('btn-back-3');
    const btnBack4 = document.getElementById('btn-back-4');
    const btnConfirm = document.getElementById('btn-confirm');

    if (btn1) btn1.addEventListener('click', () => { if (booking.service) goToStep(2); });
    if (btn2) btn2.addEventListener('click', () => { if (booking.barber) goToStep(3); });
    if (btn3) btn3.addEventListener('click', () => { if (booking.date && booking.time) { renderSummary(); goToStep(4); syncClientForm(); } });

    if (btnBack2) btnBack2.addEventListener('click', () => goToStep(1));
    if (btnBack3) btnBack3.addEventListener('click', () => goToStep(2));
    if (btnBack4) btnBack4.addEventListener('click', () => goToStep(3));
    if (btnConfirm) btnConfirm.addEventListener('click', confirmBooking);
});

// Expose for inline onclick
window.selectService = selectService;
window.selectBarber = selectBarber;
window.selectDate = selectDate;
window.selectTime = selectTime;
window.calNav = calNav;