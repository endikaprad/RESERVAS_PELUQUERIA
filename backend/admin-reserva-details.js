// ============================================================
//  PRADO BARBER CO. — admin-reserva-detail.js
//  Drawer de detalle de reserva al hacer click en fila/card
//  Incluye: info completa + propuesta cliente si aplica
// ============================================================

(function initReservaDetail() {
    'use strict';

    // ── Insertar HTML del drawer ──────────────────────────────
    const drawerHTML = `
    <div class="rd-overlay" id="rd-overlay" onclick="closeRD()"></div>
    <div class="rd-drawer" id="rd-drawer" role="dialog" aria-modal="true" aria-label="Detalle de reserva">
        <div class="rd-header">
            <div class="rd-header-left">
                <span class="rd-id" id="rd-id">#—</span>
                <div class="rd-hora-wrap">
                    <span class="rd-hora" id="rd-hora">—</span>
                    <span class="rd-estado-badge" id="rd-estado-badge"></span>
                </div>
                <span class="rd-fecha" id="rd-fecha">—</span>
            </div>
            <button class="rd-close" onclick="closeRD()" aria-label="Cerrar">✕</button>
        </div>

        <div class="rd-body">

            <!-- Cliente -->
            <div class="rd-section">
                <div class="rd-section-label">Cliente</div>
                <div class="rd-cliente-card">
                    <div class="rd-avatar" id="rd-avatar">—</div>
                    <div class="rd-cliente-info">
                        <div class="rd-cliente-nombre" id="rd-nombre">—</div>
                        <a class="rd-meta-link" id="rd-email" href="#">—</a>
                        <a class="rd-meta-link" id="rd-tel" href="#">—</a>
                    </div>
                </div>
            </div>

            <!-- Servicio / Barbero -->
            <div class="rd-section">
                <div class="rd-section-label">Cita</div>
                <div class="rd-grid-2">
                    <div class="rd-info-block">
                        <div class="rd-info-label">Servicio</div>
                        <div class="rd-info-value" id="rd-servicio">—</div>
                        <div class="rd-info-sub" id="rd-duracion">—</div>
                    </div>
                    <div class="rd-info-block">
                        <div class="rd-info-label">Precio</div>
                        <div class="rd-info-value rd-gold" id="rd-precio">—</div>
                    </div>
                    <div class="rd-info-block">
                        <div class="rd-info-label">Barbero</div>
                        <div class="rd-info-value"><span class="rd-barbero-pill" id="rd-barbero">—</span></div>
                    </div>
                    <div class="rd-info-block">
                        <div class="rd-info-label">Reserva creada</div>
                        <div class="rd-info-value rd-muted" id="rd-created">—</div>
                    </div>
                </div>
            </div>

            <!-- Notas -->
            <div class="rd-section" id="rd-notas-section" style="display:none;">
                <div class="rd-section-label">Notas del cliente</div>
                <div class="rd-notas-box" id="rd-notas">—</div>
            </div>

            <!-- Propuesta cliente (reprogramar_cliente) -->
            <div class="rd-section" id="rd-propuesta-section" style="display:none;">
                <div class="rd-section-label">Propuesta del cliente</div>
                <div class="rd-propuesta-card">
                    <div class="rd-propuesta-icon">⇄</div>
                    <div class="rd-propuesta-info">
                        <div class="rd-propuesta-title">El cliente propone un cambio de horario</div>
                        <div class="rd-propuesta-detail">
                            <div class="rd-propuesta-row">
                                <span class="rd-propuesta-label">Cita original</span>
                                <span class="rd-propuesta-val-old" id="rd-orig-slot">—</span>
                            </div>
                            <div class="rd-propuesta-row">
                                <span class="rd-propuesta-label">Propone cambiar a</span>
                                <span class="rd-propuesta-val-new" id="rd-new-slot">—</span>
                            </div>
                            <div class="rd-propuesta-row" id="rd-motivo-row" style="display:none;">
                                <span class="rd-propuesta-label">Motivo</span>
                                <span class="rd-propuesta-val-muted" id="rd-motivo">—</span>
                            </div>
                            <div class="rd-propuesta-row" id="rd-ronda-row" style="display:none;">
                                <span class="rd-propuesta-label">Ronda negociación</span>
                                <span class="rd-ronda-badge" id="rd-ronda">—</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="rd-propuesta-actions" id="rd-propuesta-actions"></div>
            </div>

            <!-- Propuesta barbero pendiente de respuesta cliente -->
            <div class="rd-section" id="rd-barbero-propuesta-section" style="display:none;">
                <div class="rd-section-label">Propuesta enviada al cliente</div>
                <div class="rd-propuesta-card rd-barbero-prop">
                    <div class="rd-propuesta-icon" style="color:#c9a84c;">⏳</div>
                    <div class="rd-propuesta-info">
                        <div class="rd-propuesta-title" style="color:#c9a84c;">Esperando respuesta del cliente</div>
                        <div class="rd-propuesta-detail">
                            <div class="rd-propuesta-row">
                                <span class="rd-propuesta-label">Cita original</span>
                                <span class="rd-propuesta-val-old" id="rd-bp-orig">—</span>
                            </div>
                            <div class="rd-propuesta-row">
                                <span class="rd-propuesta-label">Propuesta enviada</span>
                                <span class="rd-propuesta-val-new" id="rd-bp-new">—</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Token (copiable) -->
            <div class="rd-section">
                <div class="rd-section-label">Referencia</div>
                <div class="rd-token-row">
                    <code class="rd-token" id="rd-token">—</code>
                    <button class="rd-copy-btn" onclick="copyRDToken()" title="Copiar token">⎘</button>
                </div>
            </div>

        </div>

        <!-- Footer con acciones -->
        <div class="rd-footer" id="rd-footer"></div>
    </div>`;

    // ── Insertar CSS del drawer ───────────────────────────────
    const style = document.createElement('style');
    style.textContent = `
    .rd-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.65);
        backdrop-filter: blur(4px);
        z-index: 800;
        opacity: 0;
        pointer-events: none;
        transition: opacity .3s ease;
    }
    .rd-overlay.open { opacity: 1; pointer-events: all; }

    .rd-drawer {
        position: fixed;
        top: 0; right: 0; bottom: 0;
        width: min(480px, 100vw);
        background: #111119;
        border-left: 1px solid #252530;
        z-index: 801;
        display: flex;
        flex-direction: column;
        transform: translateX(100%);
        transition: transform .38s cubic-bezier(.16,1,.3,1);
        overflow: hidden;
    }
    .rd-drawer.open { transform: translateX(0); }

    .rd-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid #252530;
        flex-shrink: 0;
        background: linear-gradient(135deg, #18181f, #111119);
    }
    .rd-header-left { display: flex; flex-direction: column; gap: .2rem; }
    .rd-id { font-size: .65rem; letter-spacing: .15em; text-transform: uppercase; color: #7a7880; }
    .rd-hora-wrap { display: flex; align-items: center; gap: .75rem; margin: .15rem 0; }
    .rd-hora {
        font-family: 'Playfair Display', serif;
        font-size: 2rem; font-weight: 700;
        color: #d42b2b; line-height: 1;
    }
    .rd-fecha { font-size: .85rem; color: #a0a0b0; }
    .rd-close {
        width: 32px; height: 32px; border-radius: 50%;
        background: transparent; border: 1px solid #252530;
        color: #7a7880; cursor: pointer; font-size: .9rem;
        display: flex; align-items: center; justify-content: center;
        transition: all .2s; flex-shrink: 0;
    }
    .rd-close:hover { border-color: #d42b2b; color: #d42b2b; }

    .rd-estado-badge {
        display: inline-flex; align-items: center;
        padding: .22rem .65rem; border-radius: 100px;
        font-size: .68rem; font-weight: 600; letter-spacing: .04em;
        white-space: nowrap;
    }
    .rdb-pendiente  { background: rgba(245,158,11,.12); border: 1px solid rgba(245,158,11,.3); color: #f59e0b; }
    .rdb-aceptada   { background: rgba(34,197,94,.12);  border: 1px solid rgba(34,197,94,.3);  color: #22c55e; }
    .rdb-denegada   { background: rgba(212,43,43,.12);  border: 1px solid rgba(212,43,43,.3);  color: #d42b2b; }
    .rdb-cancelada  { background: rgba(107,114,128,.12);border: 1px solid rgba(107,114,128,.3);color: #9ca3af; }
    .rdb-reprogramar_barbero { background: rgba(201,168,76,.12); border: 1px solid rgba(201,168,76,.3); color: #c9a84c; }
    .rdb-reprogramar_cliente { background: rgba(37,80,160,.12);  border: 1px solid rgba(37,80,160,.35); color: #6b9fff; }

    .rd-body {
        flex: 1; overflow-y: auto; padding: 1.25rem 1.5rem;
    }
    .rd-body::-webkit-scrollbar { width: 4px; }
    .rd-body::-webkit-scrollbar-thumb { background: #252530; border-radius: 2px; }

    .rd-section { margin-bottom: 1.5rem; }
    .rd-section-label {
        font-size: .6rem; letter-spacing: .2em; text-transform: uppercase;
        color: #d42b2b; margin-bottom: .65rem;
        display: flex; align-items: center; gap: .5rem;
    }
    .rd-section-label::before { content: ''; width: 16px; height: 1px; background: #d42b2b; }

    .rd-cliente-card {
        display: flex; align-items: center; gap: 1rem;
        background: #18181f; border: 1px solid #252530;
        border-radius: 10px; padding: 1rem 1.25rem;
    }
    .rd-avatar {
        width: 44px; height: 44px; border-radius: 10px;
        background: rgba(212,43,43,.1); border: 1px solid rgba(212,43,43,.2);
        display: flex; align-items: center; justify-content: center;
        font-family: 'Playfair Display', serif;
        font-size: .9rem; font-weight: 700; color: #d42b2b;
        flex-shrink: 0;
    }
    .rd-cliente-info { display: flex; flex-direction: column; gap: .2rem; min-width: 0; }
    .rd-cliente-nombre { font-size: .95rem; font-weight: 500; color: #f0ece3; }
    .rd-meta-link {
        font-size: .78rem; color: #7a7880; text-decoration: none;
        transition: color .2s; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .rd-meta-link:hover { color: #d42b2b; }

    .rd-grid-2 {
        display: grid; grid-template-columns: 1fr 1fr; gap: .75rem;
    }
    .rd-info-block {
        background: #18181f; border: 1px solid #252530;
        border-radius: 8px; padding: .75rem 1rem;
    }
    .rd-info-label { font-size: .6rem; letter-spacing: .12em; text-transform: uppercase; color: #7a7880; margin-bottom: .25rem; }
    .rd-info-value { font-size: .88rem; color: #f0ece3; font-weight: 500; }
    .rd-info-sub   { font-size: .72rem; color: #7a7880; margin-top: .15rem; }
    .rd-gold  { color: #c9a84c !important; }
    .rd-muted { color: #7a7880 !important; font-size: .78rem !important; font-weight: 400 !important; }
    .rd-barbero-pill {
        display: inline-block; padding: .2rem .65rem;
        background: rgba(212,43,43,.08); border: 1px solid rgba(212,43,43,.2);
        border-radius: 100px; font-size: .75rem; color: #d42b2b;
    }

    .rd-notas-box {
        font-size: .82rem; color: #7a7880; font-style: italic;
        padding: .75rem 1rem; background: #0d0d14;
        border-radius: 6px; border-left: 2px solid #2a2a38; line-height: 1.7;
    }

    /* Propuesta card */
    .rd-propuesta-card {
        display: flex; gap: 1rem;
        background: #18181f; border: 1px solid rgba(37,80,160,.35);
        border-radius: 10px; padding: 1rem 1.25rem;
        margin-bottom: .85rem;
    }
    .rd-barbero-prop { border-color: rgba(201,168,76,.3); }
    .rd-propuesta-icon { font-size: 1.3rem; color: #6b9fff; flex-shrink: 0; line-height: 1.4; }
    .rd-propuesta-info { flex: 1; min-width: 0; }
    .rd-propuesta-title { font-size: .88rem; font-weight: 600; color: #6b9fff; margin-bottom: .65rem; }
    .rd-propuesta-detail { display: flex; flex-direction: column; gap: .45rem; }
    .rd-propuesta-row { display: flex; align-items: flex-start; gap: .75rem; font-size: .8rem; }
    .rd-propuesta-label { color: #7a7880; width: 120px; flex-shrink: 0; }
    .rd-propuesta-val-old { color: #9ca3af; text-decoration: line-through; }
    .rd-propuesta-val-new { color: #c9a84c; font-weight: 600; }
    .rd-propuesta-val-muted { color: #d4a84b; font-style: italic; }
    .rd-ronda-badge {
        display: inline-block; padding: .15rem .55rem;
        background: rgba(245,158,11,.1); border: 1px solid rgba(245,158,11,.3);
        border-radius: 100px; font-size: .68rem; color: #f59e0b;
    }

    .rd-propuesta-actions { display: flex; gap: .6rem; }
    .rd-pa-btn {
        flex: 1; padding: .65rem .5rem;
        border-radius: 7px; font-family: 'DM Sans', sans-serif;
        font-size: .72rem; font-weight: 700; letter-spacing: .08em;
        text-transform: uppercase; cursor: pointer; text-decoration: none;
        transition: all .22s; border: 1px solid transparent;
        display: flex; align-items: center; justify-content: center; gap: .3rem;
    }
    .rd-pa-accept {
        background: rgba(34,197,94,.1); border-color: rgba(34,197,94,.35); color: #22c55e;
    }
    .rd-pa-accept:hover { background: #22c55e; color: #000; }
    .rd-pa-deny {
        background: rgba(212,43,43,.1); border-color: rgba(212,43,43,.3); color: #d42b2b;
    }
    .rd-pa-deny:hover { background: #d42b2b; color: #fff; }
    .rd-pa-manage {
        background: rgba(107,114,128,.1); border-color: rgba(107,114,128,.3); color: #9ca3af;
    }
    .rd-pa-manage:hover { background: #374151; color: #fff; border-color: #4b5563; }

    .rd-token-row { display: flex; align-items: center; gap: .6rem; }
    .rd-token {
        flex: 1; font-size: .65rem; color: #7a7880;
        background: #0d0d14; border: 1px solid #1c1c26;
        border-radius: 5px; padding: .4rem .6rem;
        font-family: monospace;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .rd-copy-btn {
        width: 30px; height: 30px; border-radius: 6px;
        background: transparent; border: 1px solid #252530;
        color: #7a7880; cursor: pointer; font-size: .9rem;
        display: flex; align-items: center; justify-content: center;
        transition: all .2s; flex-shrink: 0;
    }
    .rd-copy-btn:hover { border-color: #c9a84c; color: #c9a84c; }

    .rd-footer {
        border-top: 1px solid #252530;
        padding: 1rem 1.5rem;
        display: flex; gap: .6rem; flex-wrap: wrap;
        flex-shrink: 0;
        background: #0d0d14;
    }
    .rd-footer-btn {
        flex: 1; min-width: 100px;
        padding: .7rem .75rem;
        border-radius: 8px; font-family: 'DM Sans', sans-serif;
        font-size: .75rem; font-weight: 700; letter-spacing: .1em;
        text-transform: uppercase; cursor: pointer;
        text-decoration: none; transition: all .22s;
        display: flex; align-items: center; justify-content: center; gap: .4rem;
        border: 1px solid transparent;
    }
    .rd-btn-accept { background: rgba(34,197,94,.1); border-color: rgba(34,197,94,.35); color: #22c55e; }
    .rd-btn-accept:hover { background: #22c55e; color: #000; }
    .rd-btn-deny   { background: rgba(212,43,43,.1);  border-color: rgba(212,43,43,.3);  color: #d42b2b; }
    .rd-btn-deny:hover   { background: #d42b2b; color: #fff; }
    .rd-btn-manage { background: rgba(107,114,128,.1);border-color: rgba(107,114,128,.3);color: #9ca3af; }
    .rd-btn-manage:hover { background: #374151; color: #fff; border-color: #4b5563; }

    /* Click target para filas/cards */
    .rd-clickable { cursor: pointer; }
    .rd-clickable:hover { background: rgba(37,37,48,.6) !important; }
    tr.rd-clickable:hover td { background: rgba(37,37,48,.6) !important; }

    @media (max-width: 520px) {
        .rd-grid-2 { grid-template-columns: 1fr; }
    }
    `;
    document.head.appendChild(style);

    // ── Insertar drawer en el body ────────────────────────────
    const wrap = document.createElement('div');
    wrap.innerHTML = drawerHTML;
    document.body.appendChild(wrap);

    // ── Estado del drawer ─────────────────────────────────────
    let currentToken = null;

    // ── Helpers de formato ────────────────────────────────────
    const DIAS_ES = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    const MESES_ES = ['','ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    const MESES_LARGO = ['','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];

    function formatFecha(ymd) {
        if (!ymd) return '—';
        const [y, m, d] = ymd.split('-').map(Number);
        const dow = new Date(y, m - 1, d).getDay();
        return DIAS_ES[dow] + ' ' + d + ' ' + MESES_LARGO[m] + ' ' + y;
    }

    function formatFechaCorta(ymd) {
        if (!ymd) return '—';
        const [y, m, d] = ymd.split('-').map(Number);
        const dow = new Date(y, m - 1, d).getDay();
        return DIAS_ES[dow] + ' ' + d + ' ' + MESES_ES[m];
    }

    function initials(name) {
        return (name || '—').split(' ').slice(0, 2).map(p => p[0]).join('').toUpperCase();
    }

    const ESTADO_LABELS = {
        pendiente:              '⏳ Pendiente',
        aceptada:               '✓ Aceptada',
        denegada:               '✕ Denegada',
        cancelada:              '✕ Cancelada',
        reprogramar_barbero:    '⇄ Prop. barbero',
        reprogramar_cliente:    '⇄ Prop. cliente',
    };

    // ── Función principal: abrir drawer con datos ─────────────
    window.openRD = function(data) {
        currentToken = data.token || '';

        // Header
        document.getElementById('rd-id').textContent    = '#' + data.id;
        document.getElementById('rd-hora').textContent  = data.hora ? data.hora.slice(0, 5) : '—';
        document.getElementById('rd-fecha').textContent = formatFechaCorta(data.fecha);

        // Badge estado
        const badge = document.getElementById('rd-estado-badge');
        const est   = data.estado || '';
        badge.textContent  = ESTADO_LABELS[est] || est;
        badge.className    = 'rd-estado-badge rdb-' + est;

        // Cliente
        const nombre = data.cliente_nombre || '—';
        document.getElementById('rd-avatar').textContent    = initials(nombre);
        document.getElementById('rd-nombre').textContent    = nombre;
        const emailEl = document.getElementById('rd-email');
        emailEl.textContent = data.cliente_email || '—';
        emailEl.href        = 'mailto:' + (data.cliente_email || '');
        const telEl = document.getElementById('rd-tel');
        telEl.textContent = data.cliente_telefono || '—';
        telEl.href        = 'tel:' + (data.cliente_telefono || '');

        // Cita
        document.getElementById('rd-servicio').textContent  = data.servicio || '—';
        document.getElementById('rd-duracion').textContent  = data.duracion || '';
        document.getElementById('rd-precio').textContent    = data.precio ? data.precio + ' €' : '—';
        document.getElementById('rd-barbero').textContent   = data.barbero || '—';

        // Fecha creado
        const creadoEl = document.getElementById('rd-created');
        if (data.creado_en) {
            const dt = new Date(data.creado_en);
            creadoEl.textContent = dt.toLocaleDateString('es-ES', { day:'2-digit', month:'short', year:'numeric' });
        } else {
            creadoEl.textContent = '—';
        }

        // Notas
        const notasSection = document.getElementById('rd-notas-section');
        if (data.notas && data.notas.trim()) {
            document.getElementById('rd-notas').textContent = '"' + data.notas + '"';
            notasSection.style.display = 'block';
        } else {
            notasSection.style.display = 'none';
        }

        // Token
        document.getElementById('rd-token').textContent = (data.token || '').slice(0, 32) + '…';

        // ── Propuesta cliente ─────────────────────────────────
        const propSection      = document.getElementById('rd-propuesta-section');
        const bpSection        = document.getElementById('rd-barbero-propuesta-section');
        propSection.style.display  = 'none';
        bpSection.style.display    = 'none';

        if (est === 'reprogramar_cliente') {
            propSection.style.display = 'block';

            // Cita original
            const origHora  = data.hora ? data.hora.slice(0, 5) : '—';
            const origFecha = formatFecha(data.fecha);
            document.getElementById('rd-orig-slot').textContent = origFecha + ' · ' + origHora;

            // Nueva propuesta del cliente
            const newFecha = data.nueva_fecha_propuesta || '';
            const newHora  = data.nueva_hora_propuesta  ? data.nueva_hora_propuesta.slice(0, 5) : '';
            document.getElementById('rd-new-slot').textContent =
                newFecha && newHora ? formatFecha(newFecha) + ' · ' + newHora : '—';

            // Motivo
            const motivoRow = document.getElementById('rd-motivo-row');
            if (data.motivo_cambio) {
                document.getElementById('rd-motivo').textContent = data.motivo_cambio;
                motivoRow.style.display = 'flex';
            } else {
                motivoRow.style.display = 'none';
            }

            // Ronda
            const ronda = parseInt(data.ronda_negociacion || 0);
            const rondaRow = document.getElementById('rd-ronda-row');
            if (ronda > 0) {
                document.getElementById('rd-ronda').textContent = 'Ronda ' + ronda;
                rondaRow.style.display = 'flex';
            } else {
                rondaRow.style.display = 'none';
            }

            // Acciones para propuesta del cliente
            const actionsDiv = document.getElementById('rd-propuesta-actions');
            actionsDiv.innerHTML = `
                <a class="rd-pa-btn rd-pa-accept"
                   href="?accion=aceptar&token=${encodeURIComponent(data.token)}&barbero=${encodeURIComponent(data.barbero_id||'')}&fecha=todas&estado=reprogramar_cliente"
                   onclick="return confirm('¿Aceptar el horario propuesto por el cliente?')">
                    ✓ Aceptar horario del cliente
                </a>
                <button class="rd-pa-btn rd-pa-manage"
                        onclick="closeRD(); if(typeof openCR==='function') openCR('${data.token}','${data.barbero_id||''}','${escJS(data.cliente_nombre)}','${escJS(data.servicio)}','${data.fecha}','${origHora}',${ronda})">
                    ⇄ Gestionar
                </button>`;

        } else if (est === 'reprogramar_barbero') {
            bpSection.style.display = 'block';
            const origHora  = data.hora ? data.hora.slice(0, 5) : '—';
            const origFecha = formatFecha(data.fecha);
            document.getElementById('rd-bp-orig').textContent = origFecha + ' · ' + origHora;
            const newFecha = data.nueva_fecha_propuesta || '';
            const newHora  = data.nueva_hora_propuesta  ? data.nueva_hora_propuesta.slice(0, 5) : '';
            document.getElementById('rd-bp-new').textContent =
                newFecha && newHora ? formatFecha(newFecha) + ' · ' + newHora : 'Esperando…';
        }

        // ── Footer acciones ───────────────────────────────────
        const footer = document.getElementById('rd-footer');
        footer.innerHTML = '';

        if (est === 'pendiente') {
            footer.innerHTML = `
                <a class="rd-footer-btn rd-btn-accept"
                   href="?accion=aceptar&token=${encodeURIComponent(data.token)}&barbero=todos&fecha=hoy&estado=todos"
                   onclick="return confirm('¿Aceptar la reserva de ${escJS(data.cliente_nombre)}?')">
                    ✓ Aceptar
                </a>
                <a class="rd-footer-btn rd-btn-deny"
                   href="?accion=denegar&token=${encodeURIComponent(data.token)}&barbero=todos&fecha=hoy&estado=todos"
                   onclick="return confirm('¿Denegar la reserva de ${escJS(data.cliente_nombre)}?')">
                    ✕ Denegar
                </a>`;
        } else if (est === 'aceptada' || est === 'reprogramar_barbero' || est === 'reprogramar_cliente') {
            const ronda = parseInt(data.ronda_negociacion || 0);
            const origHora = data.hora ? data.hora.slice(0, 5) : '—';
            footer.innerHTML = `
                <button class="rd-footer-btn rd-btn-manage"
                        onclick="closeRD(); if(typeof openCR==='function') openCR('${data.token}','${data.barbero_id||''}','${escJS(data.cliente_nombre)}','${escJS(data.servicio)}','${data.fecha}','${origHora}',${ronda})">
                    🚫 Cancelar / Reprogramar
                </button>`;
        }

        if (!footer.innerHTML.trim()) {
            footer.style.display = 'none';
        } else {
            footer.style.display = 'flex';
        }

        // Abrir
        document.getElementById('rd-overlay').classList.add('open');
        document.getElementById('rd-drawer').classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    window.closeRD = function() {
        document.getElementById('rd-overlay').classList.remove('open');
        document.getElementById('rd-drawer').classList.remove('open');
        document.body.style.overflow = '';
    };

    window.copyRDToken = function() {
        const tokenEl = document.getElementById('rd-token');
        navigator.clipboard.writeText(currentToken || '').then(() => {
            const btn = document.querySelector('.rd-copy-btn');
            btn.textContent = '✓';
            setTimeout(() => { btn.textContent = '⎘'; }, 1500);
        });
    };

    function escJS(str) {
        return (str || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }

    // ── Hacer filas de tabla clickeables ──────────────────────
    function attachRowListeners() {
        // Desktop table rows
        document.querySelectorAll('table tbody tr').forEach(row => {
            if (row.dataset.rdBound) return;
            row.dataset.rdBound = '1';
            row.classList.add('rd-clickable');
            row.style.cursor = 'pointer';
            row.addEventListener('click', function(e) {
                // No activar si el click fue en un botón o enlace
                if (e.target.closest('a,button,.btn-accept,.btn-deny,.tb-accept,.tb-deny,.btn-manage')) return;
                const data = extractFromRow(row);
                if (data) openRD(data);
            });
        });

        // Mobile cards
        document.querySelectorAll('.rc').forEach(card => {
            if (card.dataset.rdBound) return;
            card.dataset.rdBound = '1';
            card.addEventListener('click', function(e) {
                if (e.target.closest('a,button,.btn-accept,.btn-deny,.btn-manage-mobile,.rc-actions')) return;
                const data = extractFromCard(card);
                if (data) openRD(data);
            });
        });
    }

    function extractFromRow(row) {
        try {
            const cells  = row.querySelectorAll('td');
            if (!cells.length) return null;
            const idText    = cells[0]?.textContent?.trim().replace('#','') || '';
            const fechaText = cells[1]?.textContent?.trim() || '';
            const horaText  = cells[2]?.textContent?.trim() || '';
            const clienteTd = cells[3];
            const clienteNombre = clienteTd?.querySelector('strong')?.textContent?.trim() || '';
            const clienteSpans  = clienteTd?.querySelectorAll('span') || [];
            const clienteEmail  = clienteSpans[0]?.textContent?.trim() || '';
            const clienteTel    = clienteSpans[1]?.textContent?.trim() || '';
            const servicio  = cells[4]?.querySelector('[class]') ? cells[4].childNodes[0]?.textContent?.trim() : cells[4]?.textContent?.trim();
            const duracion  = cells[4]?.querySelector('span')?.textContent?.trim() || '';
            const precio    = cells[5]?.textContent?.trim().replace(' €','').replace('€','').trim() || '';
            const barbero   = cells[6]?.textContent?.trim() || '';
            const estado    = row.dataset.estado || guessEstadoFromBadge(cells[7]);
            const notas     = cells[9]?.textContent?.trim() === '—' ? '' : cells[9]?.textContent?.trim() || '';

            // Recuperar desde atributo data si existe (lo inyectamos abajo)
            const token   = row.dataset.token   || '';
            const barberoId = row.dataset.barberoId || '';
            const fechaISO  = row.dataset.fecha  || '';
            const creadoEn  = row.dataset.creado || '';
            const nuevaFechaProp = row.dataset.nuevaFecha || '';
            const nuevaHoraProp  = row.dataset.nuevaHora  || '';
            const motivoCambio   = row.dataset.motivo     || '';
            const ronda          = row.dataset.ronda      || '0';

            return {
                id: idText, fecha: fechaISO, hora: horaText + ':00',
                cliente_nombre: clienteNombre, cliente_email: clienteEmail,
                cliente_telefono: clienteTel, servicio, duracion, precio,
                barbero, barbero_id: barberoId, estado, notas, token,
                creado_en: creadoEn,
                nueva_fecha_propuesta: nuevaFechaProp,
                nueva_hora_propuesta:  nuevaHoraProp,
                motivo_cambio: motivoCambio,
                ronda_negociacion: ronda,
            };
        } catch(e) { return null; }
    }

    function extractFromCard(card) {
        try {
            const idText     = card.querySelector('.rc-id')?.textContent?.trim().replace('#','') || '';
            const horaText   = card.querySelector('.rc-hora')?.textContent?.trim() || '';
            const nombre     = card.querySelector('.rc-cliente-name')?.textContent?.trim() || '';
            const metas      = card.querySelectorAll('.rc-meta-item');
            const email      = (metas[0]?.textContent || '').replace('✉','').trim();
            const tel        = (metas[1]?.textContent || '').replace('📞','').trim();
            const servicio   = card.querySelector('.rc-detail-value:not(.gold):not([class*="rc-barbero"])')?.textContent?.trim() || '';
            const duracion   = card.querySelector('.rc-detail-sub')?.textContent?.trim() || '';
            const precio     = card.querySelector('.rc-detail-value.gold')?.textContent?.trim().replace(' €','').replace('€','') || '';
            const barberoEl  = card.querySelector('.rc-barbero-pill');
            const barbero    = barberoEl?.textContent?.trim() || '';
            const estadoBadge= card.querySelector('.ebadge');
            const estado     = estadoBadge ? guessEstadoFromClass(estadoBadge) : '';
            const notas      = card.querySelector('.rc-notas')?.textContent?.replace(/^"|"$/g,'').trim() || '';

            // Data attrs
            const token         = card.dataset.token    || '';
            const barberoId     = card.dataset.barberoId|| '';
            const fechaISO      = card.dataset.fecha    || '';
            const creadoEn      = card.dataset.creado   || '';
            const nuevaFechaProp= card.dataset.nuevaFecha|| '';
            const nuevaHoraProp = card.dataset.nuevaHora || '';
            const motivoCambio  = card.dataset.motivo   || '';
            const ronda         = card.dataset.ronda    || '0';

            return {
                id: idText, fecha: fechaISO, hora: horaText + ':00',
                cliente_nombre: nombre, cliente_email: email,
                cliente_telefono: tel, servicio, duracion, precio,
                barbero, barbero_id: barberoId, estado, notas, token,
                creado_en: creadoEn,
                nueva_fecha_propuesta: nuevaFechaProp,
                nueva_hora_propuesta:  nuevaHoraProp,
                motivo_cambio: motivoCambio,
                ronda_negociacion: ronda,
            };
        } catch(e) { return null; }
    }

    function guessEstadoFromBadge(td) {
        if (!td) return '';
        const text = td.textContent.toLowerCase();
        if (text.includes('pendiente'))  return 'pendiente';
        if (text.includes('aceptada'))   return 'aceptada';
        if (text.includes('denegada'))   return 'denegada';
        if (text.includes('cancelada'))  return 'cancelada';
        if (text.includes('prop. barbero') || text.includes('reprogramar_barbero')) return 'reprogramar_barbero';
        if (text.includes('prop. cliente') || text.includes('reprogramar_cliente')) return 'reprogramar_cliente';
        return '';
    }

    function guessEstadoFromClass(el) {
        const cls = el.className || '';
        if (cls.includes('pendiente'))  return 'pendiente';
        if (cls.includes('aceptada'))   return 'aceptada';
        if (cls.includes('denegada'))   return 'denegada';
        if (cls.includes('cancelada'))  return 'cancelada';
        if (cls.includes('reprogramar_barbero')) return 'reprogramar_barbero';
        if (cls.includes('reprogramar_cliente')) return 'reprogramar_cliente';
        return '';
    }

    // ── Escape para teclado ───────────────────────────────────
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeRD();
    });

    // ── Init: adjuntar listeners después de que el DOM esté listo ──
    function init() {
        attachRowListeners();
        // Re-adjuntar si el DOM cambia (filtros, reloads)
        const observer = new MutationObserver(() => attachRowListeners());
        const container = document.querySelector('.admin-body');
        if (container) observer.observe(container, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();