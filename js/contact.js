function sendContact() {
    const name = document.getElementById('c-name').value.trim();
    const email = document.getElementById('c-email').value.trim();
    const msg = document.getElementById('c-message').value.trim();
    if (!name || !email || !msg) {
        showToast('Por favor rellena todos los campos requeridos.', '⚠');
        return;
    }
    showToast('¡Mensaje enviado! Te responderemos en breve.', '✦');
}