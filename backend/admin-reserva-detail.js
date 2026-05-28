// ============================================================
//  PRADO BARBER CO. — admin-reserva-detail.js
//  FIXES APPLIED:
//  1. Motivo pre-filled & optional when barber re-proposes in negotiation
//  2. "Gestionar" button hidden for past bookings
//  3. "Propone cambiar a" now shows correctly from data attributes
//  4. Negotiation states open RD drawer when clicking "Gestionar"
//  5. Cancel from admin panel → labels as "Denegar" (not "Cancelar")
//     because cancel-by-barber.php now sets estado='denegada'
//  6. FIX: Accept for reprogramar_cliente now calls barber-accept-counter.php
//     so it updates fecha/hora to the CLIENT'S proposed slot, not the original.
//  7. FIX: Historial de negociación ahora muestra los horarios propuestos
//     por el barbero y el cliente con chips visuales de slot.
// ============================================================

(function initReservaDetail() {
    'use strict';

    // ── HTML del drawer ───────────────────────────────────────
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

            <!-- Historial de negociación finalizada -->
            <div class="rd-section" id="rd-historial-section" style="display:none;">
                <div class="rd-section-label">Historial de negociación</div>
                <div id="rd-historial-content"></div>
            </div>

            <!-- Propuesta cliente en curso (reprogramar_cliente) -->
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
            </div>

            <!-- Propuesta barbero pendiente de respuesta cliente (reprogramar_barbero) -->
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
                            <div class="rd-propuesta-row" id="rd-bp-ronda-row" style="display:none;">
                                <span class="rd-propuesta-label">Ronda</span>
                                <span class="rd-ronda-badge" id="rd-bp-ronda">—</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TOKEN -->
            <div class="rd-section">
                <div class="rd-section-label">Referencia</div>
                <div class="rd-token-row">
                    <code class="rd-token" id="rd-token">—</code>
                    <button class="rd-copy-btn" onclick="copyRDToken()" title="Copiar token">⎘</button>
                </div>
            </div>

            <!-- ── INLINE: Modal Denegar (barbero cancela = deniega) ── -->
            <div id="rd-cancel-inline" style="display:none;">
                <div class="rd-section-label" style="color:#d42b2b;">Denegar cita</div>
                <div style="background:rgba(212,43,43,.06);border:1px solid rgba(212,43,43,.2);border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.78rem;color:#d4534b;line-height:1.6;">
                    ⚠ La reserva quedará como <strong>denegada</strong> y se notificará al cliente por email con el motivo indicado.
                </div>
                <div style="display:flex;flex-direction:column;gap:.35rem;margin-bottom:1rem;">
                    <label style="font-size:.65rem;letter-spacing:.15em;text-transform:uppercase;color:#7a7880;">Motivo de denegación *</label>
                    <textarea id="rd-cancel-motivo" rows="4" placeholder="Ej: Enfermedad imprevista, problema técnico, cambio de agenda…"
                        style="background:#18181f;border:1px solid #252530;border-radius:8px;padding:.75rem 1rem;color:#f0ece3;font-family:'DM Sans',sans-serif;font-size:.88rem;resize:vertical;"></textarea>
                </div>
                <div style="display:flex;gap:.6rem;">
                    <button onclick="rdCancelBack()" style="flex:1;padding:.7rem;border-radius:7px;background:transparent;border:1px solid #252530;color:#7a7880;font-family:'DM Sans',sans-serif;font-size:.72rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;cursor:pointer;">← Volver</button>
                    <button id="rd-btn-do-cancel" onclick="rdDoCancel()" style="flex:2;padding:.7rem;border-radius:7px;background:rgba(212,43,43,.12);border:1px solid rgba(212,43,43,.4);color:#d42b2b;font-family:'DM Sans',sans-serif;font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;cursor:pointer;">✕ Confirmar denegación</button>
                </div>
                <div id="rd-cancel-status" style="display:none;margin-top:.75rem;padding:.65rem 1rem;border-radius:8px;font-size:.78rem;"></div>
            </div>

            <!-- ── INLINE: Calendario para proponer horario ── -->
            <div id="rd-reschedule-inline" style="display:none;">
                <div class="rd-section-label" style="color:#c9a84c;">Proponer nuevo horario</div>
                <div style="background:rgba(201,168,76,.06);border:1px solid rgba(201,168,76,.2);border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.78rem;color:#d4a84b;line-height:1.6;">
                    ⇄ El cliente recibirá un email con el nuevo horario propuesto.
                </div>
                <div style="display:flex;flex-direction:column;gap:.35rem;margin-bottom:1rem;">
                    <label style="font-size:.65rem;letter-spacing:.15em;text-transform:uppercase;color:#7a7880;">Motivo del cambio <span id="rd-resch-motivo-required">*</span></label>
                    <textarea id="rd-resch-motivo" rows="2" placeholder="Ej: Cambio de agenda, formación…"
                        style="background:#18181f;border:1px solid #252530;border-radius:8px;padding:.75rem 1rem;color:#f0ece3;font-family:'DM Sans',sans-serif;font-size:.88rem;resize:vertical;"></textarea>
                    <span id="rd-resch-motivo-hint" style="display:none;font-size:.68rem;color:#7a7880;font-style:italic;">El motivo anterior se usará si no escribes uno nuevo.</span>
                </div>
                <!-- Calendario inline -->
                <div style="background:#18181f;border:1px solid #252530;border-radius:10px;padding:1rem;margin-bottom:1rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.85rem;">
                        <span id="rd-cal-title" style="font-size:.9rem;font-weight:600;">—</span>
                        <div style="display:flex;gap:.3rem;">
                            <button onclick="rdCalNav(-1)" style="width:28px;height:28px;border:1px solid #252530;border-radius:4px;background:transparent;color:#7a7880;font-size:.85rem;cursor:pointer;display:flex;align-items:center;justify-content:center;font-family:'DM Sans',sans-serif;">‹</button>
                            <button onclick="rdCalNav(1)"  style="width:28px;height:28px;border:1px solid #252530;border-radius:4px;background:transparent;color:#7a7880;font-size:.85rem;cursor:pointer;display:flex;align-items:center;justify-content:center;font-family:'DM Sans',sans-serif;">›</button>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px;margin-bottom:.3rem;">
                        <div style="text-align:center;font-size:.55rem;letter-spacing:.08em;text-transform:uppercase;color:#7a7880;padding:.2rem 0;">L</div>
                        <div style="text-align:center;font-size:.55rem;letter-spacing:.08em;text-transform:uppercase;color:#7a7880;padding:.2rem 0;">M</div>
                        <div style="text-align:center;font-size:.55rem;letter-spacing:.08em;text-transform:uppercase;color:#7a7880;padding:.2rem 0;">X</div>
                        <div style="text-align:center;font-size:.55rem;letter-spacing:.08em;text-transform:uppercase;color:#7a7880;padding:.2rem 0;">J</div>
                        <div style="text-align:center;font-size:.55rem;letter-spacing:.08em;text-transform:uppercase;color:#7a7880;padding:.2rem 0;">V</div>
                        <div style="text-align:center;font-size:.55rem;letter-spacing:.08em;text-transform:uppercase;color:#7a7880;padding:.2rem 0;">S</div>
                        <div style="text-align:center;font-size:.55rem;letter-spacing:.08em;text-transform:uppercase;color:#7a7880;padding:.2rem 0;">D</div>
                    </div>
                    <div id="rd-cal-grid" style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px;"></div>
                </div>
                <!-- Slots -->
                <div style="margin-bottom:1.25rem;">
                    <div style="font-size:.65rem;letter-spacing:.15em;text-transform:uppercase;color:#7a7880;margin-bottom:.6rem;">Horarios disponibles</div>
                    <div id="rd-slots-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:.4rem;">
                        <div style="grid-column:1/-1;text-align:center;padding:.85rem;color:#7a7880;font-size:.8rem;">Selecciona un día del calendario</div>
                    </div>
                </div>
                <div style="display:flex;gap:.6rem;">
                    <button onclick="rdReschBack()" style="flex:1;padding:.7rem;border-radius:7px;background:transparent;border:1px solid #252530;color:#7a7880;font-family:'DM Sans',sans-serif;font-size:.72rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;cursor:pointer;">← Volver</button>
                    <button id="rd-btn-do-reschedule" onclick="rdDoReschedule()" disabled style="flex:2;padding:.7rem;border-radius:7px;background:linear-gradient(135deg,#c9a84c,#a17c2d);border:none;color:#000;font-family:'DM Sans',sans-serif;font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;cursor:pointer;opacity:.4;">⇄ Enviar propuesta</button>
                </div>
                <div id="rd-resch-status" style="display:none;margin-top:.75rem;padding:.65rem 1rem;border-radius:8px;font-size:.78rem;"></div>
            </div>

        </div>

        <!-- Footer con acciones -->
        <div class="rd-footer" id="rd-footer"></div>
    </div>`;

    // ── CSS del drawer ────────────────────────────────────────
    const style = document.createElement('style');
    style.textContent = `
    .rd-overlay {
        position: fixed; inset: 0;
        background: rgba(0,0,0,.65); backdrop-filter: blur(4px);
        z-index: 800; opacity: 0; pointer-events: none; transition: opacity .3s ease;
    }
    .rd-overlay.open { opacity: 1; pointer-events: all; }

    .rd-drawer {
        position: fixed; top: 0; right: 0; bottom: 0;
        width: min(480px, 100vw);
        background: #111119; border-left: 1px solid #252530;
        z-index: 801; display: flex; flex-direction: column;
        transform: translateX(100%);
        transition: transform .38s cubic-bezier(.16,1,.3,1);
        overflow: hidden;
    }
    .rd-drawer.open { transform: translateX(0); }

    .rd-header {
        display: flex; align-items: flex-start; justify-content: space-between;
        padding: 1.25rem 1.5rem; border-bottom: 1px solid #252530; flex-shrink: 0;
        background: linear-gradient(135deg, #18181f, #111119);
    }
    .rd-header-left { display: flex; flex-direction: column; gap: .2rem; }
    .rd-id { font-size: .65rem; letter-spacing: .15em; text-transform: uppercase; color: #7a7880; }
    .rd-hora-wrap { display: flex; align-items: center; gap: .75rem; margin: .15rem 0; }
    .rd-hora { font-family: 'Playfair Display', serif; font-size: 2rem; font-weight: 700; color: #d42b2b; line-height: 1; }
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
        font-size: .68rem; font-weight: 600; letter-spacing: .04em; white-space: nowrap;
    }
    .rdb-pendiente  { background: rgba(245,158,11,.12); border: 1px solid rgba(245,158,11,.3); color: #f59e0b; }
    .rdb-aceptada   { background: rgba(34,197,94,.12);  border: 1px solid rgba(34,197,94,.3);  color: #22c55e; }
    .rdb-denegada   { background: rgba(212,43,43,.12);  border: 1px solid rgba(212,43,43,.3);  color: #d42b2b; }
    .rdb-cancelada  { background: rgba(107,114,128,.12);border: 1px solid rgba(107,114,128,.3);color: #9ca3af; }
    .rdb-reprogramar_barbero { background: rgba(201,168,76,.12); border: 1px solid rgba(201,168,76,.3); color: #c9a84c; }
    .rdb-reprogramar_cliente { background: rgba(37,80,160,.12);  border: 1px solid rgba(37,80,160,.35); color: #6b9fff; }

    .rd-body { flex: 1; overflow-y: auto; padding: 1.25rem 1.5rem; }
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
        font-family: 'Playfair Display', serif; font-size: .9rem; font-weight: 700; color: #d42b2b; flex-shrink: 0;
    }
    .rd-cliente-info { display: flex; flex-direction: column; gap: .2rem; min-width: 0; }
    .rd-cliente-nombre { font-size: .95rem; font-weight: 500; color: #f0ece3; }
    .rd-meta-link { font-size: .78rem; color: #7a7880; text-decoration: none; transition: color .2s; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .rd-meta-link:hover { color: #d42b2b; }

    .rd-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
    .rd-info-block { background: #18181f; border: 1px solid #252530; border-radius: 8px; padding: .75rem 1rem; }
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

    /* Historial */
    .rd-historial-timeline { display: flex; flex-direction: column; gap: .5rem; }
    .rd-historial-item {
        display: flex; gap: .75rem; align-items: flex-start;
        background: #18181f; border: 1px solid #252530;
        border-radius: 8px; padding: .75rem 1rem;
    }
    .rd-historial-item.barbero-item { border-color: rgba(201,168,76,.25); }
    .rd-historial-item.cliente-item { border-color: rgba(37,80,160,.25); }
    .rd-historial-item.final-item   { border-color: rgba(34,197,94,.25); }
    .rd-historial-item.cancelled-item { border-color: rgba(107,114,128,.25); }
    .rd-hist-icon { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .75rem; flex-shrink: 0; margin-top: .1rem; }
    .rd-hist-icon.barbero { background: rgba(201,168,76,.15); border: 1px solid rgba(201,168,76,.3); color: #c9a84c; }
    .rd-hist-icon.cliente { background: rgba(37,80,160,.15);  border: 1px solid rgba(37,80,160,.3);  color: #6b9fff; }
    .rd-hist-icon.final   { background: rgba(34,197,94,.15);  border: 1px solid rgba(34,197,94,.3);  color: #22c55e; }
    .rd-hist-icon.cancelled { background: rgba(107,114,128,.15); border: 1px solid rgba(107,114,128,.3); color: #9ca3af; }
    .rd-hist-info { flex: 1; min-width: 0; }
    .rd-hist-title { font-size: .8rem; font-weight: 600; margin-bottom: .25rem; }
    .rd-hist-detail { font-size: .75rem; color: #7a7880; }
    .rd-hist-detail strong { color: #f0ece3; }
    .rd-hist-ronda { display: inline-block; padding: .1rem .45rem; background: rgba(245,158,11,.1); border: 1px solid rgba(245,158,11,.25); border-radius: 100px; font-size: .62rem; color: #f59e0b; margin-left: .4rem; vertical-align: middle; }

    /* Slot chips en historial */
    .rd-hist-slots { display: flex; align-items: center; gap: .4rem; flex-wrap: wrap; margin-top: .45rem; }
    .rd-slot-chip { font-size: .72rem; padding: .18rem .55rem; border-radius: 100px; white-space: nowrap; }
    .rd-slot-chip-old { color: #9ca3af; background: rgba(107,114,128,.1); border: 1px solid rgba(107,114,128,.25); text-decoration: line-through; }
    .rd-slot-chip-barbero { color: #c9a84c; background: rgba(201,168,76,.12); border: 1px solid rgba(201,168,76,.3); font-weight: 600; }
    .rd-slot-chip-cliente { color: #6b9fff; background: rgba(37,80,160,.12); border: 1px solid rgba(37,80,160,.3); font-weight: 600; }
    .rd-slot-chip-final   { color: #22c55e; background: rgba(34,197,94,.12); border: 1px solid rgba(34,197,94,.3); font-weight: 600; }
    .rd-slot-arrow { font-size: .72rem; color: #7a7880; }

    /* Propuesta card */
    .rd-propuesta-card {
        display: flex; gap: 1rem;
        background: #18181f; border: 1px solid rgba(37,80,160,.35);
        border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: .5rem;
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
    .rd-ronda-badge { display: inline-block; padding: .15rem .55rem; background: rgba(245,158,11,.1); border: 1px solid rgba(245,158,11,.3); border-radius: 100px; font-size: .68rem; color: #f59e0b; }

    .rd-token-row { display: flex; align-items: center; gap: .6rem; }
    .rd-token { flex: 1; font-size: .65rem; color: #7a7880; background: #0d0d14; border: 1px solid #1c1c26; border-radius: 5px; padding: .4rem .6rem; font-family: monospace; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .rd-copy-btn { width: 30px; height: 30px; border-radius: 6px; background: transparent; border: 1px solid #252530; color: #7a7880; cursor: pointer; font-size: .9rem; display: flex; align-items: center; justify-content: center; transition: all .2s; flex-shrink: 0; }
    .rd-copy-btn:hover { border-color: #c9a84c; color: #c9a84c; }

    .rd-footer {
        border-top: 1px solid #252530; padding: 1rem 1.5rem;
        display: flex; gap: .6rem; flex-wrap: wrap; flex-shrink: 0; background: #0d0d14;
    }
    .rd-footer-btn {
        flex: 1; min-width: 80px;
        padding: .7rem .75rem; border-radius: 8px;
        font-family: 'DM Sans', sans-serif; font-size: .72rem; font-weight: 700;
        letter-spacing: .08em; text-transform: uppercase; cursor: pointer;
        text-decoration: none; transition: all .22s;
        display: flex; align-items: center; justify-content: center; gap: .3rem;
        border: 1px solid transparent;
    }
    .rd-btn-accept    { background: rgba(34,197,94,.1);  border-color: rgba(34,197,94,.35);  color: #22c55e; }
    .rd-btn-accept:hover    { background: #22c55e; color: #000; }
    .rd-btn-deny      { background: rgba(212,43,43,.1);  border-color: rgba(212,43,43,.3);   color: #d42b2b; }
    .rd-btn-deny:hover      { background: #d42b2b; color: #fff; }
    .rd-btn-reschedule-only { background: rgba(201,168,76,.1); border-color: rgba(201,168,76,.35); color: #c9a84c; }
    .rd-btn-reschedule-only:hover { background: #c9a84c; color: #000; }
    .rd-btn-cancel-only { background: rgba(107,114,128,.1); border-color: rgba(107,114,128,.3); color: #9ca3af; }
    .rd-btn-cancel-only:hover { background: #374151; color: #fff; border-color: #4b5563; }

    /* Celdas del calendario inline */
    .rd-cal-cell {
        aspect-ratio: 1; display: flex; align-items: center; justify-content: center;
        border-radius: 6px; font-size: .72rem; cursor: pointer; transition: all .15s;
        border: 1px solid transparent;
    }
    .rd-cal-cell:hover:not(.rdc-dis):not(.rdc-empty) { border-color: rgba(201,168,76,.4); color: #c9a84c; }
    .rd-cal-cell.rdc-today:not(.rdc-sel) { border-color: rgba(212,43,43,.35); color: #d42b2b; }
    .rd-cal-cell.rdc-dis  { color: #2a2a38; cursor: not-allowed; }
    .rd-cal-cell.rdc-sel  { background: rgba(201,168,76,.18); border-color: #c9a84c; color: #c9a84c; font-weight: 700; }
    .rd-cal-cell.rdc-empty { cursor: default; }

    /* Slots inline */
    .rd-slot {
        padding: .5rem .25rem; border: 1px solid #252530; border-radius: 6px;
        text-align: center; font-size: .78rem; color: #7a7880;
        cursor: pointer; transition: all .18s; background: #18181f;
    }
    .rd-slot:hover:not(.rds-taken):not(.rds-past) { border-color: #c9a84c; color: #c9a84c; }
    .rd-slot.rds-sel   { background: rgba(201,168,76,.12); border-color: #c9a84c; color: #c9a84c; font-weight: 600; }
    .rd-slot.rds-taken { opacity: .3; cursor: not-allowed; text-decoration: line-through; }
    .rd-slot.rds-past  { opacity: .2; cursor: not-allowed; text-decoration: line-through; }

    .rd-negociacion-banner {
        background: linear-gradient(135deg, rgba(201,168,76,.08), rgba(37,80,160,.08));
        border: 1px solid rgba(201,168,76,.25);
        border-radius: 10px;
        padding: .85rem 1.1rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: .75rem;
        font-size: .8rem;
        color: #c9a84c;
    }
    .rd-negociacion-banner-icon { font-size: 1.1rem; flex-shrink: 0; }
    .rd-negociacion-banner-text { flex: 1; line-height: 1.5; }
    .rd-negociacion-banner-ronda {
        display: inline-block; padding: .2rem .65rem;
        background: rgba(245,158,11,.12); border: 1px solid rgba(245,158,11,.3);
        border-radius: 100px; font-size: .68rem; color: #f59e0b;
        white-space: nowrap; flex-shrink: 0;
    }

    .rd-clickable { cursor: pointer; }
    .rd-clickable:hover { background: rgba(37,37,48,.6) !important; }
    tr.rd-clickable:hover td { background: rgba(37,37,48,.6) !important; }

    @media (max-width: 520px) { .rd-grid-2 { grid-template-columns: 1fr; } }
    `;
    document.head.appendChild(style);

    // ── Insertar drawer ───────────────────────────────────────
    const wrap = document.createElement('div');
    wrap.innerHTML = drawerHTML;
    document.body.appendChild(wrap);

    // ── Estado del drawer ─────────────────────────────────────
    let currentToken = null;
    let currentData = null;

    // ── Calendario inline state ───────────────────────────────
    let rdCalDate = new Date();
    let rdSelectedDate = null;
    let rdSelectedSlot = null;
    let rdTakenSlots = [];

    const SLOTS_API = './api/slots.php';
    const CANCEL_API = './api/cancel-by-barber.php';
    const ACCEPT_COUNTER_API = './api/barber-accept-counter.php';

    const MONTHS_ES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const ALL_SLOTS = ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '13:00', '13:30', '16:00', '16:30', '17:00', '17:30', '18:00', '18:30', '19:00', '19:30'];

    // ── Helpers ───────────────────────────────────────────────
    const DIAS_ES = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    const MESES_ES = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    const MESES_LARGO = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

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
    function pad2(n) { return String(n).padStart(2, '0'); }
    function isoDate(y, m, d) { return y + '-' + pad2(m + 1) + '-' + pad2(d); }

    function isBookingPast(fecha) {
        if (!fecha) return false;
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const bookingDate = new Date(fecha + 'T00:00:00');
        return bookingDate < today;
    }

    const ESTADO_LABELS = {
        pendiente: '⏳ Pendiente',
        aceptada: '✓ Aceptada',
        denegada: '✕ Denegada',
        cancelada: '✕ Cancelada',
        reprogramar_barbero: '⇄ Prop. barbero',
        reprogramar_cliente: '⇄ Prop. cliente',
    };

    // ── Historial de negociación ──────────────────────────────
    // Recibe todos los datos disponibles para mostrar los slots propuestos
    // en cada paso de la negociación.
    //
    // Lógica de slots:
    //  - La BD guarda en nueva_fecha_propuesta / nueva_hora_propuesta
    //    SIEMPRE la propuesta más reciente (barbero o cliente).
    //  - El motivo_cambio es un log pipe-separated:
    //      "Motivo barbero ronda 1 | Contrapropuesta cliente (ronda 1) | Motivo barbero ronda 2 | ..."
    //  - Para estados finalizados (denegada/aceptada), nueva_fecha puede estar NULL.
    //  - Usamos la heurística: si hay N entradas en el log, la nueva_fecha corresponde
    //    a la última propuesta. Si N es par → última propuesta fue del cliente (reprogramar_cliente).
    //    Si N es impar → última propuesta fue del barbero.
    function parseHistorial(motivoCambio, estado, ronda, fechaOriginal, horaOriginal, nuevaFechaProp, nuevaHoraProp) {
        const items = [];
        if (!motivoCambio && ronda === 0) return items;

        const partes = motivoCambio
            ? motivoCambio.split(' | ').map(s => s.trim()).filter(Boolean)
            : [];

        const normHora = (h) => h ? String(h).slice(0, 5) : '—';
        const origSlotStr = (fechaOriginal && horaOriginal)
            ? formatFecha(fechaOriginal) + ' · ' + normHora(horaOriginal)
            : '—';

        // Extraer slots del barbero del log: "motivo [propuesta: YYYY-MM-DD HH:MM]"
        // Extraer slots del cliente del log: "Contrapropuesta cliente (ronda N): YYYY-MM-DD HH:MM"
        const barberoSlotsFromLog = {}; // rondaIndex -> slot string
        const clientSlots = {};         // ronda -> slot string

        let barberoRondaCounter = 0;
        partes.forEach(p => {
            const isClientEntry = /contrapropuesta cliente/i.test(p);
            if (!isClientEntry) {
                barberoRondaCounter++;
                // Intentar extraer fecha de "[propuesta: YYYY-MM-DD HH:MM]"
                const propMatch = p.match(/\[propuesta:\s*(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2})\]/i);
                if (propMatch) {
                    barberoSlotsFromLog[barberoRondaCounter] = formatFecha(propMatch[1]) + ' · ' + propMatch[2];
                }
            } else {
                const clientMatch = p.match(/contrapropuesta cliente \(ronda\s*(\d+)\)[^:]*:\s*(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2})/i);
                if (clientMatch) {
                    const r = parseInt(clientMatch[1]);
                    clientSlots[r] = formatFecha(clientMatch[2]) + ' · ' + clientMatch[3];
                }
            }
        });

        // Fallback: para la última ronda del barbero sin slot en log, usar nueva_fecha_propuesta
        // solo si el último entry es del barbero (aún no respondido por cliente)
        const lastParte = partes[partes.length - 1] || '';
        const lastIsClient = /contrapropuesta cliente/i.test(lastParte);
        const latestStoredSlot = (nuevaFechaProp && nuevaHoraProp)
            ? formatFecha(nuevaFechaProp) + ' · ' + normHora(nuevaHoraProp)
            : null;

        if (latestStoredSlot && !lastIsClient && barberoRondaCounter > 0 && !barberoSlotsFromLog[barberoRondaCounter]) {
            barberoSlotsFromLog[barberoRondaCounter] = latestStoredSlot;
        }
        // Si el estado es aceptada y el último fue cliente, nueva_fecha/hora es la cita confirmada (ya en fechaOriginal)
        // Si el último fue barbero y estado aceptada, la cita confirmada es barberoSlotsFromLog[last] = latestStoredSlot

        // Construir timeline
        let barberoIdx = 0;
        partes.forEach((p) => {
            const isClientEntry = /contrapropuesta cliente/i.test(p);

            if (!isClientEntry) {
                barberoIdx++;
                const currentRonda = barberoIdx;

                let slotOrigen = null;
                if (currentRonda === 1) {
                    slotOrigen = origSlotStr;
                } else {
                    slotOrigen = clientSlots[currentRonda - 1] || null;
                }

                // Limpiar el motivo quitando la parte [propuesta: ...]
                const motivoLimpio = p.replace(/\s*\[propuesta:[^\]]+\]/gi, '').trim();

                items.push({
                    tipo: 'barbero',
                    icono: '⇄',
                    titulo: 'Barbero propuso cambio',
                    detalle: motivoLimpio,
                    rondaLabel: 'Ronda ' + currentRonda,
                    slotOrigen: slotOrigen,
                    slotDestino: barberoSlotsFromLog[currentRonda] || null,
                });
            } else {
                const roundMatch = p.match(/ronda\s*(\d+)/i);
                const clientRonda = roundMatch ? parseInt(roundMatch[1]) : barberoIdx;

                items.push({
                    tipo: 'cliente',
                    icono: '↩',
                    titulo: 'Cliente propuso alternativa',
                    detalle: 'El cliente no pudo en el horario propuesto y ofreció una alternativa.',
                    rondaLabel: 'Ronda ' + clientRonda,
                    slotOrigen: barberoSlotsFromLog[clientRonda] || null,
                    slotDestino: clientSlots[clientRonda] || null,
                });
            }
        });

        // Estado final
        const esNegociacionFinalizada = ['aceptada', 'cancelada', 'denegada'].includes(estado);
        if (esNegociacionFinalizada && (ronda > 0 || partes.length > 0)) {
            if (estado === 'aceptada') {
                const fechaFmt = (fechaOriginal && horaOriginal)
                    ? formatFecha(fechaOriginal) + ' · ' + normHora(horaOriginal)
                    : '—';
                items.push({
                    tipo: 'final',
                    icono: '✓',
                    titulo: 'Negociación finalizada — Cita confirmada',
                    detalle: '',
                    rondaLabel: null,
                    slotOrigen: null,
                    slotDestino: fechaFmt,
                });
            } else {
                items.push({
                    tipo: 'cancelled',
                    icono: '✕',
                    titulo: 'Negociación finalizada — Cita denegada',
                    detalle: 'No se llegó a un acuerdo o fue denegada durante la negociación.',
                    rondaLabel: null,
                    slotOrigen: null,
                    slotDestino: null,
                });
            }
        }

        return items;
    }

    function renderHistorial(items) {
        if (!items.length) return '';
        return '<div class="rd-historial-timeline">' + items.map(item => {
            // Construir chips de slots
            let slotsHtml = '';
            if (item.slotOrigen || item.slotDestino) {
                slotsHtml = '<div class="rd-hist-slots">';

                if (item.slotOrigen) {
                    slotsHtml += `<span class="rd-slot-chip rd-slot-chip-old">${item.slotOrigen}</span>`;
                }

                if (item.slotOrigen && item.slotDestino) {
                    slotsHtml += `<span class="rd-slot-arrow">→</span>`;
                }

                if (item.slotDestino) {
                    // Color según quién propone
                    let chipClass = 'rd-slot-chip-barbero';
                    if (item.tipo === 'cliente') chipClass = 'rd-slot-chip-cliente';
                    if (item.tipo === 'final') chipClass = 'rd-slot-chip-final';
                    slotsHtml += `<span class="rd-slot-chip ${chipClass}">${item.slotDestino}</span>`;
                }

                slotsHtml += '</div>';
            }

            return `<div class="rd-historial-item ${item.tipo}-item">
                <div class="rd-hist-icon ${item.tipo}">${item.icono}</div>
                <div class="rd-hist-info">
                    <div class="rd-hist-title">${item.titulo}${item.rondaLabel ? '<span class="rd-hist-ronda">' + item.rondaLabel + '</span>' : ''}</div>
                    <div class="rd-hist-detail">${item.detalle}</div>
                    ${slotsHtml}
                </div>
            </div>`;
        }).join('') + '</div>';
    }

    // ── FIX: Aceptar propuesta del cliente via barber-accept-counter ──
    window.rdAcceptClientCounter = async function () {
        if (!currentToken) return;
        const nombreCliente = currentData?.cliente_nombre || 'el cliente';
        if (!confirm('¿Aceptar el horario propuesto por ' + nombreCliente + '?\n\nLa cita se confirmará con el nuevo horario y se notificará al cliente.')) return;

        const btn = document.querySelector('#rd-footer .rd-btn-accept');
        if (btn) { btn.style.pointerEvents = 'none'; btn.textContent = '⏳ Procesando…'; }

        try {
            const res = await fetch(ACCEPT_COUNTER_API + '?token=' + encodeURIComponent(currentToken) + '&accion=aceptar');
            const text = await res.text();
            if (res.ok && (text.includes('confirmada') || text.includes('confirmado') || res.redirected)) {
                closeRD();
                location.reload();
            } else {
                closeRD();
                location.reload();
            }
        } catch (e) {
            alert('Error de conexión al aceptar. Inténtalo de nuevo.');
            if (btn) { btn.style.pointerEvents = ''; btn.textContent = '✓ Aceptar propuesta'; }
        }
    };

    // ── Abrir drawer ──────────────────────────────────────────
    window.openRD = function (data) {
        currentToken = data.token || '';
        currentData = data;

        // Ocultar secciones inline al abrir
        document.getElementById('rd-cancel-inline').style.display = 'none';
        document.getElementById('rd-reschedule-inline').style.display = 'none';

        // Header
        document.getElementById('rd-id').textContent = '#' + data.id;
        document.getElementById('rd-hora').textContent = data.hora ? data.hora.slice(0, 5) : '—';
        document.getElementById('rd-fecha').textContent = formatFechaCorta(data.fecha);

        const badge = document.getElementById('rd-estado-badge');
        const est = data.estado || '';
        badge.textContent = ESTADO_LABELS[est] || est;
        badge.className = 'rd-estado-badge rdb-' + est;

        // Cliente
        const nombre = data.cliente_nombre || '—';
        document.getElementById('rd-avatar').textContent = initials(nombre);
        document.getElementById('rd-nombre').textContent = nombre;
        const emailEl = document.getElementById('rd-email');
        emailEl.textContent = data.cliente_email || '—';
        emailEl.href = 'mailto:' + (data.cliente_email || '');
        const telEl = document.getElementById('rd-tel');
        telEl.textContent = data.cliente_telefono || '—';
        telEl.href = 'tel:' + (data.cliente_telefono || '');

        // Cita
        document.getElementById('rd-servicio').textContent = data.servicio || '—';
        document.getElementById('rd-duracion').textContent = data.duracion || '';
        document.getElementById('rd-precio').textContent = data.precio ? data.precio + ' €' : '—';
        document.getElementById('rd-barbero').textContent = data.barbero || '—';

        const creadoEl = document.getElementById('rd-created');
        if (data.creado_en) {
            const dt = new Date(data.creado_en);
            creadoEl.textContent = dt.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
        } else { creadoEl.textContent = '—'; }

        // Notas
        const notasSection = document.getElementById('rd-notas-section');
        if (data.notas && data.notas.trim()) {
            document.getElementById('rd-notas').textContent = '"' + data.notas + '"';
            notasSection.style.display = 'block';
        } else { notasSection.style.display = 'none'; }

        // Token
        document.getElementById('rd-token').textContent = (data.token || '').slice(0, 32) + '…';

        // Ocultar secciones dinámicas
        document.getElementById('rd-propuesta-section').style.display = 'none';
        document.getElementById('rd-barbero-propuesta-section').style.display = 'none';
        document.getElementById('rd-historial-section').style.display = 'none';

        const ronda = parseInt(data.ronda_negociacion || 0);
        const motivoCambio = data.motivo_cambio || '';
        const nuevaFechaProp = data.nueva_fecha_propuesta || '';
        const nuevaHoraPropRaw = data.nueva_hora_propuesta || '';
        const nuevaHoraProp = nuevaHoraPropRaw ? nuevaHoraPropRaw.slice(0, 5) : '';
        const origHora = data.hora ? data.hora.slice(0, 5) : '—';

        const esNegociacionActiva = ['reprogramar_barbero', 'reprogramar_cliente'].includes(est);
        const huboNegociacion = ronda > 0 || motivoCambio;

        if (huboNegociacion && !esNegociacionActiva) {
            // Negociación finalizada — mostrar historial completo con slots
            const histItems = parseHistorial(
                motivoCambio, est, ronda,
                data.fecha, data.hora,
                nuevaFechaProp, nuevaHoraProp
            );
            if (histItems.length > 0) {
                document.getElementById('rd-historial-content').innerHTML = renderHistorial(histItems);
                document.getElementById('rd-historial-section').style.display = 'block';
            }
        } else if (est === 'reprogramar_cliente') {
            document.getElementById('rd-propuesta-section').style.display = 'block';

            document.getElementById('rd-orig-slot').textContent = formatFecha(data.fecha) + ' · ' + origHora;

            if (nuevaFechaProp && nuevaHoraProp) {
                document.getElementById('rd-new-slot').textContent = formatFecha(nuevaFechaProp) + ' · ' + nuevaHoraProp;
            } else {
                const rawNF = data.nueva_fecha_propuesta || data['nueva-fecha'] || '';
                const rawNH = data.nueva_hora_propuesta || data['nueva-hora'] || '';
                if (rawNF && rawNH) {
                    document.getElementById('rd-new-slot').textContent = formatFecha(rawNF) + ' · ' + rawNH.slice(0, 5);
                } else {
                    document.getElementById('rd-new-slot').textContent = 'Pendiente de confirmar';
                }
            }

            const motivoRow = document.getElementById('rd-motivo-row');
            if (motivoCambio) {
                document.getElementById('rd-motivo').textContent = motivoCambio.split(' | ')[0];
                motivoRow.style.display = 'flex';
            } else { motivoRow.style.display = 'none'; }

            const rondaRow = document.getElementById('rd-ronda-row');
            if (ronda > 0) {
                document.getElementById('rd-ronda').textContent = 'Ronda ' + ronda;
                rondaRow.style.display = 'flex';
            } else { rondaRow.style.display = 'none'; }

            // Solo mostrar historial previo si hay más de 2 entradas (es decir, ya hubo al menos 2 rondas completas)
            const partes = motivoCambio ? motivoCambio.split(' | ').filter(Boolean) : [];
            if (partes.length > 2) {
                const histItems = parseHistorial(
                    motivoCambio, est, ronda,
                    data.fecha, data.hora,
                    nuevaFechaProp, nuevaHoraProp
                );
                if (histItems.length > 0) {
                    document.getElementById('rd-historial-content').innerHTML = renderHistorial(histItems);
                    document.getElementById('rd-historial-section').style.display = 'block';
                }
            }

        } else if (est === 'reprogramar_barbero') {
            document.getElementById('rd-barbero-propuesta-section').style.display = 'block';

            document.getElementById('rd-bp-orig').textContent = formatFecha(data.fecha) + ' · ' + origHora;
            if (nuevaFechaProp && nuevaHoraProp) {
                document.getElementById('rd-bp-new').textContent = formatFecha(nuevaFechaProp) + ' · ' + nuevaHoraProp;
            } else {
                document.getElementById('rd-bp-new').textContent = 'Esperando…';
            }

            const bpRondaRow = document.getElementById('rd-bp-ronda-row');
            if (ronda > 0) {
                document.getElementById('rd-bp-ronda').textContent = 'Ronda ' + ronda;
                bpRondaRow.style.display = 'flex';
            } else { bpRondaRow.style.display = 'none'; }

            // Historial previo si hay múltiples rondas
            const partes = motivoCambio ? motivoCambio.split(' | ').filter(Boolean) : [];
            if (partes.length > 1) {
                const histItems = parseHistorial(
                    motivoCambio, est, ronda,
                    data.fecha, data.hora,
                    nuevaFechaProp, nuevaHoraProp
                );
                if (histItems.length > 0) {
                    document.getElementById('rd-historial-content').innerHTML = renderHistorial(histItems);
                    document.getElementById('rd-historial-section').style.display = 'block';
                }
            }
        }

        // ── Footer ────────────────────────────────────────────
        const footer = document.getElementById('rd-footer');
        footer.innerHTML = '';
        footer.style.display = 'flex';

        const isPast = isBookingPast(data.fecha);

        if (isPast && !esNegociacionActiva) {
            footer.style.display = 'none';
        } else if (est === 'pendiente') {
            footer.innerHTML = `
                <a class="rd-footer-btn rd-btn-accept"
                   href="?accion=aceptar&token=${encodeURIComponent(data.token)}&barbero=todos&fecha=hoy&estado=todos"
                   onclick="return confirm('¿Aceptar la reserva de ${escJS(data.cliente_nombre)}?')">✓ Aceptar</a>
                <a class="rd-footer-btn rd-btn-deny"
                   href="?accion=denegar&token=${encodeURIComponent(data.token)}&barbero=todos&fecha=hoy&estado=todos"
                   onclick="return confirm('¿Denegar la reserva de ${escJS(data.cliente_nombre)}?')">✕ Denegar</a>`;

        } else if (est === 'aceptada') {
            if (isPast) {
                footer.style.display = 'none';
            } else {
                footer.innerHTML = `
                    <button class="rd-footer-btn rd-btn-reschedule-only" onclick="rdShowReschedule()">⇄ Proponer cambio</button>
                    <button class="rd-footer-btn rd-btn-deny" onclick="rdShowCancel()">✕ Denegar cita</button>`;
            }

        } else if (est === 'reprogramar_barbero') {
            footer.innerHTML = `
                <div style="width:100%;display:flex;flex-direction:column;gap:.6rem;">
                    <div style="background:rgba(201,168,76,.08);border:1px solid rgba(201,168,76,.2);border-radius:8px;padding:.65rem 1rem;font-size:.75rem;color:#d4a84b;line-height:1.5;">
                        ⏳ Esperando respuesta del cliente. Puedes cambiar tu propuesta o denegar.
                    </div>
                    <div style="display:flex;gap:.6rem;">
                        <button class="rd-footer-btn rd-btn-reschedule-only" onclick="rdShowReschedule()">⇄ Cambiar propuesta</button>
                        <button class="rd-footer-btn rd-btn-deny" onclick="rdShowCancel()">✕ Denegar</button>
                    </div>
                </div>`;

        } else if (est === 'reprogramar_cliente') {
            footer.innerHTML = `
                <div style="width:100%;display:flex;flex-direction:column;gap:.6rem;">
                    <div style="background:rgba(37,80,160,.08);border:1px solid rgba(37,80,160,.25);border-radius:8px;padding:.65rem 1rem;font-size:.75rem;color:#6b9fff;line-height:1.5;">
                        ⇄ El cliente propone un cambio. Acepta su horario, propón otro o deniega.
                    </div>
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                        <button class="rd-footer-btn rd-btn-accept"
                            style="flex:1;min-width:80px;"
                            onclick="rdAcceptClientCounter()">✓ Aceptar propuesta</button>
                        <button class="rd-footer-btn rd-btn-reschedule-only" style="flex:1;min-width:80px;" onclick="rdShowReschedule()">⇄ Proponer otro</button>
                        <button class="rd-footer-btn rd-btn-deny" style="flex:1;min-width:80px;" onclick="rdShowCancel()">✕ Denegar</button>
                    </div>
                </div>`;

        } else {
            footer.style.display = 'none';
        }

        document.getElementById('rd-overlay').classList.add('open');
        document.getElementById('rd-drawer').classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    window.closeRD = function () {
        document.getElementById('rd-overlay').classList.remove('open');
        document.getElementById('rd-drawer').classList.remove('open');
        document.body.style.overflow = '';
    };

    window.copyRDToken = function () {
        navigator.clipboard.writeText(currentToken || '').then(() => {
            const btn = document.querySelector('.rd-copy-btn');
            btn.textContent = '✓';
            setTimeout(() => { btn.textContent = '⎘'; }, 1500);
        });
    };

    // ── Mostrar sección Denegar inline ────────────────────────
    window.rdShowCancel = function () {
        document.getElementById('rd-cancel-inline').style.display = 'block';
        document.getElementById('rd-reschedule-inline').style.display = 'none';
        document.getElementById('rd-footer').style.display = 'none';
        document.getElementById('rd-cancel-motivo').value = '';
        const body = document.getElementById('rd-drawer').querySelector('.rd-body');
        setTimeout(() => { body.scrollTop = body.scrollHeight; }, 50);
    };

    window.rdCancelBack = function () {
        document.getElementById('rd-cancel-inline').style.display = 'none';
        document.getElementById('rd-footer').style.display = 'flex';
    };

    window.rdDoCancel = async function () {
        const motivo = (document.getElementById('rd-cancel-motivo').value || '').trim();
        if (!motivo) { rdShowInlineStatus('rd-cancel-status', false, 'El motivo es obligatorio.'); return; }
        if (!confirm('¿Denegar la cita de ' + (currentData?.cliente_nombre || '') + '?\nSe enviará email al cliente.')) return;
        const btn = document.getElementById('rd-btn-do-cancel');
        btn.disabled = true; btn.textContent = 'Enviando…';
        try {
            const res = await fetch(CANCEL_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token: currentToken, accion: 'cancelar', motivo })
            });
            const json = await res.json();
            if (json.ok) {
                rdShowInlineStatus('rd-cancel-status', true, '✓ Reserva denegada. Email enviado al cliente.');
                setTimeout(() => { closeRD(); location.reload(); }, 2200);
            } else {
                rdShowInlineStatus('rd-cancel-status', false, json.error || 'Error al denegar.');
                btn.disabled = false; btn.textContent = '✕ Confirmar denegación';
            }
        } catch (e) {
            rdShowInlineStatus('rd-cancel-status', false, 'Error de conexión. Comprueba tu red.');
            btn.disabled = false; btn.textContent = '✕ Confirmar denegación';
        }
    };

    // ── Mostrar sección Reprogramar inline ────────────────────
    window.rdShowReschedule = function () {
        document.getElementById('rd-reschedule-inline').style.display = 'block';
        document.getElementById('rd-cancel-inline').style.display = 'none';
        document.getElementById('rd-footer').style.display = 'none';

        rdSelectedDate = null;
        rdSelectedSlot = null;

        const prevMotivo = (currentData?.motivo_cambio || '').split(' | ')[0].trim();
        const motivoEl = document.getElementById('rd-resch-motivo');
        const hintEl = document.getElementById('rd-resch-motivo-hint');
        const reqEl = document.getElementById('rd-resch-motivo-required');

        if (prevMotivo) {
            motivoEl.value = prevMotivo;
            if (hintEl) hintEl.style.display = 'block';
            if (reqEl) reqEl.textContent = '(opcional)';
        } else {
            motivoEl.value = '';
            if (hintEl) hintEl.style.display = 'none';
            if (reqEl) reqEl.textContent = '*';
        }

        document.getElementById('rd-btn-do-reschedule').disabled = true;
        document.getElementById('rd-btn-do-reschedule').style.opacity = '.4';
        document.getElementById('rd-slots-grid').innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:.85rem;color:#7a7880;font-size:.8rem;">Selecciona un día del calendario</div>';
        rdCalDate = new Date();
        rdRenderCal();
        const body = document.getElementById('rd-drawer').querySelector('.rd-body');
        setTimeout(() => { body.scrollTop = body.scrollHeight; }, 50);
    };

    window.rdReschBack = function () {
        document.getElementById('rd-reschedule-inline').style.display = 'none';
        document.getElementById('rd-footer').style.display = 'flex';
    };

    // ── Calendario inline: render ─────────────────────────────
    function rdRenderCal() {
        const title = document.getElementById('rd-cal-title');
        if (title) title.textContent = MONTHS_ES[rdCalDate.getMonth()] + ' ' + rdCalDate.getFullYear();
        const grid = document.getElementById('rd-cal-grid');
        if (!grid) return;
        const year = rdCalDate.getFullYear(), month = rdCalDate.getMonth();
        const today = new Date(); today.setHours(0, 0, 0, 0);
        const tomorrow = new Date(today); tomorrow.setDate(tomorrow.getDate() + 1);
        const firstDay = new Date(year, month, 1).getDay();
        const offset = (firstDay + 6) % 7;
        const daysIn = new Date(year, month + 1, 0).getDate();
        let html = '';
        for (let i = 0; i < offset; i++) html += '<div class="rd-cal-cell rdc-empty"></div>';
        for (let d = 1; d <= daysIn; d++) {
            const dt = new Date(year, month, d);
            const iso = isoDate(year, month, d);
            const isPast = dt < tomorrow;
            const isSun = dt.getDay() === 0;
            const isSel = rdSelectedDate === iso;
            const isTod = dt.getTime() === today.getTime();
            let cls = 'rd-cal-cell';
            if (isPast || isSun) cls += ' rdc-dis';
            else if (isTod) cls += ' rdc-today';
            if (isSel) cls += ' rdc-sel';
            const disabled = isPast || isSun;
            const onclick = disabled ? '' : `onclick="rdSelectDate('${iso}')"`;
            html += `<div class="${cls}" ${onclick}>${d}</div>`;
        }
        grid.innerHTML = html;
    }

    window.rdCalNav = function (dir) {
        rdCalDate.setMonth(rdCalDate.getMonth() + dir);
        rdRenderCal();
    };

    window.rdSelectDate = async function (iso) {
        rdSelectedDate = iso;
        rdSelectedSlot = null;
        document.getElementById('rd-btn-do-reschedule').disabled = true;
        document.getElementById('rd-btn-do-reschedule').style.opacity = '.4';
        rdRenderCal();

        const slotsGrid = document.getElementById('rd-slots-grid');
        const dt = new Date(iso + 'T00:00:00');
        if (dt.getDay() === 0) {
            slotsGrid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:.85rem;color:#d42b2b;font-size:.8rem;">🔒 Los domingos estamos cerrados</div>';
            return;
        }

        slotsGrid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:.85rem;color:#7a7880;font-size:.8rem;">Cargando horarios…</div>';

        try {
            const barberoId = currentData?.barbero_id || currentData?.barbero_id_val || '';
            const res = await fetch(`${SLOTS_API}?fecha=${iso}&barbero=${barberoId}`);
            const json = await res.json();

            if (json.ok && json.data.bloqueado) {
                slotsGrid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:.85rem;color:#d42b2b;font-size:.8rem;">🔒 Día bloqueado: ${json.data.motivo || 'No disponible'}</div>`;
                return;
            }

            rdTakenSlots = json.ok ? (json.data.ocupadas || []) : [];
            rdRenderSlots(iso);
        } catch (e) {
            slotsGrid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:.85rem;color:#d42b2b;font-size:.8rem;">Error al cargar horarios</div>';
        }
    };

    function rdRenderSlots(iso) {
        const slotsGrid = document.getElementById('rd-slots-grid');
        const now = new Date();
        const dt = new Date(iso + 'T00:00:00');
        const isToday = iso === isoDate(now.getFullYear(), now.getMonth(), now.getDate());
        const curHHMM = pad2(now.getHours()) + ':' + pad2(now.getMinutes());
        const esSabado = dt.getDay() === 6;
        const slots = ALL_SLOTS.filter(s => !esSabado || s < '14:00');

        slotsGrid.innerHTML = slots.map(s => {
            const taken = rdTakenSlots.includes(s);
            const past = isToday && s <= curHHMM;
            const sel = rdSelectedSlot === s;
            let cls = 'rd-slot';
            if (taken) cls += ' rds-taken';
            else if (past) cls += ' rds-past';
            if (sel) cls += ' rds-sel';
            const disabled = taken || past;
            const onclick = disabled ? '' : `onclick="rdSelectSlot('${s}')"`;
            return `<div class="${cls}" ${onclick}>${s}</div>`;
        }).join('');
    }

    window.rdSelectSlot = function (slot) {
        rdSelectedSlot = slot;
        rdRenderSlots(rdSelectedDate);
        const btn = document.getElementById('rd-btn-do-reschedule');
        btn.disabled = false;
        btn.style.opacity = '1';
    };

    window.rdDoReschedule = async function () {
        const motivoInput = (document.getElementById('rd-resch-motivo').value || '').trim();
        const motivoPrevio = (currentData?.motivo_cambio || '').split(' | ')[0].trim();
        const motivo = motivoInput || motivoPrevio;

        if (!motivo) { rdShowInlineStatus('rd-resch-status', false, 'El motivo es obligatorio.'); return; }
        if (!rdSelectedDate) { rdShowInlineStatus('rd-resch-status', false, 'Selecciona una fecha.'); return; }
        if (!rdSelectedSlot) { rdShowInlineStatus('rd-resch-status', false, 'Selecciona una hora.'); return; }
        if (!confirm('¿Enviar propuesta de cambio a ' + (currentData?.cliente_nombre || '') + '?\n' + rdSelectedDate + ' a las ' + rdSelectedSlot)) return;

        const btn = document.getElementById('rd-btn-do-reschedule');
        btn.disabled = true; btn.style.opacity = '.4'; btn.textContent = 'Enviando…';
        try {
            const res = await fetch(CANCEL_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token: currentToken, accion: 'reprogramar', motivo, nueva_fecha: rdSelectedDate, nueva_hora: rdSelectedSlot })
            });
            const json = await res.json();
            if (json.ok) {
                rdShowInlineStatus('rd-resch-status', true, '✓ Propuesta enviada. El cliente fue notificado por email.');
                setTimeout(() => { closeRD(); location.reload(); }, 2200);
            } else {
                rdShowInlineStatus('rd-resch-status', false, json.error || 'Error al enviar propuesta.');
                btn.disabled = false; btn.style.opacity = '1'; btn.textContent = '⇄ Enviar propuesta';
            }
        } catch (e) {
            rdShowInlineStatus('rd-resch-status', false, 'Error de conexión. Comprueba tu red.');
            btn.disabled = false; btn.style.opacity = '1'; btn.textContent = '⇄ Enviar propuesta';
        }
    };

    function rdShowInlineStatus(id, ok, msg) {
        const el = document.getElementById(id);
        if (!el) return;
        el.style.display = 'flex';
        el.style.alignItems = 'center';
        el.style.gap = '.5rem';
        el.style.background = ok ? 'rgba(34,197,94,.1)' : 'rgba(212,43,43,.1)';
        el.style.border = ok ? '1px solid rgba(34,197,94,.25)' : '1px solid rgba(212,43,43,.25)';
        el.style.color = ok ? '#22c55e' : '#d42b2b';
        el.style.borderRadius = '8px';
        el.textContent = msg;
    }

    // ── Escape ────────────────────────────────────────────────
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeRD(); });

    // ── Extraer servicio y duración sin mezclarlos ────────────
    function extractServicioFromTd(td) {
        if (!td) return { servicio: '—', duracion: '' };
        const spanEl = td.querySelector('span');
        const duracion = spanEl ? spanEl.textContent.trim() : '';
        let servicio = '';
        for (const node of td.childNodes) {
            if (node.nodeType === Node.TEXT_NODE) { const t = node.textContent.trim(); if (t) { servicio = t; break; } }
            else if (node.nodeName === 'BR') { break; }
            else if (node.nodeName !== 'SPAN') { const t = node.textContent.trim(); if (t) { servicio = t; break; } }
        }
        if (!servicio && td.textContent) servicio = td.textContent.replace(duracion, '').trim();
        return { servicio: servicio || '—', duracion };
    }

    // ── Click en filas / cards ────────────────────────────────
    function attachRowListeners() {
        document.querySelectorAll('table tbody tr').forEach(row => {
            if (row.dataset.rdBound) return;
            row.dataset.rdBound = '1';
            row.classList.add('rd-clickable');
            row.style.cursor = 'pointer';
            row.addEventListener('click', function (e) {
                if (e.target.closest('a,button,.btn-accept,.btn-deny,.tb-accept,.tb-deny,.btn-manage')) return;
                const data = extractFromRow(row);
                if (data) openRD(data);
            });
        });
        document.querySelectorAll('.rc').forEach(card => {
            if (card.dataset.rdBound) return;
            card.dataset.rdBound = '1';
            card.addEventListener('click', function (e) {
                if (e.target.closest('a,button,.btn-accept,.btn-deny,.btn-manage-mobile,.rc-actions')) return;
                const data = extractFromCard(card);
                if (data) openRD(data);
            });
        });
    }

    function extractFromRow(row) {
        try {
            const cells = row.querySelectorAll('td');
            if (!cells.length) return null;
            const idText = cells[0]?.textContent?.trim().replace('#', '') || '';
            const horaText = cells[2]?.textContent?.trim() || '';
            const clienteTd = cells[3];
            const clienteNombre = clienteTd?.querySelector('strong')?.textContent?.trim() || '';
            const clienteSpans = clienteTd?.querySelectorAll('span') || [];
            const clienteEmail = clienteSpans[0]?.textContent?.trim() || '';
            const clienteTel = clienteSpans[1]?.textContent?.trim() || '';
            const { servicio, duracion } = extractServicioFromTd(cells[4]);
            const precio = cells[5]?.textContent?.trim().replace(' €', '').replace('€', '').trim() || '';
            const barbero = cells[6]?.querySelector('.b-badge')?.textContent?.trim() || cells[6]?.textContent?.trim() || '';
            const estado = row.dataset.estado || guessEstadoFromBadge(cells[7]);
            const notas = cells[9]?.textContent?.trim() === '—' ? '' : cells[9]?.textContent?.trim() || '';
            return {
                id: idText,
                fecha: row.dataset.fecha || '',
                hora: horaText + ':00',
                cliente_nombre: clienteNombre,
                cliente_email: clienteEmail,
                cliente_telefono: clienteTel,
                servicio,
                duracion,
                precio,
                barbero,
                barbero_id: row.dataset.barberoId || '',
                estado,
                notas,
                token: row.dataset.token || '',
                creado_en: row.dataset.creado || '',
                nueva_fecha_propuesta: row.dataset.nuevaFecha || '',
                nueva_hora_propuesta: row.dataset.nuevaHora || '',
                motivo_cambio: row.dataset.motivo || '',
                ronda_negociacion: row.dataset.ronda || '0',
            };
        } catch (e) { console.error('extractFromRow', e); return null; }
    }

    function extractFromCard(card) {
        try {
            const idText = card.querySelector('.rc-id')?.textContent?.trim().replace('#', '') || '';
            const horaText = card.querySelector('.rc-hora')?.textContent?.trim() || '';
            const nombre = card.querySelector('.rc-cliente-name')?.textContent?.trim() || '';
            const metas = card.querySelectorAll('.rc-meta-item');
            const email = (metas[0]?.textContent || '').replace('✉', '').trim();
            const tel = (metas[1]?.textContent || '').replace('📞', '').trim();
            const detalles = card.querySelectorAll('.rc-detail');
            let servicio = '—', duracion = '';
            if (detalles.length > 0) {
                const svcDet = detalles[0];
                servicio = svcDet?.querySelector('.rc-detail-value')?.textContent?.trim() || '—';
                duracion = svcDet?.querySelector('.rc-detail-sub')?.textContent?.trim() || '';
            }
            const precio = card.querySelector('.rc-detail-value.gold')?.textContent?.trim().replace(' €', '').replace('€', '') || '';
            const barbero = card.querySelector('.rc-barbero-pill')?.textContent?.trim() || '';
            const estadoBadge = card.querySelector('.ebadge');
            const estado = card.dataset.estado || (estadoBadge ? guessEstadoFromClass(estadoBadge) : '');
            const notas = card.querySelector('.rc-notas')?.textContent?.replace(/^"|"$/g, '').trim() || '';
            return {
                id: idText,
                fecha: card.dataset.fecha || '',
                hora: horaText + ':00',
                cliente_nombre: nombre,
                cliente_email: email,
                cliente_telefono: tel,
                servicio,
                duracion,
                precio,
                barbero,
                barbero_id: card.dataset.barberoId || '',
                estado,
                notas,
                token: card.dataset.token || '',
                creado_en: card.dataset.creado || '',
                nueva_fecha_propuesta: card.dataset.nuevaFecha || '',
                nueva_hora_propuesta: card.dataset.nuevaHora || '',
                motivo_cambio: card.dataset.motivo || '',
                ronda_negociacion: card.dataset.ronda || '0',
            };
        } catch (e) { console.error('extractFromCard', e); return null; }
    }

    function guessEstadoFromBadge(td) {
        if (!td) return '';
        const text = td.textContent.toLowerCase();
        if (text.includes('pendiente')) return 'pendiente';
        if (text.includes('aceptada')) return 'aceptada';
        if (text.includes('denegada')) return 'denegada';
        if (text.includes('cancelada')) return 'cancelada';
        if (text.includes('prop. barbero') || text.includes('reprogramar_barbero')) return 'reprogramar_barbero';
        if (text.includes('prop. cliente') || text.includes('reprogramar_cliente')) return 'reprogramar_cliente';
        return '';
    }
    function guessEstadoFromClass(el) {
        const cls = el.className || '';
        if (cls.includes('pendiente')) return 'pendiente';
        if (cls.includes('aceptada')) return 'aceptada';
        if (cls.includes('denegada')) return 'denegada';
        if (cls.includes('cancelada')) return 'cancelada';
        if (cls.includes('reprogramar_barbero')) return 'reprogramar_barbero';
        if (cls.includes('reprogramar_cliente')) return 'reprogramar_cliente';
        return '';
    }

    function escJS(str) {
        return (str || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }

    // ── Init ──────────────────────────────────────────────────
    function init() {
        attachRowListeners();
        const observer = new MutationObserver(() => attachRowListeners());
        const container = document.querySelector('.admin-body');
        if (container) observer.observe(container, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); }
    else { init(); }

})();