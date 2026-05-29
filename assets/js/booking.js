// ============================================================
//  PRADO BARBER CO. — booking.js
//  Barberos y servicios se cargan desde la API, no hardcodeados.
// ============================================================

const API_BASE = './backend/api';

// ── Datos que llegan de la API ────────────────────────────────
let SERVICES = [];
let BARBERS  = [];

const TIME_SLOTS = [
    '09:00','09:30','10:00','10:30','11:00','11:30',
    '12:00','12:30','13:00','13:30',
    '16:00','16:30','17:00','17:30','18:00','18:30','19:00','19:30',
];

// ===== PHONE PREFIX DATA =====
const PHONE_COUNTRIES = [
    { code: 'ES', flag: '🇪🇸', name: 'España',         dial: '+34',  digits: 9,  pattern: /^[6-9]\d{8}$/,   hint: '6XX XXX XXX' },
    { code: 'US', flag: '🇺🇸', name: 'Estados Unidos', dial: '+1',   digits: 10, pattern: /^[2-9]\d{9}$/,   hint: '6XX XXX XXXX' },
    { code: 'GB', flag: '🇬🇧', name: 'Reino Unido',    dial: '+44',  digits: 10, pattern: /^[1-9]\d{9}$/,   hint: '07XXX XXXXXX' },
    { code: 'FR', flag: '🇫🇷', name: 'Francia',        dial: '+33',  digits: 9,  pattern: /^[1-9]\d{8}$/,   hint: '06 XX XX XX XX' },
    { code: 'DE', flag: '🇩🇪', name: 'Alemania',       dial: '+49',  digits: 11, pattern: /^\d{10,11}$/,    hint: '015X XXXXXXX' },
    { code: 'IT', flag: '🇮🇹', name: 'Italia',         dial: '+39',  digits: 10, pattern: /^3\d{9}$/,       hint: '3XX XXX XXXX' },
    { code: 'PT', flag: '🇵🇹', name: 'Portugal',       dial: '+351', digits: 9,  pattern: /^[29]\d{8}$/,    hint: '9XX XXX XXX' },
    { code: 'MX', flag: '🇲🇽', name: 'México',         dial: '+52',  digits: 10, pattern: /^\d{10}$/,       hint: 'XX XXXX XXXX' },
    { code: 'AR', flag: '🇦🇷', name: 'Argentina',      dial: '+54',  digits: 10, pattern: /^\d{10}$/,       hint: 'XX XXXX XXXX' },
    { code: 'CO', flag: '🇨🇴', name: 'Colombia',       dial: '+57',  digits: 10, pattern: /^3\d{9}$/,       hint: '3XX XXX XXXX' },
    { code: 'CL', flag: '🇨🇱', name: 'Chile',          dial: '+56',  digits: 9,  pattern: /^9\d{8}$/,       hint: '9XXXX XXXX' },
    { code: 'BR', flag: '🇧🇷', name: 'Brasil',         dial: '+55',  digits: 11, pattern: /^[1-9]\d{10}$/,  hint: '11 9XXXX XXXX' },
    { code: 'NL', flag: '🇳🇱', name: 'Países Bajos',  dial: '+31',  digits: 9,  pattern: /^[1-9]\d{8}$/,   hint: '06 XXXXXXXX' },
    { code: 'BE', flag: '🇧🇪', name: 'Bélgica',        dial: '+32',  digits: 9,  pattern: /^[1-9]\d{8}$/,   hint: '04XX XX XX XX' },
    { code: 'CH', flag: '🇨🇭', name: 'Suiza',          dial: '+41',  digits: 9,  pattern: /^[1-9]\d{8}$/,   hint: '076 XXX XX XX' },
    { code: 'PL', flag: '🇵🇱', name: 'Polonia',        dial: '+48',  digits: 9,  pattern: /^\d{9}$/,        hint: 'XXX XXX XXX' },
    { code: 'SE', flag: '🇸🇪', name: 'Suecia',         dial: '+46',  digits: 10, pattern: /^[1-9]\d{8,9}$/, hint: '070 XXX XXXX' },
    { code: 'NO', flag: '🇳🇴', name: 'Noruega',        dial: '+47',  digits: 8,  pattern: /^\d{8}$/,        hint: '4XX XX XXX' },
    { code: 'DK', flag: '🇩🇰', name: 'Dinamarca',      dial: '+45',  digits: 8,  pattern: /^\d{8}$/,        hint: 'XX XX XX XX' },
    { code: 'RO', flag: '🇷🇴', name: 'Rumanía',        dial: '+40',  digits: 9,  pattern: /^[67]\d{8}$/,    hint: '07XX XXX XXX' },
    { code: 'MA', flag: '🇲🇦', name: 'Marruecos',      dial: '+212', digits: 9,  pattern: /^[67]\d{8}$/,    hint: '06X XXX XXX' },
    { code: 'AU', flag: '🇦🇺', name: 'Australia',      dial: '+61',  digits: 9,  pattern: /^[24-9]\d{8}$/,  hint: '04XX XXX XXX' },
    { code: 'JP', flag: '🇯🇵', name: 'Japón',          dial: '+81',  digits: 10, pattern: /^[7-9]0\d{8}$/,  hint: '070 XXXX XXXX' },
    { code: 'CN', flag: '🇨🇳', name: 'China',          dial: '+86',  digits: 11, pattern: /^1[3-9]\d{9}$/,  hint: '1XX XXXX XXXX' },
    { code: 'IN', flag: '🇮🇳', name: 'India',          dial: '+91',  digits: 10, pattern: /^[6-9]\d{9}$/,   hint: 'XXXXX XXXXX' },
];

// ===== PREFIX STATE =====
let selectedCountry = PHONE_COUNTRIES[0];
let prefixDropdownOpen = false;

// ===== STATE =====
const booking = {
    step: 1,
    service: null,
    barber: null,
    date: null,
    time: null,
    client: { name: '', phone: '', email: '', notes: '' },
};

let calendarDate     = new Date();
let takenSlots       = [];
let loadingSlots     = false;
let dayBlocked       = false;
let dayBlockedMotivo = '';

let blockedDaysMap   = {};
let blockedDaysKey   = '';
let blockedDaysLoading = false;
let blockedDaysAbortController = null;

// ===== CARGA INICIAL DESDE API ======================================

async function loadInitialData() {
    showStepLoading(true);
    try {
        const [resSvc, resBar] = await Promise.all([
            fetch(`${API_BASE}/servicios.php`),
            fetch(`${API_BASE}/barberos.php`),
        ]);
        const jsonSvc = await resSvc.json();
        const jsonBar = await resBar.json();

        if (jsonSvc.ok) {
            SERVICES = jsonSvc.data.map(s => ({
                id:       s.id,
                name:     s.nombre,
                duration: s.duracion,
                price:    s.precio,
            }));
        }
        if (jsonBar.ok) {
            BARBERS = jsonBar.data.map(b => ({
                id:       b.id,
                name:     b.nombre,
                spec:     b.especialidad,
                initials: b.iniciales,
            }));
        }
    } catch (e) {
        console.error('Error cargando datos iniciales:', e);
        // Si falla la API se muestran mensajes de error en los grids
    }
    showStepLoading(false);
    renderServices();
    renderBarbers();
    renderCalendar();
    renderTimeSlots();
    syncClientForm();

    // Si llega desde servicios.html con ?servicio=ID, preseleccionar y saltar al paso 2
    const params = new URLSearchParams(window.location.search);
    const servicioParam = params.get('servicio');
    if (servicioParam) {
        const found = SERVICES.find(s => String(s.id) === String(servicioParam));
        if (found) {
            selectService(found.id);
            goToStep(2);
        }
    }
}

function showStepLoading(show) {
    const panel = document.getElementById('step-1');
    if (!panel) return;
    let loader = panel.querySelector('.step-loader');
    if (show) {
        if (!loader) {
            loader = document.createElement('div');
            loader.className = 'step-loader';
            loader.style.cssText = 'text-align:center;padding:2rem;color:var(--color-muted);font-size:.85rem;letter-spacing:.1em;';
            loader.textContent = 'Cargando servicios…';
            panel.prepend(loader);
        }
        const grid = document.getElementById('service-grid');
        if (grid) grid.style.display = 'none';
    } else {
        if (loader) loader.remove();
        const grid = document.getElementById('service-grid');
        if (grid) grid.style.display = '';
    }
}

// ===== NORMALIZAR HORA =====
function normalizeTime(t) {
    if (!t) return '';
    const parts = t.split(':');
    return parts[0].padStart(2, '0') + ':' + (parts[1] || '00').padStart(2, '0');
}

// ===== CSS INJECTION =====
(function injectStyles() {
    const style = document.createElement('style');
    style.textContent = `
        .cal-cell.blocked {
            background: rgba(212,43,43,0.13) !important;
            color: rgba(240,236,227,0.25) !important;
            cursor: not-allowed !important;
            position: relative;
            text-decoration: line-through;
        }
        .cal-cell.blocked:hover {
            border-color: transparent !important;
            color: rgba(240,236,227,0.25) !important;
        }
        .cal-cell.blocked::after {
            content: '';
            position: absolute;
            inset: 4px;
            border-radius: 50%;
            background: repeating-linear-gradient(
                -45deg,
                rgba(212,43,43,0.3) 0px, rgba(212,43,43,0.3) 1px,
                transparent 1px, transparent 4px
            );
            pointer-events: none;
        }
        .cal-grid.loading .cal-cell:not(.disabled):not(.empty) {
            cursor: wait !important;
            pointer-events: none;
        }
        .time-slot.taken {
            opacity: 1 !important;
            background: rgba(212,43,43,0.06) !important;
            border-color: rgba(212,43,43,0.2) !important;
            color: rgba(240,236,227,0.3) !important;
            cursor: not-allowed !important;
            position: relative;
            text-decoration: line-through !important;
        }
        .time-slot.taken::after {
            content: '●';
            position: absolute;
            top: 3px;
            right: 5px;
            font-size: 5px;
            color: rgba(212,43,43,0.5);
            line-height: 1;
        }
        .time-slot.taken:hover {
            border-color: rgba(212,43,43,0.2) !important;
            color: rgba(240,236,227,0.3) !important;
            transform: none !important;
        }
        .time-slot.past {
            opacity: 0.3 !important;
            cursor: not-allowed !important;
            text-decoration: line-through !important;
        }
        .time-slot.past:hover {
            border-color: var(--color-border) !important;
            color: var(--color-muted) !important;
        }
        /* Grid vacío cuando no hay datos */
        .grid-error {
            grid-column: 1 / -1;
            text-align: center;
            padding: 2rem;
            color: var(--color-muted);
            font-size: .85rem;
            border: 1px dashed var(--color-border);
            border-radius: var(--radius-md);
        }
    `;
    document.head.appendChild(style);
})();

// ===== DÍAS BLOQUEADOS =====
async function loadBlockedDays(year, monthOneIndexed) {
    const key = year + '-' + monthOneIndexed;
    if (key === blockedDaysKey) return;

    blockedDaysLoading = true;

    if (blockedDaysAbortController) blockedDaysAbortController.abort();
    blockedDaysAbortController = new AbortController();
    const signal = blockedDaysAbortController.signal;

    try {
        const res  = await fetch(`${API_BASE}/blocked-days.php?year=${year}&month=${monthOneIndexed}`, { signal });
        const json = await res.json();
        if (json.ok) {
            blockedDaysMap = json.data;
            blockedDaysKey = key;
        } else {
            blockedDaysMap = {};
        }
    } catch (e) {
        if (e.name === 'AbortError') { blockedDaysLoading = false; return; }
        blockedDaysMap = {};
    }

    blockedDaysLoading = false;
    const grid = document.getElementById('cal-grid');
    if (grid) grid.classList.remove('loading');
    _renderCalGrid(year, monthOneIndexed - 1);
}

function isDateBlocked(year, month, day) {
    const iso = `${year}-${String(month + 1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
    return Object.prototype.hasOwnProperty.call(blockedDaysMap, iso);
}

function formatISO(year, month, day) {
    return `${year}-${String(month + 1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
}

// ===== VALIDATION HELPERS =====
function isValidName(v) { return /^[a-zA-ZÀ-ÿ\u00f1\u00d1\s'\-]{2,}$/.test(v.trim()); }
function isValidEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()); }

function setFieldError(fieldId, message) {
    const el = document.getElementById(fieldId);
    if (!el) return;
    const wrap = el.closest('.form-group');
    if (!wrap) return;
    let errEl = wrap.querySelector('.field-error');
    if (!errEl) {
        errEl = document.createElement('div');
        errEl.className = 'field-error';
        const hint = wrap.querySelector('.phone-hint');
        if (hint) hint.after(errEl);
        else wrap.appendChild(errEl);
    }
    errEl.textContent = message;
    errEl.style.display = message ? 'flex' : 'none';
    el.classList.toggle('input-error', !!message);
}
function clearFieldError(fieldId) { setFieldError(fieldId, ''); }

function setPhoneError(message) {
    const wrap  = document.getElementById('phone-field-wrap');
    const group = document.getElementById('phone-form-group');
    if (!wrap || !group) return;
    let errEl = group.querySelector('.field-error');
    if (!errEl) {
        errEl = document.createElement('div');
        errEl.className = 'field-error';
        const hint = group.querySelector('.phone-hint');
        if (hint) hint.after(errEl);
        else group.appendChild(errEl);
    }
    errEl.textContent = message;
    errEl.style.display = message ? 'flex' : 'none';
    wrap.classList.toggle('input-error', !!message);
}
function clearPhoneError() { setPhoneError(''); }

function enforceNameInput(e) {
    const el = e.target;
    const clean = el.value.replace(/[^a-zA-ZÀ-ÿ\u00f1\u00d1\s'\-]/g, '');
    if (clean !== el.value) el.value = clean;
    booking.client.name = el.value;
    validateField('client-name');
}

function validateField(fieldId) {
    const el = document.getElementById(fieldId);
    if (!el) return true;
    const val = el.value;
    if (fieldId === 'client-name') {
        if (!val.trim()) { setFieldError(fieldId, '⚠ El nombre no puede estar vacío.'); return false; }
        if (!isValidName(val)) { setFieldError(fieldId, '⚠ Solo se permiten letras y espacios.'); return false; }
        clearFieldError(fieldId); return true;
    }
    if (fieldId === 'client-email') {
        if (!val.trim()) { setFieldError(fieldId, '⚠ El email no puede estar vacío.'); return false; }
        if (!isValidEmail(val)) { setFieldError(fieldId, '⚠ Introduce un email válido (ej: tu@email.com).'); return false; }
        clearFieldError(fieldId); return true;
    }
    return true;
}

function validatePhoneField() {
    const raw    = document.getElementById('client-phone').value.replace(/\s/g, '');
    const digits = raw.replace(/\D/g, '');
    if (!digits) { setPhoneError('⚠ El teléfono no puede estar vacío.'); return false; }
    if (!selectedCountry.pattern.test(digits)) {
        setPhoneError(`⚠ Número inválido para ${selectedCountry.name} (${selectedCountry.dial}). Formato: ${selectedCountry.hint}`);
        return false;
    }
    clearPhoneError(); return true;
}

// ===== PHONE PREFIX LOGIC =====
function renderPrefixList(list) {
    const el = document.getElementById('prefix-country-list');
    if (!el) return;
    el.innerHTML = list.map(c => `
        <div class="prefix-country-item${c.code === selectedCountry.code ? ' active' : ''}"
             onclick="selectCountry('${c.code}')" role="option"
             aria-selected="${c.code === selectedCountry.code}">
            <span class="ci-flag">${c.flag}</span>
            <span class="ci-name">${c.name}</span>
            <span class="ci-dial">${c.dial}</span>
        </div>`).join('');
}

function filterPrefixList() {
    const q = (document.getElementById('prefix-search').value || '').toLowerCase();
    const filtered = q
        ? PHONE_COUNTRIES.filter(c => c.name.toLowerCase().includes(q) || c.dial.includes(q) || c.code.toLowerCase().includes(q))
        : [...PHONE_COUNTRIES];
    renderPrefixList(filtered);
}

function openPrefixDropdown() {
    prefixDropdownOpen = true;
    document.getElementById('prefix-dropdown')?.classList.add('open');
    document.getElementById('prefix-chevron')?.classList.add('open');
    document.getElementById('prefix-btn')?.setAttribute('aria-expanded', 'true');
    const s = document.getElementById('prefix-search');
    if (s) { s.value = ''; s.focus(); }
    renderPrefixList(PHONE_COUNTRIES);
}

function closePrefixDropdown() {
    prefixDropdownOpen = false;
    document.getElementById('prefix-dropdown')?.classList.remove('open');
    document.getElementById('prefix-chevron')?.classList.remove('open');
    document.getElementById('prefix-btn')?.setAttribute('aria-expanded', 'false');
}

function togglePrefixDropdown() {
    prefixDropdownOpen ? closePrefixDropdown() : openPrefixDropdown();
}

function selectCountry(code) {
    selectedCountry = PHONE_COUNTRIES.find(c => c.code === code) || PHONE_COUNTRIES[0];
    const flagEl = document.getElementById('prefix-flag');
    const codeEl = document.getElementById('prefix-code');
    const input  = document.getElementById('client-phone');
    if (flagEl) flagEl.textContent = selectedCountry.flag;
    if (codeEl) codeEl.textContent = selectedCountry.dial;
    if (input)  { input.placeholder = selectedCountry.hint; input.maxLength = selectedCountry.digits + 4; input.value = ''; }
    updatePhoneHint();
    clearPhoneError();
    closePrefixDropdown();
    if (input) input.focus();
}

function updatePhoneHint() {
    const hint  = document.getElementById('phone-hint');
    const input = document.getElementById('client-phone');
    if (!hint || !input) return;
    const digits = input.value.replace(/\s/g, '').replace(/\D/g, '');
    if (!digits) { hint.textContent = ''; hint.className = 'phone-hint'; updatePhoneFull(''); return; }
    if (digits.length < selectedCountry.digits) {
        hint.textContent = `${digits.length} / ${selectedCountry.digits} dígitos`;
        hint.className   = 'phone-hint typing';
        updatePhoneFull(''); return;
    }
    if (selectedCountry.pattern.test(digits)) {
        hint.textContent = `✓ Válido · ${selectedCountry.name}`;
        hint.className   = 'phone-hint valid';
        updatePhoneFull(selectedCountry.dial + digits);
        clearPhoneError();
    } else {
        hint.textContent = `✕ Formato incorrecto para ${selectedCountry.name}`;
        hint.className   = 'phone-hint invalid';
        updatePhoneFull('');
    }
}

function updatePhoneFull(val) {
    const el = document.getElementById('client-phone-full');
    if (el) el.value = val;
    booking.client.phone = val;
}

function initPrefixSelector() {
    const btn    = document.getElementById('prefix-btn');
    const search = document.getElementById('prefix-search');
    const input  = document.getElementById('client-phone');
    if (btn)    btn.addEventListener('click', togglePrefixDropdown);
    if (search) { search.addEventListener('input', filterPrefixList); search.addEventListener('keydown', e => e.stopPropagation()); }
    if (input)  {
        input.placeholder = selectedCountry.hint;
        input.maxLength   = selectedCountry.digits + 4;
        input.addEventListener('input', () => { updatePhoneHint(); clearPhoneError(); });
        input.addEventListener('blur', validatePhoneField);
    }
    document.addEventListener('click', e => {
        if (!prefixDropdownOpen) return;
        const wrap = document.getElementById('phone-field-wrap');
        if (wrap && !wrap.contains(e.target)) closePrefixDropdown();
    });
    renderPrefixList(PHONE_COUNTRIES);
}

// ===== STEP NAV =====
function goToStep(n) {
    if (n < 1 || n > 5) return;
    booking.step = n;
    document.querySelectorAll('.step-panel').forEach((p, i) => p.classList.toggle('active', i + 1 === n));
    updateStepsBar();
    window.scrollTo({ top: 0, behavior: 'smooth' });
    if (n === 3) { blockedDaysKey = ''; renderCalendar(); }
}

function updateStepsBar() {
    document.querySelectorAll('.step-circle').forEach((el, i) => {
        const s = i + 1;
        el.classList.remove('active', 'done');
        if (s < booking.step)        { el.classList.add('done');   el.textContent = '✓'; }
        else if (s === booking.step) { el.classList.add('active'); el.textContent = s; }
        else                           el.textContent = s;
    });
    document.querySelectorAll('.step-line').forEach((el, i) => el.classList.toggle('done', i + 1 < booking.step));
}

// ===== STEP 1: SERVICE =====
function renderServices() {
    const grid = document.getElementById('service-grid');
    if (!grid) return;
    if (!SERVICES.length) {
        grid.innerHTML = '<div class="grid-error">No se pudieron cargar los servicios. Recarga la página.</div>';
        return;
    }
    grid.innerHTML = SERVICES.map(s => `
        <div class="service-option ${booking.service?.id === s.id ? 'selected' : ''}" onclick="selectService('${s.id}')">
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
    if (!BARBERS.length) {
        grid.innerHTML = '<div class="grid-error">No se pudieron cargar los barberos. Recarga la página.</div>';
        return;
    }
    grid.innerHTML = BARBERS.map(b => `
        <div class="barber-option ${booking.barber?.id === b.id ? 'selected' : ''}" onclick="selectBarber('${b.id}')">
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
function _renderCalGrid(year, month) {
    const grid  = document.getElementById('cal-grid');
    if (!grid) return;

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const firstDay    = new Date(year, month, 1).getDay();
    const offset      = (firstDay + 6) % 7;
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    let html = '';
    for (let i = 0; i < offset; i++) html += `<div class="cal-cell empty"></div>`;

    for (let d = 1; d <= daysInMonth; d++) {
        const date      = new Date(year, month, d);
        const isToday   = date.getTime() === today.getTime();
        const isPast    = date < today;
        const isSunday  = date.getDay() === 0;
        const isBlocked = isDateBlocked(year, month, d);

        const selDate    = booking.date;
        const isSelected = selDate &&
            selDate.getDate()     === d &&
            selDate.getMonth()    === month &&
            selDate.getFullYear() === year;

        const disabled = isPast || isSunday || isBlocked;

        let cls = 'cal-cell';
        if (isPast || isSunday) cls += ' disabled';
        if (isBlocked)          cls += ' blocked';
        if (isToday && !isBlocked) cls += ' today';
        if (isSelected && !isBlocked) cls += ' selected';

        const iso       = formatISO(year, month, d);
        const motivo    = isBlocked ? (blockedDaysMap[iso] || 'No disponible') : '';
        const titleAttr = isBlocked ? `title="${motivo}"` : '';
        const onclick   = disabled ? '' : `onclick="selectDate(${year},${month},${d})"`;

        html += `<div class="${cls}" ${onclick} ${titleAttr}>${d}</div>`;
    }
    grid.innerHTML = html;
    grid.classList.remove('loading');
}

async function renderCalendar() {
    const grid  = document.getElementById('cal-grid');
    const title = document.getElementById('cal-title');
    if (!grid) return;

    const year  = calendarDate.getFullYear();
    const month = calendarDate.getMonth();

    const MONTHS = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                    'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    title.textContent = `${MONTHS[month]} ${year}`;

    const needsFetch = (year + '-' + (month + 1)) !== blockedDaysKey;
    if (needsFetch) grid.classList.add('loading');

    await loadBlockedDays(year, month + 1);
    _renderCalGrid(year, month);
}

function selectDate(y, m, d) {
    if (blockedDaysLoading) return;
    if (isDateBlocked(y, m, d)) return;
    const date = new Date(y, m, d);
    const today = new Date(); today.setHours(0, 0, 0, 0);
    if (date < today || date.getDay() === 0) return;

    booking.date     = new Date(y, m, d);
    booking.time     = null;
    dayBlocked       = false;
    dayBlockedMotivo = '';
    _renderCalGrid(y, m);
    loadTakenSlots();
}

async function loadTakenSlots() {
    if (!booking.date || !booking.barber) {
        takenSlots = []; dayBlocked = false; dayBlockedMotivo = '';
        renderTimeSlots(); return;
    }
    loadingSlots = true;
    renderTimeSlotsLoading();

    const fecha   = formatDate(booking.date);
    const barbero = booking.barber.id;
    try {
        const res = await fetch(`${API_BASE}/slots.php?fecha=${fecha}&barbero=${barbero}`);
        if (!res.ok) { takenSlots = []; dayBlocked = false; dayBlockedMotivo = ''; renderTimeSlots(); return; }
        const json = await res.json();
        if (json.ok) {
            takenSlots       = (json.data.ocupadas || []).map(normalizeTime);
            dayBlocked       = json.data.bloqueado === true;
            dayBlockedMotivo = json.data.motivo    || '';
        } else {
            takenSlots = []; dayBlocked = false; dayBlockedMotivo = '';
        }
    } catch (e) {
        takenSlots = []; dayBlocked = false; dayBlockedMotivo = '';
    } finally {
        loadingSlots = false;
        if (dayBlocked) {
            booking.date = null;
            booking.time = null;
            _renderCalGrid(calendarDate.getFullYear(), calendarDate.getMonth());
        }
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

    if (dayBlocked) {
        booking.time = null;
        const motivo = dayBlockedMotivo ? ` — ${dayBlockedMotivo}` : '';
        wrap.innerHTML = `
            <div style="grid-column:1/-1;text-align:center;padding:2rem 1rem;
                        background:rgba(212,43,43,0.06);border:1px solid rgba(212,43,43,0.2);
                        border-radius:var(--radius-md);color:var(--color-muted);">
                <div style="font-size:1.5rem;margin-bottom:.5rem;">🔒</div>
                <div style="font-size:.9rem;color:var(--color-text);font-weight:500;margin-bottom:.25rem;">
                    Día no disponible${motivo}
                </div>
                <div style="font-size:.78rem;">Por favor selecciona otro día en el calendario.</div>
            </div>`;
        const next = document.getElementById('btn-next-3');
        if (next) next.disabled = true;
        return;
    }

    const now         = new Date();
    const isToday     = booking.date && booking.date.toDateString() === now.toDateString();
    const currentHHMM = `${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}`;
    const esSabado    = booking.date && booking.date.getDay() === 6;

    wrap.innerHTML = TIME_SLOTS
        .filter(t => !esSabado || t < '14:00')
        .map(t => {
            const taken    = takenSlots.includes(t);
            const pastTime = isToday && t <= currentHHMM;
            const selected = booking.time === t;

            let cls = 'time-slot';
            if (taken)    cls += ' taken';
            else if (pastTime) cls += ' past';
            if (selected) cls += ' selected';

            const disabled = taken || pastTime;
            const onclick = disabled ? '' : `onclick="selectTime('${t}')"`;
            return `<div class="${cls}" ${onclick}>${t}</div>`;
        }).join('');

    const hayOcupados = TIME_SLOTS.some(t => takenSlots.includes(t));
    if (hayOcupados) {
        const legend = document.createElement('div');
        legend.style.cssText = 'grid-column:1/-1;display:flex;align-items:center;gap:.5rem;font-size:.72rem;color:var(--color-muted);margin-top:.25rem;';
        legend.innerHTML = '<span style="display:inline-block;width:10px;height:10px;border:1px solid rgba(212,43,43,0.3);border-radius:2px;background:rgba(212,43,43,0.06);"></span> Horario ocupado';
        wrap.appendChild(legend);
    }

    const next = document.getElementById('btn-next-3');
    if (next) next.disabled = !(booking.date && booking.time);
}

function selectTime(t) {
    booking.time = t;
    renderTimeSlots();
    const next = document.getElementById('btn-next-3');
    if (next) next.disabled = !(booking.date && booking.time);
}

async function calNav(dir) {
    calendarDate.setMonth(calendarDate.getMonth() + dir);
    if (booking.date) {
        const sameMonth = booking.date.getMonth()    === calendarDate.getMonth() &&
                          booking.date.getFullYear() === calendarDate.getFullYear();
        if (!sameMonth) { booking.date = null; booking.time = null; renderTimeSlots(); }
    }
    await renderCalendar();
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

// ===== CLIENT FORM =====
function syncClientForm() {
    const nameEl  = document.getElementById('client-name');
    const emailEl = document.getElementById('client-email');
    const notesEl = document.getElementById('client-notes');
    if (nameEl) {
        nameEl.value = booking.client.name;
        nameEl.addEventListener('input', enforceNameInput);
        nameEl.addEventListener('blur',  () => validateField('client-name'));
    }
    if (emailEl) {
        emailEl.value = booking.client.email;
        emailEl.addEventListener('input', () => { booking.client.email = emailEl.value; });
        emailEl.addEventListener('blur',  () => validateField('client-email'));
    }
    if (notesEl) {
        notesEl.value = booking.client.notes;
        notesEl.addEventListener('input', () => { booking.client.notes = notesEl.value; });
    }
    initPrefixSelector();
}

function validateAllFields() {
    const nameEl  = document.getElementById('client-name');
    const emailEl = document.getElementById('client-email');
    if (nameEl)  booking.client.name  = nameEl.value;
    if (emailEl) booking.client.email = emailEl.value;
    const nameOk  = validateField('client-name');
    const phoneOk = validatePhoneField();
    const emailOk = validateField('client-email');
    if (!nameOk || !phoneOk || !emailOk) {
        const firstErr = document.querySelector('.input-error, .phone-field-wrap.input-error');
        if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    return nameOk && phoneOk && emailOk;
}

// ===== CONFIRM =====
async function confirmBooking() {
    if (!validateAllFields()) return;

    const phoneFullEl = document.getElementById('client-phone-full');
    const phoneValue  = phoneFullEl ? phoneFullEl.value : booking.client.phone;

    const btn = document.getElementById('btn-confirm');
    btn.disabled = true; btn.textContent = 'Procesando…';

    const payload = {
        servicio: booking.service.id,
        barbero:  booking.barber.id,
        fecha:    formatDate(booking.date),
        hora:     booking.time,
        nombre:   booking.client.name,
        telefono: phoneValue,
        email:    booking.client.email,
        notas:    booking.client.notes,
    };

    try {
        const res  = await fetch(`${API_BASE}/booking.php`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (json.ok) {
            goToStep(5); renderConfirmation();
        } else {
            if (window.showToast) showToast(json.error || 'Error al reservar', '⚠');
            btn.disabled = false; btn.textContent = 'Confirmar reserva ✦';
            if (res.status === 409) { booking.time = null; await loadTakenSlots(); }
        }
    } catch (e) {
        if (window.showToast) showToast('Sin conexión al servidor', '⚠');
        btn.disabled = false; btn.textContent = 'Confirmar reserva ✦';
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
    // Carga datos y luego renderiza — todo desde la API
    loadInitialData();

    const btn1       = document.getElementById('btn-next-1');
    const btn2       = document.getElementById('btn-next-2');
    const btn3       = document.getElementById('btn-next-3');
    const btnBack2   = document.getElementById('btn-back-2');
    const btnBack3   = document.getElementById('btn-back-3');
    const btnBack4   = document.getElementById('btn-back-4');
    const btnConfirm = document.getElementById('btn-confirm');

    if (btnConfirm) { btnConfirm.disabled = false; btnConfirm.addEventListener('click', confirmBooking); }
    if (btn1) btn1.addEventListener('click', () => { if (booking.service) goToStep(2); });
    if (btn2) btn2.addEventListener('click', () => { if (booking.barber)  goToStep(3); });
    if (btn3) btn3.addEventListener('click', () => {
        if (booking.date && booking.time && !dayBlocked) { renderSummary(); goToStep(4); syncClientForm(); }
    });
    if (btnBack2) btnBack2.addEventListener('click', () => goToStep(1));
    if (btnBack3) btnBack3.addEventListener('click', () => goToStep(2));
    if (btnBack4) btnBack4.addEventListener('click', () => goToStep(3));

    let resizeTimer = null;
    window.addEventListener('resize', () => {
        if (booking.step !== 3) return;
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            if (blockedDaysKey === calendarDate.getFullYear() + '-' + (calendarDate.getMonth() + 1)) {
                _renderCalGrid(calendarDate.getFullYear(), calendarDate.getMonth());
            } else {
                renderCalendar();
            }
        }, 150);
    });
});

window.selectService = selectService;
window.selectBarber  = selectBarber;
window.selectDate    = selectDate;
window.selectTime    = selectTime;
window.calNav        = calNav;
window.selectCountry = selectCountry;