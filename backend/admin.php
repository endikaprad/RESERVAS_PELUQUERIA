<?php
// ============================================================
//  PRADO BARBER CO. — Panel de administración
//  Acceso: /backend/admin.php
// ============================================================

// ── Autenticación simple ─────────────────────────────────────
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
    // ── LOGIN PAGE ───────────────────────────────────────────
    ?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Admin · Prado Barber Co.</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=DM+Sans:wght@300;400;500&display=swap');
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{background:#09080f;color:#f0ece3;font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;}
        .login-box{background:#111119;border:1px solid #252530;border-radius:16px;padding:3rem;width:100%;max-width:380px;}
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

// ── PANEL PRINCIPAL ──────────────────────────────────────────
require_once __DIR__ . '/config.php';

$db = getDB();

// Filtros
$filtroBarbero = $_GET['barbero'] ?? 'todos';
$filtroFecha   = $_GET['fecha']   ?? 'hoy';
$fechaCustom   = $_GET['fecha_custom'] ?? '';

$hoy = date('Y-m-d');

// Construir WHERE
$where  = 'WHERE 1=1';
$params = [];

if ($filtroBarbero !== 'todos') {
    $where .= ' AND r.barbero_id = ?';
    $params[] = $filtroBarbero;
}

if ($filtroFecha === 'hoy') {
    $where .= ' AND r.fecha = ?';
    $params[] = $hoy;
} elseif ($filtroFecha === 'semana') {
    $where .= ' AND r.fecha BETWEEN ? AND ?';
    $params[] = $hoy;
    $params[] = date('Y-m-d', strtotime('+7 days'));
} elseif ($filtroFecha === 'todas') {
    // sin filtro de fecha
} elseif ($filtroFecha === 'custom' && $fechaCustom) {
    $where .= ' AND r.fecha = ?';
    $params[] = $fechaCustom;
}

$stmt = $db->prepare("
    SELECT r.id, r.fecha, r.hora,
           r.cliente_nombre, r.cliente_telefono, r.cliente_email, r.notas,
           r.creado_en,
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

// Stats de hoy
$stmtHoy = $db->prepare("SELECT COUNT(*) as total, SUM(s.precio) as ingresos
    FROM reservas r JOIN servicios s ON s.id = r.servicio_id WHERE r.fecha = ?");
$stmtHoy->execute([$hoy]);
$statsHoy = $stmtHoy->fetch();

// Barberos para filtro
$barberos = $db->query('SELECT id, nombre FROM barberos ORDER BY nombre')->fetchAll();

$diasES = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
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

        /* HEADER */
        .admin-header{background:#111119;border-bottom:1px solid #252530;padding:1rem 2rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10;}
        .admin-brand{font-family:'Playfair Display',serif;font-size:1.25rem;font-style:italic;}
        .admin-brand span{color:#d42b2b;}
        .logout-btn{background:transparent;border:1px solid #252530;color:#7a7880;border-radius:4px;padding:.4rem 1rem;font-family:'DM Sans',sans-serif;font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;cursor:pointer;transition:all .3s;}
        .logout-btn:hover{border-color:#d42b2b;color:#d42b2b;}

        /* LAYOUT */
        .admin-body{max-width:1200px;margin:0 auto;padding:2rem;}

        /* STATS */
        .stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:2rem;}
        .stat-card{background:#111119;border:1px solid #252530;border-radius:12px;padding:1.5rem;}
        .stat-label{font-size:.68rem;letter-spacing:.2em;text-transform:uppercase;color:#7a7880;margin-bottom:.5rem;}
        .stat-value{font-family:'Playfair Display',serif;font-size:2rem;font-weight:700;color:#d42b2b;line-height:1;}
        .stat-sub{font-size:.75rem;color:#7a7880;margin-top:.25rem;}

        /* FILTERS */
        .filters{background:#111119;border:1px solid #252530;border-radius:12px;padding:1.25rem 1.5rem;margin-bottom:1.5rem;display:flex;gap:1rem;flex-wrap:wrap;align-items:center;}
        .filters form{display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;width:100%;}
        .filter-label{font-size:.68rem;letter-spacing:.15em;text-transform:uppercase;color:#7a7880;}
        select,input[type=date]{background:#18181f;border:1px solid #252530;border-radius:6px;padding:.5rem .75rem;color:#f0ece3;font-family:'DM Sans',sans-serif;font-size:.85rem;}
        select:focus,input[type=date]:focus{outline:none;border-color:#d42b2b;}
        .filter-btn{background:#d42b2b;color:#fff;border:none;border-radius:4px;padding:.5rem 1.25rem;font-family:'DM Sans',sans-serif;font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;cursor:pointer;}

        /* TABLE */
        .table-wrap{background:#111119;border:1px solid #252530;border-radius:12px;overflow:hidden;}
        .table-header{padding:1.25rem 1.5rem;border-bottom:1px solid #252530;display:flex;align-items:center;justify-content:space-between;}
        .table-title{font-family:'Playfair Display',serif;font-size:1.1rem;}
        .table-count{font-size:.78rem;color:#7a7880;}
        table{width:100%;border-collapse:collapse;}
        th{font-size:.65rem;letter-spacing:.2em;text-transform:uppercase;color:#7a7880;padding:.85rem 1.25rem;text-align:left;border-bottom:1px solid #252530;white-space:nowrap;}
        td{padding:.9rem 1.25rem;border-bottom:1px solid rgba(37,37,48,.5);font-size:.875rem;vertical-align:top;}
        tr:last-child td{border-bottom:none;}
        tr:hover td{background:rgba(37,37,48,.4);}
        .td-fecha{white-space:nowrap;}
        .td-hora{font-family:'Playfair Display',serif;font-size:1rem;color:#d42b2b;white-space:nowrap;}
        .td-cliente strong{display:block;font-weight:500;}
        .td-cliente span{font-size:.78rem;color:#7a7880;}
        .td-servicio{white-space:nowrap;}
        .td-precio{color:#c9a84c;font-weight:500;white-space:nowrap;}
        .td-barbero .b-badge{display:inline-block;padding:.2rem .6rem;background:rgba(212,43,43,.08);border:1px solid rgba(212,43,43,.2);border-radius:100px;font-size:.7rem;color:#d42b2b;}
        .td-notas{font-size:.78rem;color:#7a7880;max-width:180px;}
        .empty-state{padding:4rem;text-align:center;color:#7a7880;font-size:.9rem;}
        .empty-icon{font-size:2.5rem;margin-bottom:1rem;opacity:.3;}

        @media(max-width:768px){
            .admin-body{padding:1rem;}
            th,td{padding:.65rem .75rem;}
            .td-notas,.td-creado{display:none;}
        }
    </style>
</head>
<body>

<div class="admin-header">
    <div class="admin-brand">Prado <span>Barber</span> Co. · Admin</div>
    <form method="POST" style="margin:0;">
        <button class="logout-btn" name="logout" value="1">Cerrar sesión</button>
    </form>
</div>

<div class="admin-body">

    <!-- STATS HOY -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-label">Reservas hoy</div>
            <div class="stat-value"><?= $statsHoy['total'] ?? 0 ?></div>
            <div class="stat-sub"><?= date('d/m/Y') ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Ingresos hoy</div>
            <div class="stat-value"><?= number_format($statsHoy['ingresos'] ?? 0, 0) ?> €</div>
            <div class="stat-sub">estimado</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Mostrando</div>
            <div class="stat-value"><?= count($reservas) ?></div>
            <div class="stat-sub">reservas filtradas</div>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="filters">
        <form method="GET">
            <span class="filter-label">Barbero:</span>
            <select name="barbero">
                <option value="todos" <?= $filtroBarbero==='todos'?'selected':'' ?>>Todos</option>
                <?php foreach ($barberos as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $filtroBarbero===$b['id']?'selected':'' ?>>
                        <?= htmlspecialchars($b['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <span class="filter-label">Fecha:</span>
            <select name="fecha" onchange="this.form.submit()">
                <option value="hoy"    <?= $filtroFecha==='hoy'   ?'selected':'' ?>>Hoy</option>
                <option value="semana" <?= $filtroFecha==='semana'?'selected':'' ?>>Próximos 7 días</option>
                <option value="todas"  <?= $filtroFecha==='todas' ?'selected':'' ?>>Todas</option>
                <option value="custom" <?= $filtroFecha==='custom'?'selected':'' ?>>Fecha específica</option>
            </select>

            <?php if ($filtroFecha === 'custom'): ?>
                <input type="date" name="fecha_custom" value="<?= htmlspecialchars($fechaCustom) ?>"/>
            <?php endif; ?>

            <button type="submit" class="filter-btn">Filtrar</button>
        </form>
    </div>

    <!-- TABLE -->
    <div class="table-wrap">
        <div class="table-header">
            <div class="table-title">Reservas</div>
            <div class="table-count"><?= count($reservas) ?> resultado<?= count($reservas)!==1?'s':'' ?></div>
        </div>

        <?php if (empty($reservas)): ?>
            <div class="empty-state">
                <div class="empty-icon">📅</div>
                No hay reservas para los filtros seleccionados.
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Cliente</th>
                    <th>Servicio</th>
                    <th>Precio</th>
                    <th>Barbero</th>
                    <th>Notas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reservas as $r):
                    $dt = new DateTime($r['fecha']);
                    $diaNum = (int)$dt->format('w');
                    $mesNum = (int)$dt->format('n');
                    $fechaStr = $diasES[$diaNum] . ' ' . $dt->format('j') . ' ' . $mesesES[$mesNum];
                ?>
                <tr>
                    <td style="color:#7a7880;font-size:.78rem;">#<?= $r['id'] ?></td>
                    <td class="td-fecha"><?= $fechaStr ?></td>
                    <td class="td-hora"><?= substr($r['hora'],0,5) ?></td>
                    <td class="td-cliente">
                        <strong><?= htmlspecialchars($r['cliente_nombre']) ?></strong>
                        <span><?= htmlspecialchars($r['cliente_email']) ?></span>
                        <span><?= htmlspecialchars($r['cliente_telefono']) ?></span>
                    </td>
                    <td class="td-servicio"><?= htmlspecialchars($r['servicio']) ?><br>
                        <span style="font-size:.75rem;color:#7a7880;"><?= $r['duracion'] ?></span>
                    </td>
                    <td class="td-precio"><?= number_format($r['precio'],0) ?> €</td>
                    <td class="td-barbero">
                        <span class="b-badge"><?= htmlspecialchars($r['barbero']) ?></span>
                    </td>
                    <td class="td-notas"><?= $r['notas'] ? htmlspecialchars($r['notas']) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div><!-- /admin-body -->
</body>
</html>