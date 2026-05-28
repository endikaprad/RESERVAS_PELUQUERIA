(async function loadServicios() {
    try {
        const res = await fetch('./backend/api/servicios.php');
        const json = await res.json();
        if (!json.ok || !json.data.length) throw new Error();

        // Agrupar por precio: cortes (<= 22), barba (<= 22 y nombre contiene barba/afeit), packs (> 22)
        // Usamos categorías fijas pero con datos dinámicos
        const todos = json.data;

        // Intentar categorizar por nombre
        const categorias = {
            'Cortes': [],
            'Barba': [],
            'Packs combinados': [],
            'Otros servicios': [],
        };

        todos.forEach(s => {
            const n = s.nombre.toLowerCase();
            if (n.includes('barba') || n.includes('afeitado') || n.includes('navaja')) {
                categorias['Barba'].push(s);
            } else if (n.includes('pack') || n.includes('+') || n.includes('premium') || n.includes('completo') || n.includes('sesión')) {
                categorias['Packs combinados'].push(s);
            } else if (n.includes('corte') || n.includes('degradado') || n.includes('fade') || n.includes('infantil') || n.includes('texturizado')) {
                categorias['Cortes'].push(s);
            } else {
                categorias['Otros servicios'].push(s);
            }
        });

        let html = '';
        Object.entries(categorias).forEach(([catNombre, servicios]) => {
            if (!servicios.length) return;
            html += `
                    <div class="svc-category reveal">
                        <div class="svc-category-title">${catNombre}</div>
                        <div class="svc-list">
                            ${servicios.map(s => {
                const esPremium = s.precio >= 40;
                return `
                                <div class="svc-row" ${esPremium ? 'style="border-color:var(--color-gold-dim);"' : ''}>
                                    <div class="svc-row-info">
                                        <div class="svc-row-name">
                                            ${s.nombre}
                                            ${esPremium ? '<span class="badge badge-gold" style="margin-left:.75rem;">Destacado</span>' : ''}
                                        </div>
                                        ${s.descripcion ? `<div class="svc-row-desc">${s.descripcion}</div>` : ''}
                                    </div>
                                    <div class="svc-row-meta">
                                        <div class="svc-row-duration">${s.duracion}</div>
                                        <div class="svc-row-price">${parseFloat(s.precio).toFixed(0)} €</div>
                                        <a href="reservas.html" class="btn ${esPremium ? 'btn-primary' : 'btn-outline'} svc-row-btn"
                                           style="padding:.5rem 1rem; font-size:.7rem;">
                                            Reservar
                                        </a>
                                    </div>
                                </div>`;
            }).join('')}
                        </div>
                    </div>`;
        });

        document.getElementById('svc-loading').style.display = 'none';
        document.getElementById('svc-grid').innerHTML = html;

        // Activar reveal observer para los nuevos elementos
        const observer = new IntersectionObserver(entries => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    e.target.classList.add('revealed');
                    observer.unobserve(e.target);
                }
            });
        }, { threshold: 0.1 });
        document.querySelectorAll('#svc-grid .reveal').forEach(el => observer.observe(el));

    } catch (e) {
        document.getElementById('svc-loading').textContent = 'No se pudieron cargar los servicios. Recarga la página.';
    }
})();