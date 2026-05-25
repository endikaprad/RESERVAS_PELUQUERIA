// ============================================================
//  PRADO BARBER CO. — admin-datos.js
//  Pestaña "Datos": gestión de barberos y servicios
//  Incluir DESPUÉS de admin.php (o inline al final del body)
// ============================================================

(function initDatos() {
    'use strict';

    const DATOS_API = './api/datos.php';
    let modalTipo   = null;
    let modalId     = null;
    let datosCache  = { barberos: [], servicios: [] };
    let datosLoaded = false;

    // ── Detectar apertura de la pestaña Datos ─────────────────
    // Sobreescribe switchTab una vez que esté disponible
    function patchSwitchTab() {
        if (typeof window.switchTab !== 'function') {
            setTimeout(patchSwitchTab, 60);
            return;
        }
        const _orig = window.switchTab;
        window.switchTab = function (tab) {
            _orig(tab);
            if (tab === 'datos' && !datosLoaded) {
                datosLoaded = true;
                loadDatos();
            } else if (tab === 'datos') {
                loadDatos();
            }
        };
    }
    patchSwitchTab();

    // También cargar si la pestaña ya está activa al abrir el panel
    document.addEventListener('DOMContentLoaded', function () {
        const pane = document.getElementById('pane-datos');
        if (pane && pane.classList.contains('active')) {
            datosLoaded = true;
            loadDatos();
        }
    });

    // ── Utilidades DOM ────────────────────────────────────────
    function setListHTML(id, html) {
        const el = document.getElementById(id);
        if (el) el.innerHTML = html;
    }

    function escHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;')
            .replace(/"/g,  '&quot;');
    }

    function escAttr(str) {
        return String(str ?? '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }

    function apiPost(body) {
        return fetch(DATOS_API, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(body),
        });
    }

    function showStatus(ok, msg) {
        const el = document.getElementById('datos-status');
        if (!el) return;
        el.className = 'cfg-status visible ' + (ok ? 'ok' : 'err');
        el.textContent = (ok ? '✓ ' : '✕ ') + msg;
        clearTimeout(el._t);
        el._t = setTimeout(() => el.classList.remove('visible'), 3800);
    }

    // ── Carga principal ────────────────────────────────────────
    async function loadDatos() {
        setListHTML('barberos-list',  '<div class="datos-loading">Cargando barberos…</div>');
        setListHTML('servicios-list', '<div class="datos-loading">Cargando servicios…</div>');

        try {
            const [rb, rs] = await Promise.all([
                fetch(DATOS_API + '?tipo=barberos').then(r  => r.json()),
                fetch(DATOS_API + '?tipo=servicios').then(r => r.json()),
            ]);

            if (rb.ok) {
                datosCache.barberos = rb.data;
                renderBarberos(rb.data);
            } else {
                setListHTML('barberos-list', `<div class="datos-loading" style="color:#d42b2b;">⚠ ${escHtml(rb.error)}</div>`);
            }

            if (rs.ok) {
                datosCache.servicios = rs.data;
                renderServicios(rs.data);
            } else {
                setListHTML('servicios-list', `<div class="datos-loading" style="color:#d42b2b;">⚠ ${escHtml(rs.error)}</div>`);
            }
        } catch (e) {
            setListHTML('barberos-list',  '<div class="datos-loading" style="color:#d42b2b;">⚠ Error de conexión</div>');
            setListHTML('servicios-list', '<div class="datos-loading" style="color:#d42b2b;">⚠ Error de conexión</div>');
        }
    }

    // ── Render barberos ────────────────────────────────────────
    function renderBarberos(list) {
        const el = document.getElementById('barberos-list');
        if (!el) return;
        if (!list || !list.length) {
            el.innerHTML = '<div class="datos-loading">No hay barberos. Pulsa + Añadir barbero.</div>';
            return;
        }
        el.innerHTML = list.map(b => `
            <div class="datos-item ${b.activo == 1 ? '' : 'inactivo'}">
                <div class="datos-item-avatar">${escHtml(b.iniciales)}</div>
                <div class="datos-item-info">
                    <div class="datos-item-nombre">${escHtml(b.nombre)}</div>
                    <div class="datos-item-sub">${escHtml(b.especialidad || '—')}</div>
                </div>
                <div class="datos-item-actions">
                    <button class="datos-btn datos-btn-edit"
                            onclick="window.DATOS.abrirFormBarbero('${escAttr(b.id)}')">Editar</button>
                    <button class="datos-btn datos-btn-toggle ${b.activo == 1 ? 'activo' : ''}"
                            onclick="window.DATOS.toggleItem('barbero','${escAttr(b.id)}')">
                        ${b.activo == 1 ? 'Activo' : 'Inactivo'}
                    </button>
                    <button class="datos-btn datos-btn-del"
                            title="Eliminar barbero"
                            onclick="window.DATOS.eliminarItem('barbero','${escAttr(b.id)}','${escAttr(b.nombre)}')">✕</button>
                </div>
            </div>`).join('');
    }

    // ── Render servicios ───────────────────────────────────────
    function renderServicios(list) {
        const el = document.getElementById('servicios-list');
        if (!el) return;
        if (!list || !list.length) {
            el.innerHTML = '<div class="datos-loading">No hay servicios. Pulsa + Añadir servicio.</div>';
            return;
        }
        el.innerHTML = list.map(s => `
            <div class="datos-item ${s.activo == 1 ? '' : 'inactivo'}">
                <div class="datos-item-avatar"
                     style="font-size:.65rem;font-family:'DM Sans',sans-serif;font-weight:700;
                            color:#c9a84c;border-color:rgba(201,168,76,.25);background:rgba(201,168,76,.08);">
                    ${parseFloat(s.precio).toFixed(0)}€
                </div>
                <div class="datos-item-info">
                    <div class="datos-item-nombre">${escHtml(s.nombre)}</div>
                    <div class="datos-item-sub">${escHtml(s.duracion)}</div>
                </div>
                <div class="datos-item-actions">
                    <button class="datos-btn datos-btn-edit"
                            onclick="window.DATOS.abrirFormServicio('${escAttr(s.id)}')">Editar</button>
                    <button class="datos-btn datos-btn-toggle ${s.activo == 1 ? 'activo' : ''}"
                            onclick="window.DATOS.toggleItem('servicio','${escAttr(s.id)}')">
                        ${s.activo == 1 ? 'Activo' : 'Inactivo'}
                    </button>
                    <button class="datos-btn datos-btn-del"
                            title="Eliminar servicio"
                            onclick="window.DATOS.eliminarItem('servicio','${escAttr(s.id)}','${escAttr(s.nombre)}')">✕</button>
                </div>
            </div>`).join('');
    }

    // ── Toggle activo / inactivo ───────────────────────────────
    async function toggleItem(tipo, id) {
        const accion = tipo === 'barbero' ? 'barbero_toggle' : 'servicio_toggle';
        try {
            const res  = await apiPost({ accion, id });
            const json = await res.json();
            if (json.ok) {
                loadDatos();
            } else {
                showStatus(false, json.error || 'Error al cambiar estado');
            }
        } catch (e) {
            showStatus(false, 'Error de conexión');
        }
    }

    // ── Eliminar ───────────────────────────────────────────────
    async function eliminarItem(tipo, id, nombre) {
        if (!confirm(`¿Eliminar "${nombre}"?\n\nEsta acción no se puede deshacer.\nSi tiene reservas asociadas no podrá eliminarse.`)) return;
        const accion = tipo === 'barbero' ? 'barbero_eliminar' : 'servicio_eliminar';
        try {
            const res  = await apiPost({ accion, id });
            const json = await res.json();
            if (json.ok) {
                showStatus(true, `"${nombre}" eliminado correctamente.`);
                loadDatos();
            } else {
                showStatus(false, json.error || 'No se pudo eliminar.');
            }
        } catch (e) {
            showStatus(false, 'Error de conexión.');
        }
    }

    // ── Modal barbero ──────────────────────────────────────────
    function abrirFormBarbero(id) {
        modalTipo = 'barbero';
        modalId   = id || null;
        const b   = id ? datosCache.barberos.find(x => x.id === id) : null;

        const titleEl = document.getElementById('datos-modal-title');
        const bodyEl  = document.getElementById('datos-modal-body');
        if (titleEl) titleEl.textContent = b ? 'Editar barbero' : 'Nuevo barbero';
        if (bodyEl) {
            bodyEl.innerHTML = `
                <div class="datos-field">
                    <label>Nombre completo *</label>
                    <input id="dm-nombre" type="text"
                           value="${b ? escAttr(b.nombre) : ''}"
                           placeholder="Ej: Carlos Ruiz" maxlength="80" />
                </div>
                <div class="datos-field">
                    <label>Especialidad</label>
                    <input id="dm-especialidad" type="text"
                           value="${b ? escAttr(b.especialidad || '') : ''}"
                           placeholder="Ej: Fade &amp; corte clásico" maxlength="150" />
                </div>
                <div class="datos-field">
                    <label>Iniciales (máx. 5) *</label>
                    <input id="dm-iniciales" type="text"
                           value="${b ? escAttr(b.iniciales) : ''}"
                           placeholder="Ej: CR" maxlength="5"
                           style="text-transform:uppercase;" />
                </div>`;
        }
        abrirModal();
    }

    // ── Modal servicio ─────────────────────────────────────────
    function abrirFormServicio(id) {
        modalTipo = 'servicio';
        modalId   = id || null;
        const s   = id ? datosCache.servicios.find(x => x.id === id) : null;

        const titleEl = document.getElementById('datos-modal-title');
        const bodyEl  = document.getElementById('datos-modal-body');
        if (titleEl) titleEl.textContent = s ? 'Editar servicio' : 'Nuevo servicio';
        if (bodyEl) {
            bodyEl.innerHTML = `
                <div class="datos-field">
                    <label>Nombre del servicio *</label>
                    <input id="dm-nombre" type="text"
                           value="${s ? escAttr(s.nombre) : ''}"
                           placeholder="Ej: Afeitado exprés" maxlength="100" />
                </div>
                <div class="datos-field">
                    <label>Duración *</label>
                    <input id="dm-duracion" type="text"
                           value="${s ? escAttr(s.duracion) : ''}"
                           placeholder="Ej: 30 min" maxlength="20" />
                </div>
                <div class="datos-field">
                    <label>Precio (€) *</label>
                    <input id="dm-precio" type="number"
                           value="${s ? s.precio : ''}"
                           placeholder="Ej: 18" min="1" max="999" step="0.5" />
                </div>`;
        }
        abrirModal();
    }

    // ── Abrir / cerrar modal ───────────────────────────────────
    function abrirModal() {
        const overlay = document.getElementById('datos-modal-overlay');
        if (overlay) overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
        setTimeout(() => {
            const nombre = document.getElementById('dm-nombre');
            if (nombre) nombre.focus();
        }, 150);
    }

    function cerrarModal() {
        const overlay = document.getElementById('datos-modal-overlay');
        if (overlay) overlay.classList.remove('open');
        document.body.style.overflow = '';
        modalTipo = null;
        modalId   = null;
    }

    // ── Guardar modal ──────────────────────────────────────────
    async function guardarModal() {
        const btn = document.getElementById('datos-modal-save');
        if (btn) { btn.disabled = true; btn.textContent = 'Guardando…'; }

        try {
            let body = {};

            if (modalTipo === 'barbero') {
                const nombre       = (document.getElementById('dm-nombre')?.value       || '').trim();
                const especialidad = (document.getElementById('dm-especialidad')?.value || '').trim();
                const iniciales    = (document.getElementById('dm-iniciales')?.value    || '').trim().toUpperCase();

                if (!nombre || !iniciales) {
                    showStatus(false, 'Nombre e iniciales son obligatorios.');
                    return;
                }
                body = { accion: modalId ? 'barbero_editar' : 'barbero_crear', id: modalId, nombre, especialidad, iniciales };

            } else {
                const nombre   = (document.getElementById('dm-nombre')?.value   || '').trim();
                const duracion = (document.getElementById('dm-duracion')?.value || '').trim();
                const precio   = parseFloat(document.getElementById('dm-precio')?.value || 0);

                if (!nombre || !duracion || precio <= 0) {
                    showStatus(false, 'Todos los campos son obligatorios.');
                    return;
                }
                body = { accion: modalId ? 'servicio_editar' : 'servicio_crear', id: modalId, nombre, duracion, precio };
            }

            const res  = await apiPost(body);
            const json = await res.json();

            if (json.ok) {
                cerrarModal();
                await loadDatos();
                showStatus(true, 'Guardado correctamente.');
            } else {
                showStatus(false, json.error || 'Error al guardar.');
            }
        } catch (e) {
            showStatus(false, 'Error de conexión.');
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = 'Guardar'; }
        }
    }

    // ── Exponer a window para los onclick del HTML ─────────────
    window.DATOS = {
        abrirFormBarbero,
        abrirFormServicio,
        toggleItem,
        eliminarItem,
        cerrarModal,
        guardarModal,
        loadDatos,
    };

    // Compatibilidad con llamadas directas desde el HTML de admin.php
    window.abrirFormBarbero  = abrirFormBarbero;
    window.abrirFormServicio = abrirFormServicio;
    window.cerrarModal       = cerrarModal;
    window.guardarModal      = guardarModal;

})();