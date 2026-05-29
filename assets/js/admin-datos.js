// ============================================================
//  PRADO BARBER CO. — admin-datos.js
// ============================================================

(function initDatos() {
    'use strict';

    const DATOS_API = './api/datos.php';

    const CATEGORIAS = [
        { id: 'cortes', label: 'Cortes' },
        { id: 'barba',  label: 'Barba' },
        { id: 'packs',  label: 'Packs combinados' },
    ];

    let modalTipo  = null;
    let modalId    = null;
    let datosCache = { barberos: [], servicios: [] };

    // ── Detectar apertura de la pestaña Datos ─────────────────
    function patchSwitchTab() {
        if (typeof window.switchTab !== 'function') {
            setTimeout(patchSwitchTab, 80);
            return;
        }
        const _orig = window.switchTab;
        window.switchTab = function (tab) {
            _orig(tab);
            if (tab === 'datos') loadDatos();
        };
    }
    patchSwitchTab();

    document.addEventListener('DOMContentLoaded', function () {
        const pane = document.getElementById('pane-datos');
        if (pane && pane.classList.contains('active')) loadDatos();
    });

    // ── Utilidades ────────────────────────────────────────────
    function esc(str) {
        return String(str ?? '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function setHTML(id, html) {
        const el = document.getElementById(id);
        if (el) el.innerHTML = html;
    }

    function showStatus(ok, msg) {
        const el = document.getElementById('datos-status');
        if (!el) return;
        el.className = 'cfg-status visible ' + (ok ? 'ok' : 'err');
        el.textContent = (ok ? '✓ ' : '✕ ') + msg;
        clearTimeout(el._t);
        el._t = setTimeout(() => el.classList.remove('visible'), 3800);
    }

    function apiPost(body) {
        return fetch(DATOS_API, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(body),
        });
    }

    // ── Carga principal ────────────────────────────────────────
    async function loadDatos() {
        setHTML('barberos-list',  '<div class="datos-loading">Cargando barberos…</div>');
        setHTML('servicios-list', '<div class="datos-loading">Cargando servicios…</div>');

        try {
            const [rb, rs] = await Promise.all([
                fetch(DATOS_API + '?tipo=barberos').then(r => r.json()),
                fetch(DATOS_API + '?tipo=servicios').then(r => r.json()),
            ]);

            if (rb.ok) {
                datosCache.barberos = rb.data;
                renderBarberos(rb.data);
            } else {
                setHTML('barberos-list', `<div class="datos-loading" style="color:#d42b2b;">⚠ ${esc(rb.error)}</div>`);
            }

            if (rs.ok) {
                datosCache.servicios = rs.data;
                renderServicios(rs.data);
            } else {
                setHTML('servicios-list', `<div class="datos-loading" style="color:#d42b2b;">⚠ ${esc(rs.error)}</div>`);
            }
        } catch (e) {
            setHTML('barberos-list',  '<div class="datos-loading" style="color:#d42b2b;">⚠ Error de conexión</div>');
            setHTML('servicios-list', '<div class="datos-loading" style="color:#d42b2b;">⚠ Error de conexión</div>');
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
            <div class="datos-item ${b.activo == 1 ? '' : 'inactivo'}" draggable="true" data-id="${esc(b.id)}" data-tipo="barbero">
                <span class="datos-drag-handle" title="Arrastra para reordenar">⠿</span>
                <div class="datos-item-avatar">${esc(b.iniciales)}</div>
                <div class="datos-item-info">
                    <div class="datos-item-nombre">${esc(b.nombre)}</div>
                    <div class="datos-item-sub">${esc(b.especialidad || '—')}</div>
                </div>
                <div class="datos-item-actions">
                    <button class="datos-btn datos-btn-edit"
                            onclick="window.DATOS.abrirFormBarbero('${esc(b.id)}')">Editar</button>
                    <button class="datos-btn datos-btn-toggle ${b.activo == 1 ? 'activo' : ''}"
                            onclick="window.DATOS.toggleItem('barbero','${esc(b.id)}')">
                        ${b.activo == 1 ? 'Activo' : 'Inactivo'}
                    </button>
                    <button class="datos-btn datos-btn-del"
                            title="Eliminar barbero"
                            onclick="window.DATOS.eliminarItem('barbero','${esc(b.id)}','${esc(b.nombre)}')">✕</button>
                </div>
            </div>`).join('');

        initDragDrop(el, 'barbero');
    }

    // ── Render servicios por categorías ────────────────────────
    function renderServicios(list) {
        const el = document.getElementById('servicios-list');
        if (!el) return;

        if (!list || !list.length) {
            el.innerHTML = '<div class="datos-loading">No hay servicios. Pulsa + Añadir servicio.</div>';
            return;
        }

        const porCategoria = {};
        CATEGORIAS.forEach(c => { porCategoria[c.id] = []; });
        list.forEach(s => {
            const cat = porCategoria[s.categoria] ? s.categoria : 'cortes';
            porCategoria[cat].push(s);
        });

        el.innerHTML = CATEGORIAS.map(cat => {
            const items = porCategoria[cat.id];
            const itemsHtml = items.length
                ? items.map(s => `
                    <div class="datos-item ${s.activo == 1 ? '' : 'inactivo'}" draggable="true"
                         data-id="${esc(s.id)}" data-tipo="servicio" data-cat="${esc(cat.id)}">
                        <span class="datos-drag-handle" title="Arrastra para reordenar">⠿</span>
                        <div class="datos-item-avatar datos-item-avatar--precio">
                            ${parseFloat(s.precio).toFixed(0)}€
                        </div>
                        <div class="datos-item-info">
                            <div class="datos-item-nombre">${esc(s.nombre)}</div>
                            <div class="datos-item-sub">${esc(s.duracion)}</div>
                        </div>
                        <div class="datos-item-actions">
                            <button class="datos-btn datos-btn-edit"
                                    onclick="window.DATOS.abrirFormServicio('${esc(s.id)}')">Editar</button>
                            <button class="datos-btn datos-btn-toggle ${s.activo == 1 ? 'activo' : ''}"
                                    onclick="window.DATOS.toggleItem('servicio','${esc(s.id)}')">
                                ${s.activo == 1 ? 'Activo' : 'Inactivo'}
                            </button>
                            <button class="datos-btn datos-btn-del"
                                    title="Eliminar servicio"
                                    onclick="window.DATOS.eliminarItem('servicio','${esc(s.id)}','${esc(s.nombre)}')">✕</button>
                        </div>
                    </div>`).join('')
                : `<div class="datos-loading" style="padding:.75rem 0;font-size:.74rem;">Sin servicios en esta categoría</div>`;

            return `
                <div class="datos-categoria">
                    <div class="datos-categoria-label">${esc(cat.label)}</div>
                    <div class="datos-categoria-list" data-categoria="${esc(cat.id)}">${itemsHtml}</div>
                    <button class="datos-add-btn" onclick="window.DATOS.abrirFormServicio(null,'${esc(cat.id)}')">
                        + Añadir en ${esc(cat.label)}
                    </button>
                </div>`;
        }).join('');

        CATEGORIAS.forEach(cat => {
            const catEl = el.querySelector(`.datos-categoria-list[data-categoria="${cat.id}"]`);
            if (catEl) initDragDrop(catEl, 'servicio', cat.id);
        });
    }

    // ── Drag & Drop ────────────────────────────────────────────
    function initDragDrop(container, tipo, categoria) {
        let dragging = null;

        container.addEventListener('dragstart', e => {
            const item = e.target.closest('.datos-item');
            if (!item) return;
            dragging = item;
            item.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        container.addEventListener('dragend', e => {
            if (dragging) dragging.classList.remove('dragging');
            container.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
            dragging = null;
            saveOrder(container, tipo, categoria);
        });

        container.addEventListener('dragover', e => {
            e.preventDefault();
            if (!dragging) return;
            const target = e.target.closest('.datos-item');
            if (!target || target === dragging) return;

            container.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
            target.classList.add('drag-over');

            const rect   = target.getBoundingClientRect();
            const midY   = rect.top + rect.height / 2;
            if (e.clientY < midY) {
                container.insertBefore(dragging, target);
            } else {
                container.insertBefore(dragging, target.nextSibling);
            }
        });
    }

    async function saveOrder(container, tipo, categoria) {
        const ids = [...container.querySelectorAll('.datos-item[data-id]')].map(el => el.dataset.id);
        if (!ids.length) return;

        const accion = tipo === 'barbero' ? 'barbero_reordenar' : 'servicio_reordenar';
        const body   = tipo === 'barbero' ? { accion, ids } : { accion, ids, categoria };

        try {
            const res  = await apiPost(body);
            const json = await res.json();
            if (!json.ok) showStatus(false, 'Error al guardar orden');
        } catch (e) {
            showStatus(false, 'Error de conexión al reordenar');
        }
    }

    // ── Toggle activo / inactivo ───────────────────────────────
    async function toggleItem(tipo, id) {
        const accion = tipo === 'barbero' ? 'barbero_toggle' : 'servicio_toggle';
        try {
            const res  = await apiPost({ accion, id });
            const json = await res.json();
            if (json.ok) loadDatos();
            else showStatus(false, json.error || 'Error al cambiar estado');
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
                           value="${b ? esc(b.nombre) : ''}"
                           placeholder="Ej: Carlos Ruiz" maxlength="80" />
                </div>
                <div class="datos-field">
                    <label>Especialidad</label>
                    <input id="dm-especialidad" type="text"
                           value="${b ? esc(b.especialidad || '') : ''}"
                           placeholder="Ej: Fade &amp; corte clásico" maxlength="150" />
                </div>
                <div class="datos-field">
                    <label>Iniciales (máx. 5) *</label>
                    <input id="dm-iniciales" type="text"
                           value="${b ? esc(b.iniciales) : ''}"
                           placeholder="Ej: CR" maxlength="5"
                           style="text-transform:uppercase;" />
                </div>`;
        }
        abrirModal();
    }

    // ── Modal servicio ─────────────────────────────────────────
    function abrirFormServicio(id, categoriaDefault) {
        modalTipo = 'servicio';
        modalId   = id || null;
        const s   = id ? datosCache.servicios.find(x => x.id === id) : null;
        const catActual = s ? s.categoria : (categoriaDefault || 'cortes');

        const titleEl = document.getElementById('datos-modal-title');
        const bodyEl  = document.getElementById('datos-modal-body');
        if (titleEl) titleEl.textContent = s ? 'Editar servicio' : 'Nuevo servicio';
        if (bodyEl) {
            const catOpts = CATEGORIAS.map(c =>
                `<option value="${c.id}" ${catActual === c.id ? 'selected' : ''}>${c.label}</option>`
            ).join('');

            bodyEl.innerHTML = `
                <div class="datos-field">
                    <label>Nombre del servicio *</label>
                    <input id="dm-nombre" type="text"
                           value="${s ? esc(s.nombre) : ''}"
                           placeholder="Ej: Afeitado exprés" maxlength="100" />
                </div>
                <div class="datos-field">
                    <label>Categoría *</label>
                    <select id="dm-categoria">${catOpts}</select>
                </div>
                <div class="datos-field">
                    <label>Duración (minutos) *</label>
                    <input id="dm-duracion" type="number"
                           value="${s ? parseInt(s.duracion) : ''}"
                           placeholder="Ej: 30" min="5" max="300" step="5" />
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
        setTimeout(() => document.getElementById('dm-nombre')?.focus(), 150);
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
                const nombre    = (document.getElementById('dm-nombre')?.value    || '').trim();
                const categoria = document.getElementById('dm-categoria')?.value  || 'cortes';
                const durMin    = parseInt(document.getElementById('dm-duracion')?.value || 0);
                const precio    = parseFloat(document.getElementById('dm-precio')?.value || 0);
                const duracion  = durMin > 0 ? `${durMin} min` : '';

                if (!nombre || !duracion || precio <= 0) {
                    showStatus(false, 'Todos los campos son obligatorios.');
                    return;
                }
                body = { accion: modalId ? 'servicio_editar' : 'servicio_crear', id: modalId, nombre, categoria, duracion, precio };
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

    // ── Exponer a window ───────────────────────────────────────
    window.DATOS = { abrirFormBarbero, abrirFormServicio, toggleItem, eliminarItem, cerrarModal, guardarModal, loadDatos };
    window.abrirFormBarbero  = abrirFormBarbero;
    window.abrirFormServicio = abrirFormServicio;
    window.cerrarModal       = cerrarModal;
    window.guardarModal      = guardarModal;

})();
