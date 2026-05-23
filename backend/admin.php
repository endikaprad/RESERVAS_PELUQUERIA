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
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=DM+Sans:wght@300;400;500&display=swap');
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{background:#09080f;color:#f0ece3;font-family:'DM Sans',sans-serif;min-height:100vh;}
        a{color:inherit;text-decoration:none;}

        /* ── HEADER ── */
        .admin-header{
            background:#111119;border-bottom:1px solid #252530;
            padding:.9rem 1.25rem;
            display:flex;align-items:center;justify-content:space-between;
            position:sticky;top:0;z-index:50;
        }
        .admin-brand{font-family:'Playfair Display',serif;font-size:1.1rem;font-style:italic;}
        .admin-brand span{color:#d42b2b;}
        .logout-btn{background:transparent;border:1px solid #252530;color:#7a7880;border-radius:4px;
            padding:.4rem .9rem;font-family:'DM Sans',sans-serif;font-size:.68rem;
            letter-spacing:.1em;text-transform:uppercase;cursor:pointer;transition:all .3s;}
        .logout-btn:hover{border-color:#d42b2b;color:#d42b2b;}

        /* ── BODY ── */
        .admin-body{padding:1rem;max-width:1300px;margin:0 auto;}

        /* ── ALERT ── */
        .alert-pendientes{
            background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.25);
            border-radius:10px;padding:.85rem 1.1rem;margin-bottom:1rem;
            display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;font-size:.85rem;
        }
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

        /* cada fila de filtro: label arriba, select abajo */
        .frow{display:flex;flex-direction:column;gap:.25rem;}
        .flabel{font-size:.6rem;letter-spacing:.12em;text-transform:uppercase;color:#7a7880;}

        select,input[type=date]{
            width:100%;background:#18181f;border:1px solid #252530;border-radius:6px;
            padding:.6rem .75rem;color:#f0ece3;font-family:'DM Sans',sans-serif;font-size:.9rem;
            -webkit-appearance:none;appearance:none;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%237a7880' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat:no-repeat;background-position:right .75rem center;
            padding-right:2rem;
        }
        select:focus,input[type=date]:focus{outline:none;border-color:#d42b2b;}

        .filter-submit{
            width:100%;background:#d42b2b;color:#fff;border:none;border-radius:6px;
            padding:.7rem 1rem;font-family:'DM Sans',sans-serif;font-size:.78rem;
            font-weight:600;letter-spacing:.15em;text-transform:uppercase;
            cursor:pointer;transition:background .3s;margin-top:.2rem;
        }
        .filter-submit:hover{background:#a81e1e;}

        @media(min-width:900px){
            .filters form  { flex-direction:row; align-items:flex-end; flex-wrap:wrap; gap:.75rem; }
            .frow          { flex-direction:row; align-items:center; flex:1; min-width:120px; gap:.5rem; }
            select,input[type=date] { width:100%; }
            .filter-submit { width:auto; padding:.6rem 1.25rem; flex-shrink:0; margin-top:0; align-self:flex-end; }
        }

        /* ── SECTION HEADER ── */
        .section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem;}
        .section-title-admin{font-family:'Playfair Display',serif;font-size:1.1rem;}
        .section-count{font-size:.72rem;color:#7a7880;}

        /* ════════════════════════════════════
           MOBILE CARDS  (< 900px — default)
           ════════════════════════════════════ */
        .reservas-cards{display:flex;flex-direction:column;gap:1rem;}

        .rc{
            background:#111119;
            border:1px solid #252530;
            border-left-width:3px;
            border-radius:12px;
            overflow:hidden;
        }
        .rc.pendiente{border-left-color:#f59e0b;}
        .rc.aceptada {border-left-color:#22c55e;}
        .rc.denegada {border-left-color:#d42b2b;opacity:.65;}

        /* top row */
        .rc-top{
            display:flex;align-items:flex-start;justify-content:space-between;
            padding:1rem 1rem .6rem;gap:.5rem;
        }
        .rc-top-left{}
        .rc-id{font-size:.65rem;color:#7a7880;margin-bottom:.15rem;}
        .rc-hora{font-family:'Playfair Display',serif;font-size:1.45rem;font-weight:700;color:#d42b2b;line-height:1;}
        .rc-fecha{font-size:.8rem;color:#a0a0b0;margin-top:.2rem;}

        /* estado badge */
        .ebadge{
            display:inline-flex;align-items:center;gap:.3rem;
            padding:.3rem .75rem;border-radius:100px;
            font-size:.7rem;font-weight:600;letter-spacing:.04em;white-space:nowrap;
            flex-shrink:0;
        }
        .ebadge-pendiente{background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3);color:#f59e0b;}
        .ebadge-aceptada {background:rgba(34,197,94,.12); border:1px solid rgba(34,197,94,.3); color:#22c55e;}
        .ebadge-denegada {background:rgba(212,43,43,.12); border:1px solid rgba(212,43,43,.3); color:#d42b2b;}

        /* divider */
        .rc-divider{height:1px;background:#1c1c26;margin:0 1rem;}

        /* body */
        .rc-body{padding:.85rem 1rem 1rem;display:flex;flex-direction:column;gap:.85rem;}

        .rc-cliente-name{font-size:1rem;font-weight:500;margin-bottom:.3rem;}
        .rc-cliente-meta{display:flex;flex-direction:column;gap:.2rem;}
        .rc-meta-item{font-size:.78rem;color:#7a7880;}

        .rc-details{display:grid;grid-template-columns:1fr 1fr;gap:.6rem 1rem;}
        .rc-detail{}
        .rc-detail-label{font-size:.6rem;letter-spacing:.12em;text-transform:uppercase;color:#7a7880;margin-bottom:.2rem;}
        .rc-detail-value{font-size:.88rem;color:#f0ece3;}
        .rc-detail-value.gold{color:#c9a84c;font-weight:500;}
        .rc-detail-sub{font-size:.72rem;color:#7a7880;}

        .rc-barbero-pill{
            display:inline-block;padding:.25rem .65rem;
            background:rgba(212,43,43,.08);border:1px solid rgba(212,43,43,.2);
            border-radius:100px;font-size:.75rem;color:#d42b2b;
        }

        .rc-notas{
            font-size:.78rem;color:#7a7880;font-style:italic;
            padding:.55rem .75rem;background:#0d0d14;
            border-radius:6px;border-left:2px solid #2a2a38;
        }

        /* action buttons */
        .rc-actions{
            display:flex;gap:.6rem;
            padding:.85rem 1rem;
            border-top:1px solid #1c1c26;
        }
        .btn-accept,.btn-deny{
            flex:1;display:flex;align-items:center;justify-content:center;gap:.4rem;
            padding:.7rem .5rem;border-radius:7px;
            font-family:'DM Sans',sans-serif;font-size:.78rem;font-weight:600;
            letter-spacing:.06em;text-transform:uppercase;
            cursor:pointer;text-decoration:none;transition:all .22s;
            border:1px solid transparent;
        }
        .btn-accept{background:rgba(34,197,94,.12);border-color:rgba(34,197,94,.35);color:#22c55e;}
        .btn-accept:hover,.btn-accept:active{background:#22c55e;color:#000;}
        .btn-deny{background:rgba(212,43,43,.1);border-color:rgba(212,43,43,.3);color:#d42b2b;}
        .btn-deny:hover,.btn-deny:active{background:#d42b2b;color:#fff;}

        /* ── EMPTY ── */
        .empty-state{background:#111119;border:1px solid #252530;border-radius:12px;padding:3.5rem 2rem;text-align:center;color:#7a7880;}
        .empty-icon{font-size:2.5rem;margin-bottom:.75rem;opacity:.3;}

        /* ════════════════════════════════════
           DESKTOP TABLE  (≥ 900px)
           ════════════════════════════════════ */
        .reservas-cards  { /* already flex column, stays as-is on mobile */ }
        .table-desktop   { display:none; }   /* hidden on mobile */

        @media (min-width: 900px) {
            .admin-header   { padding:1rem 2rem; }
            .admin-body     { padding:2rem; }
            .stats-row      { grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:1.5rem; }
            .stat-value     { font-size:2rem; }

            .filters form   { flex-direction:row; align-items:center; flex-wrap:wrap; gap:.75rem; }
            .frow           { flex-wrap:nowrap; }

            /* swap visibility */
            .reservas-cards { display:none !important; }
            .table-desktop  { display:block !important; }
        }

        /* ── DESKTOP TABLE STYLES ── */
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
        .tb-accept,.tb-deny{
            padding:.32rem .65rem;border-radius:4px;font-family:'DM Sans',sans-serif;
            font-size:.67rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;
            cursor:pointer;text-decoration:none;transition:all .2s;border:1px solid transparent;white-space:nowrap;
        }
        .tb-accept{background:rgba(34,197,94,.12);border-color:rgba(34,197,94,.3);color:#22c55e;}
        .tb-accept:hover{background:#22c55e;color:#000;}
        .tb-deny{background:rgba(212,43,43,.1);border-color:rgba(212,43,43,.25);color:#d42b2b;}
        .tb-deny:hover{background:#d42b2b;color:#fff;}
        .estado-badge{display:inline-flex;align-items:center;gap:.3rem;padding:.22rem .65rem;border-radius:100px;font-size:.68rem;font-weight:600;letter-spacing:.04em;white-space:nowrap;}
        .badge-pendiente{background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3);color:#f59e0b;}
        .badge-aceptada {background:rgba(34,197,94,.12); border:1px solid rgba(34,197,94,.3); color:#22c55e;}
        .badge-denegada {background:rgba(212,43,43,.12); border:1px solid rgba(212,43,43,.3); color:#d42b2b;}
    </style>
</head>
<body>

<div class="admin-header">
    <div class="admin-brand">Prado <span>Barber</span> · Admin</div>
    <form method="POST" style="margin:0;">
        <button class="logout-btn" name="logout" value="1">Cerrar sesión</button>
    </form>
</div>

<div class="admin-body">

    <?php if ($statsPend['total'] > 0): ?>
    <div class="alert-pendientes">
        <span style="font-size:1.1rem;">⏳</span>
        <span><strong><?= $statsPend['total'] ?> reserva<?= $statsPend['total']!=1?'s':'' ?> pendiente<?= $statsPend['total']!=1?'s':'' ?></strong> sin confirmar</span>
        <a href="?estado=pendiente&fecha=todas" class="alert-link">Ver →</a>
    </div>
    <?php endif; ?>

    <!-- STATS -->
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

    <!-- FILTERS -->
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

    <!-- SECTION HEADER -->
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
                <!-- Cliente -->
                <div>
                    <div class="rc-cliente-name"><?= htmlspecialchars($r['cliente_nombre']) ?></div>
                    <div class="rc-cliente-meta">
                        <span class="rc-meta-item">✉ <?= htmlspecialchars($r['cliente_email']) ?></span>
                        <span class="rc-meta-item">📞 <?= htmlspecialchars($r['cliente_telefono']) ?></span>
                    </div>
                </div>

                <!-- Details grid -->
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

</div>
</body>
</html>