// ============================================================
//  PRADO BARBER CO. — contact.js
//  Lógica del formulario de contacto.
// ============================================================

// ===== HORARIO DINÁMICO =====
(function loadHorario() {
    const DIAS = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

    function fmt(t) { return t.replace(/^0/, '').replace(':', ':'); }

    function timeRange(cfg) {
        const man = cfg.horario_manana_activo === '1';
        const tar = cfg.horario_tarde_activo  === '1';
        if (man && tar)  return fmt(cfg.horario_manana_inicio) + ' – ' + fmt(cfg.horario_tarde_fin);
        if (man)         return fmt(cfg.horario_manana_inicio) + ' – ' + fmt(cfg.horario_manana_fin);
        if (tar)         return fmt(cfg.horario_tarde_inicio)  + ' – ' + fmt(cfg.horario_tarde_fin);
        return null;
    }

    function renderHorario(cfg) {
        const grid = document.getElementById('hours-grid');
        if (!grid) return;

        const abiertos = new Set((cfg.horario_dias_abiertos || '1,2,3,4,5').split(',').map(Number));
        const rango    = timeRange(cfg);

        // Agrupa días consecutivos con el mismo estado
        const rows = [];
        let group  = null;
        for (let d = 1; d <= 7; d++) {
            const open = abiertos.has(d);
            if (!group || group.open !== open) {
                group = { start: d, end: d, open };
                rows.push(group);
            } else {
                group.end = d;
            }
        }

        grid.innerHTML = rows.map(g => {
            const label = g.start === g.end
                ? DIAS[g.start]
                : DIAS[g.start] + ' – ' + DIAS[g.end];
            if (g.open && rango) {
                return `<div class="hours-row">
                    <span class="hours-day">${label}</span>
                    <span class="hours-time">${rango}</span>
                </div>`;
            }
            return `<div class="hours-row">
                <span class="hours-day">${label}</span>
                <span class="hours-closed">Cerrado</span>
            </div>`;
        }).join('');
    }

    document.addEventListener('DOMContentLoaded', function () {
        fetch('./backend/api/horario-negocio.php')
            .then(r => r.json())
            .then(res => { if (res.ok) renderHorario(res.data); })
            .catch(() => {}); // mantiene el fallback estático si falla
    });
})();

// ===== ENVÍO DEL FORMULARIO =====
function sendContact() {
    const name  = document.getElementById('c-name').value.trim();
    const email = document.getElementById('c-email').value.trim();
    const msg   = document.getElementById('c-message').value.trim();

    if (!name || !email || !msg) {
        showToast('Por favor rellena todos los campos requeridos.', '⚠');
        return;
    }

    showToast('¡Mensaje enviado! Te responderemos en breve.', '✦');
}