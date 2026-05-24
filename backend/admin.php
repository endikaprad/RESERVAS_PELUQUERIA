<?php
// ============================================================
//  PRADO BARBER CO. — Panel de administración (Mobile-First)
// ============================================================

define('ADMIN_USER', 'endika');
define('ADMIN_PASS', 'PradoBarber2026');

session_start();

if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_POST['user']) && isset($_POST['pass'])) {
    if ($_POST['user'] === ADMIN_USER && $_POST['pass'] === ADMIN_PASS) {
        $_SESSION['admin'] = true;
    } else {
        $loginError = true;
    }
}

if (!isset($_SESSION['admin'])) {
    ?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="theme-color" content="#111119">
    <link rel="icon" type="image/png" href="../img/admin.png">
    <title>Admin · Prado Barber Co.</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=DM+Sans:wght@300;400;500&display=swap');
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{background:#09080f;color:#f0ece3;font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem;}
        .login-box{background:#111119;border:1px solid #252530;border-radius:16px;padding:2.5rem 2rem;width:100%;max-width:380px;}
        .login-title{font-family:'Playfair Display',serif;font-size:1.75rem;font-weight:700;margin-bottom:.25rem;}
        .login-sub{color:#7a7880;font-size:.85rem;margin-bottom:2rem;}
        label{display:block;font-size:.7rem;letter-spacing:.15em;text-transform:uppercase;color:#7a7880;margin-bottom:.4rem;}
        input{width:100%;background:#18181f;border:1px solid #252530;border-radius:8px;padding:.85rem 1rem;color:#f0ece3;font-family:'DM Sans',sans-serif;font-size:.9rem;margin-bottom:1.25rem;transition:border-color .3s;}
        input:focus{outline:none;border-color:#d42b2b;}
        button{width:100%;background:#d42b2b;color:#fff;border:none;border-radius:4px;padding:1rem;font-family:'DM Sans',sans-serif;font-size:.78rem;font-weight:600;letter-spacing:.18em;text-transform:uppercase;cursor:pointer;transition:background .3s;}
        button:hover{background:#a81e1e;}
        .error{color:#d42b2b;font-size:.82rem;margin-bottom:1rem;padding:.75rem;background:rgba(212,43,43,.08);border-radius:8px;border:1px solid rgba(212,43,43,.2);}
        .brand{font-family:'Playfair Display',serif;font-size:1rem;font-style:italic;color:#7a7880;margin-bottom:2rem;}
    </style>
</head>
<body>
<div class="login-box">
    <div class="brand">Prado Barber Co.</div>
    <div class="login-title">Panel Admin</div>
    <div class="login-sub">Acceso restringido al equipo</div>
    <?php if (!empty($loginError)): ?>
        <div class="error">Usuario o contraseña incorrectos.</div>
    <?php endif; ?>
    <form method="POST">
        <label>Usuario</label>
        <input type="text" name="user" autocomplete="username" required/>
        <label>Contraseña</label>
        <input type="password" name="pass" autocomplete="current-password" required/>
        <button type="submit">Entrar</button>
    </form>
</div>
</body>
</html>
    <?php
    exit;
}

require_once __DIR__ . '/config.php';
$db = getDB();

if (isset($_GET['accion']) && isset($_GET['token'])) {
    $accion = $_GET['accion'];
    $token  = $_GET['token'];
    if (in_array($accion, ['aceptar', 'denegar'], true) && $token) {
        header('Location: api/reserva-action.php?token=' . urlencode($token) . '&accion=' . urlencode($accion) . '&from=admin');
        exit;
    }
}

$filtroBarbero = $_GET['barbero'] ?? 'todos';
$filtroFecha   = $_GET['fecha']   ?? 'hoy';
$filtroEstado  = $_GET['estado']  ?? 'todos';
$fechaCustom   = $_GET['fecha_custom'] ?? '';

$hoy = date('Y-m-d');

$where  = 'WHERE 1=1';
$params = [];

if ($filtroBarbero !== 'todos') { $where .= ' AND r.barbero_id = ?'; $params[] = $filtroBarbero; }
if ($filtroEstado  !== 'todos') { $where .= ' AND r.estado = ?';     $params[] = $filtroEstado; }

if ($filtroFecha === 'hoy') {
    $where .= ' AND r.fecha = ?'; $params[] = $hoy;
} elseif ($filtroFecha === 'manana') {
    $where .= ' AND r.fecha = ?'; $params[] = date('Y-m-d', strtotime('+1 day'));
} elseif ($filtroFecha === 'semana') {
    $where .= ' AND r.fecha BETWEEN ? AND ?'; $params[] = $hoy; $params[] = date('Y-m-d', strtotime('+7 days'));
} elseif ($filtroFecha === 'custom' && $fechaCustom) {
    $where .= ' AND r.fecha = ?'; $params[] = $fechaCustom;
}

$stmt = $db->prepare("
    SELECT r.id, r.fecha, r.hora,
           r.cliente_nombre, r.cliente_telefono, r.cliente_email, r.notas,
           r.estado, r.token, r.creado_en,
           s.nombre AS servicio, s.precio, s.duracion,
           b.nombre AS barbero
    FROM reservas r
    JOIN servicios s ON s.id = r.servicio_id
    JOIN barberos  b ON b.id = r.barbero_id
    {$where}
    ORDER BY r.fecha ASC, r.hora ASC
");
$stmt->execute($params);
$reservas = $stmt->fetchAll();

$stmtHoy = $db->prepare("SELECT COUNT(*) as total, SUM(s.precio) as ingresos FROM reservas r JOIN servicios s ON s.id = r.servicio_id WHERE r.fecha = ?");
$stmtHoy->execute([$hoy]);
$statsHoy = $stmtHoy->fetch();

$stmtPend = $db->prepare("SELECT COUNT(*) as total FROM reservas WHERE estado = 'pendiente'");
$stmtPend->execute();
$statsPend = $stmtPend->fetch();

$barberos = $db->query('SELECT id, nombre FROM barberos ORDER BY nombre')->fetchAll();

$diasES  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
$mesesES = ['','ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Admin · Prado Barber Co.</title>
    <link rel="icon" type="image/png" href="../img/admin.png">
    <meta name="theme-color" content="#111119">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=DM+Sans:wght@300;400;500&display=swap');
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{background:#09080f;color:#f0ece3;font-family:'DM Sans',sans-serif;min-height:100vh;}
        a{color:inherit;text-decoration:none;}

        /* ── HEADER ── */
        .admin-header{background:#111119;border-bottom:1px solid #252530;padding:.9rem 1.25rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;}
        .admin-brand{font-family:'Playfair Display',serif;font-size:1.1rem;font-style:italic;}
        .admin-brand span{color:#d42b2b;}
        .logout-btn{background:transparent;border:1px solid #252530;color:#7a7880;border-radius:4px;padding:.4rem .9rem;font-family:'DM Sans',sans-serif;font-size:.68rem;letter-spacing:.1em;text-transform:uppercase;cursor:pointer;transition:all .3s;}
        .logout-btn:hover{border-color:#d42b2b;color:#d42b2b;}

        .header-actions{display:flex;align-items:center;gap:.6rem;}
        .settings-btn{width:36px;height:36px;border-radius:50%;background:transparent;border:1px solid #252530;color:#7a7880;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1rem;transition:all .3s;flex-shrink:0;}
        .settings-btn:hover{border-color:#d42b2b;color:#d42b2b;background:rgba(212,43,43,.06);}
        .stats-trigger-btn{width:36px;height:36px;border-radius:50%;background:transparent;border:1px solid #252530;color:#7a7880;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.9rem;transition:all .3s;flex-shrink:0;}
        .stats-trigger-btn:hover{border-color:#c9a84c;color:#c9a84c;background:rgba(201,168,76,.06);}

        /* ── BODY ── */
        .admin-body{padding:1rem;max-width:1300px;margin:0 auto;}

        /* ── ALERT ── */
        .alert-pendientes{background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.25);border-radius:10px;padding:.85rem 1.1rem;margin-bottom:1rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;font-size:.85rem;}
        .alert-pendientes strong{color:#f59e0b;}
        .alert-link{margin-left:auto;color:#f59e0b;font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;}

        /* ── STATS ── */
        .stats-row{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1rem;}
        .stat-card{background:#111119;border:1px solid #252530;border-radius:12px;padding:1rem 1.1rem;}
        .stat-label{font-size:.6rem;letter-spacing:.2em;text-transform:uppercase;color:#7a7880;margin-bottom:.3rem;}
        .stat-value{font-family:'Playfair Display',serif;font-size:1.75rem;font-weight:700;color:#d42b2b;line-height:1;}
        .stat-value.gold{color:#c9a84c;}
        .stat-value.orange{color:#f59e0b;}
        .stat-sub{font-size:.65rem;color:#7a7880;margin-top:.2rem;}

        /* ── FILTERS ── */
        .filters{background:#111119;border:1px solid #252530;border-radius:12px;padding:1rem;margin-bottom:1rem;}
        .filters-label{font-size:.62rem;letter-spacing:.2em;text-transform:uppercase;color:#7a7880;margin-bottom:.75rem;}
        .filters form{display:flex;flex-direction:column;gap:.6rem;}
        .frow{display:flex;flex-direction:column;gap:.25rem;}
        .flabel{font-size:.6rem;letter-spacing:.12em;text-transform:uppercase;color:#7a7880;}
        select,input[type=date]{width:100%;background:#18181f;border:1px solid #252530;border-radius:6px;padding:.6rem .75rem;color:#f0ece3;font-family:'DM Sans',sans-serif;font-size:.9rem;-webkit-appearance:none;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%237a7880' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .75rem center;padding-right:2rem;}
        select:focus,input[type=date]:focus{outline:none;border-color:#d42b2b;}
        .filter-submit{width:100%;background:#d42b2b;color:#fff;border:none;border-radius:6px;padding:.7rem 1rem;font-family:'DM Sans',sans-serif;font-size:.78rem;font-weight:600;letter-spacing:.15em;text-transform:uppercase;cursor:pointer;transition:background .3s;margin-top:.2rem;}
        .filter-submit:hover{background:#a81e1e;}

        @media(min-width:900px){
            .filters form{flex-direction:row;align-items:flex-end;flex-wrap:wrap;gap:.75rem;}
            .frow{flex-direction:row;align-items:center;flex:1;min-width:120px;gap:.5rem;}
            select,input[type=date]{width:100%;}
            .filter-submit{width:auto;padding:.6rem 1.25rem;flex-shrink:0;margin-top:0;align-self:flex-end;}
        }

        /* ── SECTION HEADER ── */
        .section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem;}
        .section-title-admin{font-family:'Playfair Display',serif;font-size:1.1rem;}
        .section-count{font-size:.72rem;color:#7a7880;}

        /* ── MOBILE CARDS ── */
        .reservas-cards{display:flex;flex-direction:column;gap:1rem;}
        .rc{background:#111119;border:1px solid #252530;border-left-width:3px;border-radius:12px;overflow:hidden;}
        .rc.pendiente{border-left-color:#f59e0b;}
        .rc.aceptada{border-left-color:#22c55e;}
        .rc.denegada{border-left-color:#d42b2b;opacity:.65;}
        .rc-top{display:flex;align-items:flex-start;justify-content:space-between;padding:1rem 1rem .6rem;gap:.5rem;}
        .rc-id{font-size:.65rem;color:#7a7880;margin-bottom:.15rem;}
        .rc-hora{font-family:'Playfair Display',serif;font-size:1.45rem;font-weight:700;color:#d42b2b;line-height:1;}
        .rc-fecha{font-size:.8rem;color:#a0a0b0;margin-top:.2rem;}
        .ebadge{display:inline-flex;align-items:center;gap:.3rem;padding:.3rem .75rem;border-radius:100px;font-size:.7rem;font-weight:600;letter-spacing:.04em;white-space:nowrap;flex-shrink:0;}
        .ebadge-pendiente{background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3);color:#f59e0b;}
        .ebadge-aceptada{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.3);color:#22c55e;}
        .ebadge-denegada{background:rgba(212,43,43,.12);border:1px solid rgba(212,43,43,.3);color:#d42b2b;}
        .rc-divider{height:1px;background:#1c1c26;margin:0 1rem;}
        .rc-body{padding:.85rem 1rem 1rem;display:flex;flex-direction:column;gap:.85rem;}
        .rc-cliente-name{font-size:1rem;font-weight:500;margin-bottom:.3rem;}
        .rc-cliente-meta{display:flex;flex-direction:column;gap:.2rem;}
        .rc-meta-item{font-size:.78rem;color:#7a7880;}
        .rc-details{display:grid;grid-template-columns:1fr 1fr;gap:.6rem 1rem;}
        .rc-detail-label{font-size:.6rem;letter-spacing:.12em;text-transform:uppercase;color:#7a7880;margin-bottom:.2rem;}
        .rc-detail-value{font-size:.88rem;color:#f0ece3;}
        .rc-detail-value.gold{color:#c9a84c;font-weight:500;}
        .rc-detail-sub{font-size:.72rem;color:#7a7880;}
        .rc-barbero-pill{display:inline-block;padding:.25rem .65rem;background:rgba(212,43,43,.08);border:1px solid rgba(212,43,43,.2);border-radius:100px;font-size:.75rem;color:#d42b2b;}
        .rc-notas{font-size:.78rem;color:#7a7880;font-style:italic;padding:.55rem .75rem;background:#0d0d14;border-radius:6px;border-left:2px solid #2a2a38;}
        .rc-actions{display:flex;gap:.6rem;padding:.85rem 1rem;border-top:1px solid #1c1c26;}
        .btn-accept,.btn-deny{flex:1;display:flex;align-items:center;justify-content:center;gap:.4rem;padding:.7rem .5rem;border-radius:7px;font-family:'DM Sans',sans-serif;font-size:.78rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;cursor:pointer;text-decoration:none;transition:all .22s;border:1px solid transparent;}
        .btn-accept{background:rgba(34,197,94,.12);border-color:rgba(34,197,94,.35);color:#22c55e;}
        .btn-accept:hover,.btn-accept:active{background:#22c55e;color:#000;}
        .btn-deny{background:rgba(212,43,43,.1);border-color:rgba(212,43,43,.3);color:#d42b2b;}
        .btn-deny:hover,.btn-deny:active{background:#d42b2b;color:#fff;}

        /* ── EMPTY ── */
        .empty-state{background:#111119;border:1px solid #252530;border-radius:12px;padding:3.5rem 2rem;text-align:center;color:#7a7880;}
        .empty-icon{font-size:2.5rem;margin-bottom:.75rem;opacity:.3;}

        .reservas-cards{display:flex;flex-direction:column;gap:1rem;}
        .table-desktop{display:none;}

        @media(min-width:900px){
            .section-header{display:none;}
            .admin-header{padding:1rem 2rem;}
            .admin-body{padding:2rem;}
            .stats-row{grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;}
            .stat-value{font-size:2rem;}
            .filters form{flex-direction:row;align-items:center;flex-wrap:wrap;gap:.75rem;}
            .frow{flex-wrap:nowrap;}
            .reservas-cards{display:none !important;}
            .table-desktop{display:block !important;}
        }

        /* ── DESKTOP TABLE ── */
        .table-wrap-d{background:#111119;border:1px solid #252530;border-radius:12px;overflow:hidden;}
        .table-header-d{padding:1.1rem 1.5rem;border-bottom:1px solid #252530;display:flex;align-items:center;justify-content:space-between;}
        .table-title-d{font-family:'Playfair Display',serif;font-size:1.1rem;}
        table{width:100%;border-collapse:collapse;}
        th{font-size:.63rem;letter-spacing:.2em;text-transform:uppercase;color:#7a7880;padding:.8rem 1.1rem;text-align:left;border-bottom:1px solid #252530;white-space:nowrap;}
        td{padding:.85rem 1.1rem;border-bottom:1px solid rgba(37,37,48,.5);font-size:.875rem;vertical-align:top;}
        tr:last-child td{border-bottom:none;}
        tr:hover td{background:rgba(37,37,48,.4);}
        .td-hora{font-family:'Playfair Display',serif;font-size:1rem;color:#d42b2b;white-space:nowrap;}
        .td-cliente strong{display:block;font-weight:500;}
        .td-cliente span{font-size:.75rem;color:#7a7880;display:block;}
        .td-precio{color:#c9a84c;font-weight:500;white-space:nowrap;}
        .td-barbero .b-badge{display:inline-block;padding:.2rem .6rem;background:rgba(212,43,43,.08);border:1px solid rgba(212,43,43,.2);border-radius:100px;font-size:.7rem;color:#d42b2b;}
        .td-notas{font-size:.75rem;color:#7a7880;max-width:140px;}
        .action-btns{display:flex;gap:.4rem;}
        .tb-accept,.tb-deny{padding:.32rem .65rem;border-radius:4px;font-family:'DM Sans',sans-serif;font-size:.67rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;cursor:pointer;text-decoration:none;transition:all .2s;border:1px solid transparent;white-space:nowrap;}
        .tb-accept{background:rgba(34,197,94,.12);border-color:rgba(34,197,94,.3);color:#22c55e;}
        .tb-accept:hover{background:#22c55e;color:#000;}
        .tb-deny{background:rgba(212,43,43,.1);border-color:rgba(212,43,43,.25);color:#d42b2b;}
        .tb-deny:hover{background:#d42b2b;color:#fff;}
        .estado-badge{display:inline-flex;align-items:center;gap:.3rem;padding:.22rem .65rem;border-radius:100px;font-size:.68rem;font-weight:600;letter-spacing:.04em;white-space:nowrap;}
        .badge-pendiente{background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3);color:#f59e0b;}
        .badge-aceptada{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.3);color:#22c55e;}
        .badge-denegada{background:rgba(212,43,43,.12);border:1px solid rgba(212,43,43,.3);color:#d42b2b;}

        /* ================================================================
           CONFIG PANEL
        ================================================================ */
        .cfg-overlay{position:fixed;inset:0;background:rgba(0,0,0,.75);backdrop-filter:blur(6px);z-index:999;opacity:0;pointer-events:none;transition:opacity .3s ease;}
        .cfg-overlay.open{opacity:1;pointer-events:all;}

        .cfg-panel{position:fixed;top:0;right:0;bottom:0;width:min(480px,100vw);background:#111119;border-left:1px solid #252530;z-index:1000;display:flex;flex-direction:column;transform:translateX(100%);transition:transform .38s cubic-bezier(.16,1,.3,1);overflow:hidden;}
        .cfg-panel.open{transform:translateX(0);}

        .cfg-header{display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.5rem;border-bottom:1px solid #252530;flex-shrink:0;}
        .cfg-title{font-family:'Playfair Display',serif;font-size:1.15rem;font-weight:700;}
        .cfg-close{width:32px;height:32px;border-radius:50%;background:transparent;border:1px solid #252530;color:#7a7880;cursor:pointer;font-size:.9rem;display:flex;align-items:center;justify-content:center;transition:all .2s;}
        .cfg-close:hover{border-color:#d42b2b;color:#d42b2b;}

        .cfg-tabs{display:flex;border-bottom:1px solid #252530;flex-shrink:0;}
        .cfg-tab{flex:1;padding:.85rem 1rem;background:transparent;border:none;font-family:'DM Sans',sans-serif;font-size:.75rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:#7a7880;cursor:pointer;border-bottom:2px solid transparent;transition:all .2s;}
        .cfg-tab:hover{color:#f0ece3;}
        .cfg-tab.active{color:#d42b2b;border-bottom-color:#d42b2b;background:rgba(212,43,43,.04);}

        .cfg-body{flex:1;overflow-y:auto;padding:1.5rem;}
        .cfg-body::-webkit-scrollbar{width:4px;}
        .cfg-body::-webkit-scrollbar-thumb{background:#252530;border-radius:2px;}

        .cfg-pane{display:none;}
        .cfg-pane.active{display:block;}

        .cfg-section-label{font-size:.6rem;letter-spacing:.2em;text-transform:uppercase;color:#7a7880;margin-bottom:.75rem;margin-top:1.5rem;}
        .cfg-section-label:first-child{margin-top:0;}

        .auto-estado-chip{display:inline-flex;align-items:center;gap:.4rem;padding:.3rem .75rem;border-radius:100px;font-size:.7rem;font-weight:600;margin-bottom:1rem;}
        .auto-estado-chip.off{background:rgba(122,120,128,.1);border:1px solid rgba(122,120,128,.2);color:#7a7880;}
        .auto-estado-chip.on{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#22c55e;}

        .auto-toggle-row{display:flex;align-items:center;justify-content:space-between;background:#18181f;border:1px solid #252530;border-radius:10px;padding:1rem 1.25rem;margin-bottom:1rem;}
        .auto-toggle-info h4{font-size:.9rem;font-weight:600;margin-bottom:.2rem;}
        .auto-toggle-info p{font-size:.75rem;color:#7a7880;line-height:1.5;}

        .toggle-switch{position:relative;width:44px;height:24px;flex-shrink:0;}
        .toggle-switch input{opacity:0;width:0;height:0;position:absolute;}
        .toggle-slider{position:absolute;inset:0;background:#252530;border-radius:24px;cursor:pointer;transition:background .3s;}
        .toggle-slider::before{content:'';position:absolute;left:3px;top:3px;width:18px;height:18px;border-radius:50%;background:#f0ece3;transition:transform .3s;}
        .toggle-switch input:checked + .toggle-slider{background:#d42b2b;}
        .toggle-switch input:checked + .toggle-slider::before{transform:translateX(20px);}

        .alcance-grid{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:1.25rem;}
        .alcance-btn{padding:.7rem .5rem;background:#18181f;border:1px solid #252530;border-radius:8px;text-align:center;font-family:'DM Sans',sans-serif;font-size:.78rem;font-weight:500;color:#7a7880;cursor:pointer;transition:all .2s;}
        .alcance-btn:hover{border-color:#7a7880;color:#f0ece3;}
        .alcance-btn.selected{background:rgba(212,43,43,.1);border-color:rgba(212,43,43,.5);color:#d42b2b;}
        .alcance-desc{font-size:.72rem;color:#7a7880;padding:.6rem .75rem;background:#0d0d14;border-radius:6px;border-left:2px solid #d42b2b;margin-bottom:1.25rem;min-height:32px;}

        .cfg-save-btn{width:100%;background:linear-gradient(135deg,#d42b2b 0%,#a81e1e 100%);color:#fff;border:none;border-radius:6px;padding:.85rem 1rem;font-family:'DM Sans',sans-serif;font-size:.78rem;font-weight:600;letter-spacing:.12em;text-transform:uppercase;cursor:pointer;transition:all .25s;box-shadow:0 4px 16px rgba(212,43,43,.25);}
        .cfg-save-btn:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(212,43,43,.4);}
        .cfg-save-btn:disabled{opacity:.5;cursor:not-allowed;transform:none;}

        .mini-cal-wrap{background:#18181f;border:1px solid #252530;border-radius:10px;padding:1.1rem;margin-bottom:1rem;}
        .mini-cal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;}
        .mini-cal-title{font-size:.9rem;font-weight:600;}
        .mini-cal-nav{display:flex;gap:.3rem;}
        .mini-cal-nav button{width:26px;height:26px;border:1px solid #252530;border-radius:4px;background:transparent;color:#7a7880;font-size:.8rem;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;}
        .mini-cal-nav button:hover{border-color:#d42b2b;color:#d42b2b;}
        .mini-cal-days{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;margin-bottom:.3rem;}
        .mini-cal-day-label{text-align:center;font-size:.55rem;letter-spacing:.08em;text-transform:uppercase;color:#7a7880;padding:.2rem 0;}
        .mini-cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;}
        .mini-cell{aspect-ratio:1;display:flex;align-items:center;justify-content:center;border-radius:6px;font-size:.72rem;cursor:pointer;transition:all .15s;border:1px solid transparent;position:relative;}
        .mini-cell:hover:not(.mc-disabled):not(.mc-empty):not(.mc-blocked){border-color:rgba(212,43,43,.4);color:#d42b2b;}
        .mini-cell.mc-today:not(.mc-selected):not(.mc-blocked):not(.mc-pending){border-color:rgba(212,43,43,.3);color:#d42b2b;}
        .mini-cell.mc-disabled{color:#2a2a38;cursor:not-allowed;}
        .mini-cell.mc-empty{cursor:default;}
        .mini-cell.mc-selected{background:rgba(212,43,43,.15);border-color:rgba(212,43,43,.5);color:#d42b2b;}
        .mini-cell.mc-blocked{background:rgba(212,43,43,.25);border-color:#d42b2b;color:#fff;cursor:pointer;}
        .mini-cell.mc-blocked::after{content:'';position:absolute;inset:0;background:repeating-linear-gradient(-45deg,rgba(212,43,43,.15) 0,rgba(212,43,43,.15) 2px,transparent 2px,transparent 6px);border-radius:5px;pointer-events:none;}
        .mini-cell.mc-blocked:hover{background:rgba(212,43,43,.4);border-color:#ff4444;}
        .mini-cell.mc-pending{background:rgba(245,158,11,.2);border-color:rgba(245,158,11,.6);color:#f59e0b;cursor:pointer;}
        .mini-cell.mc-pending::after{content:'';position:absolute;inset:3px;border-radius:4px;border:1px dashed rgba(245,158,11,.5);pointer-events:none;}
        .mini-cell.mc-unblocking{background:rgba(122,120,128,.15);border-color:rgba(122,120,128,.4);color:#7a7880;text-decoration:line-through;cursor:pointer;}

        .cal-legend{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem;}
        .cal-legend-item{display:flex;align-items:center;gap:.35rem;font-size:.65rem;color:#7a7880;}
        .cal-legend-dot{width:10px;height:10px;border-radius:2px;flex-shrink:0;}
        .cal-legend-dot.blocked{background:rgba(212,43,43,.4);border:1px solid #d42b2b;}
        .cal-legend-dot.pending{background:rgba(245,158,11,.3);border:1px dashed rgba(245,158,11,.7);}
        .cal-legend-dot.unblocking{background:rgba(122,120,128,.2);border:1px solid #7a7880;}

        .vac-motivo-row{display:flex;gap:.5rem;margin-bottom:.75rem;}
        .vac-motivo-input{flex:1;background:#18181f;border:1px solid #252530;border-radius:6px;padding:.55rem .75rem;color:#f0ece3;font-family:'DM Sans',sans-serif;font-size:.82rem;}
        .vac-motivo-input:focus{outline:none;border-color:#d42b2b;}
        .vac-action-row{display:flex;gap:.5rem;margin-bottom:.75rem;}
        .vac-btn{flex:1;padding:.6rem .5rem;border-radius:6px;font-family:'DM Sans',sans-serif;font-size:.72rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;cursor:pointer;transition:all .2s;}
        .vac-btn-range{background:rgba(201,168,76,.1);border:1px solid rgba(201,168,76,.3);color:#c9a84c;}
        .vac-btn-range:hover{background:#c9a84c;color:#000;}
        .vac-btn-clear{background:transparent;border:1px solid #252530;color:#7a7880;}
        .vac-btn-clear:hover{border-color:#7a7880;color:#f0ece3;}
        .range-hint{font-size:.72rem;color:#c9a84c;padding:.5rem .7rem;background:rgba(201,168,76,.06);border:1px solid rgba(201,168,76,.2);border-radius:6px;margin-bottom:.75rem;display:none;}
        .range-hint.visible{display:block;}

        .cfg-save-days-btn{width:100%;background:linear-gradient(135deg,#c9a84c 0%,#a17c2d 100%);color:#000;border:none;border-radius:6px;padding:.85rem 1rem;font-family:'DM Sans',sans-serif;font-size:.78rem;font-weight:600;letter-spacing:.12em;text-transform:uppercase;cursor:pointer;transition:all .25s;box-shadow:0 4px 16px rgba(201,168,76,.2);margin-top:.25rem;}
        .cfg-save-days-btn:hover:not(:disabled){transform:translateY(-1px);box-shadow:0 8px 24px rgba(201,168,76,.35);}
        .cfg-save-days-btn:disabled{opacity:.4;cursor:not-allowed;transform:none;}

        .blocked-list{display:flex;flex-direction:column;gap:.4rem;max-height:220px;overflow-y:auto;}
        .blocked-list::-webkit-scrollbar{width:3px;}
        .blocked-list::-webkit-scrollbar-thumb{background:#252530;}
        .blocked-item{display:flex;align-items:center;justify-content:space-between;background:#18181f;border:1px solid #252530;border-radius:7px;padding:.55rem .85rem;font-size:.8rem;}
        .blocked-item-info{display:flex;flex-direction:column;gap:.1rem;}
        .blocked-fecha{color:#f0ece3;font-weight:500;}
        .blocked-motivo{font-size:.7rem;color:#7a7880;}
        .blocked-del{width:24px;height:24px;border-radius:50%;background:transparent;border:1px solid #252530;color:#7a7880;cursor:pointer;font-size:.75rem;display:flex;align-items:center;justify-content:center;transition:all .2s;flex-shrink:0;}
        .blocked-del:hover{border-color:#d42b2b;color:#d42b2b;background:rgba(212,43,43,.08);}
        .empty-blocked{text-align:center;color:#7a7880;font-size:.78rem;padding:1.5rem;border:1px dashed #252530;border-radius:8px;}

        .cfg-status{display:flex;align-items:center;gap:.5rem;padding:.65rem 1rem;border-radius:8px;font-size:.78rem;margin-top:1rem;opacity:0;transition:opacity .3s;}
        .cfg-status.visible{opacity:1;}
        .cfg-status.ok{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);color:#22c55e;}
        .cfg-status.err{background:rgba(212,43,43,.1);border:1px solid rgba(212,43,43,.25);color:#d42b2b;}

        /* ================================================================
           STATS PANEL
        ================================================================ */
        .stats-overlay{position:fixed;inset:0;background:rgba(0,0,0,.9);backdrop-filter:blur(12px);z-index:1100;opacity:0;pointer-events:none;transition:opacity .4s ease;}
        .stats-overlay.open{opacity:1;pointer-events:all;}

        .stats-panel{position:fixed;inset:0;overflow-y:auto;z-index:1101;opacity:0;transform:translateY(28px);pointer-events:none;transition:opacity .45s cubic-bezier(.16,1,.3,1),transform .45s cubic-bezier(.16,1,.3,1);}
        .stats-panel.open{opacity:1;transform:translateY(0);pointer-events:all;}

        .stats-inner{max-width:1200px;margin:0 auto;padding:2rem 1.5rem 4rem;}

        .stats-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:2.5rem;padding-bottom:1.25rem;border-bottom:1px solid rgba(245,240,232,.08);}
        .stats-title{font-family:'Playfair Display',serif;font-size:clamp(1.4rem,3vw,1.9rem);font-weight:700;display:flex;align-items:center;gap:.75rem;}
        .stats-title-icon{width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#d42b2b,#a81e1e);display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;box-shadow:0 4px 16px rgba(212,43,43,.35);}
        .stats-close{width:40px;height:40px;border-radius:50%;background:rgba(245,240,232,.06);border:1px solid rgba(245,240,232,.12);color:#f0ece3;cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center;transition:all .2s;}
        .stats-close:hover{background:rgba(212,43,43,.2);border-color:#d42b2b;color:#d42b2b;}

        .stats-loading{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:50vh;gap:1.5rem;color:#7a7880;font-size:.85rem;letter-spacing:.1em;text-transform:uppercase;}
        .stats-spinner{width:48px;height:48px;border-radius:50%;border:2px solid rgba(212,43,43,.15);border-top-color:#d42b2b;animation:statsSpin .9s linear infinite;}
        @keyframes statsSpin{to{transform:rotate(360deg);}}

        .stats-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:2rem;}
        .kpi-card{background:#111119;border:1px solid #252530;border-radius:14px;padding:1.25rem 1.4rem;position:relative;overflow:hidden;transition:transform .25s,border-color .25s,box-shadow .25s;cursor:default;}
        .kpi-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--kpi-accent,#d42b2b);transform:scaleX(0);transform-origin:left;transition:transform .6s cubic-bezier(.16,1,.3,1);}
        .kpi-card.visible::before{transform:scaleX(1);}
        .kpi-card:hover{transform:translateY(-3px);border-color:var(--kpi-accent,#d42b2b);box-shadow:0 8px 32px rgba(0,0,0,.35);}
        .kpi-label{font-size:.6rem;letter-spacing:.2em;text-transform:uppercase;color:#7a7880;margin-bottom:.5rem;}
        .kpi-value{font-family:'Playfair Display',serif;font-size:2rem;font-weight:700;color:var(--kpi-accent,#d42b2b);line-height:1;}
        .kpi-sub{font-size:.7rem;color:#7a7880;margin-top:.35rem;}
        .kpi-badge{position:absolute;top:.85rem;right:.85rem;font-size:.9rem;opacity:.18;}

        .stats-section{margin-bottom:2rem;}
        .stats-section-label{font-size:.6rem;letter-spacing:.22em;text-transform:uppercase;color:#d42b2b;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;}
        .stats-section-label::before{content:'';width:20px;height:1px;background:#d42b2b;}

        .stats-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
        .stats-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;}
        .stats-card{background:#111119;border:1px solid #252530;border-radius:14px;padding:1.5rem;}
        .stats-card-title{font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:#7a7880;margin-bottom:1.25rem;}

        .bar-chart{display:flex;align-items:flex-end;gap:6px;height:120px;padding-bottom:24px;position:relative;}
        .bar-chart::after{content:'';position:absolute;bottom:24px;left:0;right:0;height:1px;background:rgba(245,240,232,.06);}
        .bar-item{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;position:relative;}
        .bar-fill{width:100%;border-radius:4px 4px 0 0;min-height:3px;transform:scaleY(0);transform-origin:bottom;transition:transform .7s cubic-bezier(.34,1.56,.64,1);transition-delay:var(--bar-delay,0s);position:relative;overflow:hidden;}
        .bar-fill::after{content:'';position:absolute;inset:0;background:linear-gradient(to top,rgba(255,255,255,0),rgba(255,255,255,.12));pointer-events:none;}
        .bar-fill.animated{transform:scaleY(1);}
        .bar-label{font-size:.55rem;color:#7a7880;text-align:center;position:absolute;bottom:0;white-space:nowrap;}
        .bar-item:hover .bar-tooltip{opacity:1;}
        .bar-tooltip{position:absolute;bottom:calc(100% + 8px);left:50%;transform:translateX(-50%);background:#1c1c26;border:1px solid #252530;border-radius:6px;padding:.3rem .6rem;font-size:.7rem;color:#f0ece3;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .2s;z-index:10;}

        .line-chart-svg{width:100%;overflow:visible;}
        .line-path{fill:none;stroke:#d42b2b;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:1000;stroke-dashoffset:1000;transition:stroke-dashoffset 1.8s cubic-bezier(.16,1,.3,1);}
        .line-path.animated{stroke-dashoffset:0;}
        .line-area{fill:url(#lineGrad);opacity:0;transition:opacity 1s ease .6s;}
        .line-area.animated{opacity:1;}
        .line-dot{fill:#d42b2b;stroke:#111119;stroke-width:2;opacity:0;transition:opacity .3s;cursor:pointer;}
        .line-dot.animated{opacity:1;}
        .line-dot:hover{r:6;fill:#ff4444;}
        .line-x-label{font-size:9px;fill:#7a7880;text-anchor:middle;}
        .line-y-label{font-size:9px;fill:#7a7880;text-anchor:end;}
        .line-grid{stroke:rgba(245,240,232,.05);stroke-width:1;}

        .chart-tooltip{position:fixed;background:#1c1c26;border:1px solid #d42b2b;border-radius:8px;padding:.5rem .85rem;font-size:.75rem;color:#f0ece3;pointer-events:none;z-index:1200;opacity:0;transition:opacity .15s;transform:translate(-50%,-120%);min-width:100px;text-align:center;}
        .chart-tooltip.visible{opacity:1;}
        .chart-tooltip strong{display:block;color:#d42b2b;font-size:.85rem;}

        .donut-wrap{display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;}
        .donut-seg{fill:none;stroke-width:18;stroke-linecap:round;stroke-dasharray:0 251;transition:stroke-dasharray 1.2s cubic-bezier(.16,1,.3,1);transition-delay:var(--seg-delay,0s);}

        .conversion-wrap{display:flex;flex-direction:column;align-items:center;gap:1rem;padding:.5rem 0;}
        .conversion-ring{position:relative;width:140px;height:140px;}
        .conv-svg{width:100%;height:100%;}
        .conv-track{fill:none;stroke:#1c1c26;stroke-width:14;}
        .conv-prog{fill:none;stroke:#22c55e;stroke-width:14;stroke-linecap:round;stroke-dasharray:0 345;transform:rotate(-90deg);transform-origin:50% 50%;transition:stroke-dasharray 1.5s cubic-bezier(.16,1,.3,1) .3s;}
        .conv-prog.animated{stroke-dasharray:var(--conv-dash,0) 345;}
        .conv-center{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;}
        .conv-pct{font-family:'Playfair Display',serif;font-size:1.75rem;font-weight:700;color:#22c55e;line-height:1;}
        .conv-sub{font-size:.6rem;color:#7a7880;letter-spacing:.1em;text-transform:uppercase;margin-top:.2rem;}
        .conversion-meta{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;width:100%;}
        .conv-meta-item{text-align:center;background:#0d0d14;border-radius:8px;padding:.75rem .5rem;}
        .conv-meta-num{font-family:'Playfair Display',serif;font-size:1.2rem;font-weight:700;line-height:1;}
        .conv-meta-lbl{font-size:.58rem;color:#7a7880;letter-spacing:.08em;text-transform:uppercase;margin-top:.2rem;}

        .barbero-stat-card{background:#111119;border:1px solid #252530;border-radius:14px;padding:1.25rem;display:flex;flex-direction:column;gap:.85rem;transition:border-color .25s,transform .25s;}
        .barbero-stat-card:hover{border-color:rgba(212,43,43,.4);transform:translateY(-2px);}
        .barbero-stat-header{display:flex;align-items:center;gap:.85rem;}
        .barbero-avatar-stat{width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,rgba(212,43,43,.15),rgba(212,43,43,.05));border:1px solid rgba(212,43,43,.25);display:flex;align-items:center;justify-content:center;font-family:'Playfair Display',serif;font-size:1rem;font-weight:700;color:#d42b2b;flex-shrink:0;}
        .barbero-stat-name{font-weight:600;font-size:.9rem;margin-bottom:.1rem;}
        .barbero-stat-sub{font-size:.7rem;color:#7a7880;}
        .barbero-progress-wrap{display:flex;flex-direction:column;gap:.4rem;}
        .barbero-progress-label{display:flex;justify-content:space-between;font-size:.7rem;color:#7a7880;}
        .barbero-progress-label span:last-child{color:#c9a84c;font-weight:500;}
        .progress-track{height:6px;background:#1c1c26;border-radius:3px;overflow:hidden;}
        .progress-fill{height:100%;border-radius:3px;background:linear-gradient(90deg,#d42b2b,#ff6b6b);width:0;transition:width 1.2s cubic-bezier(.16,1,.3,1);transition-delay:var(--prog-delay,.2s);}
        .progress-fill.animated{width:var(--prog-w,0%);}
        .barbero-kpi-row{display:flex;gap:.5rem;}
        .barbero-kpi{flex:1;background:#0d0d14;border-radius:8px;padding:.6rem .75rem;text-align:center;}
        .barbero-kpi-num{font-family:'Playfair Display',serif;font-size:1.1rem;color:#d42b2b;font-weight:700;line-height:1;}
        .barbero-kpi-lbl{font-size:.58rem;color:#7a7880;letter-spacing:.1em;text-transform:uppercase;margin-top:.2rem;}

        .horas-wrap{display:flex;flex-direction:column;gap:.5rem;}
        .hora-row{display:flex;align-items:center;gap:.75rem;}
        .hora-lbl{font-size:.72rem;color:#7a7880;width:42px;flex-shrink:0;text-align:right;}
        .hora-bar-outer{flex:1;height:28px;background:#0d0d14;border-radius:6px;overflow:hidden;position:relative;}
        .hora-bar-fill{height:100%;border-radius:6px;background:linear-gradient(90deg,rgba(212,43,43,.6),rgba(212,43,43,.9));width:0;transition:width 1s cubic-bezier(.16,1,.3,1);transition-delay:var(--h-delay,0s);display:flex;align-items:center;justify-content:flex-end;padding-right:.5rem;}
        .hora-bar-fill.animated{width:var(--h-w,0%);}
        .hora-count{font-size:.65rem;color:rgba(255,255,255,.7);font-weight:600;white-space:nowrap;}

        .svc-stat-list{display:flex;flex-direction:column;gap:.6rem;}
        .svc-stat-item{display:flex;align-items:center;gap:.75rem;}
        .svc-stat-rank{width:22px;height:22px;border-radius:6px;background:rgba(212,43,43,.1);border:1px solid rgba(212,43,43,.2);font-size:.65rem;font-weight:700;color:#d42b2b;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
        .svc-stat-info{flex:1;min-width:0;}
        .svc-stat-name{font-size:.82rem;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .svc-stat-bar{height:4px;background:#1c1c26;border-radius:2px;margin-top:.3rem;overflow:hidden;}
        .svc-stat-bar-fill{height:100%;border-radius:2px;background:linear-gradient(90deg,#d42b2b,#c9a84c);width:0;transition:width 1s cubic-bezier(.16,1,.3,1);transition-delay:var(--svc-delay,0s);}
        .svc-stat-bar-fill.animated{width:var(--svc-w,0%);}
        .svc-stat-meta{text-align:right;flex-shrink:0;}
        .svc-stat-count{font-size:.82rem;color:#f0ece3;font-weight:500;}
        .svc-stat-euros{font-size:.7rem;color:#c9a84c;}

        .heatmap-wrap{overflow-x:auto;}
        .heatmap-grid{display:flex;gap:3px;padding:4px 0;}
        .hm-col{display:flex;flex-direction:column;gap:3px;align-items:center;}
        .hm-cell{width:20px;height:20px;border-radius:4px;border:1px solid #1c1c26;transition:all .2s;cursor:default;position:relative;flex-shrink:0;}
        .hm-cell:hover{transform:scale(1.3);z-index:10;}
        .hm-cell[data-v="0"]{background:#0d0d14;}
        .hm-cell[data-v="1"]{background:rgba(212,43,43,.2);}
        .hm-cell[data-v="2"]{background:rgba(212,43,43,.4);}
        .hm-cell[data-v="3"]{background:rgba(212,43,43,.65);}
        .hm-cell[data-v="4"]{background:rgba(212,43,43,.85);}

        @keyframes statsNumPop{0%{transform:scale(.5);opacity:0;}70%{transform:scale(1.08);}100%{transform:scale(1);opacity:1;}}
        .kpi-value.pop{animation:statsNumPop .5s cubic-bezier(.34,1.56,.64,1) both;}

        @media(max-width:700px){
            .stats-grid-2,.stats-grid-3{grid-template-columns:1fr;}
            .stats-kpis{grid-template-columns:repeat(2,1fr);}
            .bar-chart{height:90px;}
            .stats-inner{padding:1rem 1rem 3rem;}
        }
        @media(max-width:420px){
            .stats-kpis{grid-template-columns:1fr 1fr;}
            .kpi-value{font-size:1.6rem;}
        }

        /* ================================================================
        STATS FIXES — CSS adicional para admin.php
        Añadir dentro del bloque <style> existente, antes del cierre </style>
        ================================================================ */

        /* FIX 4: Tasa de aceptación — meta items en fila horizontal en PC */
        .conversion-meta-row {
            display: grid !important;
            grid-template-columns: repeat(4, 1fr) !important;
            gap: .5rem !important;
            width: 100% !important;
            margin-top: .75rem;
        }

        /* FIX 5: Barbero cards — cursor pointer y hover mejorado */
        .barbero-stat-card {
            position: relative;
            cursor: pointer;
            user-select: none;
            transition: border-color .25s, transform .25s, box-shadow .25s;
        }
        .barbero-stat-card:hover {
            border-color: rgba(212,43,43,.45) !important;
            box-shadow: 0 8px 32px rgba(212,43,43,.15), 0 0 0 1px rgba(212,43,43,.08) !important;
        }
        .barbero-stat-card:active {
            transform: translateY(1px) !important;
        }

        /* FIX 2: Heatmap — override de las clases antiguas */
        .hm-col, .heatmap-grid { display: none !important; }

        /* Modal de barbero — transición suave en botón de cerrar */
        #barbero-modal-overlay button:hover {
            border-color: #d42b2b !important;
            color: #d42b2b !important;
        }

        /* Responsive: en móvil los meta de conversión vuelven a 2 columnas */
        @media (max-width: 600px) {
            .conversion-meta-row {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }
    </style>
</head>
<body>

<div class="admin-header">
    <div class="admin-brand">Prado <span>Barber</span> · Admin</div>
    <div class="header-actions">
        <button class="stats-trigger-btn" onclick="openStats()" title="Estadísticas">📊</button>
        <button class="settings-btn" onclick="openCfg()" title="Configuración">⚙</button>
        <form method="POST" style="margin:0;">
            <button class="logout-btn" name="logout" value="1">Cerrar sesión</button>
        </form>
    </div>
</div>

<div class="admin-body">

    <?php if ($statsPend['total'] > 0): ?>
    <div class="alert-pendientes">
        <span style="font-size:1.1rem;">⏳</span>
        <span><strong><?= $statsPend['total'] ?> reserva<?= $statsPend['total']!=1?'s':'' ?> pendiente<?= $statsPend['total']!=1?'s':'' ?></strong> sin confirmar</span>
        <a href="?estado=pendiente&fecha=todas" class="alert-link">Ver →</a>
    </div>
    <?php endif; ?>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-label">Reservas hoy</div>
            <div class="stat-value"><?= $statsHoy['total'] ?? 0 ?></div>
            <div class="stat-sub"><?= date('d/m/Y') ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Ingresos hoy</div>
            <div class="stat-value gold"><?= number_format($statsHoy['ingresos'] ?? 0, 0) ?> €</div>
            <div class="stat-sub">estimado</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Pendientes</div>
            <div class="stat-value orange"><?= $statsPend['total'] ?></div>
            <div class="stat-sub">sin confirmar</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Mostrando</div>
            <div class="stat-value"><?= count($reservas) ?></div>
            <div class="stat-sub">reservas</div>
        </div>
    </div>

    <div class="filters">
        <div class="filters-label">Filtros</div>
        <form method="GET">
            <div class="frow">
                <span class="flabel">Barbero</span>
                <select name="barbero">
                    <option value="todos" <?= $filtroBarbero==='todos'?'selected':'' ?>>Todos</option>
                    <?php foreach ($barberos as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $filtroBarbero===$b['id']?'selected':'' ?>><?= htmlspecialchars($b['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="frow">
                <span class="flabel">Estado</span>
                <select name="estado">
                    <option value="todos"     <?= $filtroEstado==='todos'    ?'selected':'' ?>>Todos</option>
                    <option value="pendiente" <?= $filtroEstado==='pendiente'?'selected':'' ?>>⏳ Pendientes</option>
                    <option value="aceptada"  <?= $filtroEstado==='aceptada' ?'selected':'' ?>>✓ Aceptadas</option>
                    <option value="denegada"  <?= $filtroEstado==='denegada' ?'selected':'' ?>>✕ Denegadas</option>
                </select>
            </div>
            <div class="frow">
                <span class="flabel">Fecha</span>
                <select name="fecha" onchange="this.form.submit()">
                    <option value="hoy"    <?= $filtroFecha==='hoy'    ?'selected':'' ?>>Hoy</option>
                    <option value="manana" <?= $filtroFecha==='manana' ?'selected':'' ?>>Mañana</option>
                    <option value="semana" <?= $filtroFecha==='semana' ?'selected':'' ?>>Próximos 7 días</option>
                    <option value="todas"  <?= $filtroFecha==='todas'  ?'selected':'' ?>>Todas</option>
                    <option value="custom" <?= $filtroFecha==='custom' ?'selected':'' ?>>Fecha específica</option>
                </select>
            </div>
            <?php if ($filtroFecha === 'custom'): ?>
            <div class="frow">
                <span class="flabel">Día</span>
                <input type="date" name="fecha_custom" value="<?= htmlspecialchars($fechaCustom) ?>"/>
            </div>
            <?php endif; ?>
            <button type="submit" class="filter-submit">Filtrar</button>
        </form>
    </div>

    <div class="section-header">
        <div class="section-title-admin">Reservas</div>
        <div class="section-count"><?= count($reservas) ?> resultado<?= count($reservas)!=1?'s':'' ?></div>
    </div>

    <?php if (empty($reservas)): ?>
    <div class="empty-state">
        <div class="empty-icon">📅</div>
        <div>No hay reservas para los filtros seleccionados.</div>
    </div>
    <?php else: ?>

    <!-- ═══ MOBILE CARDS ═══ -->
    <div class="reservas-cards">
        <?php foreach ($reservas as $r):
            $dt      = new DateTime($r['fecha']);
            $diaNum  = (int)$dt->format('w');
            $mesNum  = (int)$dt->format('n');
            $fechaStr= $diasES[$diaNum] . ' ' . $dt->format('j') . ' ' . $mesesES[$mesNum];
            $hora    = substr($r['hora'], 0, 5);
            $est     = $r['estado'];
        ?>
        <div class="rc <?= $est ?>">
            <div class="rc-top">
                <div class="rc-top-left">
                    <div class="rc-id">#<?= $r['id'] ?></div>
                    <div class="rc-hora"><?= $hora ?></div>
                    <div class="rc-fecha"><?= $fechaStr ?></div>
                </div>
                <?php if ($est==='pendiente'): ?>
                    <span class="ebadge ebadge-pendiente">⏳ Pendiente</span>
                <?php elseif ($est==='aceptada'): ?>
                    <span class="ebadge ebadge-aceptada">✓ Aceptada</span>
                <?php else: ?>
                    <span class="ebadge ebadge-denegada">✕ Denegada</span>
                <?php endif; ?>
            </div>
            <div class="rc-divider"></div>
            <div class="rc-body">
                <div>
                    <div class="rc-cliente-name"><?= htmlspecialchars($r['cliente_nombre']) ?></div>
                    <div class="rc-cliente-meta">
                        <span class="rc-meta-item">✉ <?= htmlspecialchars($r['cliente_email']) ?></span>
                        <span class="rc-meta-item">📞 <?= htmlspecialchars($r['cliente_telefono']) ?></span>
                    </div>
                </div>
                <div class="rc-details">
                    <div class="rc-detail">
                        <div class="rc-detail-label">Servicio</div>
                        <div class="rc-detail-value"><?= htmlspecialchars($r['servicio']) ?></div>
                        <div class="rc-detail-sub"><?= $r['duracion'] ?></div>
                    </div>
                    <div class="rc-detail">
                        <div class="rc-detail-label">Precio</div>
                        <div class="rc-detail-value gold"><?= number_format($r['precio'],0) ?> €</div>
                    </div>
                    <div class="rc-detail">
                        <div class="rc-detail-label">Barbero</div>
                        <div class="rc-detail-value"><span class="rc-barbero-pill"><?= htmlspecialchars($r['barbero']) ?></span></div>
                    </div>
                </div>
                <?php if ($r['notas']): ?>
                <div class="rc-notas">"<?= htmlspecialchars($r['notas']) ?>"</div>
                <?php endif; ?>
            </div>
            <?php if ($est === 'pendiente'): ?>
            <div class="rc-actions">
                <a href="?accion=aceptar&token=<?= urlencode($r['token']) ?>&<?= http_build_query(['barbero'=>$filtroBarbero,'fecha'=>$filtroFecha,'estado'=>$filtroEstado,'fecha_custom'=>$fechaCustom]) ?>"
                   class="btn-accept"
                   onclick="return confirm('¿Aceptar la reserva de <?= htmlspecialchars(addslashes($r['cliente_nombre'])) ?>?')">
                   ✓ Aceptar
                </a>
                <a href="?accion=denegar&token=<?= urlencode($r['token']) ?>&<?= http_build_query(['barbero'=>$filtroBarbero,'fecha'=>$filtroFecha,'estado'=>$filtroEstado,'fecha_custom'=>$fechaCustom]) ?>"
                   class="btn-deny"
                   onclick="return confirm('¿Denegar la reserva de <?= htmlspecialchars(addslashes($r['cliente_nombre'])) ?>?')">
                   ✕ Denegar
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ═══ DESKTOP TABLE ═══ -->
    <div class="table-desktop">
        <div class="table-wrap-d">
            <div class="table-header-d">
                <div class="table-title-d">Reservas</div>
                <div style="font-size:.75rem;color:#7a7880;"><?= count($reservas) ?> resultado<?= count($reservas)!=1?'s':'' ?></div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>#</th><th>Fecha</th><th>Hora</th><th>Cliente</th>
                        <th>Servicio</th><th>Precio</th><th>Barbero</th>
                        <th>Estado</th><th>Acción</th><th>Notas</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($reservas as $r):
                    $dt      = new DateTime($r['fecha']);
                    $diaNum  = (int)$dt->format('w');
                    $mesNum  = (int)$dt->format('n');
                    $fechaStr= $diasES[$diaNum] . ' ' . $dt->format('j') . ' ' . $mesesES[$mesNum];
                    $rowStyle= $r['estado']==='denegada' ? 'opacity:.55;' : '';
                ?>
                <tr style="<?= $rowStyle ?>">
                    <td style="color:#7a7880;font-size:.75rem;">#<?= $r['id'] ?></td>
                    <td style="white-space:nowrap;"><?= $fechaStr ?></td>
                    <td class="td-hora"><?= substr($r['hora'],0,5) ?></td>
                    <td class="td-cliente">
                        <strong><?= htmlspecialchars($r['cliente_nombre']) ?></strong>
                        <span><?= htmlspecialchars($r['cliente_email']) ?></span>
                        <span><?= htmlspecialchars($r['cliente_telefono']) ?></span>
                    </td>
                    <td><?= htmlspecialchars($r['servicio']) ?><br><span style="font-size:.72rem;color:#7a7880;"><?= $r['duracion'] ?></span></td>
                    <td class="td-precio"><?= number_format($r['precio'],0) ?> €</td>
                    <td class="td-barbero"><span class="b-badge"><?= htmlspecialchars($r['barbero']) ?></span></td>
                    <td>
                        <?php if ($r['estado']==='pendiente'): ?>
                            <span class="estado-badge badge-pendiente">⏳ Pendiente</span>
                        <?php elseif ($r['estado']==='aceptada'): ?>
                            <span class="estado-badge badge-aceptada">✓ Aceptada</span>
                        <?php else: ?>
                            <span class="estado-badge badge-denegada">✕ Denegada</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($r['estado']==='pendiente'): ?>
                        <div class="action-btns">
                            <a href="?accion=aceptar&token=<?= urlencode($r['token']) ?>&<?= http_build_query(['barbero'=>$filtroBarbero,'fecha'=>$filtroFecha,'estado'=>$filtroEstado,'fecha_custom'=>$fechaCustom]) ?>"
                               class="tb-accept"
                               onclick="return confirm('¿Aceptar la reserva de <?= htmlspecialchars(addslashes($r['cliente_nombre'])) ?>?')">✓ Aceptar</a>
                            <a href="?accion=denegar&token=<?= urlencode($r['token']) ?>&<?= http_build_query(['barbero'=>$filtroBarbero,'fecha'=>$filtroFecha,'estado'=>$filtroEstado,'fecha_custom'=>$fechaCustom]) ?>"
                               class="tb-deny"
                               onclick="return confirm('¿Denegar la reserva de <?= htmlspecialchars(addslashes($r['cliente_nombre'])) ?>?')">✕ Denegar</a>
                        </div>
                        <?php else: ?><span style="color:#7a7880;font-size:.75rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="td-notas"><?= $r['notas'] ? htmlspecialchars($r['notas']) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php endif; ?>

</div><!-- /admin-body -->


<!-- ================================================================
     STATS PANEL — HTML
================================================================ -->
<div class="stats-overlay" id="stats-overlay" onclick="closeStats()"></div>
<div class="stats-panel" id="stats-panel">
    <div class="stats-inner">
        <div class="stats-header">
            <div class="stats-title">
                <div class="stats-title-icon">📊</div>
                Estadísticas &amp; Analytics
            </div>
            <button class="stats-close" onclick="closeStats()">✕</button>
        </div>
        <div id="stats-content">
            <div class="stats-loading" id="stats-loading">
                <div class="stats-spinner"></div>
                <span>Cargando datos…</span>
            </div>
        </div>
    </div>
</div>

<div class="chart-tooltip" id="chart-tooltip"></div>


<!-- ================================================================
     CONFIG PANEL — HTML
================================================================ -->
<div class="cfg-overlay" id="cfg-overlay" onclick="closeCfg()"></div>

<div class="cfg-panel" id="cfg-panel">
    <div class="cfg-header">
        <div class="cfg-title">⚙ Configuración</div>
        <button class="cfg-close" onclick="closeCfg()">✕</button>
    </div>
    <div class="cfg-tabs">
        <button class="cfg-tab active" onclick="switchTab('auto')">Auto-aceptar</button>
        <button class="cfg-tab"        onclick="switchTab('vac')">Vacaciones</button>
    </div>
    <div class="cfg-body">

        <!-- ── TAB: AUTO-ACEPTAR ── -->
        <div class="cfg-pane active" id="pane-auto">
            <div class="cfg-section-label">Estado actual</div>
            <div class="auto-estado-chip off" id="auto-chip">
                <span id="auto-chip-dot">●</span>
                <span id="auto-chip-text">Desactivado</span>
            </div>
            <div class="auto-toggle-row">
                <div class="auto-toggle-info">
                    <h4>Auto-aceptar reservas</h4>
                    <p>Las reservas se confirman automáticamente sin necesitar tu aprobación manual.</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" id="auto-toggle" onchange="onAutoToggle()">
                    <span class="toggle-slider"></span>
                </label>
            </div>
            <div id="alcance-section" style="display:none;">
                <div class="cfg-section-label">Periodo de auto-aceptación</div>
                <div class="alcance-grid">
                    <button class="alcance-btn" data-val="hoy"    onclick="selectAlcance(this)">Hoy</button>
                    <button class="alcance-btn" data-val="semana" onclick="selectAlcance(this)">Esta semana</button>
                    <button class="alcance-btn" data-val="mes"    onclick="selectAlcance(this)">Este mes</button>
                    <button class="alcance-btn selected" data-val="siempre" onclick="selectAlcance(this)">Siempre</button>
                </div>
                <div class="alcance-desc" id="alcance-desc">Las reservas se aceptarán automáticamente sin límite de tiempo.</div>
            </div>
            <button class="cfg-save-btn" id="btn-save-auto" onclick="saveAutoAceptar()">Guardar configuración</button>
            <div class="cfg-status" id="auto-status"></div>
        </div>

        <!-- ── TAB: VACACIONES ── -->
        <div class="cfg-pane" id="pane-vac">
            <div class="cfg-section-label">Seleccionar días no disponibles</div>
            <div class="cal-legend">
                <div class="cal-legend-item"><div class="cal-legend-dot blocked"></div><span>Ya bloqueado (clic para desbloquear)</span></div>
                <div class="cal-legend-item"><div class="cal-legend-dot pending"></div><span>Pendiente de guardar</span></div>
                <div class="cal-legend-item"><div class="cal-legend-dot unblocking"></div><span>Pendiente de desbloquear</span></div>
            </div>
            <div class="mini-cal-wrap">
                <div class="mini-cal-header">
                    <span class="mini-cal-title" id="mc-title"></span>
                    <div class="mini-cal-nav">
                        <button onclick="mcNav(-1)">‹</button>
                        <button onclick="mcNav(1)">›</button>
                    </div>
                </div>
                <div class="mini-cal-days">
                    <div class="mini-cal-day-label">L</div><div class="mini-cal-day-label">M</div>
                    <div class="mini-cal-day-label">X</div><div class="mini-cal-day-label">J</div>
                    <div class="mini-cal-day-label">V</div><div class="mini-cal-day-label">S</div>
                    <div class="mini-cal-day-label">D</div>
                </div>
                <div class="mini-cal-grid" id="mc-grid"></div>
            </div>
            <div class="range-hint" id="range-hint">📅 Modo rango: selecciona el <strong>primer día</strong> y luego el <strong>último</strong>.</div>
            <div class="vac-motivo-row">
                <input class="vac-motivo-input" id="vac-motivo" type="text" placeholder="Motivo (ej: Vacaciones, Formación…)" maxlength="100">
            </div>
            <div class="vac-action-row">
                <button class="vac-btn vac-btn-range" id="btn-rango" onclick="toggleRangeMode()">⇔ Rango de días</button>
                <button class="vac-btn vac-btn-clear" onclick="clearPending()">Limpiar selección</button>
            </div>
            <button class="cfg-save-days-btn" id="btn-save-days" onclick="saveDays()" disabled>Guardar días bloqueados</button>
            <div class="cfg-section-label" style="margin-top:1.25rem;">Días bloqueados actualmente</div>
            <div class="blocked-list" id="blocked-list"></div>
            <div class="cfg-status" id="vac-status"></div>
        </div>

    </div>
</div>


<!-- ================================================================
     STATS JAVASCRIPT
================================================================ -->
<script>
// ================================================================
//  STATS JAVASCRIPT — VERSIÓN CORREGIDA
//  Fix 1: Tooltip vacío en gráfico de barras
//  Fix 2: Heatmap rediseñado con semanas claras
//  Fix 3: Servicios separados en aceptadas vs denegadas
//  Fix 4: Meta-items tasa aceptación en fila
//  Fix 5: Todos los barberos + modal de detalle
// ================================================================

(function(){
const STATS_API = './api/stats.php';
let statsLoaded = false;

window.openStats = function() {
    document.getElementById('stats-overlay').classList.add('open');
    document.getElementById('stats-panel').classList.add('open');
    document.body.style.overflow = 'hidden';
    if (!statsLoaded) fetchStats();
};
window.closeStats = function() {
    document.getElementById('stats-overlay').classList.remove('open');
    document.getElementById('stats-panel').classList.remove('open');
    document.body.style.overflow = '';
};

async function fetchStats() {
    try {
        const r = await fetch(STATS_API);
        const j = await r.json();
        if (!j.ok) throw new Error(j.error || 'Error al cargar');
        statsLoaded = true;
        renderStats(j.data);
    } catch(e) {
        document.getElementById('stats-content').innerHTML =
            `<div class="stats-loading" style="color:#d42b2b;"><div style="font-size:2rem;">⚠</div><span>${e.message}</span></div>`;
    }
}

function animNum(el, target, decimals, suffix) {
    decimals = decimals || 0; suffix = suffix || '';
    const dur = 1200, start = performance.now();
    function step(now) {
        const p = Math.min((now - start) / dur, 1);
        const ease = 1 - Math.pow(1 - p, 3);
        const val = ease * target;
        el.textContent = (decimals ? val.toFixed(decimals) : Math.floor(val)) + suffix;
        if (p < 1) requestAnimationFrame(step);
        else { el.textContent = (decimals ? target.toFixed(decimals) : target) + suffix; el.classList.add('pop'); }
    }
    requestAnimationFrame(step);
}

const tooltip = document.getElementById('chart-tooltip');
window.showTip = function(e, unit, val) {
    tooltip.innerHTML = `<strong>${val}</strong>${unit ? ' ' + unit : ''}`;
    tooltip.style.left = e.clientX + 'px';
    tooltip.style.top  = e.clientY + 'px';
    tooltip.classList.add('visible');
};
window.hideTip = function() { tooltip.classList.remove('visible'); };
document.addEventListener('mousemove', e => {
    if (tooltip.classList.contains('visible')) {
        tooltip.style.left = e.clientX + 'px';
        tooltip.style.top  = (e.clientY - 10) + 'px';
    }
});

function onVisible(el, cb) {
    if (!el) return;
    new IntersectionObserver((entries, obs) => {
        entries.forEach(en => { if (en.isIntersecting) { cb(); obs.unobserve(el); } });
    }, { threshold: 0.15 }).observe(el);
}

// ── Barbero Modal ─────────────────────────────────────────────
function openBarberoModal(b, allBarbers) {
    // Cerrar si existe
    document.getElementById('barbero-modal-overlay')?.remove();

    const maxIng = Math.max(...allBarbers.map(x => +x.ingresos), 1);
    const pct    = maxIng > 0 ? Math.round(+b.ingresos / maxIng * 100) : 0;
    const ticket = Number(+b.ingresos / Math.max(+b.aceptadas||+b.total_citas, 1)).toFixed(0);

    const overlay = document.createElement('div');
    overlay.id = 'barbero-modal-overlay';
    overlay.style.cssText = `
        position:fixed;inset:0;background:rgba(0,0,0,0.82);backdrop-filter:blur(10px);
        z-index:2000;display:flex;align-items:center;justify-content:center;padding:1.5rem;
        opacity:0;transition:opacity .3s ease;cursor:pointer;
    `;

    const modal = document.createElement('div');
    modal.style.cssText = `
        background:#111119;border:1px solid #2f2f3c;border-radius:20px;
        max-width:440px;width:100%;padding:0;overflow:hidden;
        transform:translateY(20px) scale(.97);transition:transform .35s cubic-bezier(.16,1,.3,1),opacity .35s ease;
        opacity:0;cursor:default;box-shadow:0 24px 80px rgba(0,0,0,.7),0 0 0 1px rgba(212,43,43,.08);
    `;
    modal.addEventListener('click', e => e.stopPropagation());

    // Colores por barbero
    const colorMap = { EP:'#d42b2b', MV:'#2550a0', AR:'#c9a84c' };
    const accentColor = colorMap[b.iniciales] || '#d42b2b';

    // Actividad últimos 6 meses - simplificado
    const meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    const mesActual = new Date().getMonth();
    const ultimos6 = Array.from({length:6}, (_,i) => meses[(mesActual - 5 + i + 12) % 12]);

    modal.innerHTML = `
        <!-- Header con color del barbero -->
        <div style="background:linear-gradient(135deg,${accentColor}22 0%,${accentColor}08 100%);
                    border-bottom:1px solid ${accentColor}30;padding:2rem 2rem 1.5rem;
                    position:relative;overflow:hidden;">
            <div style="position:absolute;top:-20px;right:-20px;width:120px;height:120px;
                        border-radius:50%;border:1px solid ${accentColor}15;"></div>
            <div style="position:absolute;top:10px;right:10px;width:60px;height:60px;
                        border-radius:50%;border:1px solid ${accentColor}10;"></div>
            <div style="display:flex;align-items:center;gap:1.25rem;position:relative;z-index:1;">
                <div style="width:68px;height:68px;border-radius:16px;
                            background:linear-gradient(135deg,${accentColor}25,${accentColor}10);
                            border:2px solid ${accentColor}50;
                            display:flex;align-items:center;justify-content:center;
                            font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:700;
                            color:${accentColor};flex-shrink:0;
                            box-shadow:0 4px 20px ${accentColor}30;">
                    ${b.iniciales}
                </div>
                <div>
                    <div style="font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:700;
                                color:#f0ece3;margin-bottom:.2rem;">${b.nombre}</div>
                    <div style="font-size:.7rem;letter-spacing:.15em;text-transform:uppercase;
                                color:${accentColor};font-weight:600;">${b.total_citas} citas totales</div>
                </div>
            </div>
        </div>

        <!-- KPIs en fila -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0;border-bottom:1px solid #252530;">
            ${[
                {num: b.aceptadas||0, lbl:'Aceptadas', col:'#22c55e'},
                {num: b.pendientes||0, lbl:'Pendientes', col:'#f59e0b'},
                {num: Number(b.ingresos||0).toFixed(0)+'€', lbl:'Ingresos', col:'#c9a84c'},
            ].map((k,i) => `
                <div style="padding:1.25rem 1rem;text-align:center;${i<2?'border-right:1px solid #252530;':''}">
                    <div style="font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700;
                                color:${k.col};line-height:1;">${k.num}</div>
                    <div style="font-size:.6rem;letter-spacing:.12em;text-transform:uppercase;
                                color:#7a7880;margin-top:.3rem;">${k.lbl}</div>
                </div>`).join('')}
        </div>

        <!-- Barra de ingresos -->
        <div style="padding:1.5rem 2rem;border-bottom:1px solid #252530;">
            <div style="display:flex;justify-content:space-between;font-size:.72rem;
                        color:#7a7880;margin-bottom:.6rem;">
                <span>Rendimiento de ingresos</span>
                <span style="color:${accentColor};font-weight:600;">${Number(b.ingresos||0).toFixed(0)} €</span>
            </div>
            <div style="height:8px;background:#1c1c26;border-radius:4px;overflow:hidden;">
                <div class="modal-prog-fill" style="height:100%;border-radius:4px;width:0;
                            background:linear-gradient(90deg,${accentColor},${accentColor}99);
                            transition:width 1.2s cubic-bezier(.16,1,.3,1) .2s;
                            --target-w:${pct}%;"></div>
            </div>
        </div>

        <!-- Ticket medio + otras métricas -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;padding:1.5rem 2rem;border-bottom:1px solid #252530;">
            <div style="background:#0d0d14;border-radius:10px;padding:1rem;text-align:center;border:1px solid #1c1c26;">
                <div style="font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:700;
                            color:#c9a84c;">${ticket}€</div>
                <div style="font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;color:#7a7880;margin-top:.25rem;">Ticket medio</div>
            </div>
            <div style="background:#0d0d14;border-radius:10px;padding:1rem;text-align:center;border:1px solid #1c1c26;">
                <div style="font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:700;color:#a78bfa;">
                    ${b.total_citas > 0 ? Math.round((+b.aceptadas / +b.total_citas) * 100) : 0}%
                </div>
                <div style="font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;color:#7a7880;margin-top:.25rem;">Tasa aceptación</div>
            </div>
        </div>

        <!-- Footer -->
        <div style="padding:1.25rem 2rem;display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:.72rem;color:#7a7880;letter-spacing:.05em;">Prado Barber Co. · Admin</span>
            <button onclick="document.getElementById('barbero-modal-overlay').remove();document.body.style.overflow='';"
                    style="padding:.5rem 1.25rem;background:transparent;border:1px solid #252530;
                           border-radius:6px;color:#7a7880;font-family:'DM Sans',sans-serif;
                           font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;cursor:pointer;
                           transition:all .2s;"
                    onmouseover="this.style.borderColor='#d42b2b';this.style.color='#d42b2b';"
                    onmouseout="this.style.borderColor='#252530';this.style.color='#7a7880';">
                Cerrar
            </button>
        </div>
    `;

    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    requestAnimationFrame(() => {
        overlay.style.opacity = '1';
        modal.style.opacity   = '1';
        modal.style.transform = 'translateY(0) scale(1)';
        // Animar barra de progreso
        setTimeout(() => {
            const fill = modal.querySelector('.modal-prog-fill');
            if (fill) fill.style.width = fill.style.getPropertyValue('--target-w') || pct + '%';
        }, 100);
    });

    overlay.addEventListener('click', () => {
        overlay.style.opacity = '0';
        modal.style.opacity   = '0';
        modal.style.transform = 'translateY(10px) scale(.97)';
        setTimeout(() => { overlay.remove(); document.body.style.overflow = ''; }, 300);
    });

    document.body.style.overflow = 'hidden';
}

function renderStats(d) {
    const kpi   = d.kpi    || {};
    const hoy   = d.hoy    || {};
    const mes   = d.mes    || {};
    const barbs = d.barberos || [];
    const svcs  = d.servicios_top || [];
    const meses = d.ingresos_mensual || [];
    const dow   = d.dias_semana || [];
    const horas = d.horas_top || [];
    const hmap  = d.heatmap_30d || [];
    const tasa  = d.tasa_conversion ?? 0;

    // FIX 5: Asegurar que todos los barberos del sistema aparecen
    // (barberos con 0 citas no vienen en d.barberos desde la API, así que los fusionamos)
    const ALL_BARBERS_BASE = [
        { id:'endika', nombre:'Endika Prado', iniciales:'EP' },
        { id:'marcos', nombre:'Marcos Vila',  iniciales:'MV' },
        { id:'alex',   nombre:'Alex Ramos',   iniciales:'AR' },
    ];
    const barbsComplete = ALL_BARBERS_BASE.map(base => {
        const found = barbs.find(b => b.iniciales === base.iniciales);
        return found || { ...base, total_citas:0, ingresos:0, aceptadas:0, pendientes:0 };
    });

    const maxBarbIng = Math.max(...barbsComplete.map(b => +b.ingresos), 1);
    const maxDow     = Math.max(...dow.map(x => x.count), 1);
    const maxHora    = Math.max(...horas.map(h => +h.total), 1);
    const maxSvc     = Math.max(...svcs.map(s => +s.total), 1);

    const html = `
<div class="stats-kpis">
    ${kpiCard('Reservas totales', kpi.total_reservas||0, '#d42b2b',   '📋', '')}
    ${kpiCard('Ingresos totales', kpi.ingresos_totales||0, '#c9a84c', '💶', ' €')}
    ${kpiCard('Clientes únicos',  kpi.clientes_unicos||0, '#2550a0',  '👥', '')}
    ${kpiCard('Citas hoy',        hoy.citas_hoy||0, '#22c55e',        '📅', '')}
    ${kpiCard('Ingresos hoy',     hoy.ingresos_hoy||0, '#f59e0b',     '💰', ' €')}
    ${kpiCard('Citas este mes',   mes.citas_mes||0, '#a78bfa',         '📆', '')}
</div>

<div class="stats-section">
    <div class="stats-section-label">Evolución mensual — últimos 12 meses</div>
    <div class="stats-grid-2">
        <div class="stats-card">
            <div class="stats-card-title">Ingresos por mes (€)</div>
            ${buildLineChart(meses, 'ingresos', '€')}
        </div>
        <div class="stats-card">
            <div class="stats-card-title">Citas por mes</div>
            ${buildBarChart(meses.map(m=>({label:m.label,value:m.citas,tip:`${m.citas} citas — ${m.label}`})), '#2550a0')}
        </div>
    </div>
</div>

<div class="stats-section">
    <div class="stats-section-label">Rendimiento por barbero</div>
    <div class="stats-grid-3">
        ${barbsComplete.map((b,i) => barberCard(b, maxBarbIng, i, barbsComplete)).join('')}
    </div>
</div>

<div class="stats-section">
    <div class="stats-section-label">Servicios &amp; conversión</div>
    <div class="stats-grid-2">
        ${buildServicesCard(svcs)}
        <div class="stats-card" style="display:flex;flex-direction:column;align-items:center;">
            <div class="stats-card-title" style="width:100%;">Tasa de aceptación</div>
            ${buildConversionRing(tasa, kpi)}
        </div>
    </div>
</div>

<div class="stats-section">
    <div class="stats-section-label">Patrones de demanda</div>
    <div class="stats-grid-2">
        <div class="stats-card">
            <div class="stats-card-title">Citas por día de la semana</div>
            ${buildBarChart(dow.map(d=>({label:d.label,value:d.count,tip:`${d.count} citas los ${d.label}`})), '#d42b2b')}
        </div>
        <div class="stats-card">
            <div class="stats-card-title">Franjas horarias más populares</div>
            <div class="horas-wrap">
                ${horas.map((h,i) => `
                <div class="hora-row">
                    <span class="hora-lbl">${h.hora_slot}</span>
                    <div class="hora-bar-outer">
                        <div class="hora-bar-fill" style="--h-w:${Math.round(+h.total/maxHora*100)}%;--h-delay:${i*0.08}s;">
                            <span class="hora-count">${h.total}</span>
                        </div>
                    </div>
                </div>`).join('')}
            </div>
        </div>
    </div>
</div>

<div class="stats-section">
    <div class="stats-section-label">Actividad últimos 30 días</div>
    <div class="stats-card">
        <div class="stats-card-title">Mapa de calor de reservas</div>
        ${buildHeatmap(hmap)}
    </div>
</div>`;

    document.getElementById('stats-content').innerHTML = html;

    // Guardar referencia a barberos para el modal
    window._statsBarberos = barbsComplete;

    setTimeout(() => {
        document.querySelectorAll('.kpi-card').forEach((card, i) => {
            setTimeout(() => {
                card.classList.add('visible');
                const valEl = card.querySelector('.kpi-value');
                const raw   = parseFloat(card.dataset.target || 0);
                const dec   = parseInt(card.dataset.dec || 0);
                const suf   = card.dataset.suffix || '';
                animNum(valEl, raw, dec, suf);
            }, i * 80);
        });
    }, 50);

    setTimeout(() => {
        document.querySelectorAll('.bar-fill').forEach(el => onVisible(el, () => el.classList.add('animated')));
        document.querySelectorAll('.line-path,.line-area,.line-dot').forEach(el => onVisible(el, () => el.classList.add('animated')));
        document.querySelectorAll('.progress-fill').forEach(el => onVisible(el, () => el.classList.add('animated')));
        document.querySelectorAll('.hora-bar-fill').forEach(el => onVisible(el, () => el.classList.add('animated')));
        document.querySelectorAll('.svc-stat-bar-fill').forEach(el => onVisible(el, () => el.classList.add('animated')));
        document.querySelectorAll('.conv-prog').forEach(el => onVisible(el, () => el.classList.add('animated')));
    }, 100);
}

function kpiCard(label, value, color, icon, suffix) {
    const dec = suffix.includes('€') ? 0 : 0;
    return `<div class="kpi-card" style="--kpi-accent:${color}" data-target="${+value}" data-dec="${dec}" data-suffix="${suffix}">
        <div class="kpi-badge">${icon}</div>
        <div class="kpi-label">${label}</div>
        <div class="kpi-value">0${suffix}</div>
    </div>`;
}

// FIX 1: tooltip con valor real
function buildBarChart(items, color) {
    const maxV = Math.max(...items.map(x => x.value), 1);
    return `<div class="bar-chart">
        ${items.map((item, i) => `
        <div class="bar-item" onmouseenter="showTip(event,'','${item.tip || item.value}')" onmouseleave="hideTip()">
            <div class="bar-fill" style="height:${Math.max(item.value/maxV*88,3)}px;background:${color};--bar-delay:${i*0.07}s;"></div>
            <span class="bar-label">${item.label}</span>
        </div>`).join('')}
    </div>`;
}

function buildLineChart(meses, field, unit) {
    const W = 500, H = 130, PAD = {t:12,r:10,b:30,l:42};
    const vals = meses.map(m => +m[field]);
    const maxV = Math.max(...vals, 1);
    const pts  = vals.map((v, i) => [
        PAD.l + (i / (vals.length - 1 || 1)) * (W - PAD.l - PAD.r),
        PAD.t + (1 - v / maxV) * (H - PAD.t - PAD.b)
    ]);
    const pathD = pts.map((p,i) => (i===0?`M${p[0]},${p[1]}`:`L${p[0]},${p[1]}`)).join(' ');
    const areaD = `${pathD} L${pts[pts.length-1][0]},${H-PAD.b} L${pts[0][0]},${H-PAD.b} Z`;
    const grids = [.25,.5,.75,1].map(f => {
        const yy = PAD.t + (1-f)*(H-PAD.t-PAD.b);
        const lbl= Math.round(maxV*f);
        return `<line class="line-grid" x1="${PAD.l}" x2="${W-PAD.r}" y1="${yy}" y2="${yy}"/>
                <text class="line-y-label" x="${PAD.l-4}" y="${yy+3}">${lbl>999?(lbl/1000).toFixed(1)+'k':lbl}</text>`;
    }).join('');
    const xlbls = meses.map((m,i) => {
        if (i%3!==0 && i!==meses.length-1) return '';
        return `<text class="line-x-label" x="${pts[i][0]}" y="${H-4}">${m.label}</text>`;
    }).join('');
    const dots = pts.map(([x,y],i) => `<circle class="line-dot" cx="${x}" cy="${y}" r="4"
        onmouseenter="showTip(event,'${unit}','${vals[i]}')" onmouseleave="hideTip()"/>`).join('');
    return `<svg class="line-chart-svg" viewBox="0 0 ${W} ${H}" preserveAspectRatio="xMidYMid meet">
        <defs><linearGradient id="lineGrad" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#d42b2b" stop-opacity=".25"/>
            <stop offset="100%" stop-color="#d42b2b" stop-opacity="0"/>
        </linearGradient></defs>
        ${grids}${xlbls}
        <path class="line-area" d="${areaD}"/>
        <path class="line-path" d="${pathD}"/>
        ${dots}
    </svg>`;
}

// FIX 4: conversión meta-items en fila horizontal
function buildConversionRing(tasa, kpi) {
    const dash = (tasa / 100) * 345;
    return `<div class="conversion-wrap">
        <div class="conversion-ring">
            <svg class="conv-svg" viewBox="0 0 120 120">
                <circle class="conv-track" cx="60" cy="60" r="55"/>
                <circle class="conv-prog" cx="60" cy="60" r="55" style="--conv-dash:${dash};"
                        onmouseenter="showTip(event,'tasa aceptación','${tasa}%')" onmouseleave="hideTip()"/>
            </svg>
            <div class="conv-center">
                <div class="conv-pct">${tasa}%</div>
                <div class="conv-sub">aceptadas</div>
            </div>
        </div>
        <div class="conversion-meta conversion-meta-row">
            <div class="conv-meta-item"><div class="conv-meta-num" style="color:#22c55e;">${kpi.aceptadas||0}</div><div class="conv-meta-lbl">Aceptadas</div></div>
            <div class="conv-meta-item"><div class="conv-meta-num" style="color:#f59e0b;">${kpi.pendientes||0}</div><div class="conv-meta-lbl">Pendientes</div></div>
            <div class="conv-meta-item"><div class="conv-meta-num" style="color:#d42b2b;">${kpi.denegadas||0}</div><div class="conv-meta-lbl">Denegadas</div></div>
            <div class="conv-meta-item"><div class="conv-meta-num">${kpi.total_reservas||0}</div><div class="conv-meta-lbl">Total</div></div>
        </div>
    </div>`;
}

// FIX 5: barberos con click → modal, incluye barberos sin citas
function barberCard(b, maxIng, i, allBarbers) {
    const pct = maxIng > 0 ? Math.round(+b.ingresos / maxIng * 100) : 0;
    const ticket = Number(+b.ingresos / Math.max(+b.aceptadas||+b.total_citas, 1)).toFixed(0);
    const isEmpty = +b.total_citas === 0;
    const colorMap = { EP:'#d42b2b', MV:'#2550a0', AR:'#c9a84c' };
    const accent = colorMap[b.iniciales] || '#d42b2b';

    return `<div class="barbero-stat-card" style="cursor:pointer;${isEmpty?'opacity:.65;':''}"
                 onclick="window._openBarberoModal('${b.iniciales}')"
                 title="Ver detalles de ${b.nombre}">
        ${isEmpty ? `<div style="position:absolute;top:.75rem;right:.75rem;
                        font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;
                        color:#7a7880;border:1px solid #252530;border-radius:100px;
                        padding:.2rem .5rem;">Sin citas</div>` : ''}
        <div class="barbero-stat-header">
            <div class="barbero-avatar-stat" style="background:linear-gradient(135deg,${accent}25,${accent}08);border-color:${accent}40;color:${accent};">${b.iniciales}</div>
            <div>
                <div class="barbero-stat-name">${b.nombre}</div>
                <div class="barbero-stat-sub">${b.total_citas} citas totales</div>
            </div>
            <div style="margin-left:auto;width:28px;height:28px;border-radius:50%;
                        background:rgba(245,240,232,.04);border:1px solid #252530;
                        display:flex;align-items:center;justify-content:center;
                        font-size:.7rem;color:#7a7880;flex-shrink:0;">→</div>
        </div>
        <div class="barbero-progress-wrap">
            <div class="barbero-progress-label"><span>Ingresos generados</span><span style="color:${accent};">${Number(b.ingresos).toFixed(0)} €</span></div>
            <div class="progress-track"><div class="progress-fill" style="--prog-w:${pct}%;--prog-delay:${.2+i*.15}s;background:linear-gradient(90deg,${accent},${accent}99);"></div></div>
        </div>
        <div class="barbero-kpi-row">
            <div class="barbero-kpi"><div class="barbero-kpi-num" style="color:#22c55e;">${b.aceptadas||0}</div><div class="barbero-kpi-lbl">Aceptadas</div></div>
            <div class="barbero-kpi"><div class="barbero-kpi-num" style="color:#f59e0b;">${b.pendientes||0}</div><div class="barbero-kpi-lbl">Pendientes</div></div>
            <div class="barbero-kpi"><div class="barbero-kpi-num" style="color:#c9a84c;">${isEmpty ? '—' : ticket+'€'}</div><div class="barbero-kpi-lbl">Ticket medio</div></div>
        </div>
    </div>`;
}

// FIX 3: Servicios separados en aceptadas y denegadas
function buildServicesCard(svcs) {
    if (!svcs || svcs.length === 0) {
        return `<div class="stats-card"><div class="stats-card-title">Servicios más solicitados</div>
            <div style="color:#7a7880;font-size:.82rem;text-align:center;padding:2rem;">Sin datos</div></div>`;
    }
    const maxTotal = Math.max(...svcs.map(s => +s.total), 1);

    // Separar en dos listas visuales
    return `<div class="stats-card">
        <div class="stats-card-title">Servicios más solicitados</div>

        <div style="margin-bottom:1rem;">
            <div style="font-size:.6rem;letter-spacing:.15em;text-transform:uppercase;
                        color:#22c55e;margin-bottom:.65rem;display:flex;align-items:center;gap:.4rem;">
                <span style="width:6px;height:6px;border-radius:50%;background:#22c55e;display:inline-block;"></span>
                Citas aceptadas
            </div>
            <div class="svc-stat-list">
                ${svcs.map((s,i) => svcItem(s, maxTotal, i, true)).join('')}
            </div>
        </div>

        <div style="border-top:1px solid #1c1c26;padding-top:1rem;">
            <div style="font-size:.6rem;letter-spacing:.15em;text-transform:uppercase;
                        color:#d42b2b;margin-bottom:.65rem;display:flex;align-items:center;gap:.4rem;">
                <span style="width:6px;height:6px;border-radius:50%;background:#d42b2b;display:inline-block;"></span>
                Citas denegadas / no confirmadas
            </div>
            <div class="svc-stat-list">
                ${svcs.map((s,i) => svcItemDenied(s, maxTotal, i)).join('')}
            </div>
        </div>
    </div>`;
}

function svcItem(s, maxSvc, i, accepted) {
    // accepted = mostrar campo ingresos (solo las aceptadas generan ingresos)
    const total = accepted ? (+s.total - (s.denegadas||0)) : +s.total; // Approx: usamos s.total para aceptadas
    // Nota: la API no separa aceptadas/denegadas por servicio, usamos total y ingresos como proxy
    const aceptadasEst = s.ingresos > 0 ? Math.round(+s.ingresos / +s.precio) : 0;
    const pct = Math.round(aceptadasEst / Math.max(+s.total, 1) * 100);
    const barPct = maxSvc > 0 ? Math.round(aceptadasEst / maxSvc * 100) : 0;

    if (aceptadasEst === 0) return `<div class="svc-stat-item" style="opacity:.4;">
        <div class="svc-stat-rank">${i+1}</div>
        <div class="svc-stat-info">
            <div class="svc-stat-name">${s.nombre}</div>
            <div class="svc-stat-bar"><div class="svc-stat-bar-fill" style="--svc-w:0%;--svc-delay:${i*.09}s;"></div></div>
        </div>
        <div class="svc-stat-meta">
            <div class="svc-stat-count">0 citas</div>
            <div class="svc-stat-euros">0 €</div>
        </div>
    </div>`;

    return `<div class="svc-stat-item">
        <div class="svc-stat-rank">${i+1}</div>
        <div class="svc-stat-info">
            <div class="svc-stat-name">${s.nombre}</div>
            <div class="svc-stat-bar"><div class="svc-stat-bar-fill" style="--svc-w:${barPct}%;--svc-delay:${i*.09}s;background:linear-gradient(90deg,#22c55e,#16a34a);"></div></div>
        </div>
        <div class="svc-stat-meta">
            <div class="svc-stat-count">${aceptadasEst} citas</div>
            <div class="svc-stat-euros" style="color:#22c55e;">${Number(s.ingresos).toFixed(0)} €</div>
        </div>
    </div>`;
}

function svcItemDenied(s, maxSvc, i) {
    // Citas sin ingreso = denegadas + pendientes sin confirmar
    const aceptadasEst = s.ingresos > 0 ? Math.round(+s.ingresos / +s.precio) : 0;
    const noAceptadas  = +s.total - aceptadasEst;
    const barPct       = noAceptadas > 0 ? Math.round(noAceptadas / maxSvc * 100) : 0;

    if (noAceptadas <= 0) return `<div class="svc-stat-item" style="opacity:.35;">
        <div class="svc-stat-rank" style="background:rgba(212,43,43,.05);border-color:rgba(212,43,43,.1);">${i+1}</div>
        <div class="svc-stat-info">
            <div class="svc-stat-name">${s.nombre}</div>
            <div class="svc-stat-bar" style="height:4px;background:#1c1c26;border-radius:2px;"></div>
        </div>
        <div class="svc-stat-meta">
            <div class="svc-stat-count">0 citas</div>
            <div class="svc-stat-euros" style="color:#7a7880;">—</div>
        </div>
    </div>`;

    return `<div class="svc-stat-item">
        <div class="svc-stat-rank" style="background:rgba(212,43,43,.08);border-color:rgba(212,43,43,.2);color:#d42b2b;">${i+1}</div>
        <div class="svc-stat-info">
            <div class="svc-stat-name">${s.nombre}</div>
            <div class="svc-stat-bar"><div class="svc-stat-bar-fill" style="--svc-w:${barPct}%;--svc-delay:${i*.09}s;background:linear-gradient(90deg,#d42b2b,#a81e1e);"></div></div>
        </div>
        <div class="svc-stat-meta">
            <div class="svc-stat-count">${noAceptadas} citas</div>
            <div class="svc-stat-euros" style="color:#d42b2b;">0 €</div>
        </div>
    </div>`;
}

// FIX 2: Heatmap rediseñado — más claro, con etiquetas de semana y día
function buildHeatmap(hmap) {
    const today = new Date();
    const map = {};
    hmap.forEach(h => { map[h.dia] = +h.total; });
    const maxV = Math.max(...Object.values(map), 1);

    // Construir 30 días agrupados en semanas (columnas) × días (filas L-D)
    // Empezar desde 29 días atrás
    const DAYS_LABEL = ['L','M','X','J','V','S','D'];
    // Llenar un array de 30 entradas con {iso, dow(0=lun..6=dom), v, level}
    const entries = [];
    for (let i = 29; i >= 0; i--) {
        const d = new Date(today);
        d.setDate(d.getDate() - i);
        const iso = d.toISOString().slice(0,10);
        const dow = (d.getDay() + 6) % 7; // 0=lun
        const v = map[iso] || 0;
        const level = v===0?0:v<=maxV*.25?1:v<=maxV*.5?2:v<=maxV*.75?3:4;
        entries.push({ iso, dow, v, level, date: d });
    }

    // Agrupar en semanas (columnas)
    // Primera semana: desde el primer día hasta el próximo lunes
    const weeks = [];
    let week = Array(7).fill(null);
    entries.forEach(entry => {
        week[entry.dow] = entry;
        if (entry.dow === 6) { weeks.push(week); week = Array(7).fill(null); }
    });
    // Última semana incompleta
    if (week.some(x => x !== null)) weeks.push(week);

    // Etiquetas de semana (fecha del lunes de cada columna)
    const weekLabels = weeks.map(w => {
        const first = w.find(x => x !== null);
        if (!first) return '';
        const d = first.date;
        // Buscar el lunes de esa semana
        const mon = new Date(d);
        mon.setDate(d.getDate() - first.dow);
        return mon.getDate() + '/' + String(mon.getMonth()+1).padStart(2,'0');
    });

    return `
    <div style="overflow-x:auto;padding-bottom:4px;">
        <div style="display:inline-flex;gap:0;min-width:max-content;">

            <!-- Etiquetas de día (L-D) -->
            <div style="display:flex;flex-direction:column;justify-content:flex-end;
                        margin-right:6px;padding-bottom:0;gap:3px;margin-top:20px;">
                ${DAYS_LABEL.map(l => `
                    <div style="height:20px;width:16px;display:flex;align-items:center;
                                justify-content:flex-end;font-size:.52rem;color:#4a4a5a;
                                font-family:'DM Sans',sans-serif;letter-spacing:.05em;">
                        ${l}
                    </div>`).join('')}
            </div>

            <!-- Columnas de semanas -->
            <div style="display:flex;gap:3px;align-items:flex-end;">
                ${weeks.map((w, wi) => `
                    <div style="display:flex;flex-direction:column;gap:0;">
                        <!-- Etiqueta de semana -->
                        <div style="height:18px;font-size:.52rem;color:#4a4a5a;
                                    font-family:'DM Sans',sans-serif;
                                    white-space:nowrap;margin-bottom:2px;
                                    display:flex;align-items:center;">
                            ${wi % 2 === 0 ? weekLabels[wi] : ''}
                        </div>
                        <!-- Celdas de días -->
                        ${Array(7).fill(0).map((_,di) => {
                            const cell = w[di];
                            if (!cell) return `<div style="width:20px;height:20px;margin-bottom:3px;opacity:0;"></div>`;
                            const colors = ['#0d0d14','rgba(212,43,43,.2)','rgba(212,43,43,.42)','rgba(212,43,43,.68)','#d42b2b'];
                            const borders = ['#1c1c26','rgba(212,43,43,.15)','rgba(212,43,43,.3)','rgba(212,43,43,.5)','rgba(212,43,43,.8)'];
                            return `<div title="${cell.iso}: ${cell.v} cita${cell.v!==1?'s':''}"
                                        onmouseenter="showTip(event,'',' ${cell.v} cita${cell.v!==1?'s':''} — ${cell.iso}')"
                                        onmouseleave="hideTip()"
                                        style="width:20px;height:20px;margin-bottom:3px;border-radius:4px;
                                               background:${colors[cell.level]};border:1px solid ${borders[cell.level]};
                                               transition:transform .15s,box-shadow .15s;cursor:default;"
                                        onmouseover="this.style.transform='scale(1.35)';this.style.zIndex='10';this.style.boxShadow='0 2px 8px rgba(0,0,0,.5)';"
                                        onmouseout="this.style.transform='';this.style.zIndex='';this.style.boxShadow='';"></div>`;
                        }).join('')}
                    </div>`).join('')}
            </div>
        </div>

        <!-- Leyenda -->
        <div style="display:flex;align-items:center;gap:.5rem;margin-top:.85rem;justify-content:flex-end;">
            <span style="font-size:.6rem;color:#4a4a5a;font-family:'DM Sans',sans-serif;">Menos</span>
            ${[0,1,2,3,4].map(l => {
                const cs = ['#0d0d14','rgba(212,43,43,.2)','rgba(212,43,43,.42)','rgba(212,43,43,.68)','#d42b2b'];
                const bs = ['#1c1c26','rgba(212,43,43,.15)','rgba(212,43,43,.3)','rgba(212,43,43,.5)','rgba(212,43,43,.8)'];
                return `<div style="width:14px;height:14px;border-radius:3px;background:${cs[l]};border:1px solid ${bs[l]};flex-shrink:0;"></div>`;
            }).join('')}
            <span style="font-size:.6rem;color:#4a4a5a;font-family:'DM Sans',sans-serif;">Más</span>
        </div>
    </div>`;
}

// Exponer función de abrir modal de barbero globalmente
window._openBarberoModal = function(iniciales) {
    const barbers = window._statsBarberos || [];
    const b = barbers.find(x => x.iniciales === iniciales);
    if (b) openBarberoModal(b, barbers);
};

})();
</script>

</body>
</html>