// ===== GALLERY DATA =====
const GALLERY_DATA = [
    { tag: 'Degradado', title: 'High Fade con textura', barber: 'Endika Prado', bg: 'gp-1 gp-glow gp-lines', initials: 'EP', sub: 'High Fade' },
    { tag: 'Barba', title: 'Barba esculpida con navaja', barber: 'Marcos Vila', bg: 'gp-2', initials: 'MV', sub: 'Barba Esculpida' },
    { tag: 'Corte', title: 'Corte clásico a tijera', barber: 'Alex Ramos', bg: 'gp-3 gp-lines', initials: 'AR', sub: 'Corte Clásico' },
    { tag: 'Premium', title: 'Sesión completa con masaje', barber: 'Endika Prado', bg: 'gp-4 gp-glow', initials: 'EP', sub: 'Sesión Premium' },
    { tag: 'Degradado', title: 'Mid fade con diseño lateral', barber: 'Alex Ramos', bg: 'gp-5 gp-lines', initials: 'AR', sub: 'Mid Fade' },
    { tag: 'Barba', title: 'Afeitado clásico con toalla caliente', barber: 'Marcos Vila', bg: 'gp-6 gp-glow', initials: 'MV', sub: 'Afeitado Navaja' },
    { tag: 'Corte', title: 'Texturizado natural', barber: 'Endika Prado', bg: 'gp-7', initials: 'EP', sub: 'Texturizado' },
    { tag: 'Degradado', title: 'Low fade con acabado perfecto', barber: 'Endika Prado', bg: 'gp-8 gp-lines gp-glow', initials: 'EP', sub: 'Low Fade' },
    { tag: 'Premium', title: 'Pack Corte + Barba esculpida', barber: 'Marcos Vila', bg: 'gp-9 gp-glow', initials: 'MV', sub: 'Corte + Barba' },
    { tag: 'Corte', title: 'Corte urbano con acabado mate', barber: 'Alex Ramos', bg: 'gp-10 gp-lines', initials: 'AR', sub: 'Corte Urbano' },
    { tag: 'Barba', title: 'Perfilado y arreglo express', barber: 'Marcos Vila', bg: 'gp-11', initials: 'MV', sub: 'Perfilado' },
    { tag: 'Degradado', title: 'Skin fade con diseño', barber: 'Alex Ramos', bg: 'gp-12 gp-glow gp-lines', initials: 'AR', sub: 'Skin Fade' },
];

let currentLightboxIndex = 0;
let visibleIndices = GALLERY_DATA.map((_, i) => i);

// ===== FILTER =====
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const filter = btn.dataset.filter;
        const items = document.querySelectorAll('.gallery-item');

        visibleIndices = [];
        items.forEach(item => {
            const cat = item.dataset.category;
            const show = filter === 'all' || cat === filter;
            item.classList.toggle('hidden', !show);
            if (show) visibleIndices.push(parseInt(item.dataset.index));
        });
    });
});

// ===== LIGHTBOX =====
function openLightbox(index) {
    currentLightboxIndex = index;
    updateLightbox();
    document.getElementById('lightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    document.getElementById('lightbox').classList.remove('open');
    document.body.style.overflow = '';
}

function updateLightbox() {
    const data = GALLERY_DATA[currentLightboxIndex];
    document.getElementById('lb-tag').textContent = data.tag;
    document.getElementById('lb-title').textContent = data.title;
    document.getElementById('lb-barber').textContent = 'por ' + data.barber;

    const photo = document.getElementById('lightbox-photo');
    // Remove old background classes
    photo.className = 'lightbox-photo ' + data.bg;
    // If no inner placeholder exists yet, create it
    let inner = photo.querySelector('.gallery-photo-inner');
    if (!inner) {
        inner = document.createElement('div');
        inner.className = 'gallery-photo-inner';
        photo.appendChild(inner);
    }
    inner.innerHTML = `<div class="gp-icon">${data.initials}</div><div class="gp-label">${data.sub}</div>`;
}

function lightboxNav(dir) {
    const pos = visibleIndices.indexOf(currentLightboxIndex);
    const next = (pos + dir + visibleIndices.length) % visibleIndices.length;
    currentLightboxIndex = visibleIndices[next];
    updateLightbox();
}

// Attach click to gallery items
document.querySelectorAll('.gallery-item').forEach(item => {
    item.addEventListener('click', () => {
        openLightbox(parseInt(item.dataset.index));
    });
});

document.getElementById('lb-close').addEventListener('click', closeLightbox);
document.getElementById('lb-prev').addEventListener('click', () => lightboxNav(-1));
document.getElementById('lb-next').addEventListener('click', () => lightboxNav(1));

document.getElementById('lightbox').addEventListener('click', e => {
    if (e.target === document.getElementById('lightbox')) closeLightbox();
});

document.addEventListener('keydown', e => {
    const lb = document.getElementById('lightbox');
    if (!lb.classList.contains('open')) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') lightboxNav(-1);
    if (e.key === 'ArrowRight') lightboxNav(1);
});