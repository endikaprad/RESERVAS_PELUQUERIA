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
        .settings-btn:hover{border-color:#d42b2b;color:#d42b2b;transform:rotate(45deg);background:rgba(212,43,43,.06);}

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

        /* Mini calendario */
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
    </style>
</head>
<body>

<div class="admin-header">
    <div class="admin-brand">Prado <span>Barber</span> · Admin</div>
    <div class="header-actions">
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
                <div class="alcance-desc" id="alcance-desc">
                    Las reservas se aceptarán automáticamente sin límite de tiempo.
                </div>
            </div>

            <button class="cfg-save-btn" id="btn-save-auto" onclick="saveAutoAceptar()">
                Guardar configuración
            </button>
            <div class="cfg-status" id="auto-status"></div>

        </div>

        <!-- ── TAB: VACACIONES ── -->
        <div class="cfg-pane" id="pane-vac">

            <div class="cfg-section-label">Seleccionar días no disponibles</div>

            <div class="cal-legend">
                <div class="cal-legend-item">
                    <div class="cal-legend-dot blocked"></div>
                    <span>Ya bloqueado (clic para desbloquear)</span>
                </div>
                <div class="cal-legend-item">
                    <div class="cal-legend-dot pending"></div>
                    <span>Pendiente de guardar</span>
                </div>
                <div class="cal-legend-item">
                    <div class="cal-legend-dot unblocking"></div>
                    <span>Pendiente de desbloquear</span>
                </div>
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
                    <div class="mini-cal-day-label">L</div>
                    <div class="mini-cal-day-label">M</div>
                    <div class="mini-cal-day-label">X</div>
                    <div class="mini-cal-day-label">J</div>
                    <div class="mini-cal-day-label">V</div>
                    <div class="mini-cal-day-label">S</div>
                    <div class="mini-cal-day-label">D</div>
                </div>
                <div class="mini-cal-grid" id="mc-grid"></div>
            </div>

            <div class="range-hint" id="range-hint">
                📅 Modo rango: selecciona el <strong>primer día</strong> y luego el <strong>último</strong>.
            </div>

            <div class="vac-motivo-row">
                <input class="vac-motivo-input" id="vac-motivo"
                       type="text" placeholder="Motivo (ej: Vacaciones, Formación…)" maxlength="100">
            </div>

            <div class="vac-action-row">
                <button class="vac-btn vac-btn-range" id="btn-rango" onclick="toggleRangeMode()">
                    ⇔ Rango de días
                </button>
                <button class="vac-btn vac-btn-clear" onclick="clearPending()">
                    Limpiar selección
                </button>
            </div>

            <button class="cfg-save-days-btn" id="btn-save-days" onclick="saveDays()" disabled>
                Guardar días bloqueados
            </button>

            <div class="cfg-section-label" style="margin-top:1.25rem;">Días bloqueados actualmente</div>
            <div class="blocked-list" id="blocked-list"></div>
            <div class="cfg-status" id="vac-status"></div>

        </div>
    </div>
</div>


<!-- ================================================================
     CONFIG PANEL — JavaScript
     ================================================================ -->
<script>
const CFG_API = './api/settings.php';

let cfgState = {
    autoAceptar:      'no',
    autoAceptarHasta: '',
    diasBloqueados:   [],
};

let mcDate      = new Date();
let mcSelected  = null;
let rangeMode   = false;
let rangeStart  = null;

let pendingDays    = [];
let pendingUnblock = [];

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

function switchTab(tab) {
    document.querySelectorAll('.cfg-tab').forEach((t, i) => {
        t.classList.toggle('active', (i === 0 && tab === 'auto') || (i === 1 && tab === 'vac'));
    });
    document.getElementById('pane-auto').classList.toggle('active', tab === 'auto');
    document.getElementById('pane-vac').classList.toggle('active',  tab === 'vac');
}

async function loadSettings() {
    try {
        const r = await fetch(CFG_API);
        const j = await r.json();
        if (!j.ok) return;
        cfgState.autoAceptar      = j.data.auto_aceptar;
        cfgState.autoAceptarHasta = j.data.auto_aceptar_hasta;
        cfgState.diasBloqueados   = j.data.dias_bloqueados || [];
        pendingDays    = [];
        pendingUnblock = [];
        applyAutoState();
        renderBlockedList();
        renderMiniCal();
        updateSaveBtn();
    } catch (e) { console.warn('No se pudo cargar configuración:', e); }
}

function applyAutoState() {
    const v      = cfgState.autoAceptar;
    const toggle = document.getElementById('auto-toggle');
    const chip   = document.getElementById('auto-chip');
    const chipTxt= document.getElementById('auto-chip-text');
    const section= document.getElementById('alcance-section');
    const isOn   = v !== 'no';

    toggle.checked        = isOn;
    section.style.display = isOn ? 'block' : 'none';

    if (isOn) {
        chip.className = 'auto-estado-chip on';
        const labels   = { hoy:'Activo — solo hoy', semana:'Activo — esta semana', mes:'Activo — este mes', siempre:'Activo — siempre' };
        chipTxt.textContent = labels[v] || 'Activo';
        document.querySelectorAll('.alcance-btn').forEach(b => b.classList.toggle('selected', b.dataset.val === v));
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
        document.getElementById('auto-chip').className        = 'auto-estado-chip off';
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
    const btn  = document.getElementById('btn-save-auto');
    btn.disabled = true; btn.textContent = 'Guardando…';

    try {
        const r = await fetch(CFG_API, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
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
    btn.disabled = false; btn.textContent = 'Guardar configuración';
}

function renderMiniCal() {
    const grid  = document.getElementById('mc-grid');
    const title = document.getElementById('mc-title');
    if (!grid) return;

    const MONTHS = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    const y = mcDate.getFullYear();
    const m = mcDate.getMonth();
    title.textContent = `${MONTHS[m]} ${y}`;

    const today  = new Date(); today.setHours(0,0,0,0);
    const first  = new Date(y, m, 1).getDay();
    const offset = (first + 6) % 7;
    const daysInM= new Date(y, m+1, 0).getDate();

    const blockedFechas = cfgState.diasBloqueados.map(d => d.fecha);
    const pendingFechas = pendingDays.map(d => d.fecha);
    const unblockFechas = pendingUnblock;

    let html = '';
    for (let i = 0; i < offset; i++) html += `<div class="mini-cell mc-empty"></div>`;

    for (let d = 1; d <= daysInM; d++) {
        const dt   = new Date(y, m, d);
        const iso  = fmtDate(dt);
        const isPast   = dt < today;
        const isToday  = dt.getTime() === today.getTime();

        const isBlockedInDB = blockedFechas.includes(iso);
        const isPending     = pendingFechas.includes(iso);
        const isUnblocking  = unblockFechas.includes(iso);

        let cls = 'mini-cell';
        if (isPast)              cls += ' mc-disabled';
        else if (isPending)      cls += ' mc-pending';
        else if (isUnblocking)   cls += ' mc-unblocking';
        else if (isBlockedInDB)  cls += ' mc-blocked';
        else if (isToday)        cls += ' mc-today';

        const onclick = isPast ? '' : `onclick="mcSelectDay('${iso}')"`;
        let titleAttr = '';
        if (isPending)    titleAttr = `title="Pendiente de guardar — haz clic para quitar"`;
        if (isUnblocking) titleAttr = `title="Se desbloqueará al guardar — haz clic para cancelar"`;
        if (isBlockedInDB && !isUnblocking) {
            const mot = cfgState.diasBloqueados.find(x => x.fecha === iso)?.motivo || '';
            titleAttr = `title="${mot ? mot + ' — ' : ''}Haz clic para marcar como desbloquear"`;
        }

        html += `<div class="${cls}" ${onclick} ${titleAttr}>${d}</div>`;
    }
    grid.innerHTML = html;
}

function mcNav(dir) {
    mcDate.setMonth(mcDate.getMonth() + dir);
    renderMiniCal();
}

function mcSelectDay(iso) {
    const blockedFechas = cfgState.diasBloqueados.map(d => d.fecha);
    const pendingFechas = pendingDays.map(d => d.fecha);
    const unblockFechas = pendingUnblock;

    if (rangeMode) {
        if (!rangeStart) {
            rangeStart = iso;
            document.getElementById('range-hint').innerHTML =
                `📅 Rango activo: <strong>${iso}</strong> → selecciona el día final.`;
        } else {
            addRangeToPending(rangeStart, iso);
            rangeStart = null;
            toggleRangeMode();
        }
        renderMiniCal();
        updateSaveBtn();
        return;
    }

    if (pendingFechas.includes(iso)) {
        pendingDays.splice(pendingDays.findIndex(d => d.fecha === iso), 1);
    } else if (unblockFechas.includes(iso)) {
        pendingUnblock.splice(pendingUnblock.indexOf(iso), 1);
    } else if (blockedFechas.includes(iso)) {
        pendingUnblock.push(iso);
    } else {
        const motivo = document.getElementById('vac-motivo').value.trim() || 'Vacaciones';
        pendingDays.push({ fecha: iso, motivo });
    }

    renderMiniCal();
    updateSaveBtn();
}

function addRangeToPending(desde, hasta) {
    const [d, h] = desde <= hasta ? [desde, hasta] : [hasta, desde];
    const motivo = document.getElementById('vac-motivo').value.trim() || 'Vacaciones';
    const cur    = new Date(d + 'T00:00:00');
    const end    = new Date(h + 'T00:00:00');
    const existingFechas = [
        ...pendingDays.map(p => p.fecha),
        ...cfgState.diasBloqueados.map(b => b.fecha),
    ];
    while (cur <= end) {
        const iso = fmtDate(cur);
        if (!existingFechas.includes(iso)) pendingDays.push({ fecha: iso, motivo });
        cur.setDate(cur.getDate() + 1);
    }
}

function toggleRangeMode() {
    rangeMode  = !rangeMode;
    rangeStart = null;
    const hint = document.getElementById('range-hint');
    const btn  = document.getElementById('btn-rango');
    hint.classList.toggle('visible', rangeMode);
    btn.style.background = rangeMode ? 'rgba(201,168,76,.25)' : '';
    if (rangeMode) {
        hint.innerHTML = '📅 Modo rango: selecciona el <strong>primer día</strong> y luego el <strong>último</strong>.';
    }
    renderMiniCal();
}

function clearPending() {
    pendingDays    = [];
    pendingUnblock = [];
    rangeStart     = null;
    if (rangeMode) toggleRangeMode();
    renderMiniCal();
    updateSaveBtn();
}

function updateSaveBtn() {
    const btn   = document.getElementById('btn-save-days');
    const total = pendingDays.length + pendingUnblock.length;
    if (!btn) return;
    if (total === 0) {
        btn.textContent = 'Guardar días bloqueados';
        btn.disabled    = true;
    } else {
        const parts = [];
        if (pendingDays.length)    parts.push(`+${pendingDays.length} bloquear`);
        if (pendingUnblock.length) parts.push(`-${pendingUnblock.length} desbloquear`);
        btn.textContent = `Guardar (${parts.join(', ')})`;
        btn.disabled    = false;
    }
}

async function saveDays() {
    const btn = document.getElementById('btn-save-days');
    btn.disabled = true; btn.textContent = 'Guardando…';

    let errors = 0;

    for (const { fecha, motivo } of pendingDays) {
        try {
            const r = await fetch(CFG_API, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ accion: 'bloquear_dia', fecha, motivo }),
            });
            if (!(await r.json()).ok) errors++;
        } catch { errors++; }
    }

    for (const fecha of pendingUnblock) {
        try {
            const r = await fetch(CFG_API, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ accion: 'desbloquear_dia', fecha }),
            });
            if (!(await r.json()).ok) errors++;
        } catch { errors++; }
    }

    if (errors === 0) {
        showCfgStatus('vac-status', 'ok', '✓ Cambios guardados correctamente.');
    } else {
        showCfgStatus('vac-status', 'err', `⚠ ${errors} operación(es) fallaron. Reintenta.`);
    }

    await loadSettings();
}

async function desbloquearDia(fecha) {
    try {
        const r = await fetch(CFG_API, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'desbloquear_dia', fecha }),
        });
        const j = await r.json();
        if (j.ok) {
            showCfgStatus('vac-status', 'ok', `✓ Día ${fecha} desbloqueado.`);
            await loadSettings();
        } else {
            showCfgStatus('vac-status', 'err', '✕ ' + (j.error || 'Error.'));
        }
    } catch {
        showCfgStatus('vac-status', 'err', '✕ Sin conexión con el servidor.');
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
            <button class="blocked-del" onclick="desbloquearDia('${d.fecha}')" title="Desbloquear ahora">✕</button>
        </div>`;
    }).join('');
}

function fmtDate(d) {
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}
function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function showCfgStatus(id, type, msg) {
    const el = document.getElementById(id);
    if (!el) return;
    el.className   = `cfg-status ${type} visible`;
    el.textContent = msg;
    setTimeout(() => el.classList.remove('visible'), 4000);
}
</script>

</body>
</html>