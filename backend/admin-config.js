// ============================================================
//  CONFIG PANEL — JS
// ============================================================
const CFG_API = './api/settings.php';

// ── Estado global ─────────────────────────────────────────────
let cfgState = {
    autoAceptar:      'no',
    autoAceptarHasta: '',
    diasBloqueados:   [],
};

let mcDate     = new Date();
let mcSelected = null;
let rangeMode  = false;
let rangeStart = null;

// ── Abrir / Cerrar ────────────────────────────────────────────
function openCfg() {
    document.getElementById('cfg-overlay').classList.add('open');
    document.getElementById('cfg-panel').classList.add('open');
    loadSettings();
}
function closeCfg() {
    document.getElementById('cfg-overlay').classList.remove('open');
    document.getElementById('cfg-panel').classList.remove('open');
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && document.getElementById('cfg-panel').classList.contains('open'))
        closeCfg();
});

// ── Tabs ──────────────────────────────────────────────────────
function switchTab(tab) {
    document.querySelectorAll('.cfg-tab').forEach((t, i) => {
        t.classList.toggle('active', (i === 0 && tab === 'auto') || (i === 1 && tab === 'vac'));
    });
    document.getElementById('pane-auto').classList.toggle('active', tab === 'auto');
    document.getElementById('pane-vac').classList.toggle('active',  tab === 'vac');
}

// ── Cargar configuración ──────────────────────────────────────
async function loadSettings() {
    try {
        const r = await fetch(CFG_API);
        const j = await r.json();
        if (!j.ok) return;
        cfgState.autoAceptar      = j.data.auto_aceptar;
        cfgState.autoAceptarHasta = j.data.auto_aceptar_hasta;
        cfgState.diasBloqueados   = j.data.dias_bloqueados || [];
        applyAutoState();
        renderBlockedList();
        renderMiniCal();
    } catch (e) { console.warn('No se pudo cargar configuración:', e); }
}

// ── Auto-aceptar ──────────────────────────────────────────────
function applyAutoState() {
    const v       = cfgState.autoAceptar;
    const toggle  = document.getElementById('auto-toggle');
    const chip    = document.getElementById('auto-chip');
    const chipTxt = document.getElementById('auto-chip-text');
    const section = document.getElementById('alcance-section');
    const isOn    = v !== 'no';

    toggle.checked            = isOn;
    section.style.display     = isOn ? 'block' : 'none';

    if (isOn) {
        chip.className = 'auto-estado-chip on';
        const labels   = { hoy:'Activo — solo hoy', semana:'Activo — esta semana',
                           mes:'Activo — este mes',  siempre:'Activo — siempre' };
        chipTxt.textContent = labels[v] || 'Activo';
        document.querySelectorAll('.alcance-btn').forEach(b =>
            b.classList.toggle('selected', b.dataset.val === v));
        updateAlcanceDesc(v);
    } else {
        chip.className      = 'auto-estado-chip off';
        chipTxt.textContent = 'Desactivado';
    }
}

function onAutoToggle() {
    const isOn = document.getElementById('auto-toggle').checked;
    document.getElementById('alcance-section').style.display = isOn ? 'block' : 'none';
    if (!isOn) {
        document.getElementById('auto-chip').className      = 'auto-estado-chip off';
        document.getElementById('auto-chip-text').textContent = 'Desactivado';
    } else {
        const sel = document.querySelector('.alcance-btn.selected');
        updateAlcanceDesc(sel ? sel.dataset.val : 'siempre');
    }
}

function selectAlcance(btn) {
    document.querySelectorAll('.alcance-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    updateAlcanceDesc(btn.dataset.val);
}

function updateAlcanceDesc(v) {
    const descs = {
        hoy:     'Las reservas de <strong>hoy</strong> se aceptarán automáticamente.',
        semana:  'Las reservas de <strong>los próximos 7 días</strong> se aceptarán automáticamente.',
        mes:     'Las reservas del <strong>próximo mes</strong> se aceptarán automáticamente.',
        siempre: 'Las reservas se aceptarán automáticamente <strong>sin límite de tiempo</strong>.',
    };
    document.getElementById('alcance-desc').innerHTML = descs[v] || '';
}

async function saveAutoAceptar() {
    const isOn = document.getElementById('auto-toggle').checked;
    const sel  = document.querySelector('.alcance-btn.selected');
    const val  = isOn ? (sel ? sel.dataset.val : 'siempre') : 'no';

    const btn       = document.getElementById('btn-save-auto');
    btn.disabled    = true;
    btn.textContent = 'Guardando…';

    try {
        const r = await fetch(CFG_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'auto_aceptar', valor: val }),
        });
        const j = await r.json();
        if (j.ok) {
            cfgState.autoAceptar = val;
            applyAutoState();
            showCfgStatus('auto-status', 'ok', '✓ Configuración guardada correctamente.');
        } else {
            showCfgStatus('auto-status', 'err', '✕ ' + (j.error || 'Error al guardar.'));
        }
    } catch (e) {
        showCfgStatus('auto-status', 'err', '✕ Sin conexión con el servidor.');
    }

    btn.disabled    = false;
    btn.textContent = 'Guardar configuración';
}

// ── Vacaciones — Mini calendario ──────────────────────────────
function renderMiniCal() {
    const grid  = document.getElementById('mc-grid');
    const title = document.getElementById('mc-title');
    if (!grid) return;

    const MONTHS = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                    'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    const y = mcDate.getFullYear();
    const m = mcDate.getMonth();
    title.textContent = `${MONTHS[m]} ${y}`;

    const today   = new Date(); today.setHours(0, 0, 0, 0);
    const first   = new Date(y, m, 1).getDay();
    const offset  = (first + 6) % 7;
    const daysInM = new Date(y, m + 1, 0).getDate();
    const blocked = cfgState.diasBloqueados.map(d => d.fecha);

    let html = '';
    for (let i = 0; i < offset; i++) html += `<div class="mini-cell mc-empty"></div>`;

    for (let d = 1; d <= daysInM; d++) {
        const dt        = new Date(y, m, d);
        const iso       = fmtDate(dt);
        const isPast    = dt < today;
        const isToday   = dt.getTime() === today.getTime();
        const isBlocked = blocked.includes(iso);
        const isSel     = mcSelected === iso;

        let cls = 'mini-cell';
        if (isPast)              cls += ' mc-disabled';
        if (isToday)             cls += ' mc-today';
        if (isBlocked)           cls += ' mc-blocked';
        if (isSel && !isBlocked) cls += ' mc-selected';

        const onclick = isPast ? '' : `onclick="mcSelectDay('${iso}')"`;
        html += `<div class="${cls}" ${onclick} title="${iso}">${d}</div>`;
    }
    grid.innerHTML = html;
}

function mcNav(dir) {
    mcDate.setMonth(mcDate.getMonth() + dir);
    renderMiniCal();
}

function mcSelectDay(iso) {
    if (rangeMode) {
        if (!rangeStart) {
            rangeStart = iso;
            mcSelected = iso;
            document.getElementById('range-hint').innerHTML =
                `📅 Rango: <strong>${iso}</strong> → selecciona el día final.`;
        } else {
            bloquearRango(rangeStart, iso);
            rangeStart = null;
            mcSelected = null;
            toggleRangeMode();
        }
    } else {
        mcSelected = (mcSelected === iso) ? null : iso;
    }
    renderMiniCal();
}

function toggleRangeMode() {
    rangeMode  = !rangeMode;
    rangeStart = null;
    mcSelected = null;
    const hint = document.getElementById('range-hint');
    const btn  = document.getElementById('btn-rango');
    hint.classList.toggle('visible', rangeMode);
    btn.style.background = rangeMode ? 'rgba(201,168,76,.25)' : '';
    if (rangeMode) {
        hint.innerHTML = '📅 Modo rango: selecciona el <strong>primer día</strong> y luego el <strong>último</strong>.';
    }
    renderMiniCal();
}

async function bloquearDia() {
    if (!mcSelected) {
        showCfgStatus('vac-status', 'err', '⚠ Selecciona un día en el calendario.');
        return;
    }
    const motivo = document.getElementById('vac-motivo').value.trim() || 'Vacaciones';
    await cfgPost(
        { accion: 'bloquear_dia', fecha: mcSelected, motivo },
        'vac-status',
        `✓ Día ${mcSelected} bloqueado.`
    );
    mcSelected = null;
}

async function bloquearRango(desde, hasta) {
    const [d, h] = desde <= hasta ? [desde, hasta] : [hasta, desde];
    const motivo = document.getElementById('vac-motivo').value.trim() || 'Vacaciones';
    await cfgPost(
        { accion: 'bloquear_rango', desde: d, hasta: h, motivo },
        'vac-status',
        `✓ Rango ${d} → ${h} bloqueado.`
    );
}

async function desbloquearDia(fecha) {
    await cfgPost(
        { accion: 'desbloquear_dia', fecha },
        'vac-status',
        `✓ Día ${fecha} desbloqueado.`
    );
}

async function cfgPost(body, statusId, okMsg) {
    try {
        const r = await fetch(CFG_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const j = await r.json();
        if (j.ok) {
            showCfgStatus(statusId, 'ok', okMsg);
            await loadSettings();
        } else {
            showCfgStatus(statusId, 'err', '✕ ' + (j.error || 'Error.'));
        }
    } catch (e) {
        showCfgStatus(statusId, 'err', '✕ Sin conexión con el servidor.');
    }
}

function renderBlockedList() {
    const list = document.getElementById('blocked-list');
    if (!list) return;
    if (!cfgState.diasBloqueados.length) {
        list.innerHTML = `<div class="empty-blocked">No hay días bloqueados</div>`;
        return;
    }
    const DIAS = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
    list.innerHTML = cfgState.diasBloqueados.map(d => {
        const dt  = new Date(d.fecha + 'T00:00:00');
        const dia = DIAS[dt.getDay()];
        const fmt = `${dia} ${dt.getDate()}/${String(dt.getMonth()+1).padStart(2,'0')}/${dt.getFullYear()}`;
        return `
        <div class="blocked-item">
            <div class="blocked-item-info">
                <span class="blocked-fecha">📅 ${fmt}</span>
                <span class="blocked-motivo">${escHtml(d.motivo)}</span>
            </div>
            <button class="blocked-del" onclick="desbloquearDia('${d.fecha}')" title="Desbloquear">✕</button>
        </div>`;
    }).join('');
}

// ── Helpers ───────────────────────────────────────────────────
function fmtDate(d) {
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}
function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function showCfgStatus(id, type, msg) {
    const el = document.getElementById(id);
    if (!el) return;
    el.className  = `cfg-status ${type} visible`;
    el.textContent = msg;
    setTimeout(() => el.classList.remove('visible'), 4000);
}