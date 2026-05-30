<?php
// ============================================================
//  PRADO BARBER CO. — Panel de administración (Mobile-First)
// ============================================================

require_once __DIR__ . '/config.php';

if (!defined('ADMIN_USER') || !defined('ADMIN_PASS')) {
    die('Error de configuración: credenciales de administrador no definidas.');
}

session_start();

// Expirar sesión tras 30 minutos de inactividad
if (isset($_SESSION['admin'])) {
    $timeout = 1800;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_destroy();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    $_SESSION['last_activity'] = time();
}

if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_POST['user']) && isset($_POST['pass'])) {
    if ($_POST['user'] === ADMIN_USER && password_verify($_POST['pass'], ADMIN_PASS)) {
        $_SESSION['admin'] = true;
        $_SESSION['last_activity'] = time();
    } else {
        $loginError = true;
    }
}

if (!isset($_SESSION['admin'])) {
?>
    <!DOCTYPE html>
    <html lang="es">

    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="theme-color" content="#111119">
        <link rel="icon" type="image/png" href="../img/admin.png">
        <title>Admin · Prado Barber Co.</title>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=DM+Sans:wght@300;400;500&display=swap');

            *,
            *::before,
            *::after {
                box-sizing: border-box;
                margin: 0;
                padding: 0
            }

            body {
                background: #09080f;
                color: #f0ece3;
                font-family: 'DM Sans', sans-serif;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1rem;
            }

            /* Admin login bg: cool navy control-room atmosphere */
            body::before {
                content: '';
                position: fixed;
                inset: 0;
                z-index: 0;
                background:
                    radial-gradient(ellipse 75% 60% at 50% 0%,   rgba(20,45,100,0.40) 0%, transparent 65%),
                    radial-gradient(ellipse 55% 45% at 0%  100%,  rgba(15,35,85,0.30)  0%, transparent 60%),
                    radial-gradient(ellipse 45% 40% at 100% 80%,  rgba(25,55,120,0.22) 0%, transparent 58%),
                    #070810;
                pointer-events: none;
            }

            /* Subtle tech grid overlay */
            body::after {
                content: '';
                position: fixed;
                inset: 0;
                z-index: 0;
                background-image:
                    linear-gradient(rgba(37,80,160,0.06) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(37,80,160,0.06) 1px, transparent 1px);
                background-size: 48px 48px;
                mask-image: radial-gradient(ellipse 90% 90% at 50% 50%, black 0%, transparent 80%);
                -webkit-mask-image: radial-gradient(ellipse 90% 90% at 50% 50%, black 0%, transparent 80%);
                pointer-events: none;
            }

            .login-box {
                position: relative;
                z-index: 1;
            }

            .login-box {
                background: #111119;
                border: 1px solid #252530;
                border-radius: 16px;
                padding: 2.5rem 2rem;
                width: 100%;
                max-width: 380px;
            }

            .login-title {
                font-family: 'Playfair Display', serif;
                font-size: 1.75rem;
                font-weight: 700;
                margin-bottom: .25rem;
            }

            .login-sub {
                color: #7a7880;
                font-size: .85rem;
                margin-bottom: 2rem;
            }

            label {
                display: block;
                font-size: .7rem;
                letter-spacing: .15em;
                text-transform: uppercase;
                color: #7a7880;
                margin-bottom: .4rem;
            }

            input {
                width: 100%;
                background: #18181f;
                border: 1px solid #252530;
                border-radius: 8px;
                padding: .85rem 1rem;
                color: #f0ece3;
                font-family: 'DM Sans', sans-serif;
                font-size: .9rem;
                margin-bottom: 1.25rem;
                transition: border-color .3s;
            }

            input:focus {
                outline: none;
                border-color: #d42b2b;
            }

            button {
                width: 100%;
                background: #d42b2b;
                color: #fff;
                border: none;
                border-radius: 4px;
                padding: 1rem;
                font-family: 'DM Sans', sans-serif;
                font-size: .78rem;
                font-weight: 600;
                letter-spacing: .18em;
                text-transform: uppercase;
                cursor: pointer;
                transition: background .3s;
            }

            button:hover {
                background: #a81e1e;
            }

            .error {
                color: #d42b2b;
                font-size: .82rem;
                margin-bottom: 1rem;
                padding: .75rem;
                background: rgba(212, 43, 43, .08);
                border-radius: 8px;
                border: 1px solid rgba(212, 43, 43, .2);
            }

            .brand {
                font-family: 'Playfair Display', serif;
                font-size: 1rem;
                font-style: italic;
                color: #7a7880;
                margin-bottom: 2rem;
            }
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
                <input type="text" name="user" autocomplete="username" required />
                <label>Contraseña</label>
                <input type="password" name="pass" autocomplete="current-password" required />
                <button type="submit">Entrar</button>
            </form>
        </div>
    </body>

    </html>
<?php
    exit;
}

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

if ($filtroBarbero !== 'todos') {
    $where .= ' AND r.barbero_id = ?';
    $params[] = $filtroBarbero;
}
if ($filtroEstado  !== 'todos') {
    $where .= ' AND r.estado = ?';
    $params[] = $filtroEstado;
}

if ($filtroFecha === 'hoy') {
    $where .= ' AND r.fecha = ?';
    $params[] = $hoy;
} elseif ($filtroFecha === 'manana') {
    $where .= ' AND r.fecha = ?';
    $params[] = date('Y-m-d', strtotime('+1 day'));
} elseif ($filtroFecha === 'semana') {
    $where .= ' AND r.fecha BETWEEN ? AND ?';
    $params[] = $hoy;
    $params[] = date('Y-m-d', strtotime('+7 days'));
} elseif ($filtroFecha === 'pasadas') {
    $where .= ' AND r.fecha < ?';
    $params[] = $hoy;
} elseif ($filtroFecha === 'proximas') {
    $where .= ' AND r.fecha > ?';
    $params[] = $hoy;
} elseif ($filtroFecha === 'custom' && $fechaCustom) {
    $where .= ' AND r.fecha = ?';
    $params[] = $fechaCustom;
}

$stmt = $db->prepare("
    SELECT r.id, r.fecha, r.hora,
           r.cliente_nombre, r.cliente_telefono, r.cliente_email, r.notas,
           r.estado, r.token, r.creado_en,
           r.barbero_id,
           COALESCE(r.ronda_negociacion, 0) AS ronda_negociacion,
           COALESCE(DATE_FORMAT(r.nueva_fecha_propuesta, '%Y-%m-%d'), '') AS nueva_fecha_propuesta,
           COALESCE(TIME_FORMAT(r.nueva_hora_propuesta, '%H:%i'), '') AS nueva_hora_propuesta,
           COALESCE(r.motivo_cambio, '') AS motivo_cambio,
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

$diasES  = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$mesesES = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin · Prado Barber Co.</title>
    <link rel="icon" type="image/png" href="../img/admin.png">
    <meta name="theme-color" content="#111119">

    <script src="../assets/js/admin-datos.js?v=<?= filemtime(__DIR__.'/../assets/js/admin-datos.js') ?>" defer></script>
    <script src="../assets/js/admin-reserva-detail.js?v=<?= filemtime(__DIR__.'/../assets/js/admin-reserva-detail.js') ?>" defer></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=DM+Sans:wght@300;400;500&display=swap');

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        body {
            background: #09080f;
            color: #f0ece3;
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
        }

        /* Admin panel bg: cool navy control-room atmosphere */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: -2;
            background:
                radial-gradient(ellipse 75% 60% at 50% 0%,   rgba(20,45,100,0.40) 0%, transparent 65%),
                radial-gradient(ellipse 55% 45% at 0%  100%,  rgba(15,35,85,0.30)  0%, transparent 60%),
                radial-gradient(ellipse 45% 40% at 100% 80%,  rgba(25,55,120,0.22) 0%, transparent 58%),
                #070810;
            pointer-events: none;
        }

        /* Subtle tech grid overlay */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            z-index: -1;
            background-image:
                linear-gradient(rgba(37,80,160,0.06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(37,80,160,0.06) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: radial-gradient(ellipse 90% 90% at 50% 50%, black 0%, transparent 80%);
            -webkit-mask-image: radial-gradient(ellipse 90% 90% at 50% 50%, black 0%, transparent 80%);
            pointer-events: none;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        /* ── HEADER ── */
        .admin-header {
            background: rgb(7 8 16 / 49%);
            backdrop-filter: blur(18px) saturate(1.4);
            -webkit-backdrop-filter: blur(18px) saturate(1.4);
            border-bottom: 1px solid rgba(37,80,160,0.18);
            padding: .9rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .admin-brand {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            font-style: italic;
        }

        .admin-brand span {
            color: #d42b2b;
        }

        .logout-btn {
            background: transparent;
            border: 1px solid #252530;
            color: #7a7880;
            border-radius: 4px;
            padding: .4rem .9rem;
            font-family: 'DM Sans', sans-serif;
            font-size: .68rem;
            letter-spacing: .1em;
            text-transform: uppercase;
            cursor: pointer;
            transition: all .3s;
        }

        .logout-btn:hover {
            border-color: #d42b2b;
            color: #d42b2b;
        }

        .home-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background: transparent;
            border: 1px solid #252530;
            border-radius: 4px;
            cursor: pointer;
            transition: all .3s;
            text-decoration: none;
        }

        .home-btn:hover {
            border-color: #c9a84c;
            background: rgba(201,168,76,.1);
        }

        .home-btn:hover svg path {
            stroke: #c9a84c;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: .6rem;
        }

        .settings-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: transparent;
            border: 1px solid #252530;
            color: #7a7880;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1rem;
            transition: all .3s;
            flex-shrink: 0;
        }

        .settings-btn:hover {
            border-color: #d42b2b;
            color: #d42b2b;
            background: rgba(212, 43, 43, .06);
        }

        .stats-trigger-btn {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(145deg, #d42b2b 0%, #8b1515 100%);
            border: none;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all .3s cubic-bezier(0.34, 1.56, 0.64, 1);
            flex-shrink: 0;
            box-shadow: 0 3px 10px rgba(212, 43, 43, .35), inset 0 1px 0 rgba(255,255,255,.12);
            position: relative;
            overflow: hidden;
        }
        .stats-trigger-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(145deg, rgba(255,255,255,.10) 0%, transparent 60%);
            border-radius: inherit;
        }

        .stats-trigger-btn:hover {
            transform: scale(1.08) translateY(-1px);
            box-shadow: 0 6px 18px rgba(212, 43, 43, .50), inset 0 1px 0 rgba(255,255,255,.15);
        }

        /* ── BODY ── */
        .admin-body {
            padding: 1rem;
            max-width: 1300px;
            margin: 0 auto;
        }

        /* ── ALERT ── */
        .alert-pendientes {
            background: rgba(245, 158, 11, .08);
            border: 1px solid rgba(245, 158, 11, .25);
            border-radius: 10px;
            padding: .85rem 1.1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: .75rem;
            flex-wrap: wrap;
            font-size: .85rem;
        }

        .alert-pendientes strong {
            color: #f59e0b;
        }

        .alert-link {
            margin-left: auto;
            color: #f59e0b;
            font-size: .72rem;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        /* ── STATS ── */
        .stats-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .75rem;
            margin-bottom: 1rem;
        }

        .stat-card {
            background: #111119;
            border: 1px solid #252530;
            border-radius: 12px;
            padding: 1rem 1.1rem;
            transition: box-shadow .25s, transform .25s cubic-bezier(.16,1,.3,1), border-color .25s;
        }

        .stat-card:has(.stat-value:not(.gold):not(.orange)):hover {
            transform: translateY(-2px);
            border-color: rgba(212,43,43,.5);
        }
        .stat-card:has(.stat-value.gold):hover {
            transform: translateY(-2px);
            border-color: rgba(201,168,76,.5);
        }
        .stat-card:has(.stat-value.orange):hover {
            transform: translateY(-2px);
            border-color: rgba(245,158,11,.5);
        }

        .stat-label {
            font-size: .6rem;
            letter-spacing: .2em;
            text-transform: uppercase;
            color: #7a7880;
            margin-bottom: .3rem;
        }

        .stat-value {
            font-family: 'Playfair Display', serif;
            font-size: 1.75rem;
            font-weight: 700;
            color: #d42b2b;
            line-height: 1;
        }

        .stat-value.gold {
            color: #c9a84c;
        }

        .stat-value.orange {
            color: #f59e0b;
        }

        .stat-sub {
            font-size: .65rem;
            color: #7a7880;
            margin-top: .2rem;
            transform: translateY(5px);
        }

        /* ── FILTERS ── */
        .filters {
            background: #111119;
            border: 1px solid #252530;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .filters-label {
            font-size: .62rem;
            letter-spacing: .2em;
            text-transform: uppercase;
            color: #7a7880;
            margin-bottom: .75rem;
        }

        .filters form {
            display: flex;
            flex-direction: column;
            gap: .6rem;
        }

        .frow {
            display: flex;
            flex-direction: column;
            gap: .25rem;
        }

        .flabel {
            font-size: .6rem;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #7a7880;
        }

        select,
        input[type=date] {
            width: 100%;
            background: #18181f;
            border: 1px solid #252530;
            border-radius: 6px;
            padding: .6rem .75rem;
            color: #f0ece3;
            font-family: 'DM Sans', sans-serif;
            font-size: .9rem;
            -webkit-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%237a7880' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right .75rem center;
            padding-right: 2rem;
        }

        select:focus,
        input[type=date]:focus {
            outline: none;
            border-color: #d42b2b;
        }

        /* ── Admin Date Picker ── */
        .adp-wrap { position: relative; display: inline-block; width: 100%; }
        .adp-trigger {
            width: 100%; background: #18181f; border: 1px solid #252530;
            border-radius: 6px; color: #f0ece3; padding: .6rem .75rem;
            cursor: pointer; font-family: 'DM Sans', sans-serif; font-size: .9rem;
            display: flex; align-items: center; gap: .5rem; text-align: left;
            transition: border-color .2s; box-sizing: border-box;
        }
        .adp-trigger:hover, .adp-trigger:focus { outline: none; border-color: #d42b2b; }
        .adp-trigger-text { flex: 1; }
        .adp-trigger svg { flex-shrink: 0; opacity: .5; }
        .adp-popover {
            position: absolute; top: calc(100% + 6px); left: 0; z-index: 9999;
            background: #111119; border: 1px solid #252530; border-radius: 14px;
            padding: 1.1rem; min-width: 256px;
            box-shadow: 0 20px 60px rgba(0,0,0,.8);
            display: none;
        }
        .adp-popover.open { display: block; }
        .adp-hdr { display: flex; align-items: center; justify-content: space-between; margin-bottom: .9rem; }
        .adp-title { font-weight: 700; font-size: .95rem; color: #e8e8f0; letter-spacing: .01em; }
        .adp-nav { display: flex; gap: .3rem; }
        .adp-nav button {
            width: 26px; height: 26px; border: 1px solid #252530; border-radius: 7px;
            color: #777; background: none; display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 1rem; transition: .2s; line-height: 1;
        }
        .adp-nav button:hover { border-color: #c9a84c; color: #c9a84c; }
        .adp-labels { display: grid; grid-template-columns: repeat(7,1fr); gap: 2px; margin-bottom: .3rem; }
        .adp-dlabel { text-align: center; font-size: .58rem; letter-spacing: .1em; text-transform: uppercase; color: #555; padding: .15rem 0; }
        .adp-grid { display: grid; grid-template-columns: repeat(7,1fr); gap: 2px; }
        .adp-cell {
            aspect-ratio: 1; display: flex; align-items: center; justify-content: center;
            border-radius: 8px; font-size: .8rem; cursor: pointer; border: 1px solid transparent;
            transition: .15s; color: #c8c4d8;
        }
        .adp-cell:hover:not(.adp-empty):not(.adp-dis) { border-color: rgba(201,168,76,.4); color: #c9a84c; }
        .adp-cell.adp-sel { background: #c9a84c; color: #000; font-weight: 600; border-color: transparent; }
        .adp-cell.adp-today:not(.adp-sel) { border-color: #d42b2b; color: #d42b2b; }
        .adp-cell.adp-dis { color: #2a2a38; cursor: default; }
        .adp-cell.adp-empty { cursor: default; }

        .filter-submit {
            width: 100%;
            background: #d42b2b;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: .7rem 1rem;
            font-family: 'DM Sans', sans-serif;
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .15em;
            text-transform: uppercase;
            cursor: pointer;
            transition: background .3s;
            margin-top: .2rem;
        }

        .filter-submit:hover {
            background: #a81e1e;
        }

        @media(min-width:900px) {
            .filters form {
                flex-direction: row;
                align-items: flex-end;
                flex-wrap: wrap;
                gap: .75rem;
            }

            .frow {
                flex-direction: row;
                align-items: center;
                flex: 1;
                min-width: 120px;
                gap: .5rem;
            }

            select,
            input[type=date] {
                width: 100%;
            }

            .filter-submit {
                width: auto;
                padding: .6rem 1.25rem;
                flex-shrink: 0;
                margin-top: 0;
                align-self: flex-end;
            }
        }

        /* ── SECTION HEADER ── */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: .75rem;
        }

        .section-title-admin {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
        }

        .section-count {
            font-size: .72rem;
            color: #7a7880;
        }

        /* ── MOBILE CARDS ── */
        .reservas-cards {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .rc {
            background: #111119;
            border: 1px solid #252530;
            border-left-width: 3px;
            border-radius: 12px;
            overflow: hidden;
        }

        .rc.pendiente {
            border-left-color: #f59e0b;
        }

        .rc.aceptada {
            border-left-color: #22c55e;
        }

        .rc.denegada {
            border-left-color: #d42b2b;
            opacity: .65;
        }

        .rc.cancelada {
            border-left-color: #6b7280;
            opacity: .65;
        }

        .rc-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            padding: 1rem 1rem .6rem;
            gap: .5rem;
        }

        .rc-id {
            font-size: .65rem;
            color: #7a7880;
            margin-bottom: .15rem;
        }

        .rc-hora {
            font-family: 'Playfair Display', serif;
            font-size: 1.45rem;
            font-weight: 700;
            color: #d42b2b;
            line-height: 1;
        }

        .rc-fecha {
            font-size: .8rem;
            color: #a0a0b0;
            margin-top: .2rem;
        }

        .ebadge {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            padding: .3rem .75rem;
            border-radius: 100px;
            font-size: .7rem;
            font-weight: 600;
            letter-spacing: .04em;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .ebadge-pendiente {
            background: rgba(245, 158, 11, .12);
            border: 1px solid rgba(245, 158, 11, .3);
            color: #f59e0b;
        }

        .ebadge-aceptada {
            background: rgba(34, 197, 94, .12);
            border: 1px solid rgba(34, 197, 94, .3);
            color: #22c55e;
        }

        .ebadge-denegada {
            background: rgba(212, 43, 43, .12);
            border: 1px solid rgba(212, 43, 43, .3);
            color: #d42b2b;
        }

        .ebadge-cancelada {
            background: rgba(107, 114, 128, .12);
            border: 1px solid rgba(107, 114, 128, .3);
            color: #9ca3af;
        }

        .rc-divider {
            height: 1px;
            background: #1c1c26;
            margin: 0 1rem;
        }

        .rc-body {
            padding: .85rem 1rem 1rem;
            display: flex;
            flex-direction: column;
            gap: .85rem;
        }

        .rc-cliente-name {
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: .3rem;
        }

        .rc-cliente-meta {
            display: flex;
            flex-direction: column;
            gap: .2rem;
        }

        .rc-meta-item {
            font-size: .78rem;
            color: #7a7880;
        }

        .rc-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .6rem 1rem;
        }

        .rc-detail-label {
            font-size: .6rem;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #7a7880;
            margin-bottom: .2rem;
        }

        .rc-detail-value {
            font-size: .88rem;
            color: #f0ece3;
        }

        .rc-detail-value.gold {
            color: #c9a84c;
            font-weight: 500;
        }

        .rc-detail-sub {
            font-size: .72rem;
            color: #7a7880;
        }

        .rc-barbero-pill {
            display: inline-block;
            padding: .25rem .65rem;
            background: rgba(212, 43, 43, .08);
            border: 1px solid rgba(212, 43, 43, .2);
            border-radius: 100px;
            font-size: .75rem;
            color: #d42b2b;
        }

        .rc-notas {
            font-size: .78rem;
            color: #7a7880;
            font-style: italic;
            padding: .55rem .75rem;
            background: #0d0d14;
            border-radius: 6px;
            border-left: 2px solid #2a2a38;
        }

        .rc-actions {
            display: flex;
            gap: .6rem;
            padding: .85rem 1rem;
            border-top: 1px solid #1c1c26;
        }

        .btn-accept,
        .btn-deny {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            padding: .7rem .5rem;
            border-radius: 7px;
            font-family: 'DM Sans', sans-serif;
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            cursor: pointer;
            text-decoration: none;
            transition: all .22s;
            border: 1px solid transparent;
        }

        .btn-accept {
            background: rgba(34, 197, 94, .12);
            border-color: rgba(34, 197, 94, .35);
            color: #22c55e;
        }

        .btn-accept:hover,
        .btn-accept:active {
            background: #22c55e;
            color: #000;
        }

        .btn-deny {
            background: rgba(212, 43, 43, .1);
            border-color: rgba(212, 43, 43, .3);
            color: #d42b2b;
        }

        .btn-deny:hover,
        .btn-deny:active {
            background: #d42b2b;
            color: #fff;
        }

        /* ── EMPTY ── */
        .empty-state {
            background: #111119;
            border: 1px solid #252530;
            border-radius: 12px;
            padding: 3.5rem 2rem;
            text-align: center;
            color: #7a7880;
        }

        .empty-icon {
            font-size: 2.5rem;
            margin-bottom: .75rem;
            opacity: .3;
        }

        .table-desktop {
            display: none;
        }

        @media(min-width:900px) {
            .section-header {
                display: none;
            }

            .admin-header {
                padding: 1rem 2rem;
            }

            .admin-body {
                padding: 2rem;
            }

            .stats-row {
                grid-template-columns: repeat(4, 1fr);
                gap: 1rem;
                margin-bottom: 1.5rem;
            }

            .stat-value {
                font-size: 2rem;
            }

            .filters form {
                flex-direction: row;
                align-items: center;
                flex-wrap: wrap;
                gap: .75rem;
            }

            .frow {
                flex-wrap: nowrap;
            }

            .reservas-cards {
                display: none !important;
            }

            .table-desktop {
                display: block !important;
            }
        }

        /* ── DESKTOP TABLE ── */
        .table-wrap-d {
            background: #111119;
            border: 1px solid #252530;
            border-radius: 12px;
            overflow: hidden;
        }

        .table-header-d {
            padding: 1.1rem 1.5rem;
            border-bottom: 1px solid #252530;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-title-d {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            font-size: .63rem;
            letter-spacing: .2em;
            text-transform: uppercase;
            color: #7a7880;
            padding: .8rem 1.1rem;
            text-align: left;
            border-bottom: 1px solid #252530;
            white-space: nowrap;
        }

        td {
            padding: .85rem 1.1rem;
            border-bottom: 1px solid rgba(37, 37, 48, .5);
            font-size: .875rem;
            vertical-align: top;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: rgba(37, 37, 48, .4);
        }

        .td-hora {
            font-family: 'Playfair Display', serif;
            font-size: 1rem;
            color: #d42b2b;
            white-space: nowrap;
        }

        .td-cliente strong {
            display: block;
            font-weight: 500;
        }

        .td-cliente span {
            font-size: .75rem;
            color: #7a7880;
            display: block;
        }

        .td-precio {
            color: #c9a84c;
            font-weight: 500;
            white-space: nowrap;
        }

        .td-barbero .b-badge {
            display: inline-block;
            padding: .2rem .6rem;
            background: rgba(212, 43, 43, .08);
            border: 1px solid rgba(212, 43, 43, .2);
            border-radius: 100px;
            font-size: .7rem;
            color: #d42b2b;
        }

        .td-notas {
            font-size: .75rem;
            color: #7a7880;
            max-width: 140px;
        }

        .action-btns {
            display: flex;
            gap: .4rem;
        }

        .tb-accept,
        .tb-deny {
            padding: .32rem .65rem;
            border-radius: 4px;
            font-family: 'DM Sans', sans-serif;
            font-size: .67rem;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
            cursor: pointer;
            text-decoration: none;
            transition: all .2s;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .tb-accept {
            background: rgba(34, 197, 94, .12);
            border-color: rgba(34, 197, 94, .3);
            color: #22c55e;
        }

        .tb-accept:hover {
            background: #22c55e;
            color: #000;
        }

        .tb-deny {
            background: rgba(212, 43, 43, .1);
            border-color: rgba(212, 43, 43, .25);
            color: #d42b2b;
        }

        .tb-deny:hover {
            background: #d42b2b;
            color: #fff;
        }

        .estado-badge {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            padding: .22rem .65rem;
            border-radius: 100px;
            font-size: .68rem;
            font-weight: 600;
            letter-spacing: .04em;
            white-space: nowrap;
        }

        .badge-pendiente {
            background: rgba(245, 158, 11, .12);
            border: 1px solid rgba(245, 158, 11, .3);
            color: #f59e0b;
        }

        .badge-aceptada {
            background: rgba(34, 197, 94, .12);
            border: 1px solid rgba(34, 197, 94, .3);
            color: #22c55e;
        }

        .badge-denegada {
            background: rgba(212, 43, 43, .12);
            border: 1px solid rgba(212, 43, 43, .3);
            color: #d42b2b;
        }

        .badge-cancelada {
            background: rgba(107, 114, 128, .12);
            border: 1px solid rgba(107, 114, 128, .3);
            color: #9ca3af;
        }

        /* ================================================================
           CONFIG PANEL
        ================================================================ */
        .cfg-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .75);
            backdrop-filter: blur(6px);
            z-index: 999;
            opacity: 0;
            pointer-events: none;
            transition: opacity .3s ease;
        }

        .cfg-overlay.open {
            opacity: 1;
            pointer-events: all;
        }

        .cfg-panel {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            width: min(480px, 100vw);
            background: #111119;
            border-left: 1px solid #252530;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            transform: translateX(100%);
            transition: transform .38s cubic-bezier(.16, 1, .3, 1);
            overflow: hidden;
        }

        .cfg-panel.open {
            transform: translateX(0);
        }

        .cfg-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #252530;
            flex-shrink: 0;
        }

        .cfg-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.15rem;
            font-weight: 700;
        }

        .cfg-close {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: transparent;
            border: 1px solid #252530;
            color: #7a7880;
            cursor: pointer;
            font-size: .9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .2s;
        }

        .cfg-close:hover {
            border-color: #d42b2b;
            color: #d42b2b;
        }

        .cfg-tabs {
            display: flex;
            border-bottom: 1px solid #252530;
            flex-shrink: 0;
            overflow: hidden;
            padding: 0 .55rem;
            position: relative;
        }

        .cfg-tab-indicator {
            position: absolute;
            bottom: 0;
            height: 2px;
            background: #d42b2b;
            border-radius: 2px 2px 0 0;
            transition: left .35s cubic-bezier(.4,0,.2,1), width .35s cubic-bezier(.4,0,.2,1);
            pointer-events: none;
            box-shadow: 0 0 8px rgba(212,43,43,.5);
        }

        .cfg-tab {
            flex: 1;
            min-width: 0;
            padding: .82rem .36rem;
            background: transparent;
            border: none;
            font-family: 'DM Sans', sans-serif;
            font-size: .64rem;
            font-weight: 700;
            letter-spacing: .055em;
            line-height: 1.15;
            text-transform: uppercase;
            color: #7a7880;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: color .25s ease, background .25s ease;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-align: center;
            position: relative;
        }

        .cfg-tab::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 50% 120%, rgba(212,43,43,.12) 0%, transparent 70%);
            opacity: 0;
            transition: opacity .25s ease;
        }

        .cfg-tab:hover {
            color: #d0ccd8;
        }

        .cfg-tab:hover::before {
            opacity: .5;
        }

        .cfg-tab.active {
            color: #d42b2b;
            background: transparent;
        }

        .cfg-tab.active::before {
            opacity: 1;
        }

        .cfg-body {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
        }

        .cfg-body::-webkit-scrollbar {
            width: 4px;
        }

        .cfg-body::-webkit-scrollbar-thumb {
            background: #252530;
            border-radius: 2px;
        }

        .cfg-pane {
            display: none;
            opacity: 0;
            transform: translateY(10px);
            transition: opacity .3s ease, transform .3s ease;
            pointer-events: none;
        }

        .cfg-pane.visible {
            display: block;
        }

        .cfg-pane.active {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        .cfg-section-label {
            font-size: .7rem;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: #b0adb8;
            font-weight: 700;
            margin-bottom: .75rem;
            margin-top: 1.5rem;
            padding-bottom: .4rem;
            border-bottom: 1px solid #252530;
        }

        .cfg-section-label:first-child {
            margin-top: 0;
        }

        .rm-kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 1.4rem;
        }

        .rd-stat-kpi {
            min-width: 0;
            background: #18181f;
            border: 1px solid #252530;
            border-radius: 8px;
            padding: .9rem .55rem;
            text-align: center;
        }

        .rd-stat-kpi.rd-stat-accent {
            background: rgba(212, 43, 43, .08);
            border-color: rgba(212, 43, 43, .45);
        }

        .rd-stat-val {
            font-family: 'Playfair Display', serif;
            font-size: 1.15rem;
            line-height: 1;
            color: #f0ece3;
            font-weight: 700;
            margin-bottom: .28rem;
        }

        .rd-stat-accent .rd-stat-val {
            color: #d42b2b;
        }

        .rd-stat-lbl {
            font-size: .58rem;
            line-height: 1.2;
            letter-spacing: .11em;
            color: #7a7880;
            text-transform: uppercase;
        }

        .rm-section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
        }

        .rm-refresh-btn {
            flex-shrink: 0;
            font-size: .62rem;
            padding: .32rem .62rem;
            border-radius: 5px;
            background: transparent;
            border: 1px solid #252530;
            color: #7a7880;
            font-family: 'DM Sans', sans-serif;
            letter-spacing: .06em;
            text-transform: uppercase;
            cursor: pointer;
            transition: all .2s;
        }

        .rm-refresh-btn:hover {
            border-color: #d42b2b;
            color: #d42b2b;
        }

        .rm-list {
            margin-bottom: 1.35rem;
        }

        .rm-panel-box {
            background: #18181f;
            border: 1px solid #252530;
            border-radius: 8px;
            padding: 1rem 1.15rem;
            margin-bottom: 1rem;
        }

        .auto-estado-chip {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .3rem .75rem;
            border-radius: 100px;
            font-size: .7rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .auto-estado-chip.off {
            background: rgba(122, 120, 128, .1);
            border: 1px solid rgba(122, 120, 128, .2);
            color: #7a7880;
        }

        .auto-estado-chip.on {
            background: rgba(34, 197, 94, .1);
            border: 1px solid rgba(34, 197, 94, .3);
            color: #22c55e;
        }

        .auto-toggle-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #18181f;
            border: 1px solid #252530;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
        }

        .auto-toggle-info h4 {
            font-size: .9rem;
            font-weight: 600;
            margin-bottom: .2rem;
        }

        .auto-toggle-info p {
            font-size: .75rem;
            color: #7a7880;
            line-height: 1.5;
        }

        .toggle-switch {
            position: relative;
            width: 44px;
            height: 24px;
            flex-shrink: 0;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
            position: absolute;
        }

        .toggle-slider {
            position: absolute;
            inset: 0;
            background: #252530;
            border-radius: 24px;
            cursor: pointer;
            transition: background .3s;
        }

        .toggle-slider::before {
            content: '';
            position: absolute;
            left: 3px;
            top: 3px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #f0ece3;
            transition: transform .3s;
        }

        .toggle-switch input:checked+.toggle-slider {
            background: #d42b2b;
        }

        .toggle-switch input:checked+.toggle-slider::before {
            transform: translateX(20px);
        }

        .alcance-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .5rem;
            margin-bottom: 1.25rem;
        }

        .alcance-btn {
            padding: .7rem .5rem;
            background: #18181f;
            border: 1px solid #252530;
            border-radius: 8px;
            text-align: center;
            font-family: 'DM Sans', sans-serif;
            font-size: .78rem;
            font-weight: 500;
            color: #7a7880;
            cursor: pointer;
            transition: all .2s;
        }

        .alcance-btn:hover {
            border-color: #7a7880;
            color: #f0ece3;
        }

        .alcance-btn.selected {
            background: rgba(212, 43, 43, .1);
            border-color: rgba(212, 43, 43, .5);
            color: #d42b2b;
        }

        .alcance-desc {
            font-size: .72rem;
            color: #7a7880;
            padding: .6rem .75rem;
            background: #0d0d14;
            border-radius: 6px;
            border-left: 2px solid #d42b2b;
            margin-bottom: 1.25rem;
            min-height: 32px;
        }

        .cfg-save-btn {
            width: 100%;
            background: linear-gradient(135deg, #d42b2b 0%, #a81e1e 100%);
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: .85rem 1rem;
            font-family: 'DM Sans', sans-serif;
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .12em;
            text-transform: uppercase;
            cursor: pointer;
            transition: all .25s;
            box-shadow: 0 4px 16px rgba(212, 43, 43, .25);
        }

        .cfg-save-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(212, 43, 43, .4);
        }

        .cfg-save-btn:disabled {
            opacity: .5;
            cursor: not-allowed;
            transform: none;
        }

        .mini-cal-wrap {
            background: #18181f;
            border: 1px solid #252530;
            border-radius: 10px;
            padding: 1.1rem;
            margin-bottom: 1rem;
        }

        .mini-cal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .mini-cal-title {
            font-size: .9rem;
            font-weight: 600;
        }

        .mini-cal-nav {
            display: flex;
            gap: .3rem;
        }

        .mini-cal-nav button {
            width: 26px;
            height: 26px;
            border: 1px solid #252530;
            border-radius: 4px;
            background: transparent;
            color: #7a7880;
            font-size: .8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all .2s;
        }

        .mini-cal-nav button:hover {
            border-color: #d42b2b;
            color: #d42b2b;
        }

        .mini-cal-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            margin-bottom: .3rem;
        }

        .mini-cal-day-label {
            text-align: center;
            font-size: .55rem;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #7a7880;
            padding: .2rem 0;
        }

        .mini-cal-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
        }

        .mini-cell {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            font-size: .72rem;
            cursor: pointer;
            transition: all .15s;
            border: 1px solid transparent;
            position: relative;
        }

        .mini-cell:hover:not(.mc-disabled):not(.mc-empty):not(.mc-blocked) {
            border-color: rgba(212, 43, 43, .4);
            color: #d42b2b;
        }

        .mini-cell.mc-today:not(.mc-selected):not(.mc-blocked):not(.mc-pending) {
            border-color: rgba(212, 43, 43, .3);
            color: #d42b2b;
        }

        .mini-cell.mc-disabled {
            color: #2a2a38;
            cursor: not-allowed;
        }

        .mini-cell.mc-empty {
            cursor: default;
        }

        .mini-cell.mc-selected {
            background: rgba(212, 43, 43, .15);
            border-color: rgba(212, 43, 43, .5);
            color: #d42b2b;
        }

        .mini-cell.mc-blocked {
            background: rgba(212, 43, 43, .25);
            border-color: #d42b2b;
            color: #fff;
            cursor: pointer;
        }

        .mini-cell.mc-blocked::after {
            content: '';
            position: absolute;
            inset: 0;
            background: repeating-linear-gradient(-45deg, rgba(212, 43, 43, .15) 0, rgba(212, 43, 43, .15) 2px, transparent 2px, transparent 6px);
            border-radius: 5px;
            pointer-events: none;
        }

        .mini-cell.mc-blocked:hover {
            background: rgba(212, 43, 43, .4);
            border-color: #ff4444;
        }

        .mini-cell.mc-pending {
            background: rgba(245, 158, 11, .2);
            border-color: rgba(245, 158, 11, .6);
            color: #f59e0b;
            cursor: pointer;
        }

        .mini-cell.mc-pending::after {
            content: '';
            position: absolute;
            inset: 3px;
            border-radius: 4px;
            border: 1px dashed rgba(245, 158, 11, .5);
            pointer-events: none;
        }

        .mini-cell.mc-unblocking {
            background: rgba(122, 120, 128, .15);
            border-color: rgba(122, 120, 128, .4);
            color: #7a7880;
            text-decoration: line-through;
            cursor: pointer;
        }

        .cal-legend {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            margin-bottom: 1rem;
        }

        .cal-legend-item {
            display: flex;
            align-items: center;
            gap: .35rem;
            font-size: .65rem;
            color: #7a7880;
        }

        .cal-legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 2px;
            flex-shrink: 0;
        }

        .cal-legend-dot.blocked {
            background: rgba(212, 43, 43, .4);
            border: 1px solid #d42b2b;
        }

        .cal-legend-dot.pending {
            background: rgba(245, 158, 11, .3);
            border: 1px dashed rgba(245, 158, 11, .7);
        }

        .cal-legend-dot.unblocking {
            background: rgba(122, 120, 128, .2);
            border: 1px solid #7a7880;
        }

        .vac-motivo-row {
            display: flex;
            gap: .5rem;
            margin-bottom: .75rem;
        }

        .vac-motivo-input {
            flex: 1;
            background: #18181f;
            border: 1px solid #252530;
            border-radius: 6px;
            padding: .55rem .75rem;
            color: #f0ece3;
            font-family: 'DM Sans', sans-serif;
            font-size: .82rem;
        }

        .vac-motivo-input:focus {
            outline: none;
            border-color: #d42b2b;
        }

        .vac-action-row {
            display: flex;
            gap: .5rem;
            margin-bottom: .75rem;
        }

        .vac-btn {
            flex: 1;
            padding: .6rem .5rem;
            border-radius: 6px;
            font-family: 'DM Sans', sans-serif;
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            cursor: pointer;
            transition: all .2s;
        }

        .vac-btn-range {
            background: rgba(201, 168, 76, .1);
            border: 1px solid rgba(201, 168, 76, .3);
            color: #c9a84c;
        }

        .vac-btn-range:hover {
            background: #c9a84c;
            color: #000;
        }

        .vac-btn-clear {
            background: transparent;
            border: 1px solid #252530;
            color: #7a7880;
        }

        .vac-btn-clear:hover {
            border-color: #7a7880;
            color: #f0ece3;
        }

        .range-hint {
            font-size: .72rem;
            color: #c9a84c;
            padding: .5rem .7rem;
            background: rgba(201, 168, 76, .06);
            border: 1px solid rgba(201, 168, 76, .2);
            border-radius: 6px;
            margin-bottom: .75rem;
            display: none;
        }

        .range-hint.visible {
            display: block;
        }

        .cfg-save-days-btn {
            width: 100%;
            background: linear-gradient(135deg, #c9a84c 0%, #a17c2d 100%);
            color: #000;
            border: none;
            border-radius: 6px;
            padding: .85rem 1rem;
            font-family: 'DM Sans', sans-serif;
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .12em;
            text-transform: uppercase;
            cursor: pointer;
            transition: all .25s;
            box-shadow: 0 4px 16px rgba(201, 168, 76, .2);
            margin-top: .25rem;
        }

        .cfg-save-days-btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(201, 168, 76, .35);
        }

        .cfg-save-days-btn:disabled {
            opacity: .4;
            cursor: not-allowed;
            transform: none;
        }

        .blocked-list {
            display: flex;
            flex-direction: column;
            gap: .4rem;
            max-height: 220px;
            overflow-y: auto;
        }

        .blocked-list::-webkit-scrollbar {
            width: 3px;
        }

        .blocked-list::-webkit-scrollbar-thumb {
            background: #252530;
        }

        .blocked-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #18181f;
            border: 1px solid #252530;
            border-radius: 7px;
            padding: .55rem .85rem;
            font-size: .8rem;
        }

        .blocked-item-info {
            display: flex;
            flex-direction: column;
            gap: .1rem;
        }

        .blocked-fecha {
            color: #f0ece3;
            font-weight: 500;
        }

        .blocked-motivo {
            font-size: .7rem;
            color: #7a7880;
        }

        .blocked-del {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: transparent;
            border: 1px solid #252530;
            color: #7a7880;
            cursor: pointer;
            font-size: .75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .2s;
            flex-shrink: 0;
        }

        .blocked-del:hover {
            border-color: #d42b2b;
            color: #d42b2b;
            background: rgba(212, 43, 43, .08);
        }

        .empty-blocked {
            text-align: center;
            color: #7a7880;
            font-size: .78rem;
            padding: 1.5rem;
            border: 1px dashed #252530;
            border-radius: 8px;
        }

        .cfg-status {
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .65rem 1rem;
            border-radius: 8px;
            font-size: .78rem;
            margin-top: 1rem;
            opacity: 0;
            transition: opacity .3s;
        }

        .cfg-status.visible {
            opacity: 1;
        }

        .cfg-status.ok {
            background: rgba(34, 197, 94, .1);
            border: 1px solid rgba(34, 197, 94, .25);
            color: #22c55e;
        }

        .cfg-status.err {
            background: rgba(212, 43, 43, .1);
            border: 1px solid rgba(212, 43, 43, .25);
            color: #d42b2b;
        }

        /* ================================================================
           STATS PANEL
        ================================================================ */
        .stats-overlay {
            position: fixed;
            inset: 0;
            background: rgba(7,8,16,0.72);
            backdrop-filter: blur(24px) saturate(1.3);
            -webkit-backdrop-filter: blur(24px) saturate(1.3);
            z-index: 1100;
            opacity: 0;
            pointer-events: none;
            transition: opacity .4s cubic-bezier(.16,1,.3,1);
        }

        .stats-overlay.open {
            opacity: 1;
            pointer-events: all;
        }

        .stats-panel {
            position: fixed;
            inset: 0;
            overflow-y: auto;
            z-index: 1101;
            opacity: 0;
            transform: translateY(28px);
            pointer-events: none;
            transition: opacity .45s cubic-bezier(.16, 1, .3, 1), transform .45s cubic-bezier(.16, 1, .3, 1);
            scrollbar-width: thin;
            scrollbar-color: #2550a0 #09080f;
        }
        .stats-panel::-webkit-scrollbar { width: 6px; }
        .stats-panel::-webkit-scrollbar-track { background: #09080f; }
        .stats-panel::-webkit-scrollbar-thumb { background: #2550a0 !important; border-radius: 3px; }
        .stats-panel::-webkit-scrollbar-thumb:hover { background: #1a3a6b !important; }

        .stats-panel.open {
            opacity: 1;
            transform: translateY(0);
            pointer-events: all;
        }

        .stats-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
        }

        .stats-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2.5rem;
            padding-bottom: 1.25rem;
            border-bottom: 1px solid rgba(245, 240, 232, .08);
        }

        .stats-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.4rem, 3vw, 1.9rem);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: .75rem;
        }

        .stats-title-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(145deg, #d42b2b 0%, #8b1515 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 6px 20px rgba(212, 43, 43, .40), inset 0 1px 0 rgba(255,255,255,.12);
            position: relative;
            overflow: hidden;
        }
        .stats-title-icon::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(145deg, rgba(255,255,255,.10) 0%, transparent 60%);
            border-radius: inherit;
        }
        .stats-title-icon svg {
            position: relative;
            z-index: 1;
            filter: drop-shadow(0 1px 2px rgba(0,0,0,.35));
        }

        .stats-close {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(245, 240, 232, .06);
            border: 1px solid rgba(245, 240, 232, .12);
            color: #f0ece3;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .2s;
        }

        .stats-close:hover {
            background: rgba(212, 43, 43, .2);
            border-color: #d42b2b;
            color: #d42b2b;
        }

        .stats-loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 50vh;
            gap: 1.5rem;
            color: #7a7880;
            font-size: .85rem;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .stats-spinner {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: 2px solid rgba(212, 43, 43, .15);
            border-top-color: #d42b2b;
            animation: statsSpin .9s linear infinite;
        }

        @keyframes statsSpin {
            to {
                transform: rotate(360deg);
            }
        }

        .stats-kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .kpi-card {
            background: #111119;
            border: 1px solid #252530;
            border-radius: 14px;
            padding: 1.25rem 1.4rem;
            position: relative;
            overflow: hidden;
            transition: transform .25s cubic-bezier(.16,1,.3,1), border-color .25s, box-shadow .25s;
            cursor: pointer;
            user-select: none;
        }

        .kpi-card:active {
            transform: scale(.97) !important;
        }

        /* ── KPI DETAIL MODAL ── */
        .kpi-detail-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.72);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 1500;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            opacity: 0;
            transition: opacity .25s;
        }
        .kpi-detail-overlay.open { opacity: 1; }

        .kpi-detail-modal {
            background: #111119;
            border: 1px solid #2a2a38;
            border-radius: 20px;
            width: 100%;
            max-width: 440px;
            overflow: hidden;
            transform: translateY(24px) scale(.95);
            opacity: 0;
            transition: transform .5s cubic-bezier(.16,1,.3,1), opacity .3s;
            box-shadow: 0 32px 80px rgba(0,0,0,.6), 0 0 0 1px rgba(255,255,255,.04);
        }
        .kpi-detail-overlay.open .kpi-detail-modal {
            transform: translateY(0) scale(1);
            opacity: 1;
        }
        .kpi-detail-modal.closing {
            transform: translateY(16px) scale(.96);
            opacity: 0;
            transition: transform .3s cubic-bezier(.4,0,1,1), opacity .2s;
        }

        .kdm-header {
            padding: 1.6rem 1.75rem 1.3rem;
            border-bottom: 1px solid #1e1e2c;
            display: flex;
            align-items: flex-start;
            gap: 1.1rem;
        }
        .kdm-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .kdm-icon svg { width: 22px; height: 22px; fill: currentColor; }
        .kdm-title-group { flex: 1; min-width: 0; }
        .kdm-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            font-weight: 700;
            color: #f0ece3;
            line-height: 1.2;
        }
        .kdm-subtitle {
            font-size: .7rem;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: #7a7880;
            margin-top: .25rem;
        }
        .kdm-close {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: transparent;
            border: 1px solid #252530;
            color: #7a7880;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: .85rem;
            transition: border-color .2s, color .2s, transform .2s;
            flex-shrink: 0;
        }
        .kdm-close:hover { border-color: #d42b2b; color: #d42b2b; transform: scale(1.08); }
        .kdm-close:active { transform: scale(.92); }

        .kdm-value-hero {
            padding: 1.5rem 1.75rem;
            border-bottom: 1px solid #1e1e2c;
            display: flex;
            align-items: baseline;
            gap: .5rem;
        }
        .kdm-value-num {
            font-family: 'Playfair Display', serif;
            font-size: 3rem;
            font-weight: 700;
            line-height: 1;
        }
        .kdm-value-suffix {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
            opacity: .7;
        }

        .kdm-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-bottom: 1px solid #1e1e2c;
        }
        .kdm-cell {
            padding: 1.1rem 1.4rem;
            border-right: 1px solid #1e1e2c;
            border-radius: 10px;
            transition: box-shadow 0.2s;
        }
        .kdm-cell:last-child,
        .kdm-cell:nth-child(2n) { border-right: none; }
        .kdm-cell-label {
            font-size: .6rem;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: #7a7880;
            margin-bottom: .35rem;
        }
        .kdm-cell-value {
            font-family: 'Playfair Display', serif;
            font-size: 1.35rem;
            font-weight: 700;
        }
        .kdm-cell-sub {
            font-size: .7rem;
            color: #7a7880;
            margin-top: .15rem;
        }

        .kdm-bar-section {
            padding: 1.2rem 1.75rem;
            border-bottom: 1px solid #1e1e2c;
        }
        .kdm-bar-row {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: .6rem;
        }
        .kdm-bar-row:last-child { margin-bottom: 0; }
        .kdm-bar-label {
            font-size: .72rem;
            color: #a0a0b0;
            width: 110px;
            flex-shrink: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .kdm-bar-track {
            flex: 1;
            height: 6px;
            background: #1c1c26;
            border-radius: 3px;
            overflow: hidden;
        }
        .kdm-bar-fill {
            height: 100%;
            border-radius: 3px;
            width: 0;
            transition: width 1s cubic-bezier(.16,1,.3,1);
        }
        .kdm-bar-num {
            font-size: .72rem;
            font-weight: 600;
            min-width: 52px;
            text-align: right;
            flex-shrink: 0;
            white-space: nowrap;
        }

        .kdm-list {
            padding: 1rem 1.75rem;
            border-bottom: 1px solid #1e1e2c;
            display: flex;
            flex-direction: column;
            gap: .55rem;
        }
        .kdm-list-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: .82rem;
        }
        .kdm-list-label { color: #c0c0d0; }
        .kdm-list-val { font-weight: 600; color: #f0ece3; }

        .kdm-footer {
            padding: 1rem 1.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .kdm-footer-note {
            font-size: .68rem;
            color: #7a7880;
        }
        .kdm-footer-btn {
            padding: .45rem 1.1rem;
            background: transparent;
            border: 1px solid #252530;
            border-radius: 6px;
            color: #7a7880;
            font-family: 'DM Sans', sans-serif;
            font-size: .68rem;
            letter-spacing: .1em;
            text-transform: uppercase;
            cursor: pointer;
            transition: border-color .2s, color .2s, transform .15s;
        }
        .kdm-footer-btn:hover { border-color: #d42b2b; color: #d42b2b; }
        .kdm-footer-btn:active { transform: scale(.96); }

        .kdm-progress-section {
            padding: 1.2rem 1.75rem;
            border-bottom: 1px solid #1e1e2c;
        }
        .kdm-progress-label {
            display: flex;
            justify-content: space-between;
            font-size: .68rem;
            color: #7a7880;
            margin-bottom: .5rem;
        }
        .kdm-progress-track {
            height: 8px;
            background: #1c1c26;
            border-radius: 4px;
            overflow: hidden;
        }
        .kdm-progress-fill {
            height: 100%;
            border-radius: 4px;
            width: 0;
            transition: width 1.2s cubic-bezier(.16,1,.3,1) .15s;
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--kpi-accent, #d42b2b);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform .6s cubic-bezier(.16, 1, .3, 1);
        }

        .kpi-card.visible::before {
            transform: scaleX(1);
        }

        .kpi-card:hover {
            transform: translateY(-3px);
            border-color: var(--kpi-accent, #d42b2b);
        }

        .kpi-label {
            font-size: .6rem;
            letter-spacing: .2em;
            text-transform: uppercase;
            color: #7a7880;
            margin-bottom: .5rem;
        }

        .kpi-value {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--kpi-accent, #d42b2b);
            line-height: 1;
        }

        .kpi-sub {
            font-size: .7rem;
            color: #7a7880;
            margin-top: .35rem;
        }

        .kpi-badge {
            position: absolute;
            top: .85rem;
            right: .85rem;
            opacity: .18;
            line-height: 0;
        }
        .kpi-badge svg {
            width: 1.5rem;
            height: 1.5rem;
            fill: var(--kpi-accent, #fff);
        }

        .stats-section {
            margin-bottom: 2rem;
        }

        .stats-section-label {
            font-size: .6rem;
            letter-spacing: .22em;
            text-transform: uppercase;
            color: #d42b2b;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .stats-section-label::before {
            content: '';
            width: 20px;
            height: 1px;
            background: #d42b2b;
        }

        .stats-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .stats-grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
        }

        .stats-grid-barberos {
            display: grid;
            grid-template-columns: repeat(var(--barb-cols, 3), 1fr);
            gap: 1rem;
            justify-items: stretch;
        }

        .stats-grid-barberos.barb-count-1 {
            max-width: 380px;
            margin-left: auto;
            margin-right: auto;
        }


        .stats-card {
            background: #111119;
            border: 1px solid #252530;
            border-radius: 14px;
            padding: 1.5rem;
        }

        .stats-card-title {
            font-size: .72rem;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #7a7880;
            margin-bottom: 1.25rem;
        }

        .bar-chart {
            display: flex;
            align-items: flex-end;
            gap: 6px;
            height: 200px;
            padding: 28px 0 36px;
            position: relative;
            box-sizing: border-box;
        }

        .bar-chart::after {
            content: '';
            position: absolute;
            bottom: 24px;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(245, 240, 232, .06);
        }

        .bar-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            position: relative;
        }

        .bar-fill {
            width: 100%;
            border-radius: 4px 4px 0 0;
            min-height: 3px;
            transform: scaleY(0);
            transform-origin: bottom;
            transition: transform .7s cubic-bezier(.34, 1.56, .64, 1);
            transition-delay: var(--bar-delay, 0s);
            position: relative;
            overflow: hidden;
        }

        .bar-fill::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(255, 255, 255, 0), rgba(255, 255, 255, .12));
            pointer-events: none;
        }

        .bar-fill.animated {
            transform: scaleY(1);
        }

        .bar-label {
            font-size: .72rem;
            color: #7a7880;
            text-align: center;
            position: absolute;
            bottom: -28px;
            white-space: nowrap;
            left: 50%;
            transform: translateX(-50%);
        }

        .bar-item:hover .bar-tooltip {
            opacity: 1;
        }

        .bar-tooltip {
            position: absolute;
            bottom: calc(100% + 8px);
            left: 50%;
            transform: translateX(-50%);
            background: #1c1c26;
            border: 1px solid #252530;
            border-radius: 6px;
            padding: .3rem .6rem;
            font-size: .7rem;
            color: #f0ece3;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity .2s;
            z-index: 10;
        }

        .line-chart-svg {
            width: 100%;
            overflow: visible;
        }

        .line-path {
            fill: none;
            stroke: #d42b2b;
            stroke-width: 2.5;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-dasharray: 1000;
            stroke-dashoffset: 1000;
            transition: stroke-dashoffset 1.8s cubic-bezier(.16, 1, .3, 1);
        }

        .line-path.animated {
            stroke-dashoffset: 0;
        }

        .line-area {
            fill: url(#lineGrad);
            opacity: 0;
            transition: opacity 1s ease .6s;
        }

        .line-area.animated {
            opacity: 1;
        }

        .line-dot {
            fill: #d42b2b;
            stroke: #111119;
            stroke-width: 2;
            opacity: 0;
            transition: opacity .3s;
            cursor: pointer;
        }

        .line-dot.animated {
            opacity: 1;
        }

        .line-dot:hover {
            r: 6;
            fill: #ff4444;
        }

        .line-x-label {
            font-size: 9px;
            fill: #7a7880;
            text-anchor: middle;
        }

        .line-y-label {
            font-size: 9px;
            fill: #7a7880;
            text-anchor: end;
        }

        .line-grid {
            stroke: rgba(245, 240, 232, .05);
            stroke-width: 1;
        }

        .chart-tooltip {
            position: fixed;
            background: #1c1c26;
            border: 1px solid #d42b2b;
            border-radius: 8px;
            padding: .5rem .85rem;
            font-size: .75rem;
            color: #f0ece3;
            pointer-events: none;
            z-index: 1200;
            opacity: 0;
            transition: opacity .15s;
            transform: translate(-50%, -120%);
            min-width: 100px;
            text-align: center;
        }

        .chart-tooltip.visible {
            opacity: 1;
        }

        .chart-tooltip strong {
            display: block;
            color: #d42b2b;
            font-size: .85rem;
        }

        .donut-wrap {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .donut-seg {
            fill: none;
            stroke-width: 18;
            stroke-linecap: round;
            stroke-dasharray: 0 251;
            transition: stroke-dasharray 1.2s cubic-bezier(.16, 1, .3, 1);
            transition-delay: var(--seg-delay, 0s);
        }

        .conversion-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            padding: .5rem 0;
        }

        .conversion-ring {
            position: relative;
            width: 140px;
            height: 140px;
        }

        .conv-svg {
            width: 100%;
            height: 100%;
        }

        .conv-track {
            fill: none;
            stroke: #1c1c26;
            stroke-width: 14;
        }

        .conv-prog {
            fill: none;
            stroke: #22c55e;
            stroke-width: 14;
            stroke-linecap: round;
            stroke-dasharray: 0 345;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
            transition: stroke-dasharray 1.5s cubic-bezier(.16, 1, .3, 1) .3s;
        }

        .conv-prog.animated {
            stroke-dasharray: var(--conv-dash, 0) 345;
        }

        .conv-center {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .conv-pct {
            font-family: 'Playfair Display', serif;
            font-size: 1.75rem;
            font-weight: 700;
            color: #22c55e;
            line-height: 1;
        }

        .conv-sub {
            font-size: .6rem;
            color: #7a7880;
            letter-spacing: .1em;
            text-transform: uppercase;
            margin-top: .2rem;
        }

        .conversion-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .75rem;
            width: 100%;
        }

        .conv-meta-item {
            text-align: center;
            background: #0d0d14;
            border-radius: 8px;
            padding: .75rem .5rem;
        }

        .conv-meta-num {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            font-weight: 700;
            line-height: 1;
        }

        .conv-meta-lbl {
            font-size: .58rem;
            color: #7a7880;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin-top: .2rem;
        }

        .barbero-stat-card {
            background: #111119;
            border: 1px solid #252530;
            border-radius: 14px;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: .85rem;
            transition: border-color .25s, transform .25s;
        }

        .barbero-stat-card:hover {
            border-color: rgba(212, 43, 43, .4);
            transform: translateY(-2px);
        }

        .barbero-stat-header {
            display: flex;
            align-items: center;
            gap: .85rem;
        }

        .barbero-avatar-stat {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(212, 43, 43, .15), rgba(212, 43, 43, .05));
            border: 1px solid rgba(212, 43, 43, .25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 1rem;
            font-weight: 700;
            color: #d42b2b;
            flex-shrink: 0;
        }

        .barbero-stat-name {
            font-weight: 600;
            font-size: .9rem;
            margin-bottom: .1rem;
        }

        .barbero-stat-sub {
            font-size: .7rem;
            color: #7a7880;
        }

        .barbero-progress-wrap {
            display: flex;
            flex-direction: column;
            gap: .4rem;
        }

        .barbero-progress-label {
            display: flex;
            justify-content: space-between;
            font-size: .7rem;
            color: #7a7880;
        }

        .barbero-progress-label span:last-child {
            color: #c9a84c;
            font-weight: 500;
        }

        .progress-track {
            height: 6px;
            background: #1c1c26;
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 3px;
            background: linear-gradient(90deg, #d42b2b, #ff6b6b);
            width: 0;
            transition: width 1.2s cubic-bezier(.16, 1, .3, 1);
            transition-delay: var(--prog-delay, .2s);
        }

        .progress-fill.animated {
            width: var(--prog-w, 0%);
        }

        .barbero-kpi-row {
            display: flex;
            gap: .5rem;
        }

        .barbero-kpi {
            flex: 1;
            background: #0d0d14;
            border-radius: 8px;
            padding: .6rem .75rem;
            text-align: center;
        }

        .barbero-kpi-num {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            color: #d42b2b;
            font-weight: 700;
            line-height: 1;
        }

        .barbero-kpi-lbl {
            font-size: .58rem;
            color: #7a7880;
            letter-spacing: .1em;
            text-transform: uppercase;
            margin-top: .2rem;
        }

        .horas-wrap {
            display: flex;
            flex-direction: column;
            gap: .5rem;
        }

        .hora-row {
            display: flex;
            align-items: center;
            gap: .75rem;
        }

        .hora-lbl {
            font-size: .72rem;
            color: #7a7880;
            width: 42px;
            flex-shrink: 0;
            text-align: right;
        }

        .hora-bar-outer {
            flex: 1;
            height: 28px;
            background: #0d0d14;
            border-radius: 6px;
            overflow: hidden;
            position: relative;
        }

        .hora-bar-fill {
            height: 100%;
            border-radius: 6px;
            background: linear-gradient(90deg, rgba(212, 43, 43, .6), rgba(212, 43, 43, .9));
            width: 0;
            transition: width 1s cubic-bezier(.16, 1, .3, 1);
            transition-delay: var(--h-delay, 0s);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: .5rem;
        }

        .hora-bar-fill.animated {
            width: var(--h-w, 0%);
        }

        .hora-count {
            font-size: .65rem;
            color: rgba(255, 255, 255, .7);
            font-weight: 600;
            white-space: nowrap;
        }

        .svc-stat-list {
            display: flex;
            flex-direction: column;
            gap: .6rem;
        }

        .svc-stat-item {
            display: flex;
            align-items: center;
            gap: .75rem;
        }

        .svc-stat-rank {
            width: 22px;
            height: 22px;
            border-radius: 6px;
            background: rgba(212, 43, 43, .1);
            border: 1px solid rgba(212, 43, 43, .2);
            font-size: .65rem;
            font-weight: 700;
            color: #d42b2b;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .svc-stat-info {
            flex: 1;
            min-width: 0;
        }

        .svc-stat-name {
            font-size: .82rem;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .svc-stat-bar {
            height: 4px;
            background: #1c1c26;
            border-radius: 2px;
            margin-top: .3rem;
            overflow: hidden;
        }

        .svc-stat-bar-fill {
            height: 100%;
            border-radius: 2px;
            background: linear-gradient(90deg, #d42b2b, #c9a84c);
            width: 0;
            transition: width 1s cubic-bezier(.16, 1, .3, 1);
            transition-delay: var(--svc-delay, 0s);
        }

        .svc-stat-bar-fill.animated {
            width: var(--svc-w, 0%);
        }

        .svc-stat-meta {
            text-align: right;
            flex-shrink: 0;
        }

        .svc-stat-count {
            font-size: .82rem;
            color: #f0ece3;
            font-weight: 500;
        }

        .svc-stat-euros {
            font-size: .7rem;
            color: #c9a84c;
        }

        .heatmap-wrap {
            overflow-x: auto;
        }

        .heatmap-grid {
            display: flex;
            gap: 3px;
            padding: 4px 0;
        }

        .hm-col {
            display: flex;
            flex-direction: column;
            gap: 3px;
            align-items: center;
        }

        .hm-cell {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            border: 1px solid #1c1c26;
            transition: all .2s;
            cursor: default;
            position: relative;
            flex-shrink: 0;
        }

        .hm-cell:hover {
            transform: scale(1.3);
            z-index: 10;
        }

        .hm-cell[data-v="0"] {
            background: #0d0d14;
        }

        .hm-cell[data-v="1"] {
            background: rgba(212, 43, 43, .2);
        }

        .hm-cell[data-v="2"] {
            background: rgba(212, 43, 43, .4);
        }

        .hm-cell[data-v="3"] {
            background: rgba(212, 43, 43, .65);
        }

        .hm-cell[data-v="4"] {
            background: rgba(212, 43, 43, .85);
        }

        @keyframes statsNumPop {
            0% {
                transform: scale(.5);
                opacity: 0;
            }

            70% {
                transform: scale(1.08);
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .kpi-value.pop {
            animation: statsNumPop .5s cubic-bezier(.34, 1.56, .64, 1) both;
        }

        @media(max-width:900px) and (min-width:701px) {
            .stats-grid-barberos {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media(max-width:700px) {

            .stats-grid-2,
            .stats-grid-3,
            .stats-grid-barberos {
                grid-template-columns: 1fr;
            }

            .stats-kpis {
                grid-template-columns: repeat(2, 1fr);
            }

            .bar-chart {
                height: 90px;
            }

            .stats-inner {
                padding: 1rem 1rem 3rem;
            }
        }

        @media(max-width:420px) {
            .stats-kpis {
                grid-template-columns: 1fr 1fr;
            }

            .kpi-value {
                font-size: 1.6rem;
            }
        }

        /* ================================================================
        STATS FIXES — CSS adicional para admin.php
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
            border-color: rgba(212, 43, 43, .45) !important;
            box-shadow: 0 8px 32px rgba(212, 43, 43, .15), 0 0 0 1px rgba(212, 43, 43, .08) !important;
        }

        .barbero-stat-card:active {
            transform: translateY(1px) !important;
        }

        /* FIX 2: Heatmap — override de las clases antiguas */
        .hm-col,
        .heatmap-grid {
            display: none !important;
        }

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

        .datos-list {
            display: flex;
            flex-direction: column;
            gap: .5rem;
            margin-bottom: .75rem;
        }

        .datos-item {
            display: flex;
            align-items: center;
            gap: .75rem;
            background: #18181f;
            border: 1px solid #252530;
            border-radius: 9px;
            padding: .65rem 1rem;
            transition: border-color .2s;
        }

        .datos-item.inactivo {
            opacity: .45;
        }

        .datos-item-avatar {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: rgba(212, 43, 43, .1);
            border: 1px solid rgba(212, 43, 43, .2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: .75rem;
            font-weight: 700;
            color: #d42b2b;
            flex-shrink: 0;
        }

        .datos-item-info {
            flex: 1;
            min-width: 0;
        }

        .datos-item-nombre {
            font-size: .875rem;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .datos-item-sub {
            font-size: .72rem;
            color: #7a7880;
            margin-top: .1rem;
        }

        .datos-item-actions {
            display: flex;
            gap: .4rem;
            flex-shrink: 0;
        }

        .datos-btn {
            padding: .28rem .6rem;
            border-radius: 5px;
            font-family: 'DM Sans', sans-serif;
            font-size: .66rem;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            cursor: pointer;
            transition: all .18s;
            border: 1px solid transparent;
        }

        .datos-btn-edit {
            background: rgba(201, 168, 76, .08);
            border-color: rgba(201, 168, 76, .25);
            color: #c9a84c;
        }

        .datos-btn-edit:hover {
            background: #c9a84c;
            color: #000;
        }

        .datos-btn-toggle {
            background: rgba(107, 114, 128, .08);
            border-color: rgba(107, 114, 128, .25);
            color: #9ca3af;
        }

        .datos-btn-toggle.activo {
            background: rgba(34, 197, 94, .08);
            border-color: rgba(34, 197, 94, .25);
            color: #22c55e;
        }

        .datos-btn-toggle:hover {
            opacity: .8;
        }

        .datos-add-btn {
            width: 100%;
            background: transparent;
            border: 1px dashed #252530;
            border-radius: 8px;
            color: #7a7880;
            font-family: 'DM Sans', sans-serif;
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .06em;
            padding: .65rem;
            cursor: pointer;
            transition: all .2s;
            margin-top: .25rem;
        }

        .datos-add-btn:hover {
            border-color: #d42b2b;
            color: #d42b2b;
            background: rgba(212, 43, 43, .04);
        }

        .datos-btn-del {
            background: rgba(212, 43, 43, .08);
            border: 1px solid rgba(212, 43, 43, .2);
            color: #d42b2b;
            width: 28px;
            height: 28px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            font-size: .75rem;
            flex-shrink: 0;
        }

        .datos-btn-del:hover {
            background: #d42b2b;
            color: #fff;
            border-color: #d42b2b;
        }

        .datos-loading {
            text-align: center;
            padding: 1.5rem;
            color: #7a7880;
            font-size: .8rem;
        }

        .datos-drag-handle {
            cursor: grab;
            color: #3d3d4d;
            font-size: 1rem;
            line-height: 1;
            flex-shrink: 0;
            padding: 0 2px;
            user-select: none;
            transition: color .15s;
        }
        .datos-item:hover .datos-drag-handle { color: #7a7880; }
        .datos-item.dragging { opacity: .45; border-style: dashed; }
        .datos-item.drag-over { border-color: #c9a84c; background: rgba(201,168,76,.06); }

        .datos-item-avatar--precio {
            font-size: .65rem;
            font-family: 'DM Sans', sans-serif;
            font-weight: 700;
            color: #c9a84c;
            border-color: rgba(201,168,76,.25);
            background: rgba(201,168,76,.08);
        }

        .datos-categoria { margin-bottom: 1.25rem; }
        .datos-categoria-label {
            font-size: .58rem;
            font-weight: 600;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: #c9a84c;
            margin-bottom: .5rem;
            padding-left: .6rem;
            border-left: 2px solid rgba(201,168,76,.35);
        }
        .datos-categoria-list {
            display: flex;
            flex-direction: column;
            gap: .4rem;
            min-height: 2px;
        }

        .datos-field select {
            width: 100%;
            background: #18181f;
            border: 1px solid #2f2f3c;
            border-radius: 7px;
            color: #f0ece3;
            font-family: 'DM Sans', sans-serif;
            font-size: .85rem;
            padding: .55rem .75rem;
            outline: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' fill='none'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%237a7880' stroke-width='1.5' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right .75rem center;
        }
        .datos-field select:focus { border-color: #c9a84c; }

        .datos-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .72);
            backdrop-filter: blur(6px);
            z-index: 1100;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            opacity: 0;
            pointer-events: none;
            transition: opacity .25s;
        }

        .datos-modal-overlay.open {
            opacity: 1;
            pointer-events: all;
        }

        .datos-modal {
            background: #111119;
            border: 1px solid #2f2f3c;
            border-radius: 14px;
            width: 100%;
            max-width: 420px;
            overflow: hidden;
            transform: translateY(16px) scale(.97);
            transition: transform .3s cubic-bezier(.16, 1, .3, 1), opacity .3s;
            opacity: 0;
        }

        .datos-modal-overlay.open .datos-modal {
            transform: translateY(0) scale(1);
            opacity: 1;
        }

        .datos-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.1rem 1.5rem;
            border-bottom: 1px solid #252530;
            font-family: 'Playfair Display', serif;
            font-size: 1rem;
            font-weight: 700;
        }

        .datos-modal-body {
            padding: 1.25rem 1.5rem;
            display: flex;
            flex-direction: column;
            gap: .85rem;
        }

        .datos-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: .65rem;
            padding: 1rem 1.5rem;
            border-top: 1px solid #252530;
        }

        .datos-field {
            display: flex;
            flex-direction: column;
            gap: .3rem;
        }

        .datos-field label {
            font-size: .62rem;
            letter-spacing: .15em;
            text-transform: uppercase;
            color: #7a7880;
        }

        .datos-field input {
            background: #18181f;
            border: 1px solid #252530;
            border-radius: 6px;
            padding: .65rem .85rem;
            color: #f0ece3;
            font-family: 'DM Sans', sans-serif;
            font-size: .88rem;
            transition: border-color .2s;
            width: 100%;
            box-sizing: border-box;
        }

        .datos-field input:focus {
            outline: none;
            border-color: #d42b2b;
        }

        @keyframes spinOnce {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .reload-btn.spinning {
            animation: spinOnce .5s ease forwards;
            border-color: #d42b2b !important;
            color: #d42b2b !important;
        }

        /* ── PERIOD SELECTOR ── */
        .stats-period-bar {
            position: relative;
            display: flex;
            gap: .3rem;
            flex-wrap: nowrap;
            margin-bottom: 1.75rem;
            padding: .3rem;
            background: #0d0d14;
            border-radius: 12px;
            border: 1px solid #1c1c26;
        }
        .stats-period-pill {
            position: absolute;
            border-radius: 8px;
            background: linear-gradient(135deg, #d42b2b, #a81e1e);
            box-shadow: 0 2px 14px rgba(212,43,43,.45);
            pointer-events: none;
            z-index: 0;
            transition: left .38s cubic-bezier(.34,1.4,.64,1),
                        top .38s cubic-bezier(.34,1.4,.64,1),
                        width .38s cubic-bezier(.34,1.4,.64,1),
                        height .32s cubic-bezier(.34,1.4,.64,1);
        }
        .stats-period-btn {
            flex: 1;
            min-width: 52px;
            position: relative;
            z-index: 1;
            padding: .5rem .6rem;
            background: transparent;
            border: none;
            border-radius: 8px;
            color: #7a7880;
            font-family: 'DM Sans', sans-serif;
            font-size: .68rem;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
            cursor: pointer;
            transition: color .25s;
            white-space: nowrap;
            text-align: center;
        }
        .stats-period-btn:hover {
            color: #f0ece3;
        }
        .stats-period-btn.active {
            color: #fff;
        }
        @media (max-width: 480px) {
            .stats-period-btn {
                min-width: 0;
                padding: .45rem .25rem;
                font-size: .56rem;
                letter-spacing: .04em;
            }
        }
        .stats-period-loading {
            opacity: .5;
            pointer-events: none;
        }
        #stats-content {
            transition: opacity .28s ease, transform .28s ease;
        }
        #stats-content.stats-fading {
            opacity: 0;
            transform: translateY(10px);
        }

        /* ── KPI PILLS ── */
        .kpi-pills {
            display: flex;
            flex-wrap: wrap;
            gap: .3rem;
            margin-top: .55rem;
        }
        .kpi-pill {
            display: inline-flex;
            align-items: center;
            gap: .22rem;
            padding: .2rem .52rem;
            border-radius: 20px;
            font-size: .62rem;
            font-weight: 600;
            letter-spacing: .03em;
            line-height: 1.3;
        }
        .kpi-pill--ok {
            background: rgba(34,197,94,.12);
            border: 1px solid rgba(34,197,94,.28);
            color: #4ade80;
        }
        .kpi-pill--denied {
            background: rgba(248,113,113,.1);
            border: 1px solid rgba(248,113,113,.25);
            color: #f87171;
        }
        .kpi-pill--pending {
            background: rgba(250,204,21,.1);
            border: 1px solid rgba(250,204,21,.2);
            color: #facc15;
        }

        /* ── ESTADO SUMMARY (bajo KPI cards) ── */
        .estado-summary {
            display: flex;
            align-items: stretch;
            gap: 0;
            background: #111119;
            border: 1px solid #252530;
            border-radius: 14px;
            padding: 1.1rem 1.5rem;
            margin-bottom: 2rem;
            overflow: hidden;
        }
        .estado-summary__item {
            flex: 1;
            display: flex;
            align-items: center;
            gap: .85rem;
            min-width: 0;
        }
        .estado-summary__sep {
            width: 1px;
            background: #252530;
            margin: 0 1.25rem;
            flex-shrink: 0;
        }
        .estado-summary__icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .estado-summary__data {
            display: flex;
            flex-direction: column;
            gap: .1rem;
            min-width: 48px;
        }
        .estado-summary__num {
            font-family: 'Playfair Display', serif;
            font-size: 1.35rem;
            font-weight: 700;
            line-height: 1;
        }
        .estado-summary__lbl {
            font-size: .6rem;
            letter-spacing: .15em;
            text-transform: uppercase;
            color: #7a7880;
        }
        .estado-summary__track {
            flex: 1;
            height: 4px;
            background: #1c1c26;
            border-radius: 99px;
            overflow: hidden;
            min-width: 40px;
        }
        .estado-summary__fill {
            height: 100%;
            border-radius: 99px;
            transition: width .8s cubic-bezier(.16,1,.3,1);
        }
        .estado-summary__pct {
            font-size: .75rem;
            font-weight: 500;
            min-width: 34px;
            text-align: right;
            flex-shrink: 0;
        }
        @media (max-width: 640px) {
            .estado-summary {
                flex-direction: column;
                gap: 1rem;
                padding: 1.1rem 1.2rem;
            }
            .estado-summary__sep {
                width: 100%;
                height: 1px;
                margin: 0;
            }
        }

        /* ── STATS SECTION HEADER MEJORADO ── */
        .stats-section-label {
            font-size: .6rem;
            letter-spacing: .22em;
            text-transform: uppercase;
            color: #d42b2b;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .stats-section-label::before {
            content: '';
            width: 20px;
            height: 1px;
            background: #d42b2b;
        }
        .stats-section-label .period-badge {
            margin-left: auto;
            background: rgba(212,43,43,.1);
            border: 1px solid rgba(212,43,43,.2);
            color: #d42b2b;
            padding: .15rem .5rem;
            border-radius: 20px;
            font-size: .58rem;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        /* ── KPI CARD EXTRA ── */
        .kpi-card-accent-bar {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 2px;
            background: var(--kpi-accent, #d42b2b);
            opacity: 0;
            transition: opacity .3s;
            border-radius: 0 0 14px 14px;
        }
        .kpi-card:hover .kpi-card-accent-bar { opacity: .4; }

        /* ── DONUT LEGEND PILLS ── */
        .donut-layout {
            display: flex;
            align-items: center;
            gap: 2rem;
            width: 100%;
            padding: .5rem 0;
        }
        .donut-svg-wrap {
            flex-shrink: 0;
            position: relative;
        }
        .donut-legend {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .75rem;
            flex: 1;
            min-width: 0;
        }
        .donut-legend-item {
            display: flex;
            align-items: center;
            gap: .75rem;
            background: #0d0d14;
            border: 1px solid #1c1c26;
            border-radius: 12px;
            padding: 1rem 1.1rem;
            transition: border-color .2s;
        }
        .donut-legend-item:hover { border-color: #2e2e3e; }
        .donut-legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .donut-legend-info { flex: 1; min-width: 0; }
        .donut-legend-num {
            font-family: 'Playfair Display', serif;
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1;
        }
        .donut-legend-lbl {
            font-size: .6rem;
            color: #7a7880;
            letter-spacing: .1em;
            text-transform: uppercase;
            margin-top: .3rem;
        }
        @media (max-width: 640px) {
            .donut-layout { flex-direction: column; align-items: center; }
            .donut-legend { grid-template-columns: 1fr 1fr; width: 100%; }
        }
    </style>
    <style id="cancel-reschedule-css">
        /* ── Panel lateral: Cancelar / Reprogramar ── */
        .cr-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .75);
            backdrop-filter: blur(6px);
            z-index: 1200;
            opacity: 0;
            pointer-events: none;
            transition: opacity .3s ease;
        }

        .cr-overlay.open {
            opacity: 1;
            pointer-events: all;
        }

        .cr-panel {
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            width: min(520px, 100vw);
            background: #111119;
            border-left: 1px solid #252530;
            z-index: 1201;
            display: flex;
            flex-direction: column;
            transform: translateX(100%);
            transition: transform .38s cubic-bezier(.16, 1, .3, 1);
            overflow: hidden;
        }

        .cr-panel.open {
            transform: translateX(0);
        }

        .cr-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #252530;
            flex-shrink: 0;
        }

        .cr-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem;
            font-weight: 700;
        }

        .cr-close {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: transparent;
            border: 1px solid #252530;
            color: #7a7880;
            cursor: pointer;
            font-size: .9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .2s;
        }

        .cr-close:hover {
            border-color: #d42b2b;
            color: #d42b2b;
        }

        .cr-body {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
        }

        .cr-body::-webkit-scrollbar {
            width: 4px;
        }

        .cr-body::-webkit-scrollbar-thumb {
            background: #252530;
            border-radius: 2px;
        }

        /* Reserva info badge */
        .cr-reserva-info {
            background: #18181f;
            border: 1px solid #2f2f3c;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 1.5rem;
        }

        .cr-reserva-info .ri-nombre {
            font-weight: 600;
            font-size: .95rem;
            margin-bottom: .25rem;
        }

        .cr-reserva-info .ri-detalle {
            font-size: .78rem;
            color: #7a7880;
            display: flex;
            flex-wrap: wrap;
            gap: .4rem;
        }

        .cr-reserva-info .ri-chip {
            padding: .15rem .5rem;
            border-radius: 100px;
            background: rgba(212, 43, 43, .1);
            border: 1px solid rgba(212, 43, 43, .2);
            color: #d42b2b;
            font-size: .7rem;
        }

        /* Mode tabs */
        .cr-mode-tabs {
            display: flex;
            gap: .5rem;
            margin-bottom: 1.5rem;
        }

        .cr-mode-tab {
            flex: 1;
            padding: .75rem .5rem;
            background: #18181f;
            border: 1px solid #252530;
            border-radius: 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #7a7880;
            cursor: pointer;
            transition: all .2s;
        }

        .cr-mode-tab:hover {
            border-color: #7a7880;
            color: #f0ece3;
        }

        .cr-mode-tab.active.cancel {
            background: rgba(107, 114, 128, .12);
            border-color: rgba(107, 114, 128, .5);
            color: #9ca3af;
        }

        .cr-mode-tab.active.reschedule {
            background: rgba(201, 168, 76, .1);
            border-color: rgba(201, 168, 76, .5);
            color: #c9a84c;
        }

        /* Panes */
        .cr-pane {
            display: none;
        }

        .cr-pane.active {
            display: block;
        }

        /* Form fields */
        .cr-field {
            display: flex;
            flex-direction: column;
            gap: .35rem;
            margin-bottom: 1rem;
        }

        .cr-field label {
            font-size: .65rem;
            letter-spacing: .15em;
            text-transform: uppercase;
            color: #7a7880;
        }

        .cr-field input,
        .cr-field select,
        .cr-field textarea {
            background: #18181f;
            border: 1px solid #252530;
            border-radius: 8px;
            padding: .75rem 1rem;
            color: #f0ece3;
            font-family: 'DM Sans', sans-serif;
            font-size: .88rem;
            transition: border-color .2s;
            width: 100%;
            box-sizing: border-box;
        }

        .cr-field input:focus,
        .cr-field select:focus,
        .cr-field textarea:focus {
            outline: none;
            border-color: var(--accent, #d42b2b);
        }

        .cr-field textarea {
            resize: vertical;
            min-height: 80px;
        }

        /* Slots grid */
        .cr-slots {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: .4rem;
            margin-bottom: 1rem;
        }

        .cr-slot {
            padding: .5rem .25rem;
            border: 1px solid #252530;
            border-radius: 6px;
            text-align: center;
            font-size: .78rem;
            color: #7a7880;
            cursor: pointer;
            transition: all .18s;
            background: #18181f;
        }

        .cr-slot:hover:not(.taken):not(.past) {
            border-color: #c9a84c;
            color: #c9a84c;
        }

        .cr-slot.selected {
            background: rgba(201, 168, 76, .12);
            border-color: #c9a84c;
            color: #c9a84c;
            font-weight: 600;
        }

        .cr-slot.taken {
            opacity: .35;
            cursor: not-allowed;
            text-decoration: line-through;
        }

        .cr-slot.past {
            opacity: .25;
            cursor: not-allowed;
            text-decoration: line-through;
        }

        .cr-slots-loading {
            text-align: center;
            padding: 1rem;
            color: #7a7880;
            font-size: .8rem;
            grid-column: 1/-1;
        }

        /* Buttons */
        .cr-btn-cancel {
            width: 100%;
            padding: .9rem;
            border-radius: 8px;
            background: rgba(107, 114, 128, .12);
            border: 1px solid rgba(107, 114, 128, .4);
            color: #9ca3af;
            font-family: 'DM Sans', sans-serif;
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            cursor: pointer;
            transition: all .25s;
        }

        .cr-btn-cancel:hover:not(:disabled) {
            background: #4b5563;
            color: #fff;
            border-color: #6b7280;
        }

        .cr-btn-reschedule {
            width: 100%;
            padding: .9rem;
            border-radius: 8px;
            background: linear-gradient(135deg, #c9a84c, #a17c2d);
            border: none;
            color: #000;
            font-family: 'DM Sans', sans-serif;
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            cursor: pointer;
            transition: all .25s;
            box-shadow: 0 4px 16px rgba(201, 168, 76, .2);
        }

        .cr-btn-reschedule:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(201, 168, 76, .35);
        }

        .cr-btn-reschedule:disabled,
        .cr-btn-cancel:disabled {
            opacity: .45;
            cursor: not-allowed;
            transform: none;
        }

        /* Warning box */
        .cr-warning {
            background: rgba(245, 158, 11, .06);
            border: 1px solid rgba(245, 158, 11, .2);
            border-radius: 8px;
            padding: .75rem 1rem;
            margin-bottom: 1rem;
            font-size: .78rem;
            color: #d4a84b;
            line-height: 1.6;
        }

        /* Status */
        .cr-status {
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .65rem 1rem;
            border-radius: 8px;
            font-size: .78rem;
            margin-top: .75rem;
            opacity: 0;
            transition: opacity .3s;
        }

        .cr-status.visible {
            opacity: 1;
        }

        .cr-status.ok {
            background: rgba(34, 197, 94, .1);
            border: 1px solid rgba(34, 197, 94, .25);
            color: #22c55e;
        }

        .cr-status.err {
            background: rgba(212, 43, 43, .1);
            border: 1px solid rgba(212, 43, 43, .25);
            color: #d42b2b;
        }

        /* Ronda badge */
        .cr-ronda-badge {
            display: inline-block;
            padding: .2rem .65rem;
            background: rgba(245, 158, 11, .12);
            border: 1px solid rgba(245, 158, 11, .3);
            border-radius: 100px;
            font-size: .7rem;
            color: #f59e0b;
            margin-bottom: 1rem;
        }

        /* Botón en tabla/cards */
        .btn-manage {
            padding: .32rem .65rem;
            border-radius: 4px;
            font-family: 'DM Sans', sans-serif;
            font-size: .67rem;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all .2s;
            background: rgba(107, 114, 128, .1);
            border-color: rgba(107, 114, 128, .3);
            color: #9ca3af;
            white-space: nowrap;
        }

        .btn-manage:hover {
            background: #374151;
            color: #fff;
            border-color: #4b5563;
        }

        /* Mobile card action button */
        .btn-manage-mobile {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            padding: .7rem .5rem;
            border-radius: 7px;
            font-family: 'DM Sans', sans-serif;
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            cursor: pointer;
            border: 1px solid rgba(107, 114, 128, .35);
            background: rgba(107, 114, 128, .1);
            color: #9ca3af;
            transition: all .22s;
        }

        .btn-manage-mobile:hover {
            background: #374151;
            color: #fff;
            border-color: #4b5563;
        }

        /* Estado reprogramar badge */
        .ebadge-reprogramar_barbero {
            background: rgba(201, 168, 76, .12);
            border: 1px solid rgba(201, 168, 76, .3);
            color: #c9a84c;
        }

        .ebadge-reprogramar_cliente {
            background: rgba(37, 80, 160, .12);
            border: 1px solid rgba(37, 80, 160, .35);
            color: #6b9fff;
        }

        .badge-reprogramar_barbero {
            background: rgba(201, 168, 76, .12);
            border: 1px solid rgba(201, 168, 76, .3);
            color: #c9a84c;
        }

        .badge-reprogramar_cliente {
            background: rgba(37, 80, 160, .12);
            border: 1px solid rgba(37, 80, 160, .35);
            color: #6b9fff;
        }
    </style>
    <style id="sh-style">
        /* ── Selector de fecha ── */
        .sh-date-row {
            display: flex;
            gap: .5rem;
            align-items: center;
            margin-bottom: .75rem;
        }

        .sh-date-input {
            flex: 1;
            background: #18181f;
            border: 1px solid #252530;
            border-radius: 8px;
            padding: .65rem 1rem;
            color: #f0ece3;
            font-family: 'DM Sans', sans-serif;
            font-size: .88rem;
            transition: border-color .2s;
            -webkit-appearance: none;
            appearance: none;
        }

        .sh-date-input:focus {
            outline: none;
            border-color: #d42b2b;
        }

        .sh-date-row .adp-wrap { flex: 1; }
        .sh-date-row .adp-trigger { border-radius: 8px; padding: .65rem 1rem; font-size: .88rem; }

        .sh-today-btn {
            padding: .65rem 1rem;
            background: rgba(212, 43, 43, .1);
            border: 1px solid rgba(212, 43, 43, .3);
            border-radius: 8px;
            color: #d42b2b;
            font-family: 'DM Sans', sans-serif;
            font-size: .75rem;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
            cursor: pointer;
            transition: all .2s;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .sh-today-btn:hover {
            background: #d42b2b;
            color: #fff;
        }

        /* ── Info del día ── */
        .sh-day-info {
            margin-bottom: .5rem;
        }

        .sh-day-info-label {
            font-family: 'Playfair Display', serif;
            font-size: 1rem;
            font-weight: 700;
            color: #f0ece3;
            margin-bottom: .35rem;
        }

        .sh-day-blocked-warn {
            background: rgba(212, 43, 43, .08);
            border: 1px solid rgba(212, 43, 43, .25);
            border-radius: 8px;
            padding: .65rem 1rem;
            font-size: .78rem;
            color: #d4534b;
            line-height: 1.5;
        }

        /* ── Leyenda ── */
        .sh-legend {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            margin-left: auto;
            font-size: .62rem;
            color: #7a7880;
            text-transform: none;
            letter-spacing: 0;
            font-weight: 400;
        }

        .sh-leg-dot {
            width: 8px;
            height: 8px;
            border-radius: 2px;
            flex-shrink: 0;
        }

        .sh-leg-free {
            background: rgba(245, 240, 232, .08);
            border: 1px solid #252530;
        }

        .sh-leg-blocked {
            background: rgba(212, 43, 43, .35);
            border: 1px solid rgba(212, 43, 43, .6);
        }

        .sh-leg-reserved {
            background: rgba(34, 197, 94, .15);
            border: 1px solid rgba(34, 197, 94, .35);
        }

        /* ── Loading ── */
        .sh-loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .75rem;
            padding: 2rem;
            color: #7a7880;
            font-size: .8rem;
        }

        .sh-spinner {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 2px solid rgba(212, 43, 43, .15);
            border-top-color: #d42b2b;
            animation: shSpin .8s linear infinite;
        }

        @keyframes shSpin {
            to {
                transform: rotate(360deg);
            }
        }

        /* ── Estado vacío ── */
        .sh-empty-state {
            text-align: center;
            color: #7a7880;
            font-size: .8rem;
            padding: 2rem 1rem;
            border: 1px dashed #252530;
            border-radius: 10px;
        }

        /* ── Turno label ── */
        .sh-turno-label {
            font-size: .6rem;
            letter-spacing: .2em;
            text-transform: uppercase;
            color: #7a7880;
            margin-bottom: .5rem;
        }

        /* ── Grid de slots ── */
        .sh-slots-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: .4rem;
            margin-bottom: .25rem;
        }

        /* ── Slot chip ── */
        .sh-slot {
            position: relative;
            padding: .55rem .25rem;
            border-radius: 7px;
            text-align: center;
            font-size: .78rem;
            font-family: 'DM Sans', sans-serif;
            font-weight: 500;
            cursor: pointer;
            transition: all .18s;
            user-select: none;
            border: 1px solid transparent;
        }

        /* Estado: libre */
        .sh-slot.sh-free {
            background: rgba(245, 240, 232, .04);
            border-color: #252530;
            color: #7a7880;
        }

        .sh-slot.sh-free:hover {
            border-color: rgba(212, 43, 43, .4);
            color: #d42b2b;
            background: rgba(212, 43, 43, .06);
        }

        /* Estado: pendiente de bloquear */
        .sh-slot.sh-pending-block {
            background: rgba(212, 43, 43, .15);
            border-color: rgba(212, 43, 43, .5);
            color: #d42b2b;
            font-weight: 700;
        }

        .sh-slot.sh-pending-block::after {
            content: '+';
            position: absolute;
            top: 2px;
            right: 4px;
            font-size: .6rem;
            opacity: .7;
        }

        /* Estado: bloqueado ya guardado */
        .sh-slot.sh-blocked {
            background: rgba(212, 43, 43, .25);
            border-color: rgba(212, 43, 43, .6);
            color: #ff8080;
            font-weight: 600;
        }

        .sh-slot.sh-blocked::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 6px;
            background: repeating-linear-gradient(-45deg,
                    rgba(212, 43, 43, .15) 0px, rgba(212, 43, 43, .15) 2px,
                    transparent 2px, transparent 5px);
            pointer-events: none;
        }

        .sh-slot.sh-blocked:hover {
            background: rgba(212, 43, 43, .12);
            border-color: rgba(212, 43, 43, .3);
            color: #9ca3af;
        }

        /* Estado: pendiente de desbloquear */
        .sh-slot.sh-pending-unblock {
            background: rgba(107, 114, 128, .1);
            border-color: rgba(107, 114, 128, .35);
            color: #9ca3af;
            text-decoration: line-through;
        }

        .sh-slot.sh-pending-unblock::after {
            content: '−';
            position: absolute;
            top: 2px;
            right: 4px;
            font-size: .6rem;
            opacity: .7;
            text-decoration: none;
        }

        /* Estado: reservado (no editable) */
        .sh-slot.sh-reserved {
            background: rgba(34, 197, 94, .08);
            border-color: rgba(34, 197, 94, .25);
            color: #22c55e;
            cursor: not-allowed;
            opacity: .85;
        }

        .sh-slot.sh-reserved::after {
            content: '●';
            position: absolute;
            top: 3px;
            right: 4px;
            font-size: .5rem;
            color: rgba(34, 197, 94, .7);
        }

        /* ── Acciones rápidas ── */
        .sh-quick-actions {
            display: flex;
            gap: .5rem;
            margin-top: 1rem;
            margin-bottom: .75rem;
        }

        .sh-quick-btn {
            flex: 1;
            padding: .55rem .5rem;
            border-radius: 7px;
            font-family: 'DM Sans', sans-serif;
            font-size: .68rem;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            cursor: pointer;
            transition: all .2s;
            border: 1px solid transparent;
        }

        .sh-quick-block-all {
            background: rgba(212, 43, 43, .08);
            border-color: rgba(212, 43, 43, .3);
            color: #d42b2b;
        }

        .sh-quick-block-all:hover {
            background: #d42b2b;
            color: #fff;
            border-color: #d42b2b;
        }

        .sh-quick-unblock-all {
            background: transparent;
            border-color: #252530;
            color: #7a7880;
        }

        .sh-quick-unblock-all:hover {
            border-color: #7a7880;
            color: #f0ece3;
        }

        /* ── Motivo input ── */
        .sh-motivo-row {
            margin-bottom: .75rem;
        }

        .sh-motivo-input {
            width: 100%;
            background: #18181f;
            border: 1px solid #252530;
            border-radius: 8px;
            padding: .65rem 1rem;
            color: #f0ece3;
            font-family: 'DM Sans', sans-serif;
            font-size: .85rem;
            transition: border-color .2s;
            box-sizing: border-box;
        }

        .sh-motivo-input:focus {
            outline: none;
            border-color: #d42b2b;
        }

        .sh-motivo-input::placeholder {
            color: #4a4a58;
        }

        /* ── Botón guardar ── */
        .sh-save-btn {
            width: 100%;
            background: linear-gradient(135deg, #d42b2b 0%, #a81e1e 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: .85rem 1rem;
            font-family: 'DM Sans', sans-serif;
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            cursor: pointer;
            transition: all .25s;
            box-shadow: 0 4px 16px rgba(212, 43, 43, .25);
        }

        .sh-save-btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(212, 43, 43, .4);
        }

        .sh-save-btn:disabled {
            opacity: .4;
            cursor: not-allowed;
            transform: none;
        }

        /* ── Resumen de cambios pendientes ── */
        .sh-pending-summary {
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .5rem .85rem;
            background: rgba(245, 158, 11, .06);
            border: 1px solid rgba(245, 158, 11, .2);
            border-radius: 7px;
            font-size: .75rem;
            color: #d4a84b;
            margin-bottom: .75rem;
        }

        /* ══ HORARIO DEL NEGOCIO ═══════════════════════════════ */
        .hn-card {
            background: #111119;
            border: 1px solid #252530;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .hn-period {
            margin-bottom: .75rem;
        }
        .hn-period-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: .5rem;
        }
        .hn-period-name {
            font-size: .8rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #c9a84c;
        }
        .hn-period-body {
            transition: opacity .2s;
        }
        .hn-period-body.disabled {
            opacity: .3;
            pointer-events: none;
        }
        .hn-time-pair {
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .hn-time-field {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: .25rem;
        }
        .hn-time-field label {
            font-size: .68rem;
            color: #7a7880;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .hn-time-field input[type="time"] {
            background: #18181f;
            border: 1px solid #252530;
            border-radius: 7px;
            padding: .5rem .75rem;
            color: #f0ece3;
            font-family: 'DM Sans', sans-serif;
            font-size: .85rem;
            width: 100%;
            box-sizing: border-box;
            transition: border-color .2s;
        }
        .hn-time-field input[type="time"]:focus {
            outline: none;
            border-color: #d42b2b;
        }
        .hn-time-sep {
            color: #4a4a58;
            font-size: 1rem;
            margin-top: 1.1rem;
            flex-shrink: 0;
        }
        .hn-divider {
            height: 1px;
            background: #252530;
            border: none;
            margin: .75rem 0;
        }
        .hn-interval-row {
            display: flex;
            flex-direction: column;
            gap: .4rem;
            margin-top: .25rem;
        }
        .hn-interval-label {
            font-size: .72rem;
            color: #7a7880;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .hn-interval-btns {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem;
        }
        .hn-int-btn {
            background: #18181f;
            border: 1px solid #252530;
            border-radius: 6px;
            padding: .35rem .75rem;
            color: #a8a4b0;
            font-family: 'DM Sans', sans-serif;
            font-size: .75rem;
            cursor: pointer;
            transition: all .18s;
        }
        .hn-int-btn:hover {
            border-color: #d42b2b;
            color: #d42b2b;
        }
        .hn-int-btn.selected {
            background: rgba(212, 43, 43, .15);
            border-color: rgba(212, 43, 43, .5);
            color: #d42b2b;
            font-weight: 700;
        }
        .hn-preview-wrap {
            margin-top: .85rem;
            padding-top: .85rem;
            border-top: 1px solid #1c1c26;
        }
        .hn-preview-slots {
            display: flex;
            flex-wrap: wrap;
            gap: .3rem;
            min-height: 1.8rem;
        }
        .hn-preview-slot {
            background: rgba(37, 80, 160, .15);
            border: 1px solid rgba(37, 80, 160, .3);
            border-radius: 5px;
            padding: .2rem .5rem;
            font-size: .72rem;
            color: #6e9af0;
            font-family: 'DM Mono', monospace;
        }
        .hn-section-sep {
            height: 1px;
            background: #252530;
            margin: 1.25rem 0;
        }
        .hn-days-row {
            display: flex;
            flex-direction: column;
            gap: .4rem;
            margin-top: .25rem;
        }
        .hn-days-label {
            font-size: .72rem;
            color: #7a7880;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .hn-days-grid {
            display: flex;
            gap: .35rem;
            flex-wrap: wrap;
        }
        .hn-day-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 1px solid #252530;
            background: #18181f;
            color: #a8a4b0;
            font-family: 'DM Sans', sans-serif;
            font-size: .72rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .18s;
            flex-shrink: 0;
        }
        .hn-day-btn:hover {
            border-color: #d42b2b;
            color: #d42b2b;
        }
        .hn-day-btn.active {
            background: rgba(212, 43, 43, .18);
            border-color: rgba(212, 43, 43, .55);
            color: #e06060;
            font-weight: 700;
        }
    </style>
    </style>
</head>

<body>

    <div class="admin-header">
        <div style="display:flex;align-items:center;gap:.8rem;">
            <a href="../index.html" class="home-btn" title="Ir a la web principal"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 12L12 3l9 9" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 10v9a1 1 0 001 1h4v-4h4v4h4a1 1 0 001-1v-9" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
            <div class="admin-brand">Prado <span>Barber</span> · Admin</div>
        </div>
        <div class="header-actions">
            <button class="stats-trigger-btn" onclick="openStats()" title="Estadísticas"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="12" width="4" height="8" rx="1.5" fill="white" opacity="0.9"/><rect x="10" y="7" width="4" height="13" rx="1.5" fill="white"/><rect x="17" y="4" width="4" height="16" rx="1.5" fill="white" opacity="0.75"/></svg></button>
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
                <span><strong><?= $statsPend['total'] ?> reserva<?= $statsPend['total'] != 1 ? 's' : '' ?> pendiente<?= $statsPend['total'] != 1 ? 's' : '' ?></strong> sin confirmar</span>
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
                        <option value="todos" <?= $filtroBarbero === 'todos' ? 'selected' : '' ?>>Todos</option>
                        <?php foreach ($barberos as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $filtroBarbero === $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="frow">
                    <span class="flabel">Estado</span>
                    <select name="estado">
                        <option value="todos" <?= $filtroEstado === 'todos'    ? 'selected' : '' ?>>Todos</option>
                        <option value="pendiente" <?= $filtroEstado === 'pendiente' ? 'selected' : '' ?>>⏳ Pendientes</option>
                        <option value="aceptada" <?= $filtroEstado === 'aceptada' ? 'selected' : '' ?>>✓ Aceptadas</option>
                        <option value="denegada" <?= $filtroEstado === 'denegada' ? 'selected' : '' ?>>✕ Denegadas</option>
                        <option value="cancelada" <?= $filtroEstado === 'cancelada' ? 'selected' : '' ?>>✕ Canceladas</option>
                        <option value="reprogramar_barbero" <?= $filtroEstado === 'reprogramar_barbero' ? 'selected' : '' ?>>⇄ Prop. barbero</option>
                        <option value="reprogramar_cliente" <?= $filtroEstado === 'reprogramar_cliente' ? 'selected' : '' ?>>⇄ Prop. cliente</option>
                    </select>
                </div>
                <div class="frow">
                    <span class="flabel">Fecha</span>
                    <select name="fecha" onchange="this.form.submit()">
                        <option value="hoy" <?= $filtroFecha === 'hoy'      ? 'selected' : '' ?>>Hoy</option>
                        <option value="manana" <?= $filtroFecha === 'manana'   ? 'selected' : '' ?>>Mañana</option>
                        <option value="semana" <?= $filtroFecha === 'semana'   ? 'selected' : '' ?>>Próximos 7 días</option>
                        <option value="proximas" <?= $filtroFecha === 'proximas' ? 'selected' : '' ?>>Próximas (futuras)</option>
                        <option value="todas" <?= $filtroFecha === 'todas'    ? 'selected' : '' ?>>Todas</option>
                        <option value="pasadas" <?= $filtroFecha === 'pasadas'  ? 'selected' : '' ?>>Anteriores</option>
                        <option value="custom" <?= $filtroFecha === 'custom'   ? 'selected' : '' ?>>Fecha específica</option>
                    </select>
                </div>
                <?php if ($filtroFecha === 'custom'): ?>
                    <div class="frow">
                        <span class="flabel">Día</span>
                        <input type="date" id="fecha-custom-input" name="fecha_custom" value="<?= htmlspecialchars($fechaCustom) ?>" />
                    </div>
                <?php endif; ?>
                <button type="submit" class="filter-submit">Filtrar</button>
            </form>
        </div>

        <div class="section-header">
            <div class="section-title-admin">Reservas</div>
            <div style="display:flex;align-items:center;gap:.75rem;">
                <div class="section-count"><?= count($reservas) ?> resultado<?= count($reservas) != 1 ? 's' : '' ?></div>
                <button class="reload-btn" onclick="this.classList.remove('spinning');void this.offsetWidth;this.classList.add('spinning');setTimeout(()=>location.reload(),400)" style="width:32px;height:32px;border-radius:50%;background:transparent;border:1px solid #252530;color:#7a7880;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.9rem;transition:border-color .3s,color .3s;" onmouseover="this.style.borderColor='#d42b2b';this.style.color='#d42b2b';" onmouseout="this.style.borderColor='#252530';this.style.color='#7a7880';" title="Recargar reservas">↻</button>
            </div>
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
                    $fechaStr = $diasES[$diaNum] . ' ' . $dt->format('j') . ' ' . $mesesES[$mesNum];
                    $hora    = substr($r['hora'], 0, 5);
                    $est     = $r['estado'];
                ?>
                    <div class="rc <?= $est ?>"
                        data-token="<?= htmlspecialchars($r['token']) ?>"
                        data-barbero-id="<?= htmlspecialchars($r['barbero_id'] ?? '') ?>"
                        data-fecha="<?= $r['fecha'] ?>"
                        data-creado="<?= $r['creado_en'] ?>"
                        data-nueva-fecha="<?= htmlspecialchars($r['nueva_fecha_propuesta'] ?? '') ?>"
                        data-nueva-hora="<?= htmlspecialchars(substr($r['nueva_hora_propuesta'] ?? '', 0, 5)) ?>"
                        data-motivo="<?= htmlspecialchars($r['motivo_cambio'] ?? '') ?>"
                        data-ronda="<?= (int)($r['ronda_negociacion'] ?? 0) ?>"
                        data-estado="<?= $r['estado'] ?>">
                        <div class="rc-top">
                            <div class="rc-top-left">
                                <div class="rc-id">#<?= $r['id'] ?></div>
                                <div class="rc-hora"><?= $hora ?></div>
                                <div class="rc-fecha"><?= $fechaStr ?></div>
                            </div>
                            <?php if ($est === 'pendiente'): ?>
                                <span class="ebadge ebadge-pendiente">⏳ Pendiente</span>
                            <?php elseif ($est === 'aceptada'): ?>
                                <span class="ebadge ebadge-aceptada">✓ Aceptada</span>
                            <?php elseif ($est === 'denegada'): ?>
                                <span class="ebadge ebadge-denegada">✕ Denegada</span>
                            <?php elseif ($est === 'cancelada'): ?>
                                <span class="ebadge ebadge-cancelada">✕ Cancelada</span>
                            <?php elseif ($est === 'reprogramar_barbero'): ?>
                                <span class="ebadge ebadge-reprogramar_barbero">⇄ Prop. barbero</span>
                            <?php elseif ($est === 'reprogramar_cliente'): ?>
                                <span class="ebadge ebadge-reprogramar_cliente">⇄ Prop. cliente</span>
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
                                    <div class="rc-detail-value gold"><?= number_format($r['precio'], 0) ?> €</div>
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
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- ═══ DESKTOP TABLE ═══ -->
            <div class="table-desktop">
                <div class="table-wrap-d">
                    <div class="table-header-d">
                        <div class="table-title-d">Reservas</div>
                        <div style="display:flex;align-items:center;gap:.75rem;">
                            <div style="font-size:.75rem;color:#7a7880;"><?= count($reservas) ?> resultado<?= count($reservas) != 1 ? 's' : '' ?></div>
                            <button class="reload-btn" onclick="this.classList.remove('spinning');void this.offsetWidth;this.classList.add('spinning');setTimeout(()=>location.reload(),400)" style="width:32px;height:32px;border-radius:50%;background:transparent;border:1px solid #252530;color:#7a7880;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.9rem;transition:border-color .3s,color .3s;" onmouseover="this.style.borderColor='#d42b2b';this.style.color='#d42b2b';" onmouseout="this.style.borderColor='#252530';this.style.color='#7a7880';" title="Recargar reservas">↻</button>
                        </div>
                    </div>
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
                                <th>Estado</th>
                                <th>Notas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservas as $r):
                                $dt      = new DateTime($r['fecha']);
                                $diaNum  = (int)$dt->format('w');
                                $mesNum  = (int)$dt->format('n');
                                $fechaStr = $diasES[$diaNum] . ' ' . $dt->format('j') . ' ' . $mesesES[$mesNum];
                                $rowStyle = $r['estado'] === 'denegada' ? 'opacity:.55;' : '';
                            ?>
                                <tr style="<?= $rowStyle ?>"
                                    data-token="<?= htmlspecialchars($r['token']) ?>"
                                    data-barbero-id="<?= htmlspecialchars($r['barbero_id'] ?? '') ?>"
                                    data-fecha="<?= $r['fecha'] ?>"
                                    data-creado="<?= $r['creado_en'] ?>"
                                    data-nueva-fecha="<?= htmlspecialchars($r['nueva_fecha_propuesta'] ?? '') ?>"
                                    data-nueva-hora="<?= htmlspecialchars(substr($r['nueva_hora_propuesta'] ?? '', 0, 5)) ?>"
                                    data-motivo="<?= htmlspecialchars($r['motivo_cambio'] ?? '') ?>"
                                    data-ronda="<?= (int)($r['ronda_negociacion'] ?? 0) ?>"
                                    data-estado="<?= $r['estado'] ?>">
                                    <td style="color:#7a7880;font-size:.75rem;">#<?= $r['id'] ?></td>
                                    <td style="white-space:nowrap;"><?= $fechaStr ?></td>
                                    <td class="td-hora"><?= substr($r['hora'], 0, 5) ?></td>
                                    <td class="td-cliente">
                                        <strong><?= htmlspecialchars($r['cliente_nombre']) ?></strong>
                                        <span><?= htmlspecialchars($r['cliente_email']) ?></span>
                                        <span><?= htmlspecialchars($r['cliente_telefono']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($r['servicio']) ?><br><span style="font-size:.72rem;color:#7a7880;"><?= $r['duracion'] ?></span></td>
                                    <td class="td-precio"><?= number_format($r['precio'], 0) ?> €</td>
                                    <td class="td-barbero"><span class="b-badge"><?= htmlspecialchars($r['barbero']) ?></span></td>
                                    <td>
                                        <?php if ($r['estado'] === 'pendiente'): ?>
                                            <span class="estado-badge badge-pendiente">⏳ Pendiente</span>
                                        <?php elseif ($r['estado'] === 'aceptada'): ?>
                                            <span class="estado-badge badge-aceptada">✓ Aceptada</span>
                                        <?php elseif ($r['estado'] === 'denegada'): ?>
                                            <span class="estado-badge badge-denegada">✕ Denegada</span>
                                        <?php elseif ($r['estado'] === 'cancelada'): ?>
                                            <span class="estado-badge badge-cancelada">✕ Cancelada</span>
                                        <?php elseif ($r['estado'] === 'reprogramar_barbero'): ?>
                                            <span class="estado-badge badge-reprogramar_barbero">⇄ Prop. barbero</span>
                                        <?php elseif ($r['estado'] === 'reprogramar_cliente'): ?>
                                            <span class="estado-badge badge-reprogramar_cliente">⇄ Prop. cliente</span>
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
                    <div class="stats-title-icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="12" width="4" height="9" rx="1.5" fill="white" opacity="0.85"/><rect x="10" y="7" width="4" height="14" rx="1.5" fill="white"/><rect x="17" y="3" width="4" height="18" rx="1.5" fill="white" opacity="0.7"/></svg></div>
                    Estadísticas &amp; Analytics
                </div>
                <button class="stats-close" onclick="closeStats()">✕</button>
            </div>
            <div class="stats-period-bar" id="stats-period-bar">
                <span class="stats-period-pill" id="stats-period-pill"></span>
                <button class="stats-period-btn" data-periodo="hoy"       onclick="changePeriodo('hoy')">Hoy</button>
                <button class="stats-period-btn" data-periodo="semana"    onclick="changePeriodo('semana')">Semana</button>
                <button class="stats-period-btn" data-periodo="mes"       onclick="changePeriodo('mes')">Mes</button>
                <button class="stats-period-btn" data-periodo="trimestre" onclick="changePeriodo('trimestre')">Trimestre</button>
                <button class="stats-period-btn" data-periodo="año"       onclick="changePeriodo('año')">Año</button>
                <button class="stats-period-btn active" data-periodo="todo" onclick="changePeriodo('todo')">Todo</button>
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
        <div class="cfg-tabs" id="cfg-tabs">
            <button class="cfg-tab active" onclick="switchTab('auto')" title="Auto-aceptar">Auto</button>
            <button class="cfg-tab" onclick="switchTab('vac')">Vacaciones</button>
            <button class="cfg-tab" onclick="switchTab('horarios')">Horarios</button>
            <button class="cfg-tab" onclick="switchTab('datos')">Datos</button>
            <span class="cfg-tab-indicator" id="cfg-tab-indicator"></span>
        </div>
        <div class="cfg-body">

            <!-- ── TAB: AUTO-ACEPTAR ── -->
            <div class="cfg-pane visible active" id="pane-auto">
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
                        <button class="alcance-btn" data-val="hoy" onclick="selectAlcance(this)">Hoy</button>
                        <button class="alcance-btn" data-val="semana" onclick="selectAlcance(this)">Esta semana</button>
                        <button class="alcance-btn" data-val="mes" onclick="selectAlcance(this)">Este mes</button>
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
                    <div class="cal-legend-item">
                        <div class="cal-legend-dot blocked"></div><span>Ya bloqueado (clic para desbloquear)</span>
                    </div>
                    <div class="cal-legend-item">
                        <div class="cal-legend-dot pending"></div><span>Pendiente de guardar</span>
                    </div>
                    <div class="cal-legend-item">
                        <div class="cal-legend-dot unblocking"></div><span>Pendiente de desbloquear</span>
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

            <!-- ══ PESTAÑA HORARIOS: HTML ══════════════════════════════════════ -->
            <div class="cfg-pane" id="pane-horarios">

                <!-- ── Horario global del negocio ── -->
                <div class="cfg-section-label">Horario del negocio</div>
                <div class="hn-card">

                    <!-- Mañana -->
                    <div class="hn-period">
                        <div class="hn-period-head">
                            <span class="hn-period-name">Mañana</span>
                            <label class="toggle-switch">
                                <input type="checkbox" id="hn-manana-activo" onchange="hnTogglePeriod('manana')">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="hn-period-body" id="hn-manana-body">
                            <div class="hn-time-pair">
                                <div class="hn-time-field">
                                    <label>Apertura</label>
                                    <input type="time" id="hn-manana-inicio" oninput="hnUpdatePreview()">
                                </div>
                                <span class="hn-time-sep">—</span>
                                <div class="hn-time-field">
                                    <label>Cierre</label>
                                    <input type="time" id="hn-manana-fin" oninput="hnUpdatePreview()">
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="hn-divider">

                    <!-- Tarde -->
                    <div class="hn-period">
                        <div class="hn-period-head">
                            <span class="hn-period-name">Tarde</span>
                            <label class="toggle-switch">
                                <input type="checkbox" id="hn-tarde-activo" onchange="hnTogglePeriod('tarde')">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="hn-period-body" id="hn-tarde-body">
                            <div class="hn-time-pair">
                                <div class="hn-time-field">
                                    <label>Apertura</label>
                                    <input type="time" id="hn-tarde-inicio" oninput="hnUpdatePreview()">
                                </div>
                                <span class="hn-time-sep">—</span>
                                <div class="hn-time-field">
                                    <label>Cierre</label>
                                    <input type="time" id="hn-tarde-fin" oninput="hnUpdatePreview()">
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="hn-divider">

                    <!-- Días de apertura -->
                    <div class="hn-days-row">
                        <span class="hn-days-label">Días de apertura</span>
                        <div class="hn-days-grid">
                            <button class="hn-day-btn active" data-day="1" onclick="hnToggleDay(this)" title="Lunes">L</button>
                            <button class="hn-day-btn active" data-day="2" onclick="hnToggleDay(this)" title="Martes">M</button>
                            <button class="hn-day-btn active" data-day="3" onclick="hnToggleDay(this)" title="Miércoles">X</button>
                            <button class="hn-day-btn active" data-day="4" onclick="hnToggleDay(this)" title="Jueves">J</button>
                            <button class="hn-day-btn active" data-day="5" onclick="hnToggleDay(this)" title="Viernes">V</button>
                            <button class="hn-day-btn active" data-day="6" onclick="hnToggleDay(this)" title="Sábado">S</button>
                            <button class="hn-day-btn" data-day="0" onclick="hnToggleDay(this)" title="Domingo">D</button>
                        </div>
                    </div>

                    <hr class="hn-divider">

                    <!-- Intervalo -->
                    <div class="hn-interval-row">
                        <span class="hn-interval-label">Intervalo entre citas</span>
                        <div class="hn-interval-btns" id="hn-interval-btns">
                            <button class="hn-int-btn" data-val="15" onclick="hnSelectInterval(this)">15 min</button>
                            <button class="hn-int-btn" data-val="20" onclick="hnSelectInterval(this)">20 min</button>
                            <button class="hn-int-btn selected" data-val="30" onclick="hnSelectInterval(this)">30 min</button>
                            <button class="hn-int-btn" data-val="40" onclick="hnSelectInterval(this)">40 min</button>
                            <button class="hn-int-btn" data-val="45" onclick="hnSelectInterval(this)">45 min</button>
                            <button class="hn-int-btn" data-val="60" onclick="hnSelectInterval(this)">60 min</button>
                        </div>
                    </div>

                    <!-- Vista previa -->
                    <div class="hn-preview-wrap">
                        <div class="cfg-section-label" style="margin-bottom:.5rem;margin-top:0;">Vista previa</div>
                        <div class="hn-preview-slots" id="hn-preview-slots"></div>
                    </div>

                    <button class="cfg-save-btn" id="hn-save-btn" onclick="hnSave()" style="margin-top:1rem;">Guardar horario</button>
                    <div class="cfg-status" id="hn-status"></div>
                </div>

                <div class="hn-section-sep"></div>
                <div class="cfg-section-label">Bloquear horarios del día</div>

                <!-- Selector de fecha -->
                <div class="sh-date-row">
                    <input type="date" id="sh-fecha-input" class="sh-date-input"
                        onchange="shOnFechaChange()" />
                    <button class="sh-today-btn" onclick="shSetToday()">Hoy</button>
                </div>

                <!-- Info del día seleccionado -->
                <div class="sh-day-info" id="sh-day-info" style="display:none;">
                    <div class="sh-day-info-label" id="sh-day-label">—</div>
                    <div class="sh-day-blocked-warn" id="sh-day-blocked-warn" style="display:none;">
                        🔒 Este día está bloqueado por vacaciones — los clientes no pueden reservar ningún horario.
                    </div>
                </div>

                <!-- Grid de slots -->
                <div class="cfg-section-label" style="margin-top:1.25rem;">
                    Horarios disponibles
                    <span class="sh-legend">
                        <span class="sh-leg-dot sh-leg-free"></span>Libre
                        <span class="sh-leg-dot sh-leg-blocked"></span>Bloqueado
                        <span class="sh-leg-dot sh-leg-reserved"></span>Reservado
                    </span>
                </div>

                <div class="sh-loading" id="sh-loading" style="display:none;">
                    <div class="sh-spinner"></div>
                    <span>Cargando horarios…</span>
                </div>

                <div class="sh-empty-state" id="sh-empty-state">
                    Selecciona un día para gestionar sus horarios.
                </div>

                <!-- Turnos: mañana y tarde -->
                <div id="sh-slots-container" style="display:none;">
                    <div class="sh-turno-label">Mañana</div>
                    <div class="sh-slots-grid" id="sh-grid-morning"></div>
                    <div class="sh-turno-label" style="margin-top:1rem;">Tarde</div>
                    <div class="sh-slots-grid" id="sh-grid-afternoon"></div>

                    <!-- Acciones rápidas -->
                    <div class="sh-quick-actions">
                        <button class="sh-quick-btn sh-quick-block-all"
                            onclick="shBlockAll()">🔒 Bloquear todos los libres</button>
                        <button class="sh-quick-btn sh-quick-unblock-all"
                            onclick="shUnblockAll()">🔓 Liberar todos</button>
                    </div>

                    <!-- Motivo -->
                    <div class="sh-motivo-row">
                        <input type="text" id="sh-motivo-input" class="sh-motivo-input"
                            placeholder="Motivo del bloqueo (ej: Formación, Descanso…)" maxlength="100" />
                    </div>

                    <!-- Guardar -->
                    <button class="sh-save-btn" id="sh-save-btn"
                        onclick="shSavePending()" disabled>
                        Guardar cambios
                    </button>
                    <div class="cfg-status" id="sh-status"></div>
                </div>
            </div>

            <!-- ══ PESTAÑA DATOS: HTML ══════════════════════════════════════ -->
            <div class="cfg-pane" id="pane-datos">

                <!-- ── Barberos ── -->
                <div class="cfg-section-label">Barberos</div>
                <div id="barberos-list"></div>
                <button class="datos-add-btn" style="margin-top:.25rem;" onclick="abrirFormBarbero(null)">
                    + Añadir barbero
                </button>

                <!-- ── Servicios ── -->
                <div class="cfg-section-label" style="margin-top:1.75rem;">Servicios</div>
                <div id="servicios-list"></div>

                <div class="cfg-status" id="datos-status"></div>
            </div>

            <!-- ══ MODAL EDICIÓN ════════════════════════════════════════════ -->
            <div class="datos-modal-overlay" id="datos-modal-overlay" onclick="cerrarModal()">
                <div class="datos-modal" onclick="event.stopPropagation()">
                    <div class="datos-modal-header">
                        <span id="datos-modal-title">Editar</span>
                        <button class="cfg-close" onclick="cerrarModal()">✕</button>
                    </div>
                    <div class="datos-modal-body" id="datos-modal-body"></div>
                    <div class="datos-modal-footer">
                        <button class="vac-btn vac-btn-clear" onclick="cerrarModal()">Cancelar</button>
                        <button class="cfg-save-btn" id="datos-modal-save" onclick="guardarModal()"
                            style="width:auto;padding:.7rem 1.75rem;">Guardar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- ================================================================
     STATS JAVASCRIPT
================================================================ -->
    <script>
        // ================================================================
        //  PRADO BARBER CO. — Script completo corregido para admin.php
        //  Sustituye el bloque <script> al final del body en admin.php
        //
        //  CORRECCIONES:
        //  1. Panel de configuración (openCfg/closeCfg/switchTab/etc.) — AÑADIDO
        //  2. Heatmap rediseñado: más compacto y claro
        //  3. Bar chart: espacio vertical corregido
        //  4. Servicios: muestra dinero perdido con tachado + canceladas
        //  5. Donut conversión: multicolor (verde/rojo/naranja), sin recorte
        // ================================================================

        // ── Slots dinámicos (cargados desde horario-negocio.php) ─────────
        window._MORNING_SLOTS   = ['09:00','09:30','10:00','10:30','11:00','11:30','12:00','12:30','13:00','13:30'];
        window._AFTERNOON_SLOTS = ['16:00','16:30','17:00','17:30','18:00','18:30','19:00','19:30'];
        window._ALL_SLOTS       = [...window._MORNING_SLOTS, ...window._AFTERNOON_SLOTS];
        window._OPEN_DAYS       = [1, 2, 3, 4, 5, 6];

        (async function loadDynamicSlots() {
            try {
                const r = await fetch('./api/horario-negocio.php?slots=1');
                const j = await r.json();
                if (j.ok) {
                    window._MORNING_SLOTS   = j.data.manana        || [];
                    window._AFTERNOON_SLOTS = j.data.tarde         || [];
                    window._ALL_SLOTS       = j.data.todos         || [];
                    window._OPEN_DAYS       = j.data.dias_abiertos || [1,2,3,4,5,6];
                }
            } catch (e) {}
        })();

        // ── CONFIG PANEL ────────────────────────────────────────────────
        (function initCfgPanel() {
            'use strict';
            const CFG_API = './api/settings.php';
            let mcDate = new Date();
            let blockedDates = {};
            let pendingAdd = [];
            let pendingDel = [];
            let rangeMode = false;
            let rangeStart = null;

            function initCfgIndicator() {
                const activeBtn  = document.querySelector('.cfg-tab.active');
                const indicator  = document.getElementById('cfg-tab-indicator');
                const tabsEl     = document.getElementById('cfg-tabs');
                if (!activeBtn || !indicator || !tabsEl) return;
                const tabsRect = tabsEl.getBoundingClientRect();
                const btnRect  = activeBtn.getBoundingClientRect();
                indicator.style.transition = 'none';
                indicator.style.left  = (btnRect.left - tabsRect.left) + 'px';
                indicator.style.width = btnRect.width + 'px';
                requestAnimationFrame(() => { indicator.style.transition = ''; });
            }

            window.openCfg = function() {
                document.getElementById('cfg-overlay').classList.add('open');
                document.getElementById('cfg-panel').classList.add('open');
                document.body.style.overflow = 'hidden';
                requestAnimationFrame(initCfgIndicator);
                loadCfg();
            };
            window.closeCfg = function() {
                document.getElementById('cfg-overlay').classList.remove('open');
                document.getElementById('cfg-panel').classList.remove('open');
                document.body.style.overflow = '';
            };
            window.switchTab = function(tab) {
                // Update tab buttons & move indicator
                const tabs = document.querySelectorAll('.cfg-tab');
                let activeBtn = null;
                tabs.forEach(t => {
                    const isActive = t.getAttribute('onclick').includes("'" + tab + "'");
                    t.classList.toggle('active', isActive);
                    if (isActive) activeBtn = t;
                });
                const indicator = document.getElementById('cfg-tab-indicator');
                if (indicator && activeBtn) {
                    const tabsEl = document.getElementById('cfg-tabs');
                    const tabsRect = tabsEl.getBoundingClientRect();
                    const btnRect  = activeBtn.getBoundingClientRect();
                    indicator.style.left  = (btnRect.left - tabsRect.left) + 'px';
                    indicator.style.width = btnRect.width + 'px';
                }

                // Animate pane transition
                const currentPane = document.querySelector('.cfg-pane.active');
                const nextPane    = document.getElementById('pane-' + tab);
                if (!nextPane || currentPane === nextPane) return;

                if (currentPane) {
                    currentPane.classList.remove('active');
                    currentPane.addEventListener('transitionend', function hide() {
                        currentPane.classList.remove('visible');
                        currentPane.removeEventListener('transitionend', hide);
                    }, { once: true });
                }

                nextPane.classList.add('visible');
                requestAnimationFrame(() => requestAnimationFrame(() => {
                    nextPane.classList.add('active');
                }));
            };

            async function loadCfg() {
                try {
                    const res = await fetch(CFG_API);
                    const json = await res.json();
                    if (!json.ok) return;
                    const d = json.data;

                    const isOn = d.auto_aceptar && d.auto_aceptar !== 'no';
                    const toggle = document.getElementById('auto-toggle');
                    const chip = document.getElementById('auto-chip');
                    const text = document.getElementById('auto-chip-text');
                    const alcSec = document.getElementById('alcance-section');

                    if (toggle) toggle.checked = isOn;
                    if (chip) chip.className = 'auto-estado-chip ' + (isOn ? 'on' : 'off');
                    if (text) text.textContent = isOn ? 'Activado' : 'Desactivado';
                    if (alcSec) alcSec.style.display = isOn ? 'block' : 'none';

                    if (isOn && d.auto_aceptar) {
                        document.querySelectorAll('.alcance-btn').forEach(b =>
                            b.classList.toggle('selected', b.dataset.val === d.auto_aceptar));
                        updateAlcanceDesc(d.auto_aceptar);
                    }

                    blockedDates = {};
                    (d.dias_bloqueados || []).forEach(item => {
                        blockedDates[item.fecha] = item.motivo;
                    });
                    pendingAdd = [];
                    pendingDel = [];
                    renderMiniCal();
                    renderBlockedList();
                } catch (e) {
                    console.error('loadCfg', e);
                }
            }

            window.onAutoToggle = function() {
                const toggle = document.getElementById('auto-toggle');
                const chip = document.getElementById('auto-chip');
                const text = document.getElementById('auto-chip-text');
                const alcSec = document.getElementById('alcance-section');
                const isOn = toggle.checked;
                if (chip) chip.className = 'auto-estado-chip ' + (isOn ? 'on' : 'off');
                if (text) text.textContent = isOn ? 'Activado' : 'Desactivado';
                if (alcSec) alcSec.style.display = isOn ? 'block' : 'none';
            };
            window.selectAlcance = function(btn) {
                document.querySelectorAll('.alcance-btn').forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');
                updateAlcanceDesc(btn.dataset.val);
            };

            function updateAlcanceDesc(val) {
                const desc = document.getElementById('alcance-desc');
                if (!desc) return;
                const map = {
                    hoy: 'Las reservas se aceptarán automáticamente solo hoy.',
                    semana: 'Las reservas se aceptarán automáticamente durante los próximos 7 días.',
                    mes: 'Las reservas se aceptarán automáticamente durante el próximo mes.',
                    siempre: 'Las reservas se aceptarán automáticamente sin límite de tiempo.',
                };
                desc.textContent = map[val] || '';
            }
            window.saveAutoAceptar = async function() {
                const toggle = document.getElementById('auto-toggle');
                const isOn = toggle && toggle.checked;
                let alcance = 'no';
                if (isOn) {
                    const sel = document.querySelector('.alcance-btn.selected');
                    alcance = sel ? sel.dataset.val : 'siempre';
                }
                const btn = document.getElementById('btn-save-auto');
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Guardando…';
                }
                try {
                    const res = await fetch(CFG_API, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            accion: 'auto_aceptar',
                            valor: alcance
                        })
                    });
                    const json = await res.json();
                    showCfgStatus('auto-status', json.ok, json.ok ? 'Configuración guardada.' : (json.error || 'Error'));
                } catch (e) {
                    showCfgStatus('auto-status', false, 'Error de conexión.');
                } finally {
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = 'Guardar configuración';
                    }
                }
            };

            const MONTHS_ES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            window.mcNav = function(dir) {
                mcDate.setMonth(mcDate.getMonth() + dir);
                renderMiniCal();
            };

            function renderMiniCal() {
                const title = document.getElementById('mc-title');
                if (title) title.textContent = MONTHS_ES[mcDate.getMonth()] + ' ' + mcDate.getFullYear();
                const grid = document.getElementById('mc-grid');
                if (!grid) return;
                const year = mcDate.getFullYear(),
                    month = mcDate.getMonth();
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const firstDay = new Date(year, month, 1).getDay();
                const offset = (firstDay + 6) % 7;
                const daysIn = new Date(year, month + 1, 0).getDate();
                let html = '';
                for (let i = 0; i < offset; i++) html += "<div class='mini-cell mc-empty'></div>";
                for (let d = 1; d <= daysIn; d++) {
                    const dt = new Date(year, month, d);
                    const iso = isoDate(year, month, d);
                    const isPast      = dt < today;
                    const isClosedDay = !(window._OPEN_DAYS || [1,2,3,4,5,6]).includes(dt.getDay());
                    const isBlocked   = Object.prototype.hasOwnProperty.call(blockedDates, iso);
                    const isPendAdd   = pendingAdd.includes(iso);
                    const isPendDel   = pendingDel.includes(iso);
                    const isToday     = dt.getTime() === today.getTime();
                    let cls = 'mini-cell';
                    if (isPendAdd) cls += ' mc-pending';
                    else if (isPast || isClosedDay) cls += ' mc-disabled';
                    else if (isPendDel) cls += ' mc-unblocking';
                    else if (isBlocked) cls += ' mc-blocked';
                    else if (isToday) cls += ' mc-today';
                    const disabled = isPast || (isClosedDay && !isPendAdd);
                    const onclick = disabled ? '' : `onclick="mcCellClick('${iso}')"`;
                    const motivo = isBlocked ? (blockedDates[iso] || 'Bloqueado') : '';
                    const title2 = motivo ? `title="${motivo}"` : '';
                    html += `<div class="${cls}" ${onclick} ${title2}>${d}</div>`;
                }
                grid.innerHTML = html;
            }

            window.mcCellClick = function(iso) {
                const isBlocked = Object.prototype.hasOwnProperty.call(blockedDates, iso);
                const iPendAdd = pendingAdd.indexOf(iso);
                const iPendDel = pendingDel.indexOf(iso);
                if (rangeMode) {
                    if (!rangeStart) {
                        rangeStart = iso;
                        const hint = document.getElementById('range-hint');
                        if (hint) hint.innerHTML = `📅 Rango: <strong>${iso}</strong> → selecciona el día final.`;
                    } else {
                        const [start, end] = rangeStart < iso ? [rangeStart, iso] : [iso, rangeStart];
                        expandRange(start, end);
                        rangeStart = null;
                        const hint = document.getElementById('range-hint');
                        if (hint) hint.innerHTML = '📅 Modo rango: selecciona el <strong>primer día</strong> y luego el <strong>último</strong>.';
                    }
                } else {
                    if (isBlocked) {
                        if (iPendDel !== -1) pendingDel.splice(iPendDel, 1);
                        else pendingDel.push(iso);
                    } else {
                        if (iPendAdd !== -1) pendingAdd.splice(iPendAdd, 1);
                        else pendingAdd.push(iso);
                    }
                }
                updateSaveDaysBtn();
                renderMiniCal();
            };

            function expandRange(start, end) {
                const cur = new Date(start + 'T00:00:00');
                const fin = new Date(end + 'T00:00:00');
                while (cur <= fin) {
                    const iso = isoDate(cur.getFullYear(), cur.getMonth(), cur.getDate());
                    if (!Object.prototype.hasOwnProperty.call(blockedDates, iso) && !pendingAdd.includes(iso))
                        pendingAdd.push(iso);
                    cur.setDate(cur.getDate() + 1);
                }
                updateSaveDaysBtn();
                renderMiniCal();
            }
            window.toggleRangeMode = function() {
                rangeMode = !rangeMode;
                rangeStart = null;
                const btn = document.getElementById('btn-rango');
                const hint = document.getElementById('range-hint');
                if (btn) btn.textContent = rangeMode ? '✕ Cancelar rango' : '⇔ Rango de días';
                if (hint) hint.className = 'range-hint' + (rangeMode ? ' visible' : '');
            };
            window.clearPending = function() {
                pendingAdd = [];
                pendingDel = [];
                rangeStart = null;
                rangeMode = false;
                const btn = document.getElementById('btn-rango');
                const hint = document.getElementById('range-hint');
                if (btn) btn.textContent = '⇔ Rango de días';
                if (hint) hint.className = 'range-hint';
                updateSaveDaysBtn();
                renderMiniCal();
            };

            function updateSaveDaysBtn() {
                const btn = document.getElementById('btn-save-days');
                if (btn) btn.disabled = (pendingAdd.length === 0 && pendingDel.length === 0);
            }
            window.saveDays = async function() {
                const motivo = (document.getElementById('vac-motivo').value.trim()) || 'Vacaciones';
                const btn = document.getElementById('btn-save-days');
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Guardando…';
                }
                try {
                    for (const f of pendingAdd)
                        await fetch(CFG_API, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                accion: 'bloquear_dia',
                                fecha: f,
                                motivo
                            })
                        });
                    for (const f of pendingDel)
                        await fetch(CFG_API, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                accion: 'desbloquear_dia',
                                fecha: f
                            })
                        });
                    pendingAdd = [];
                    pendingDel = [];
                    await loadCfg();
                    showCfgStatus('vac-status', true, 'Días guardados correctamente.');
                } catch (e) {
                    showCfgStatus('vac-status', false, 'Error al guardar.');
                } finally {
                    if (btn) {
                        btn.textContent = 'Guardar días bloqueados';
                        updateSaveDaysBtn();
                    }
                }
            };
            window.deleteBlocked = async function(fecha) {
                try {
                    await fetch(CFG_API, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            accion: 'desbloquear_dia',
                            fecha
                        })
                    });
                    await loadCfg();
                    showCfgStatus('vac-status', true, 'Día desbloqueado.');
                } catch (e) {
                    showCfgStatus('vac-status', false, 'Error.');
                }
            };

            function renderBlockedList() {
                const el = document.getElementById('blocked-list');
                if (!el) return;
                const keys = Object.keys(blockedDates).sort();
                if (!keys.length) {
                    el.innerHTML = "<div class='empty-blocked'>No hay días bloqueados actualmente.</div>";
                    return;
                }
                el.innerHTML = keys.map(f =>
                    `<div class='blocked-item'>
               <div class='blocked-item-info'>
                 <span class='blocked-fecha'>${f}</span>
                 <span class='blocked-motivo'>${blockedDates[f]||''}</span>
               </div>
               <button class='blocked-del' onclick="deleteBlocked('${f}')">✕</button>
             </div>`
                ).join('');
            }

            function showCfgStatus(id, ok, msg) {
                const el = document.getElementById(id);
                if (!el) return;
                el.className = 'cfg-status visible ' + (ok ? 'ok' : 'err');
                el.textContent = (ok ? '✓ ' : '✕ ') + msg;
                setTimeout(() => el.classList.remove('visible'), 3500);
            }

            function isoDate(y, m, d) {
                return `${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
            }
        })();


        // ── STATS PANEL ─────────────────────────────────────────────────
        (function() {
            'use strict';

            const STATS_API = './api/stats.php';
            let statsLoaded = false;
            let currentPeriodo = 'todo';
            let isFetching = false;

            window.openStats = function() {
                document.getElementById('stats-overlay').classList.add('open');
                document.getElementById('stats-panel').classList.add('open');
                document.body.style.overflow = 'hidden';
                // Position pill after panel is visible (needs layout)
                requestAnimationFrame(() => requestAnimationFrame(() => movePeriodPill(currentPeriodo)));
                if (!statsLoaded) fetchStats('todo');
            };
            window.closeStats = function() {
                document.getElementById('stats-overlay').classList.remove('open');
                document.getElementById('stats-panel').classList.remove('open');
                document.body.style.overflow = '';
            };
            function movePeriodPill(periodo) {
                const bar  = document.getElementById('stats-period-bar');
                const pill = document.getElementById('stats-period-pill');
                const btn  = bar && bar.querySelector(`[data-periodo="${periodo}"]`);
                if (!btn || !pill || !bar) return;
                const barRect = bar.getBoundingClientRect();
                const btnRect = btn.getBoundingClientRect();
                pill.style.left   = (btnRect.left - barRect.left) + 'px';
                pill.style.top    = (btnRect.top  - barRect.top)  + 'px';
                pill.style.width  = btnRect.width  + 'px';
                pill.style.height = btnRect.height + 'px';
            }

            window.changePeriodo = function(periodo) {
                if (isFetching || periodo === currentPeriodo) return;
                currentPeriodo = periodo;
                document.querySelectorAll('.stats-period-btn').forEach(b => {
                    b.classList.toggle('active', b.dataset.periodo === periodo);
                });
                movePeriodPill(periodo);
                fetchStats(periodo);
            };

            async function fetchStats(periodo) {
                periodo = periodo || 'todo';
                if (isFetching) return;
                isFetching = true;
                const content = document.getElementById('stats-content');
                content.classList.add('stats-fading');
                await new Promise(r => setTimeout(r, 200));
                try {
                    const r = await fetch(STATS_API + '?periodo=' + encodeURIComponent(periodo));
                    const j = await r.json();
                    if (!j.ok) throw new Error(j.error || 'Error al cargar');
                    statsLoaded = true;
                    renderStats(j.data, periodo);
                    requestAnimationFrame(() => {
                        content.classList.remove('stats-fading');
                    });
                } catch (e) {
                    content.innerHTML =
                        `<div class="stats-loading" style="color:#d42b2b;"><div style="font-size:2rem;">⚠</div><span>${e.message}</span></div>`;
                    content.classList.remove('stats-fading');
                } finally {
                    isFetching = false;
                }
            }

            function animNum(el, target, decimals, suffix) {
                decimals = decimals || 0;
                suffix = suffix || '';
                const dur = 1200,
                    start = performance.now();

                function step(now) {
                    const p = Math.min((now - start) / dur, 1),
                        ease = 1 - Math.pow(1 - p, 3),
                        val = ease * target;
                    el.textContent = (decimals ? val.toFixed(decimals) : Math.floor(val)) + suffix;
                    if (p < 1) requestAnimationFrame(step);
                    else {
                        el.textContent = (decimals ? target.toFixed(decimals) : target) + suffix;
                        el.classList.add('pop');
                    }
                }
                requestAnimationFrame(step);
            }

            const tooltip = document.getElementById('chart-tooltip');
            window.showTip = function(e, unit, val) {
                tooltip.innerHTML = `<strong>${val}</strong>${unit?' '+unit:''}`;
                tooltip.style.left = e.clientX + 'px';
                tooltip.style.top = e.clientY + 'px';
                tooltip.classList.add('visible');
            };
            window.hideTip = function() {
                tooltip.classList.remove('visible');
            };
            document.addEventListener('mousemove', e => {
                if (tooltip.classList.contains('visible')) {
                    tooltip.style.left = e.clientX + 'px';
                    tooltip.style.top = (e.clientY - 10) + 'px';
                }
            });

            function onVisible(el, cb) {
                if (!el) return;
                new IntersectionObserver((entries, obs) => {
                    entries.forEach(en => {
                        if (en.isIntersecting) {
                            cb();
                            obs.unobserve(el);
                        }
                    });
                }, {
                    threshold: 0.15
                }).observe(el);
            }

            // ── Barbero modal ─────────────────────────────────────────────
            window._openBarberoModal = function(iniciales) {
                const b = (window._statsBarberos || []).find(x => x.iniciales === iniciales);
                if (b) openBarberoModal(b, window._statsBarberos || []);
            };

            function openBarberoModal(b, allBarbers) {
                const existing = document.getElementById('barbero-modal-overlay');
                if (existing) existing.remove();
                const maxIng = Math.max(...allBarbers.map(x => +x.ingresos), 1);
                const pct = maxIng > 0 ? Math.round(+b.ingresos / maxIng * 100) : 0;
                const ticket = (+b.ingresos / Math.max(+(b.aceptadas || b.total_citas) || 1, 1)).toFixed(0);
                const colorMap = {
                    EP: '#d42b2b',
                    MV: '#2550a0',
                    AR: '#c9a84c'
                };
                const accent = colorMap[b.iniciales] || '#d42b2b';
                const acceptPct = b.total_citas > 0 ? Math.round((+(b.aceptadas || 0) / +b.total_citas) * 100) : 0;

                const overlay = document.createElement('div');
                overlay.id = 'barbero-modal-overlay';
                overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.82);backdrop-filter:blur(10px);z-index:2000;display:flex;align-items:center;justify-content:center;padding:1.5rem;opacity:0;transition:opacity .3s ease;cursor:pointer;';
                const modal = document.createElement('div');
                modal.style.cssText = 'background:#111119;border:1px solid #2f2f3c;border-radius:20px;max-width:440px;width:100%;overflow:hidden;transform:translateY(20px) scale(.97);transition:transform .35s cubic-bezier(.16,1,.3,1),opacity .35s ease;opacity:0;cursor:default;box-shadow:0 24px 80px rgba(0,0,0,.7);';
                modal.addEventListener('click', e => e.stopPropagation());
                modal.innerHTML = `
          <div style="background:linear-gradient(135deg,${accent}22 0%,${accent}08 100%);border-bottom:1px solid ${accent}30;padding:2rem 2rem 1.5rem;">
            <div style="display:flex;align-items:center;gap:1.25rem;">
              <div style="width:68px;height:68px;border-radius:16px;background:linear-gradient(135deg,${accent}25,${accent}10);border:2px solid ${accent}50;display:flex;align-items:center;justify-content:center;font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:700;color:${accent};flex-shrink:0;">${b.iniciales}</div>
              <div>
                <div style="font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:700;color:#f0ece3;margin-bottom:.2rem;">${b.nombre}</div>
                <div style="font-size:.7rem;letter-spacing:.15em;text-transform:uppercase;color:${accent};font-weight:600;">${b.total_citas} citas totales</div>
              </div>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);border-bottom:1px solid #252530;">
            ${kpiCell(b.aceptadas||0,'Aceptadas','#22c55e',true)}
            ${kpiCell(b.pendientes||0,'Pendientes','#f59e0b',true)}
            ${kpiCell((+b.ingresos).toFixed(0)+'€','Ingresos','#c9a84c',false)}
          </div>
          <div style="padding:1.5rem 2rem;border-bottom:1px solid #252530;">
            <div style="display:flex;justify-content:space-between;font-size:.72rem;color:#7a7880;margin-bottom:.6rem;"><span>Rendimiento de ingresos</span><span style="color:${accent};font-weight:600;">${(+b.ingresos).toFixed(0)}€</span></div>
            <div style="height:8px;background:#1c1c26;border-radius:4px;overflow:hidden;">
              <div id="bm-prog" style="height:100%;border-radius:4px;width:0;background:linear-gradient(90deg,${accent},${accent}99);transition:width 1.2s cubic-bezier(.16,1,.3,1) .2s;"></div>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;padding:1.5rem 2rem;border-bottom:1px solid #252530;">
            ${miniKpi(ticket+'€','Ticket medio')}${miniKpi(acceptPct+'%','Tasa aceptación')}
          </div>
          <div style="padding:1.25rem 2rem;display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:.72rem;color:#7a7880;">Prado Barber Co. · Admin</span>
            <button onclick="document.getElementById('barbero-modal-overlay').remove();document.body.style.overflow='';" style="padding:.5rem 1.25rem;background:transparent;border:1px solid #252530;border-radius:6px;color:#7a7880;font-family:'DM Sans',sans-serif;font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;cursor:pointer;" onmouseover="this.style.borderColor='#d42b2b';this.style.color='#d42b2b';" onmouseout="this.style.borderColor='#252530';this.style.color='#7a7880';">Cerrar</button>
          </div>`;
                overlay.appendChild(modal);
                document.body.appendChild(overlay);
                requestAnimationFrame(() => {
                    overlay.style.opacity = '1';
                    modal.style.opacity = '1';
                    modal.style.transform = 'translateY(0) scale(1)';
                    setTimeout(() => {
                        const f = document.getElementById('bm-prog');
                        if (f) f.style.width = pct + '%';
                    }, 100);
                });
                overlay.addEventListener('click', () => {
                    overlay.style.opacity = '0';
                    modal.style.opacity = '0';
                    modal.style.transform = 'translateY(10px) scale(.97)';
                    setTimeout(() => {
                        overlay.remove();
                        document.body.style.overflow = '';
                    }, 300);
                });
                document.body.style.overflow = 'hidden';
            }

            function kpiCell(num, lbl, col, border) {
                return `<div style="padding:1.25rem 1rem;text-align:center;${border?'border-right:1px solid #252530;':''}"><div style="font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700;color:${col};line-height:1;">${num}</div><div style="font-size:.6rem;letter-spacing:.12em;text-transform:uppercase;color:#7a7880;margin-top:.3rem;">${lbl}</div></div>`;
            }

            function miniKpi(num, lbl) {
                return `<div style="background:#0d0d14;border-radius:10px;padding:1rem;text-align:center;border:1px solid #1c1c26;"><div style="font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:700;color:#c9a84c;">${num}</div><div style="font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;color:#7a7880;margin-top:.25rem;">${lbl}</div></div>`;
            }

            // ── RENDER STATS ─────────────────────────────────────────────
            const PERIODO_LABELS = {
                hoy: 'Hoy', semana: 'Esta semana', mes: 'Este mes',
                trimestre: 'Trimestre', año: 'Este año', todo: 'Todo el tiempo'
            };

            function renderStats(d, periodo) {
                periodo = periodo || 'todo';
                const kpi = d.kpi || {};
                const hoy = d.hoy || {};
                const mes = d.mes || {};
                const barbs = d.barberos || [];
                const svcs = d.servicios_top || [];
                const meses = d.ingresos_mensual || [];
                const horasHoy = d.evolucion_horas || [];
                const dow = d.dias_semana || [];
                const horas = d.horas_top || [];
                const hmap = d.heatmap_30d || [];
                const tasa = d.tasa_conversion != null ? d.tasa_conversion : 0;

                const barbsAll = barbs;
                const maxBarbIng = Math.max(...barbsAll.map(b => +b.ingresos), 1);
                const maxHora = Math.max(...horas.map(h => +h.total), 1);

                let html = '';

                // KPIs
                html += '<div class="stats-kpis">';
                const iconReservas = `<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm-7 3a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm-5 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm10 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2zM7 11h10v2H7zm0 4h7v2H7z"/></svg>`;
                const iconIngresosTotales = `<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/><path d="M11.5 6.5c-1.1 0-2 .9-2 2h1c0-.55.45-1 1-1s1 .45 1 1c0 .55-.45 1-1 1-.55 0-1 .45-1 1v.5h1V11c.55 0 1-.45 1-1 0-1.1-.9-2-2-2z" style="display:none"/><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm.5 14.5h-1V15h1v1.5zm0-3h-1c0-1.5 1.5-2 1.5-3 0-.55-.45-1-1-1s-1 .45-1 1h-1c0-1.1.9-2 2-2s2 .9 2 2c0 1.5-1.5 2-1.5 3z" style="display:none"/></svg>`;
                const iconIngTot = `<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>`;
                const iconClientes = `<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>`;
                const iconCitasHoy = `<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M20 3h-1V1h-2v2H7V1H5v2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 18H4V8h16v13zm-7-5H9v-2h4v2zm4-4H9v-2h8v2z"/></svg>`;
                const iconIngresosHoy = `<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M21 18v1c0 1.1-.9 2-2 2H5c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>`;
                const iconCitesMes = `<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>`;
                html += kpiCard('Reservas totales', kpi.total_reservas || 0, '#d42b2b', iconReservas, '', 'reservas_totales');
                html += kpiCard('Ingresos totales', kpi.ingresos_totales || 0, '#c9a84c', iconIngTot, ' €', 'ingresos_totales');
                html += kpiCard('Clientes únicos', kpi.clientes_unicos || 0, '#2550a0', iconClientes, '', 'clientes_unicos');
                if (periodo === 'todo') {
                    html += kpiCard('Citas hoy', hoy.citas_hoy || 0, '#22c55e', iconCitasHoy, '', 'citas_hoy');
                    html += kpiCard('Ingresos hoy', hoy.ingresos_hoy || 0, '#f59e0b', iconIngresosHoy, ' €', 'ingresos_hoy');
                    html += kpiCard('Citas este mes', mes.citas_mes || 0, '#a78bfa', iconCitesMes, '', 'citas_mes');
                } else {
                    const _ticketMedio = +(kpi.aceptadas || 0) > 0 ? Math.round(+(kpi.ingresos_totales || 0) / +(kpi.aceptadas)) : 0;
                    html += kpiCard('Aceptadas', kpi.aceptadas || 0, '#22c55e', iconCitasHoy, '', '');
                    html += kpiCard('Pendientes', kpi.pendientes || 0, '#f59e0b', iconIngresosHoy, '', '');
                    html += kpiCard('Ticket medio', _ticketMedio, '#a78bfa', iconCitesMes, ' €', '');
                }
                html += '</div>';

                // Barra de estado de reservas — contextualizada bajo el grupo de KPIs
                const _totalRes   = +(kpi.total_reservas || 0);
                const _acept      = +(kpi.aceptadas  || 0);
                const _denegadas  = +(kpi.denegadas  || 0);
                const _canceladas = Math.max(_totalRes - _acept - +(kpi.pendientes||0) - _denegadas, 0);
                const _acPct = _totalRes > 0 ? Math.round(_acept      / _totalRes * 100) : 0;
                const _dePct = _totalRes > 0 ? Math.round(_denegadas  / _totalRes * 100) : 0;
                const _caPct = _totalRes > 0 ? Math.round(_canceladas / _totalRes * 100) : 0;
                html += `<div class="estado-summary">
                  <div class="estado-summary__item">
                    <div class="estado-summary__icon" style="--es-clr:#22c55e;background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.28);">
                      <svg viewBox="0 0 24 24" width="15" height="15" fill="#22c55e"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                    </div>
                    <div class="estado-summary__data">
                      <span class="estado-summary__num" style="color:#22c55e;">${_acept}</span>
                      <span class="estado-summary__lbl">Aceptadas</span>
                    </div>
                    <div class="estado-summary__track">
                      <div class="estado-summary__fill" style="width:${_acPct}%;background:#22c55e;"></div>
                    </div>
                    <span class="estado-summary__pct" style="color:#22c55e;">${_acPct}%</span>
                  </div>
                  <div class="estado-summary__sep"></div>
                  <div class="estado-summary__item">
                    <div class="estado-summary__icon" style="background:rgba(248,113,113,.1);border-color:rgba(248,113,113,.28);">
                      <svg viewBox="0 0 24 24" width="15" height="15" fill="#f87171"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                    </div>
                    <div class="estado-summary__data">
                      <span class="estado-summary__num" style="color:#f87171;">${_denegadas}</span>
                      <span class="estado-summary__lbl">Denegadas</span>
                    </div>
                    <div class="estado-summary__track">
                      <div class="estado-summary__fill" style="width:${_dePct}%;background:#f87171;"></div>
                    </div>
                    <span class="estado-summary__pct" style="color:#f87171;">${_dePct}%</span>
                  </div>
                  <div class="estado-summary__sep"></div>
                  <div class="estado-summary__item">
                    <div class="estado-summary__icon" style="background:rgba(107,114,128,.1);border-color:rgba(107,114,128,.28);">
                      <svg viewBox="0 0 24 24" width="15" height="15" fill="#9ca3af"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11H7v-2h10v2z"/></svg>
                    </div>
                    <div class="estado-summary__data">
                      <span class="estado-summary__num" style="color:#9ca3af;">${_canceladas}</span>
                      <span class="estado-summary__lbl">Canceladas</span>
                    </div>
                    <div class="estado-summary__track">
                      <div class="estado-summary__fill" style="width:${_caPct}%;background:#6b7280;"></div>
                    </div>
                    <span class="estado-summary__pct" style="color:#9ca3af;">${_caPct}%</span>
                  </div>
                </div>`;

                const periodoLbl = PERIODO_LABELS[periodo] || '';
                const periodoBadge = `<span class="period-badge">${periodoLbl}</span>`;

                // Evolution charts — hourly for 'hoy', standard for other periods
                if (periodo === 'hoy') {
                    html += `<div class="stats-section"><div class="stats-section-label">Actividad por franja horaria ${periodoBadge}</div>`;
                    html += '<div class="stats-grid-2">';
                    html += '<div class="stats-card" style="padding-bottom:.5rem"><div class="stats-card-title">Ingresos por hora (€)</div>' + buildBarChartTall(horasHoy.map(h => ({label: h.hora, value: h.ingresos, tip: h.ingresos.toFixed(0) + '€ — ' + h.hora})), '#c9a84c') + '</div>';
                    html += '<div class="stats-card"><div class="stats-card-title">Citas por hora</div>' + buildBarChartTall(horasHoy.map(h => ({label: h.hora, value: h.citas, tip: h.citas + ' cita' + (h.citas !== 1 ? 's' : '') + ' — ' + h.hora})), '#2550a0') + '</div>';
                    html += '</div></div>';
                } else {
                    html += `<div class="stats-section"><div class="stats-section-label">Evolución — ingresos &amp; citas ${periodoBadge}</div>`;
                    html += '<div class="stats-grid-2">';
                    html += '<div class="stats-card" style="padding-bottom:.5rem"><div class="stats-card-title">Ingresos (€)</div>' + buildLineChart(meses, 'ingresos', '€') + '</div>';
                    html += '<div class="stats-card"><div class="stats-card-title">Citas</div>' + buildBarChart(meses.map(m => ({
                        label: m.label,
                        value: m.citas,
                        tip: m.citas + ' citas — ' + m.label
                    })), '#2550a0') + '</div>';
                    html += '</div></div>';
                }

                // Barbers — grid dinámico según número de barberos
                const barbCount = barbsAll.length;
                // 1→1col, 2/4→2cols, 3/5/6+→3cols (evita huérfanos desencuadrados)
                const barbCols = barbCount <= 1 ? 1 : barbCount === 2 || barbCount === 4 ? 2 : 3;
                const barbGridClass = `stats-grid-barberos barb-count-${barbCount}`;
                const barbGridStyle = `--barb-cols:${barbCols};`;
                html += `<div class="stats-section"><div class="stats-section-label">Rendimiento por barbero ${periodoBadge}</div><div class="${barbGridClass}" style="${barbGridStyle}">`;
                barbsAll.forEach((b, i) => {
                    html += barberCard(b, maxBarbIng, i);
                });
                html += '</div></div>';

                // Servicios + conversión
                html += `<div class="stats-section"><div class="stats-section-label">Servicios &amp; conversión ${periodoBadge}</div>`;
                html += '<div class="stats-grid-2">';
                html += buildServicesCardV2(svcs, kpi);
                html += '<div class="stats-card" style="display:flex;flex-direction:column;align-items:center;">';
                html += '<div class="stats-card-title" style="width:100%;">Tasa de aceptación</div>';
                html += buildConversionDonut(tasa, kpi);
                html += '</div></div></div>';

                // Demand patterns
                html += `<div class="stats-section"><div class="stats-section-label">Patrones de demanda ${periodoBadge}</div><div class="stats-grid-2">`;
                html += '<div class="stats-card"><div class="stats-card-title">Citas por día de la semana</div>' + buildBarChartTall(dow.map(d => ({
                    label: d.label,
                    value: d.count,
                    tip: d.count + ' citas los ' + d.label
                })), '#d42b2b') + '</div>';
                html += '<div class="stats-card"><div class="stats-card-title">Franjas horarias más populares</div><div class="horas-wrap">';
                horas.forEach((h, i) => {
                    const pct = Math.round(+h.total / maxHora * 100);
                    html += `<div class="hora-row"><span class="hora-lbl">${h.hora_slot}</span><div class="hora-bar-outer"><div class="hora-bar-fill" style="--h-w:${pct}%;--h-delay:${i*0.08}s;"><span class="hora-count">${h.total}</span></div></div></div>`;
                });
                html += '</div></div></div></div>';

                // Heatmap rediseñado ← MEJORADO
                html += '<div class="stats-section"><div class="stats-section-label">Actividad últimos 30 días</div>';
                html += '<div class="stats-card"><div class="stats-card-title">Mapa de calor de reservas</div>';
                html += buildHeatmapV2(hmap);
                html += '</div></div>';

                document.getElementById('stats-content').innerHTML = html;
                window._statsBarberos = barbsAll;
                window._statsData = d;
                window._statsPeriodo = periodo;

                // Animate + click
                setTimeout(() => {
                    document.querySelectorAll('.kpi-card').forEach((card, i) => {
                        setTimeout(() => {
                            card.classList.add('visible');
                            const valEl = card.querySelector('.kpi-value');
                            animNum(valEl, parseFloat(card.dataset.target || 0), parseInt(card.dataset.dec || 0), card.dataset.suffix || '');
                        }, i * 80);
                        if (card.dataset.kpi) {
                            card.addEventListener('click', () => openKpiDetail(card.dataset.kpi));
                        }
                    });
                }, 50);
                setTimeout(() => {
                    document.querySelectorAll('.bar-fill,.line-path,.line-area,.line-dot,.progress-fill,.hora-bar-fill,.svc-stat-bar-fill,.conv-prog-seg').forEach(el => {
                        onVisible(el, () => el.classList.add('animated'));
                    });
                }, 100);
            }

            function kpiCard(label, value, color, icon, suffix, kpiKey) {
                return `<div class="kpi-card" style="--kpi-accent:${color}" data-target="${+value}" data-dec="0" data-suffix="${suffix}" data-kpi="${kpiKey||''}" data-color="${color}">${icon ? `<div class="kpi-badge">${icon}</div>` : ''}<div class="kpi-label">${label}</div><div class="kpi-value">0${suffix}</div><div class="kpi-card-accent-bar"></div></div>`;
            }
            function kpiCardWithSub(label, value, color, icon, suffix, subHtml) {
                return `<div class="kpi-card" style="--kpi-accent:${color}" data-target="${+value}" data-dec="0" data-suffix="${suffix}"><div class="kpi-badge">${icon}</div><div class="kpi-label">${label}</div><div class="kpi-value">0${suffix}</div>${subHtml}<div class="kpi-card-accent-bar"></div></div>`;
            }

            // ── KPI DETAIL MODAL ─────────────────────────────────────────
            function openKpiDetail(kpiKey) {
                const d = window._statsData || {};
                const kpi = d.kpi || {};
                const hoy = d.hoy || {};
                const mes = d.mes || {};
                const svcs = d.servicios_top || [];
                const barbs = window._statsBarberos || [];
                const periodo = window._statsPeriodo || 'todo';
                const totalRes = +(kpi.total_reservas || 0);
                const ticketMedio = +(kpi.ticket_medio || 0) || (totalRes > 0 && kpi.ingresos_totales > 0 ? +(kpi.ingresos_totales) / totalRes : 0);
                const acept = +(kpi.aceptadas || 0);
                const pend  = +(kpi.pendientes || 0);
                const dene  = +(kpi.denegadas || 0);
                const canc  = Math.max(totalRes - acept - pend - dene, 0);
                const acPct = totalRes > 0 ? Math.round(acept / totalRes * 100) : 0;
                const dePct = totalRes > 0 ? Math.round(dene  / totalRes * 100) : 0;
                const caPct = totalRes > 0 ? Math.round(canc  / totalRes * 100) : 0;
                const pePct = totalRes > 0 ? Math.round(pend  / totalRes * 100) : 0;

                const configs = {
                    reservas_totales: {
                        title: 'Reservas totales',
                        subtitle: 'Historial completo del período',
                        color: '#d42b2b',
                        value: totalRes,
                        suffix: '',
                        icon: `<svg viewBox="0 0 24 24" width="22" height="22" fill="#d42b2b"><path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm-7 3a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm-5 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm10 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2zM7 11h10v2H7zm0 4h7v2H7z"/></svg>`,
                        cells: [
                            { label: 'Aceptadas', value: acept, color: '#22c55e' },
                            { label: 'Pendientes', value: pend, color: '#f59e0b' },
                            { label: 'Denegadas', value: dene, color: '#d42b2b' },
                            { label: 'Canceladas', value: canc, color: '#6b7280' },
                        ],
                        bars: [
                            { label: 'Aceptadas', pct: acPct, color: '#22c55e', num: acPct+'%' },
                            { label: 'Pendientes', pct: pePct, color: '#f59e0b', num: pePct+'%' },
                            { label: 'Denegadas', pct: dePct, color: '#d42b2b', num: dePct+'%' },
                            { label: 'Canceladas', pct: caPct, color: '#6b7280', num: caPct+'%' },
                        ],
                        list: [
                            { label: 'Ticket medio', val: ticketMedio.toFixed(0)+'€' },
                            { label: 'Tasa conversión', val: (+(d.tasa_conversion||0)).toFixed(1)+'%' },
                            { label: 'Ingresos generados', val: (+(kpi.ingresos_totales||0)).toFixed(0)+'€' },
                        ]
                    },
                    ingresos_totales: {
                        title: 'Ingresos totales',
                        subtitle: 'Facturación acumulada del período',
                        color: '#c9a84c',
                        value: +(kpi.ingresos_totales||0),
                        suffix: ' €',
                        icon: `<svg viewBox="0 0 24 24" width="22" height="22" fill="#c9a84c"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>`,
                        cells: [
                            { label: 'Ticket medio', value: ticketMedio.toFixed(0)+'€', color: '#c9a84c' },
                            { label: 'Reservas pagadas', value: acept, color: '#22c55e' },
                            { label: 'Ingresos hoy', value: (+(hoy.ingresos_hoy||0)).toFixed(0)+'€', color: '#f59e0b' },
                            { label: 'Ingresos mes', value: (+(mes.ingresos_mes||0)).toFixed(0)+'€', color: '#a78bfa' },
                        ],
                        bars: svcs.slice(0,4).map(s => {
                            const maxIng = Math.max(...svcs.map(x=>+(x.ingresos||0)),1);
                            return { label: s.nombre, pct: Math.round(+(s.ingresos||0)/maxIng*100), color: '#c9a84c', num: (+(s.ingresos||0)).toFixed(0)+'€' };
                        }),
                        list: [
                            { label: 'Mejor servicio', val: svcs[0] ? svcs[0].nombre : '—' },
                            { label: 'Tasa conversión', val: (+(d.tasa_conversion||0)).toFixed(1)+'%' },
                            { label: 'Total reservas', val: totalRes },
                        ]
                    },
                    clientes_unicos: {
                        title: 'Clientes únicos',
                        subtitle: 'Base de clientes del período',
                        color: '#5b7fd4',
                        value: +(kpi.clientes_unicos||0),
                        suffix: '',
                        icon: `<svg viewBox="0 0 24 24" width="22" height="22" fill="#5b7fd4"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>`,
                        cells: [
                            { label: 'Total clientes', value: +(kpi.clientes_unicos||0), color: '#5b7fd4' },
                            { label: 'Reservas totales', value: totalRes, color: '#d42b2b' },
                            { label: 'Media por cliente', value: totalRes > 0 ? (totalRes/(+(kpi.clientes_unicos||1))).toFixed(1) : '0', color: '#c9a84c' },
                            { label: 'Ticket medio', value: ticketMedio.toFixed(0)+'€', color: '#22c55e' },
                        ],
                        bars: barbs.filter(b=>b.total_citas>0).slice(0,4).map(b => {
                            const maxC = Math.max(...barbs.map(x=>+(x.total_citas||0)),1);
                            return { label: b.nombre.split(' ')[0], pct: Math.round(+(b.total_citas||0)/maxC*100), color: '#5b7fd4', num: b.total_citas+' citas' };
                        }),
                        list: [
                            { label: 'Tasa aceptación', val: acPct+'%' },
                            { label: 'Ingresos totales', val: (+(kpi.ingresos_totales||0)).toFixed(0)+'€' },
                            { label: 'Período analizado', val: { hoy:'Hoy',semana:'Esta semana',mes:'Este mes',trimestre:'Trimestre',año:'Este año',todo:'Todo el tiempo' }[periodo]||periodo },
                        ]
                    },
                    citas_hoy: {
                        title: 'Citas hoy',
                        subtitle: new Date().toLocaleDateString('es-ES',{weekday:'long',day:'numeric',month:'long'}),
                        color: '#22c55e',
                        value: +(hoy.citas_hoy||0),
                        suffix: '',
                        icon: `<svg viewBox="0 0 24 24" width="22" height="22" fill="#22c55e"><path d="M20 3h-1V1h-2v2H7V1H5v2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 18H4V8h16v13zm-7-5H9v-2h4v2zm4-4H9v-2h8v2z"/></svg>`,
                        cells: [
                            { label: 'Citas hoy', value: +(hoy.citas_hoy||0), color: '#22c55e' },
                            { label: 'Ingresos hoy', value: (+(hoy.ingresos_hoy||0)).toFixed(0)+'€', color: '#c9a84c' },
                            { label: 'Citas este mes', value: +(mes.citas_mes||0), color: '#a78bfa' },
                            { label: 'Pendientes', value: pend, color: '#f59e0b' },
                        ],
                        bars: barbs.filter(b=>b.total_citas>0).slice(0,4).map(b => {
                            const maxC = Math.max(...barbs.map(x=>+(x.total_citas||0)),1);
                            return { label: b.nombre.split(' ')[0], pct: Math.round(+(b.total_citas||0)/maxC*100), color: '#22c55e', num: b.total_citas };
                        }),
                        list: [
                            { label: 'Ingresos estimados hoy', val: (+(hoy.ingresos_hoy||0)).toFixed(0)+'€' },
                            { label: 'Ticket medio', val: ticketMedio.toFixed(0)+'€' },
                            { label: 'Total mes en curso', val: +(mes.citas_mes||0)+' citas' },
                        ]
                    },
                    ingresos_hoy: {
                        title: 'Ingresos hoy',
                        subtitle: new Date().toLocaleDateString('es-ES',{weekday:'long',day:'numeric',month:'long'}),
                        color: '#f59e0b',
                        value: +(hoy.ingresos_hoy||0),
                        suffix: ' €',
                        icon: `<svg viewBox="0 0 24 24" width="22" height="22" fill="#f59e0b"><path d="M21 18v1c0 1.1-.9 2-2 2H5c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>`,
                        cells: [
                            { label: 'Ingresos hoy', value: (+(hoy.ingresos_hoy||0)).toFixed(0)+'€', color: '#f59e0b' },
                            { label: 'Citas hoy', value: +(hoy.citas_hoy||0), color: '#22c55e' },
                            { label: 'Ingresos mes', value: (+(mes.ingresos_mes||0)).toFixed(0)+'€', color: '#a78bfa' },
                            { label: 'Ingresos totales', value: (+(kpi.ingresos_totales||0)).toFixed(0)+'€', color: '#c9a84c' },
                        ],
                        bars: svcs.slice(0,4).map(s => {
                            const maxIng = Math.max(...svcs.map(x=>+(x.ingresos||0)),1);
                            return { label: s.nombre, pct: Math.round(+(s.ingresos||0)/maxIng*100), color: '#f59e0b', num: (+(s.ingresos||0)).toFixed(0)+'€' };
                        }),
                        list: [
                            { label: 'Ticket medio global', val: ticketMedio.toFixed(0)+'€' },
                            { label: '% sobre total mensual', val: mes.ingresos_mes > 0 ? Math.round(hoy.ingresos_hoy/mes.ingresos_mes*100)+'%' : '—' },
                            { label: 'Citas este mes', val: +(mes.citas_mes||0) },
                        ]
                    },
                    citas_mes: {
                        title: 'Citas este mes',
                        subtitle: new Date().toLocaleDateString('es-ES',{month:'long',year:'numeric'}),
                        color: '#a78bfa',
                        value: +(mes.citas_mes||0),
                        suffix: '',
                        icon: `<svg viewBox="0 0 24 24" width="22" height="22" fill="#a78bfa"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>`,
                        cells: [
                            { label: 'Citas mes', value: +(mes.citas_mes||0), color: '#a78bfa' },
                            { label: 'Ingresos mes', value: (+(mes.ingresos_mes||0)).toFixed(0)+'€', color: '#c9a84c' },
                            { label: 'Citas hoy', value: +(hoy.citas_hoy||0), color: '#22c55e' },
                            { label: 'Total acumulado', value: totalRes, color: '#d42b2b' },
                        ],
                        bars: barbs.filter(b=>b.total_citas>0).slice(0,4).map(b => {
                            const maxC = Math.max(...barbs.map(x=>+(x.total_citas||0)),1);
                            return { label: b.nombre.split(' ')[0], pct: Math.round(+(b.total_citas||0)/maxC*100), color: '#a78bfa', num: b.total_citas };
                        }),
                        list: [
                            { label: 'Ingresos del mes', val: (+(mes.ingresos_mes||0)).toFixed(0)+'€' },
                            { label: 'Media diaria estimada', val: mes.citas_mes > 0 ? (mes.citas_mes/new Date().getDate()).toFixed(1)+'/día' : '—' },
                            { label: 'Tasa aceptación global', val: acPct+'%' },
                        ]
                    }
                };

                const cfg = configs[kpiKey];
                if (!cfg) return;

                const overlay = document.createElement('div');
                overlay.className = 'kpi-detail-overlay';

                const accentAlpha = cfg.color + '22';
                const accentBorder = cfg.color + '40';

                let cellsHtml = '';
                if (cfg.cells) {
                    cellsHtml = `<div class="kdm-grid">` + cfg.cells.map(c =>
                        `<div class="kdm-cell" style="box-shadow:0 4px 18px 0 ${c.color}33, 0 1px 4px 0 ${c.color}22; border: 1px solid ${c.color}28;">
                            <div class="kdm-cell-label">${c.label}</div>
                            <div class="kdm-cell-value" style="color:${c.color};">${c.value}</div>
                        </div>`
                    ).join('') + `</div>`;
                }

                let barsHtml = '';
                if (cfg.bars && cfg.bars.length) {
                    barsHtml = `<div class="kdm-bar-section">
                        <div style="font-size:.6rem;letter-spacing:.14em;text-transform:uppercase;color:#7a7880;margin-bottom:.75rem;">Distribución</div>
                        ${cfg.bars.map(b => `
                            <div class="kdm-bar-row">
                                <span class="kdm-bar-label">${b.label}</span>
                                <div class="kdm-bar-track">
                                    <div class="kdm-bar-fill" style="background:${b.color};" data-pct="${b.pct}"></div>
                                </div>
                                <span class="kdm-bar-num" style="color:${b.color};">${b.num}</span>
                            </div>`).join('')}
                    </div>`;
                }

                let listHtml = '';
                if (cfg.list && cfg.list.length) {
                    listHtml = `<div class="kdm-list">
                        ${cfg.list.map(item => `
                            <div class="kdm-list-item">
                                <span class="kdm-list-label">${item.label}</span>
                                <span class="kdm-list-val">${item.val}</span>
                            </div>`).join('')}
                    </div>`;
                }

                overlay.innerHTML = `
                    <div class="kpi-detail-modal">
                        <div class="kdm-header">
                            <div class="kdm-icon" style="background:${accentAlpha};border:1px solid ${accentBorder};">${cfg.icon}</div>
                            <div class="kdm-title-group">
                                <div class="kdm-title">${cfg.title}</div>
                                <div class="kdm-subtitle">${cfg.subtitle}</div>
                            </div>
                            <button class="kdm-close" id="kdm-close-btn">✕</button>
                        </div>
                        <div class="kdm-value-hero">
                            <span class="kdm-value-num" style="color:${cfg.color};">${(+cfg.value).toLocaleString('es-ES')}</span>
                            ${cfg.suffix ? `<span class="kdm-value-suffix" style="color:${cfg.color};">${cfg.suffix.trim()}</span>` : ''}
                        </div>
                        ${cellsHtml}
                        ${barsHtml}
                        ${listHtml}
                        <div class="kdm-footer">
                            <span class="kdm-footer-note">Prado Barber Co. · Panel Admin</span>
                            <button class="kdm-footer-btn" id="kdm-close-footer">Cerrar</button>
                        </div>
                    </div>`;

                document.body.appendChild(overlay);
                document.body.style.overflow = 'hidden';

                const closeModal = () => {
                    const modal = overlay.querySelector('.kpi-detail-modal');
                    overlay.style.opacity = '0';
                    modal.classList.add('closing');
                    setTimeout(() => { overlay.remove(); document.body.style.overflow = ''; }, 350);
                };

                requestAnimationFrame(() => {
                    overlay.classList.add('open');
                    setTimeout(() => {
                        overlay.querySelectorAll('.kdm-bar-fill').forEach(el => {
                            el.style.width = el.dataset.pct + '%';
                        });
                    }, 200);
                });

                overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });
                overlay.querySelector('#kdm-close-btn').addEventListener('click', closeModal);
                overlay.querySelector('#kdm-close-footer').addEventListener('click', closeModal);

                document.addEventListener('keydown', function escHandler(e) {
                    if (e.key === 'Escape') { closeModal(); document.removeEventListener('keydown', escHandler); }
                });
            }

            // ── Bar chart con altura correcta ────────────────────────────
            function buildBarChart(items, color) {
                return buildBarChartTall(items, color);
            }

            function buildBarChartTall(items, color) {
                const maxV = Math.max(...items.map(x => x.value), 1);
                const chartH = 200;
                const barMaxH = 120;
                const padBottom = 48;
                const padTop = 28;
                const minSlots = 7;
                const slots = Math.max(items.length, minSlots);
                const maxItemPct = Math.floor(100 / slots);

                let html = `<div style="display:flex;align-items:flex-end;gap:6px;height:${chartH}px;padding:${padTop}px 0 ${padBottom}px;position:relative;box-sizing:border-box;">`;
                html += `<div style="position:absolute;bottom:${padBottom}px;left:0;right:0;height:1px;background:rgba(245,240,232,0.06);"></div>`;

                const showEvery = items.length > 8 ? 3 : items.length > 5 ? 2 : 1;
                items.forEach((item, i) => {
                    const h = Math.max(Math.round(item.value / maxV * barMaxH), 3);
                    const showLabel = (i % showEvery === 0) || (i === items.length - 1);
                    html += `
        <div style="flex:1;max-width:${maxItemPct}%;display:flex;flex-direction:column;align-items:center;position:relative;height:100%;justify-content:flex-end;">
            <div style="font-size:.7rem;color:#a0a0b0;font-weight:500;margin-bottom:4px;line-height:1;">${item.value || ''}</div>
            <div style="width:80%;border-radius:4px 4px 0 0;background:${color};height:${h}px;min-height:3px;transition:height .4s;"
                 onmouseenter="showTip(event,'','${item.tip||item.value}')" onmouseleave="hideTip()"></div>
            <div style="position:absolute;bottom:-${padBottom - 6}px;font-size:.7rem;color:#7a7880;white-space:nowrap;text-align:center;transform:rotate(-40deg);transform-origin:top center;${showLabel ? '' : 'visibility:hidden;'}">${item.label}</div>
        </div>`;
                });

                html += '</div>';
                return html;
            }

            function buildLineChart(meses, field, unit) {
                const W = 500,
                    H = 150,
                    PAD = {
                        t: 12,
                        r: 10,
                        b: 28,
                        l: 42
                    };
                const vals = meses.map(m => +m[field]);
                const maxV = Math.max(...vals, 1);
                const pts = vals.map((v, i) => [
                    PAD.l + (i / Math.max(vals.length - 1, 1)) * (W - PAD.l - PAD.r),
                    PAD.t + (1 - v / maxV) * (H - PAD.t - PAD.b)
                ]);
                const pathD = pts.map((p, i) => (i === 0 ? 'M' : 'L') + p[0].toFixed(1) + ',' + p[1].toFixed(1)).join(' ');
                const areaD = pathD + ` L${pts[pts.length-1][0].toFixed(1)},${H-PAD.b} L${pts[0][0].toFixed(1)},${H-PAD.b} Z`;
                const grids = [0.25, 0.5, 0.75, 1].map(f => {
                    const yy = PAD.t + (1 - f) * (H - PAD.t - PAD.b),
                        lbl = Math.round(maxV * f);
                    return `<line class="line-grid" x1="${PAD.l}" x2="${W-PAD.r}" y1="${yy.toFixed(1)}" y2="${yy.toFixed(1)}"/><text class="line-y-label" x="${PAD.l-4}" y="${(yy+3).toFixed(1)}">${lbl>999?(lbl/1000).toFixed(1)+'k':lbl}</text>`;
                }).join('');
                const xlbls = meses.map((m, i) => {
                    if (i % 3 !== 0 && i !== meses.length - 1) return '';
                    return `<text class="line-x-label" x="${pts[i][0].toFixed(1)}" y="${H-4}">${m.label}</text>`;
                }).join('');
                const dots = pts.map((p, i) => `<circle class="line-dot" cx="${p[0].toFixed(1)}" cy="${p[1].toFixed(1)}" r="4" onmouseenter="showTip(event,'${unit}','${vals[i]}')" onmouseleave="hideTip()"/>`).join('');
                return `<svg class="line-chart-svg" viewBox="0 0 ${W} ${H}" preserveAspectRatio="xMidYMid meet"><defs><linearGradient id="lineGrad" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#d42b2b" stop-opacity=".25"/><stop offset="100%" stop-color="#d42b2b" stop-opacity="0"/></linearGradient></defs>${grids}${xlbls}<path class="line-area" d="${areaD}"/><path class="line-path" d="${pathD}"/>${dots}</svg>`;
            }

            // ── DONUT MULTICOLOR (FIX 5) ─────────────────────────────────
            // Radio más pequeño para que no se corte, con segmentos coloreados
            function buildConversionDonut(tasa, kpi) {
                const aceptadas = +(kpi.aceptadas || 0);
                const pendientes = +(kpi.pendientes || 0);
                const denegadas = +(kpi.denegadas || 0);
                const canceladas = +((kpi.total_reservas || 0) - aceptadas - pendientes - denegadas);
                const total = aceptadas + pendientes + denegadas + Math.max(canceladas, 0);

                // SVG donut con viewBox holgado para no cortar
                const cx = 90,
                    cy = 90,
                    r = 62,
                    strokeW = 16;
                const circ = 2 * Math.PI * r;

                function seg(value, color, offset, delay) {
                    if (!value || total === 0) return '';
                    const dash = (value / total) * circ;
                    const label = color==='#22c55e'?'Aceptadas':color==='#f59e0b'?'Pendientes':color==='#d42b2b'?'Denegadas':'Canceladas';
                    return `<circle class="conv-prog-seg" cx="${cx}" cy="${cy}" r="${r}" fill="none" stroke="${color}" stroke-width="${strokeW}" stroke-linecap="butt"
              stroke-dasharray="${dash.toFixed(2)} ${circ.toFixed(2)}"
              stroke-dashoffset="${(-offset).toFixed(2)}"
              transform="rotate(-90 ${cx} ${cy})"
              pointer-events="visibleStroke"
              style="transition:opacity .6s ease ${delay}s;cursor:pointer;"
              onmouseenter="showTip(event,'','${value} ${label}')"
              onmouseleave="hideTip()"/>`;
                }

                // Calcular offsets acumulados
                const o1 = 0;
                const o2 = total > 0 ? (aceptadas / total) * circ : 0;
                const o3 = o2 + (total > 0 ? (pendientes / total) * circ : 0);
                const o4 = o3 + (total > 0 ? (denegadas / total) * circ : 0);

                // SVG más grande y con strokeW mayor para PC
                const svgSize = 240;
                const svgHtml = `
          <svg viewBox="0 0 180 180" style="width:${svgSize}px;height:${svgSize}px;flex-shrink:0;display:block;">
            <circle cx="${cx}" cy="${cy}" r="${r}" fill="none" stroke="#1a1a24" stroke-width="${strokeW + 4}"/>
            ${seg(aceptadas,  '#22c55e', o1, 0.1)}
            ${seg(pendientes, '#f59e0b', o2, 0.25)}
            ${seg(denegadas,  '#d42b2b', o3, 0.4)}
            ${seg(Math.max(canceladas,0), '#6b7280', o4, 0.55)}
            <text x="${cx}" y="${cy - 6}" text-anchor="middle" dominant-baseline="middle" font-family="'Playfair Display',serif" font-size="26" font-weight="700" fill="#22c55e">${tasa}%</text>
            <text x="${cx}" y="${cy + 13}" text-anchor="middle" dominant-baseline="middle" font-family="'DM Sans',sans-serif" font-size="8" fill="#7a7880" letter-spacing="1.5">ACEPTADAS</text>
          </svg>`;

                return `
          <div class="donut-layout">
            <div class="donut-svg-wrap">${svgHtml}</div>
            <div class="donut-legend">
              ${donutLegendItem(aceptadas,'Aceptadas','#22c55e')}
              ${donutLegendItem(Math.max(canceladas,0),'Canceladas','#6b7280')}
              ${donutLegendItem(denegadas,'Denegadas','#f87171')}
              ${donutLegendItem(total,'Total','#f0ece3')}
            </div>
          </div>`;
            }

            function convMeta(num, lbl, col) {
                return `<div class="conv-meta-item"><div class="conv-meta-num" style="color:${col};">${num}</div><div class="conv-meta-lbl">${lbl}</div></div>`;
            }
            function donutLegendItem(num, lbl, col) {
                return `<div class="donut-legend-item"><div class="donut-legend-dot" style="background:${col};box-shadow:0 0 6px ${col}60;"></div><div class="donut-legend-info"><div class="donut-legend-num" style="color:${col};">${num}</div><div class="donut-legend-lbl">${lbl}</div></div></div>`;
            }

            // ── SERVICIOS MEJORADO (FIX 4) ───────────────────────────────
            function buildHeatmapV2(hmap) {
                const today = new Date();
                const MONTHS_ES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                const DOW = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];

                const map = {};
                hmap.forEach(h => {
                    map[h.dia] = +h.total;
                });
                const maxV = Math.max(...Object.values(map), 1);

                function getLevel(v) {
                    if (!v) return 0;
                    if (v <= maxV * .25) return 1;
                    if (v <= maxV * .5) return 2;
                    if (v <= maxV * .75) return 3;
                    return 4;
                }

                const COLORS = [{
                        bg: 'rgba(255,255,255,0.04)',
                        border: 'rgba(255,255,255,0.07)'
                    },
                    {
                        bg: 'rgba(212,43,43,0.20)',
                        border: 'rgba(212,43,43,0.30)'
                    },
                    {
                        bg: 'rgba(212,43,43,0.45)',
                        border: 'rgba(212,43,43,0.55)'
                    },
                    {
                        bg: 'rgba(212,43,43,0.72)',
                        border: 'rgba(212,43,43,0.80)'
                    },
                    {
                        bg: '#d42b2b',
                        border: '#ff4040'
                    },
                ];

                const todayISO = today.toISOString().slice(0, 10);

                // Estado de navegación: offset en meses desde el mes actual
                // offset=0 → muestra mes anterior y mes actual
                // offset=-1 → muestra hace 2 meses y mes anterior, etc.
                let hmOffset = 0;

                function getMonthRange(offset) {
                    // Mostrar dos meses: (hoy - 1 + offset) y (hoy + offset)
                    const m1 = new Date(today.getFullYear(), today.getMonth() - 1 + offset, 1);
                    const m2 = new Date(today.getFullYear(), today.getMonth() + offset, 1);
                    return [m1, m2];
                }

                function renderMonth(mDate) {
                    const y = mDate.getFullYear(),
                        m = mDate.getMonth();
                    const daysInMonth = new Date(y, m + 1, 0).getDate();
                    const firstDow = (new Date(y, m, 1).getDay() + 6) % 7;

                    let html = `<div class="hm3-month">`;
                    html += `<div class="hm3-mname">${MONTHS_ES[m]} ${y}</div>`;
                    html += `<div class="hm3-dow-row">`;
                    DOW.forEach(d => html += `<div class="hm3-dow">${d}</div>`);
                    html += `</div><div class="hm3-grid">`;

                    for (let p = 0; p < firstDow; p++) html += `<div class="hm3-c" style="background:transparent;border:none;"></div>`;

                    const start30 = new Date(today);
                    start30.setDate(start30.getDate() - 29);

                    for (let d = 1; d <= daysInMonth; d++) {
                        const dt = new Date(y, m, d);
                        const iso = `${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
                        const isInRange = dt >= start30 && dt <= today;
                        const isToday = iso === todayISO;
                        const v = map[iso] || 0;

                        if (!isInRange) {
                            html += `<div class="hm3-c" style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.04);color:#2a2a38;">${d}</div>`;
                        } else {
                            const c = COLORS[getLevel(v)];
                            const txtColor = getLevel(v) >= 3 ? 'rgba(255,255,255,0.85)' : '#4a4a5a';
                            const border = isToday ? '2px solid rgba(212,43,43,0.8)' : `1px solid ${c.border}`;
                            html += `<div class="hm3-c" style="background:${c.bg};border:${border};color:${txtColor};">${d}`;
                            if (v > 0) html += `<div class="hm3-tip">${v} cita${v!==1?'s':''}</div>`;
                            html += `</div>`;
                        }
                    }
                    html += `</div></div>`;
                    return html;
                }

                function renderHeatmapContent(dir) {
                    const [m1, m2] = getMonthRange(hmOffset);
                    // No permitir navegar al futuro más allá del mes actual
                    document.getElementById('hm-btn-next').style.opacity = hmOffset >= 0 ? '0.3' : '1';
                    document.getElementById('hm-btn-next').style.pointerEvents = hmOffset >= 0 ? 'none' : 'auto';

                    const cal = document.getElementById('hm-calendar');
                    cal.classList.remove('anim-left', 'anim-right');
                    cal.innerHTML = renderMonth(m1) + renderMonth(m2);
                    if (dir) {
                        // forzar reflow para que la animación arranque de nuevo
                        void cal.offsetWidth;
                        cal.classList.add(dir === 'prev' ? 'anim-left' : 'anim-right');
                    }
                }

                // Stats
                const totalCitas = Object.values(map).reduce((a, b) => a + b, 0);
                const diasActivos = Object.keys(map).filter(k => map[k] > 0).length;
                const maxEntry = Object.entries(map).sort((a, b) => b[1] - a[1])[0];
                const avgCitas = diasActivos > 0 ? (totalCitas / diasActivos).toFixed(1) : '0';

                let html = `
    <style>
        .hm3-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;}
        .hm3-legend{display:flex;align-items:center;gap:5px;}
        .hm3-legend span{font-size:10px;color:#4a4a5a;}
        .hm3-lswatch{width:11px;height:11px;border-radius:3px;}
        .hm3-nav{display:flex;align-items:center;gap:.5rem;}
        .hm3-nav-btn{width:32px;height:32px;border-radius:50%;background:rgba(245,240,232,0.06);border:1px solid rgba(245,240,232,0.15);color:#f0ece3;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1rem;font-weight:700;transition:all .2s;font-family:'DM Sans',sans-serif;line-height:1;}
        .hm3-nav-btn:hover{border-color:#d42b2b;color:#d42b2b;background:rgba(212,43,43,0.1);}
        .hm3-calendar{display:flex;gap:0;flex-wrap:wrap;}
        .hm3-month{flex:1;min-width:200px;padding-right:20px;}
        .hm3-month:last-child{padding-right:0;}
        .hm3-mname{font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#d42b2b;font-weight:600;margin-bottom:8px;}
        .hm3-dow-row{display:grid;grid-template-columns:repeat(7,1fr);gap:3px;margin-bottom:3px;}
        .hm3-dow{font-size:10px;color:#3a3a48;text-align:center;padding:2px 0;}
        .hm3-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:3px;}
        .hm3-c{aspect-ratio:1;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:9px;position:relative;cursor:default;}
        .hm3-c:hover .hm3-tip{display:block;}
        .hm3-tip{display:none;position:absolute;bottom:calc(100% + 4px);left:50%;transform:translateX(-50%);background:#1c1c26;border:1px solid #2f2f3c;border-radius:5px;padding:3px 8px;font-size:10px;color:#f0ece3;white-space:nowrap;z-index:20;pointer-events:none;}
        .hm3-calendar{overflow:hidden;}
        @keyframes hm3SlideLeft{from{opacity:0;transform:translateX(22px)}to{opacity:1;transform:translateX(0)}}
        @keyframes hm3SlideRight{from{opacity:0;transform:translateX(-22px)}to{opacity:1;transform:translateX(0)}}
        .hm3-calendar.anim-left .hm3-month{animation:hm3SlideLeft .32s cubic-bezier(.22,.68,0,1.2) both;}
        .hm3-calendar.anim-left .hm3-month:nth-child(2){animation-delay:.06s;}
        .hm3-calendar.anim-right .hm3-month{animation:hm3SlideRight .32s cubic-bezier(.22,.68,0,1.2) both;}
        .hm3-calendar.anim-right .hm3-month:nth-child(2){animation-delay:.06s;}
        .hm3-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:14px;}
        .hm3-stat{background:#0d0d14;border:1px solid #1c1c26;border-radius:8px;padding:10px;text-align:center;}
        .hm3-snum{font-family:'Playfair Display',serif;font-size:1.25rem;font-weight:700;line-height:1;}
        .hm3-slbl{font-size:10px;color:#7a7880;letter-spacing:.1em;text-transform:uppercase;margin-top:3px;}
        @media(max-width:520px){
          .hm3-top{flex-direction:column;align-items:flex-start;gap:10px;margin-bottom:12px;}
          .hm3-top>div:first-child{display:flex;align-items:center;gap:.75rem;width:100%;}
          .hm3-nav{margin-left:auto;}
          .hm3-legend{width:100%;justify-content:flex-start;flex-wrap:wrap;gap:4px;}
          .hm3-calendar{flex-direction:column;gap:20px;}
          .hm3-month{min-width:0;padding-right:0;padding-bottom:0;width:100%;}
          .hm3-c{font-size:9px;}
          .hm3-stats{grid-template-columns:repeat(2,1fr);gap:8px;margin-top:16px;padding-top:14px;border-top:1px solid #1c1c26;}
          .hm3-stat{padding:10px 8px;background:#111118;border-color:#222230;}
          .hm3-snum{font-size:1.3rem;}
          .hm3-slbl{font-size:10px;letter-spacing:.06em;margin-top:4px;}
        }
        </style>
    <div class="hm3-top">
      <div style="display:flex;align-items:center;gap:.75rem;">
        <div style="font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:#7a7880;">Reservas por día</div>
        <div class="hm3-nav">
          <button class="hm3-nav-btn" id="hm-btn-prev" title="Mes anterior">‹</button>
          <button class="hm3-nav-btn" id="hm-btn-next" title="Mes siguiente" style="opacity:0.3;pointer-events:none;">›</button>
        </div>
      </div>
      <div class="hm3-legend">
        <span>Sin reservas</span>`;

                COLORS.forEach(c => {
                    html += `<div class="hm3-lswatch" style="background:${c.bg};border:1px solid ${c.border};"></div>`;
                });
                html += `<span>Muchas</span></div></div>`;
                html += `<div class="hm3-calendar" id="hm-calendar"></div>`;
                html += `<div class="hm3-stats">
      <div class="hm3-stat"><div class="hm3-snum" style="color:#d42b2b;">${totalCitas}</div><div class="hm3-slbl">Total 30d</div></div>
      <div class="hm3-stat"><div class="hm3-snum" style="color:#c9a84c;">${diasActivos}</div><div class="hm3-slbl">Días activos</div></div>
      <div class="hm3-stat"><div class="hm3-snum" style="color:#22c55e;">${maxEntry ? maxEntry[1] : 0}</div><div class="hm3-slbl">Máx. día</div></div>
      <div class="hm3-stat"><div class="hm3-snum" style="color:#6b9fff;">${avgCitas}</div><div class="hm3-slbl">Media/día activo</div></div>
    </div>`;

                // Inicializar después de insertar en DOM
                setTimeout(() => {
                    renderHeatmapContent();
                    document.getElementById('hm-btn-prev').addEventListener('click', () => {
                        hmOffset--;
                        renderHeatmapContent('prev');
                    });
                    document.getElementById('hm-btn-next').addEventListener('click', () => {
                        if (hmOffset >= 0) return;
                        hmOffset++;
                        renderHeatmapContent('next');
                    });
                }, 0);

                return html;
            }

            function buildServicesCardV2(svcs, kpi) {
                const maxTotal = Math.max(...svcs.map(s => +s.total), 1);
                const maxAcept = Math.max(...svcs.map(s => +s.citas_aceptadas || 0), 1);
                const maxPerd = Math.max(...svcs.map(s => +s.citas_perdidas || 0), 1);

                let items = '';
                svcs.forEach((s, i) => {
                    const pctAcept = Math.round((+s.citas_aceptadas || 0) / maxAcept * 100);
                    const pctPerd = Math.round((+s.citas_perdidas || 0) / maxPerd * 100);
                    const ingOk = (+s.ingresos || 0).toFixed(0);
                    const ingLost = (+s.ingresos_perdidos || 0).toFixed(0);
                    const hasLoss = +ingLost > 0;

                    items += `
        <div class="svc-stat-item" style="align-items:flex-start;padding:.85rem 0;border-bottom:1px solid #1c1c26;">
            <div class="svc-stat-rank">${i + 1}</div>
            <div class="svc-stat-info" style="flex:1;min-width:0;">
                <div class="svc-stat-name" style="margin-bottom:.55rem;">${s.nombre}</div>
                <div style="display:flex;flex-direction:column;gap:.35rem;">
                    <div style="display:flex;align-items:center;gap:.6rem;">
                        <span style="font-size:.63rem;letter-spacing:.08em;text-transform:uppercase;color:#22c55e;width:68px;flex-shrink:0;">Aceptadas</span>
                        <div style="flex:1;height:8px;background:#1c1c26;border-radius:4px;overflow:hidden;">
                            <div class="svc-stat-bar-fill" style="--svc-w:${pctAcept}%;--svc-delay:${i*0.1}s;height:100%;border-radius:4px;background:#22c55e;"></div>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:.6rem;">
                        <span style="font-size:.63rem;letter-spacing:.08em;text-transform:uppercase;color:#d42b2b;width:68px;flex-shrink:0;">Denegadas</span>
                        <div style="flex:1;height:8px;background:#1c1c26;border-radius:4px;overflow:hidden;">
                            <div class="svc-stat-bar-fill" style="--svc-w:${pctPerd}%;--svc-delay:${i*0.12+0.05}s;height:100%;border-radius:4px;background:#d42b2b;"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div style="flex-shrink:0;text-align:right;min-width:70px;padding-left:.75rem;">
                <div style="font-size:.82rem;color:#f0ece3;font-weight:500;margin-bottom:.15rem;">${s.total} cita${s.total!=1?'s':''}</div>
                <div style="font-size:.78rem;color:#22c55e;font-weight:600;">${ingOk} €</div>
                ${hasLoss
                    ? `<div style="font-size:.73rem;color:#d42b2b;text-decoration:line-through;opacity:.85;">-${ingLost} €</div>`
                    : `<div style="font-size:.72rem;color:#3a3a48;">sin pérdidas</div>`
                }
            </div>
        </div>`;
                });

                return `<div class="stats-card" style="padding-bottom:.25rem;">
        <div class="stats-card-title">Servicios más reservados</div>
        <div style="display:flex;flex-direction:column;">${items}</div>
    </div>`;
            }

            // Barber card (igual que antes)
            function barberCard(b, maxIng, i) {
                const pct = maxIng > 0 ? Math.round(+b.ingresos / maxIng * 100) : 0;
                const ticket = (+b.ingresos / Math.max(+(b.aceptadas || b.total_citas) || 1, 1)).toFixed(0);
                const isEmpty = +b.total_citas === 0;
                const colorMap = {
                    EP: '#d42b2b',
                    MV: '#2550a0',
                    AR: '#c9a84c'
                };
                const accent = colorMap[b.iniciales] || '#d42b2b';
                return `<div class="barbero-stat-card" style="cursor:pointer;${isEmpty?'opacity:.65;':''}" onclick="window._openBarberoModal('${b.iniciales}')" title="Ver detalles de ${b.nombre}">
          <div class="barbero-stat-header">
            <div class="barbero-avatar-stat" style="background:linear-gradient(135deg,${accent}25,${accent}08);border-color:${accent}40;color:${accent};">${b.iniciales}</div>
            <div><div class="barbero-stat-name">${b.nombre}</div><div class="barbero-stat-sub">${b.total_citas} citas totales</div></div>
            <div style="margin-left:auto;width:28px;height:28px;border-radius:50%;background:rgba(245,240,232,.04);border:1px solid #252530;display:flex;align-items:center;justify-content:center;font-size:.7rem;color:#7a7880;flex-shrink:0;">→</div>
          </div>
          <div class="barbero-progress-wrap">
            <div class="barbero-progress-label"><span>Ingresos generados</span><span style="color:${accent};">${(+b.ingresos).toFixed(0)} €</span></div>
            <div class="progress-track"><div class="progress-fill" style="--prog-w:${pct}%;--prog-delay:${0.2+i*0.15}s;background:linear-gradient(90deg,${accent},${accent}99);"></div></div>
          </div>
          <div class="barbero-kpi-row">
            <div class="barbero-kpi"><div class="barbero-kpi-num" style="color:#22c55e;">${b.aceptadas||0}</div><div class="barbero-kpi-lbl">Aceptadas</div></div>
            <div class="barbero-kpi"><div class="barbero-kpi-num" style="color:#f59e0b;">${b.pendientes||0}</div><div class="barbero-kpi-lbl">Pendientes</div></div>
            <div class="barbero-kpi"><div class="barbero-kpi-num" style="color:#c9a84c;">${isEmpty?'—':ticket+'€'}</div><div class="barbero-kpi-lbl">Ticket medio</div></div>
          </div>
        </div>`;
            }

        })();
    </script>

    <div class="cr-overlay" id="cr-overlay" onclick="closeCR()"></div>

    <div class="cr-panel" id="cr-panel">
        <div class="cr-header">
            <div class="cr-title" id="cr-panel-title">Gestionar reserva</div>
            <button class="cr-close" onclick="closeCR()">✕</button>
        </div>
        <div class="cr-body">

            <!-- Info de la reserva -->
            <div class="cr-reserva-info" id="cr-reserva-info">
                <div class="ri-nombre" id="cr-info-nombre">—</div>
                <div class="ri-detalle">
                    <span class="ri-chip" id="cr-info-servicio">—</span>
                    <span id="cr-info-fecha" style="color:#7a7880;font-size:.78rem;">—</span>
                    <span id="cr-info-hora" style="color:#d42b2b;font-weight:700;font-size:.78rem;">—</span>
                </div>
            </div>

            <!-- Badge de ronda de negociación -->
            <div class="cr-ronda-badge" id="cr-ronda-badge" style="display:none;">
                ⇄ Negociación activa
            </div>

            <!-- Tabs: cancelar | reprogramar -->
            <div class="cr-mode-tabs">
                <button class="cr-mode-tab active cancel" id="cr-tab-cancel"
                    onclick="crSwitchMode('cancel')">🚫 Cancelar cita</button>
                <button class="cr-mode-tab reschedule" id="cr-tab-reschedule"
                    onclick="crSwitchMode('reschedule')">⇄ Proponer cambio</button>
            </div>

            <!-- PANE: Cancelar -->
            <div class="cr-pane active" id="cr-pane-cancel">
                <div class="cr-warning">
                    ⚠ Al cancelar se notificará al cliente por email con el motivo indicado y podrá hacer una nueva reserva.
                </div>
                <div class="cr-field">
                    <label>Motivo de cancelación *</label>
                    <textarea id="cr-cancel-motivo" placeholder="Ej: Enfermedad imprevista, problema técnico…" rows="4"></textarea>
                </div>
                <button class="cr-btn-cancel" id="cr-btn-do-cancel" onclick="crDoCancel()">
                    🚫 Confirmar cancelación y notificar cliente
                </button>
                <div class="cr-status" id="cr-cancel-status"></div>
            </div>

            <!-- PANE: Reprogramar -->
            <div class="cr-pane" id="cr-pane-reschedule">
                <div class="cr-warning">
                    ⇄ El cliente recibirá un email con el nuevo horario propuesto y podrá aceptarlo, rechazarlo o proponer otro.
                </div>
                <div class="cr-field">
                    <label>Motivo del cambio *</label>
                    <textarea id="cr-resch-motivo" placeholder="Ej: Cambio de agenda, formación…" rows="3"></textarea>
                </div>
                <div class="cr-field">
                    <label>Nueva fecha propuesta *</label>
                    <input type="date" id="cr-resch-fecha"
                        onchange="crLoadSlots()" />
                </div>
                <div class="cr-field">
                    <label>Nueva hora propuesta *</label>
                    <div class="cr-slots" id="cr-slots-grid">
                        <div class="cr-slots-loading">Selecciona una fecha primero</div>
                    </div>
                    <input type="hidden" id="cr-resch-hora" />
                </div>
                <button class="cr-btn-reschedule" id="cr-btn-do-reschedule" onclick="crDoReschedule()" disabled>
                    ⇄ Enviar propuesta al cliente
                </button>
                <div class="cr-status" id="cr-resch-status"></div>
            </div>

        </div>
    </div>

    <!-- ======== SCRIPT DE CANCELACION Y REPROGRAMACION ======== -->
    <script id="cancel-reschedule-js">
        (function() {
            'use strict';

            const API = './api/cancel-by-barber.php';
            const SLOTS_API = './api/slots.php';


            let crState = {
                token: null,
                barberoId: null,
                nombre: null,
                servicio: null,
                fecha: null, // YYYY-MM-DD
                hora: null,
                ronda: 0,
                mode: 'cancel',
                selectedSlot: null,
            };

            // ── Abrir panel ──────────────────────────────────────────
            window.openCR = function(token, barberoId, nombre, servicio, fecha, hora, ronda) {
                crState = {
                    token,
                    barberoId,
                    nombre,
                    servicio,
                    fecha,
                    hora,
                    ronda: +ronda,
                    mode: 'cancel',
                    selectedSlot: null
                };

                // Rellenar info
                document.getElementById('cr-info-nombre').textContent = nombre;
                document.getElementById('cr-info-servicio').textContent = servicio;
                document.getElementById('cr-info-fecha').textContent = fecha;
                document.getElementById('cr-info-hora').textContent = hora;
                document.getElementById('cr-panel-title').textContent = 'Gestionar reserva';

                // Badge ronda
                const rondaBadge = document.getElementById('cr-ronda-badge');
                if (+ronda > 0) {
                    rondaBadge.textContent = '⇄ Negociación — ronda ' + ronda;
                    rondaBadge.style.display = 'inline-block';
                } else {
                    rondaBadge.style.display = 'none';
                }

                // Reset forms
                document.getElementById('cr-cancel-motivo').value = '';
                document.getElementById('cr-resch-motivo').value = '';
                document.getElementById('cr-resch-fecha').value = '';
                document.getElementById('cr-resch-hora').value = '';
                document.getElementById('cr-slots-grid').innerHTML = '<div class="cr-slots-loading">Selecciona una fecha primero</div>';
                document.getElementById('cr-btn-do-reschedule').disabled = true;

                // Fecha mínima: mañana
                const tomorrow = new Date();
                tomorrow.setDate(tomorrow.getDate() + 1);
                document.getElementById('cr-resch-fecha').min = tomorrow.toISOString().slice(0, 10);

                crSwitchMode('cancel');

                document.getElementById('cr-overlay').classList.add('open');
                document.getElementById('cr-panel').classList.add('open');
                document.body.style.overflow = 'hidden';
            };

            window.closeCR = function() {
                document.getElementById('cr-overlay').classList.remove('open');
                document.getElementById('cr-panel').classList.remove('open');
                document.body.style.overflow = '';
            };

            window.crSwitchMode = function(mode) {
                crState.mode = mode;
                document.querySelectorAll('.cr-mode-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.cr-pane').forEach(p => p.classList.remove('active'));
                document.getElementById('cr-tab-' + mode).classList.add('active');
                document.getElementById('cr-pane-' + mode).classList.add('active');
            };

            // ── Cargar slots para fecha seleccionada ─────────────────
            window.crLoadSlots = async function() {
                const fecha = document.getElementById('cr-resch-fecha').value;
                const grid = document.getElementById('cr-slots-grid');
                const btnResch = document.getElementById('cr-btn-do-reschedule');
                crState.selectedSlot = null;
                document.getElementById('cr-resch-hora').value = '';
                btnResch.disabled = true;

                if (!fecha) {
                    grid.innerHTML = '<div class="cr-slots-loading">Selecciona una fecha primero</div>';
                    return;
                }

                const dt = new Date(fecha + 'T00:00:00');
                if (!(window._OPEN_DAYS || [1,2,3,4,5,6]).includes(dt.getDay())) {
                    grid.innerHTML = '<div class="cr-slots-loading" style="color:#d42b2b;">Día cerrado según la configuración de horario</div>';
                    return;
                }

                grid.innerHTML = '<div class="cr-slots-loading">Cargando horarios…</div>';

                try {
                    const res = await fetch(`${SLOTS_API}?fecha=${fecha}&barbero=${crState.barberoId}`);
                    const json = await res.json();

                    if (json.ok && json.data.bloqueado) {
                        grid.innerHTML = `<div class="cr-slots-loading" style="color:#d42b2b;">🔒 Día bloqueado: ${json.data.motivo || 'No disponible'}</div>`;
                        return;
                    }

                    const ocupadas = json.ok ? (json.data.ocupadas || []) : [];
                    const now = new Date();
                    const isToday = fecha === now.toISOString().slice(0, 10);
                    const currentHHMM = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
                    const slotsFiltrados = window._ALL_SLOTS || [];

                    grid.innerHTML = slotsFiltrados.map(s => {
                        const taken = ocupadas.includes(s);
                        const past = isToday && s <= currentHHMM;
                        let cls = 'cr-slot';
                        if (taken) cls += ' taken';
                        else if (past) cls += ' past';
                        const disabled = taken || past;
                        const onclick = disabled ? '' : `onclick="crSelectSlot('${s}')"`;
                        return `<div class="${cls}" id="crslot-${s.replace(':','')}" ${onclick}>${s}</div>`;
                    }).join('');

                } catch (e) {
                    grid.innerHTML = '<div class="cr-slots-loading" style="color:#d42b2b;">Error al cargar horarios</div>';
                }
            };

            window.crSelectSlot = function(slot) {
                document.querySelectorAll('.cr-slot').forEach(el => el.classList.remove('selected'));
                const el = document.getElementById('crslot-' + slot.replace(':', ''));
                if (el) el.classList.add('selected');
                crState.selectedSlot = slot;
                document.getElementById('cr-resch-hora').value = slot;
                document.getElementById('cr-btn-do-reschedule').disabled = false;
            };

            // ── Ejecutar: CANCELAR ───────────────────────────────────
            window.crDoCancel = async function() {
                const motivo = document.getElementById('cr-cancel-motivo').value.trim();
                if (!motivo) {
                    crShowStatus('cr-cancel-status', false, 'El motivo es obligatorio.');
                    return;
                }

                if (!confirm(`¿Cancelar la reserva de ${crState.nombre}?\n\nSe le enviará un email con el motivo.`)) return;

                const btn = document.getElementById('cr-btn-do-cancel');
                btn.disabled = true;
                btn.textContent = 'Enviando…';

                try {
                    const res = await fetch(API, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            token: crState.token,
                            accion: 'cancelar',
                            motivo
                        }),
                    });
                    const json = await res.json();
                    if (json.ok) {
                        crShowStatus('cr-cancel-status', true, '✓ Reserva cancelada. Email enviado al cliente.');
                        setTimeout(() => {
                            closeCR();
                            location.reload();
                        }, 2200);
                    } else {
                        crShowStatus('cr-cancel-status', false, json.error || 'Error al cancelar.');
                    }
                } catch (e) {
                    crShowStatus('cr-cancel-status', false, 'Error de conexión.');
                } finally {
                    btn.disabled = false;
                    btn.textContent = '🚫 Confirmar cancelación y notificar cliente';
                }
            };

            // ── Ejecutar: REPROGRAMAR ────────────────────────────────
            window.crDoReschedule = async function() {
                const motivo = document.getElementById('cr-resch-motivo').value.trim();
                const nuevaFecha = document.getElementById('cr-resch-fecha').value;
                const nuevaHora = crState.selectedSlot;

                if (!motivo) {
                    crShowStatus('cr-resch-status', false, 'El motivo es obligatorio.');
                    return;
                }
                if (!nuevaFecha) {
                    crShowStatus('cr-resch-status', false, 'Selecciona una fecha.');
                    return;
                }
                if (!nuevaHora) {
                    crShowStatus('cr-resch-status', false, 'Selecciona una hora.');
                    return;
                }

                if (!confirm(`¿Proponer cambio de horario a ${crState.nombre}?\n\nNuevo horario: ${nuevaFecha} a las ${nuevaHora}\nEl cliente recibirá un email para aceptar o negociar.`)) return;

                const btn = document.getElementById('cr-btn-do-reschedule');
                btn.disabled = true;
                btn.textContent = 'Enviando…';

                try {
                    const res = await fetch(API, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            token: crState.token,
                            accion: 'reprogramar',
                            motivo,
                            nueva_fecha: nuevaFecha,
                            nueva_hora: nuevaHora,
                        }),
                    });
                    const json = await res.json();
                    if (json.ok) {
                        crShowStatus('cr-resch-status', true, '✓ Propuesta enviada. El cliente fue notificado por email.');
                        setTimeout(() => {
                            closeCR();
                            location.reload();
                        }, 2200);
                    } else {
                        crShowStatus('cr-resch-status', false, json.error || 'Error al enviar propuesta.');
                    }
                } catch (e) {
                    crShowStatus('cr-resch-status', false, 'Error de conexión.');
                } finally {
                    btn.disabled = false;
                    btn.textContent = '⇄ Enviar propuesta al cliente';
                }
            };

            function crShowStatus(id, ok, msg) {
                const el = document.getElementById(id);
                if (!el) return;
                el.className = 'cr-status visible ' + (ok ? 'ok' : 'err');
                el.textContent = msg;
                if (!ok) setTimeout(() => el.classList.remove('visible'), 4000);
            }

            // ── Manejar propuestas del cliente desde el admin ─────────
            // (cuando el barbero recibe ?reschedule_pt=XXX&raccion=aceptar|denegar)
            (function handleRescheduleFromAdmin() {
                const params = new URLSearchParams(window.location.search);
                const pt = params.get('reschedule_pt');
                const raccion = params.get('raccion');
                if (!pt || !raccion) return;

                // Limpiar URL sin recargar
                history.replaceState({}, '', window.location.pathname);

                if (raccion === 'aceptar' || raccion === 'denegar') {
                    const url = `./api/reschedule-response.php?pt=${pt}&accion=${raccion}`;
                    // Redirigir a la página de resultado
                    window.location.href = url;
                }
                // 'reproponer' → el barbero quiere proponer otro: abrir panel
                // (necesita que la página haya cargado y el token esté en BD)
                if (raccion === 'reproponer') {
                    // Mostrar un mensaje para que el barbero lo gestione desde el panel de la reserva correspondiente
                    setTimeout(() => {
                        alert('Para proponer otro horario, busca la reserva en el panel y usa el botón "Gestionar".');
                    }, 800);
                }
            })();

        })();
    </script>
    <script id="sh-script">
        (function initSlotHorarios() {
            'use strict';

            const SH_API = './api/blocked-slots.php';
            const SLOTS_API = './api/slots.php';
            const SH_BARBEROS = <?= json_encode(array_column($barberos, 'id'), JSON_UNESCAPED_UNICODE) ?>;


            // Estado
            let shFecha = '';
            let shBlockedSlots = new Set(); // slots ya bloqueados en BD
            let shReservedSlots = new Set(); // slots con reserva activa
            let shPendingBlock = new Set(); // pendientes de bloquear
            let shPendingUnblock = new Set(); // pendientes de desbloquear
            let shDayBlocked = false; // día bloqueado por vacaciones

            const DIAS_ES = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
            const MESES_ES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

            function shFormatFecha(ymd) {
                if (!ymd) return '';
                const [y, m, d] = ymd.split('-').map(Number);
                const dow = new Date(y, m - 1, d).getDay();
                return DIAS_ES[dow] + ', ' + d + ' de ' + MESES_ES[m - 1] + ' de ' + y;
            }

            // ── Inicializar la pestaña cuando se abre ─────────────────
            (function patchSwitchTab() {
                if (typeof window.switchTab !== 'function') {
                    setTimeout(patchSwitchTab, 80);
                    return;
                }
                const _orig = window.switchTab;
                window.switchTab = function(tab) {
                    _orig(tab);
                    if (tab === 'horarios') shOnTabOpen();
                };
            })();

            // ══ HORARIO DEL NEGOCIO ═══════════════════════════════════════
            const HN_API = './api/horario-negocio.php';

            function hnShowStatus(ok, msg) {
                const el = document.getElementById('hn-status');
                if (!el) return;
                el.className = 'cfg-status visible ' + (ok ? 'ok' : 'err');
                el.textContent = (ok ? '✓ ' : '✕ ') + msg;
                setTimeout(() => el.classList.remove('visible'), 3500);
            }

            function hnGenPreviewSlots(inicioStr, finStr, intervalo) {
                const slots = [];
                if (!inicioStr || !finStr || !intervalo) return slots;
                const [hI, mI] = inicioStr.split(':').map(Number);
                const [hF, mF] = finStr.split(':').map(Number);
                const minIni = hI * 60 + mI, minFin = hF * 60 + mF;
                if (minIni >= minFin || intervalo <= 0) return slots;
                for (let t = minIni; t < minFin; t += intervalo) {
                    slots.push(String(Math.floor(t / 60)).padStart(2, '0') + ':' + String(t % 60).padStart(2, '0'));
                }
                return slots;
            }

            window.hnUpdatePreview = function() {
                const intervalo = parseInt(
                    document.querySelector('#hn-interval-btns .hn-int-btn.selected')?.dataset.val || '30', 10
                );
                const mananaOn = document.getElementById('hn-manana-activo')?.checked;
                const tardeOn  = document.getElementById('hn-tarde-activo')?.checked;
                const manSlots = mananaOn
                    ? hnGenPreviewSlots(
                        document.getElementById('hn-manana-inicio')?.value,
                        document.getElementById('hn-manana-fin')?.value,
                        intervalo)
                    : [];
                const tarSlots = tardeOn
                    ? hnGenPreviewSlots(
                        document.getElementById('hn-tarde-inicio')?.value,
                        document.getElementById('hn-tarde-fin')?.value,
                        intervalo)
                    : [];
                const all = [...manSlots, ...tarSlots];
                const preview = document.getElementById('hn-preview-slots');
                if (!preview) return;
                if (!all.length) {
                    preview.innerHTML = '<span style="color:#4a4a58;font-size:.75rem;font-style:italic;">Sin horarios configurados</span>';
                    return;
                }
                preview.innerHTML = all.map(s => `<div class="hn-preview-slot">${s}</div>`).join('');
            };

            window.hnToggleDay = function(btn) {
                btn.classList.toggle('active');
            };

            window.hnTogglePeriod = function(period) {
                const chk  = document.getElementById(`hn-${period}-activo`);
                const body = document.getElementById(`hn-${period}-body`);
                if (body) body.classList.toggle('disabled', !chk?.checked);
                hnUpdatePreview();
            };

            window.hnSelectInterval = function(btn) {
                document.querySelectorAll('.hn-int-btn').forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');
                hnUpdatePreview();
            };

            async function hnLoad() {
                try {
                    const r = await fetch(HN_API);
                    const j = await r.json();
                    if (!j.ok) return;
                    const d = j.data;

                    const manActivo = d.horario_manana_activo === '1';
                    const tarActivo = d.horario_tarde_activo  === '1';

                    const mAct = document.getElementById('hn-manana-activo');
                    const tAct = document.getElementById('hn-tarde-activo');
                    if (mAct) mAct.checked = manActivo;
                    if (tAct) tAct.checked = tarActivo;

                    const mIni = document.getElementById('hn-manana-inicio');
                    const mFin = document.getElementById('hn-manana-fin');
                    const tIni = document.getElementById('hn-tarde-inicio');
                    const tFin = document.getElementById('hn-tarde-fin');
                    if (mIni) mIni.value = d.horario_manana_inicio || '09:00';
                    if (mFin) mFin.value = d.horario_manana_fin    || '14:00';
                    if (tIni) tIni.value = d.horario_tarde_inicio  || '16:00';
                    if (tFin) tFin.value = d.horario_tarde_fin     || '20:00';

                    const mBody = document.getElementById('hn-manana-body');
                    const tBody = document.getElementById('hn-tarde-body');
                    if (mBody) mBody.classList.toggle('disabled', !manActivo);
                    if (tBody) tBody.classList.toggle('disabled', !tarActivo);

                    const intervalo = d.horario_intervalo || '30';
                    document.querySelectorAll('.hn-int-btn').forEach(b => {
                        b.classList.toggle('selected', b.dataset.val === intervalo);
                    });

                    // Días de apertura
                    const diasAbiertos = (d.horario_dias_abiertos || '1,2,3,4,5,6')
                        .split(',').map(Number);
                    document.querySelectorAll('.hn-day-btn').forEach(b => {
                        b.classList.toggle('active', diasAbiertos.includes(+b.dataset.day));
                    });

                    hnUpdatePreview();
                } catch (e) {
                    console.error('hnLoad', e);
                }
            }

            window.hnSave = async function() {
                const btn = document.getElementById('hn-save-btn');
                if (btn) { btn.disabled = true; btn.textContent = 'Guardando…'; }

                const intervalo = document.querySelector('#hn-interval-btns .hn-int-btn.selected')?.dataset.val || '30';
                const diasAbiertos = Array.from(document.querySelectorAll('.hn-day-btn.active'))
                    .map(b => b.dataset.day).join(',') || '1,2,3,4,5,6';

                const body = {
                    accion:                  'guardar',
                    horario_manana_activo:   document.getElementById('hn-manana-activo')?.checked ? '1' : '0',
                    horario_manana_inicio:   document.getElementById('hn-manana-inicio')?.value || '09:00',
                    horario_manana_fin:      document.getElementById('hn-manana-fin')?.value    || '14:00',
                    horario_tarde_activo:    document.getElementById('hn-tarde-activo')?.checked ? '1' : '0',
                    horario_tarde_inicio:    document.getElementById('hn-tarde-inicio')?.value  || '16:00',
                    horario_tarde_fin:       document.getElementById('hn-tarde-fin')?.value     || '20:00',
                    horario_intervalo:       intervalo,
                    horario_dias_abiertos:   diasAbiertos,
                };

                try {
                    const r = await fetch(HN_API, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(body),
                    });
                    const j = await r.json();
                    if (j.ok) {
                        // Recargar slots globales
                        const rs = await fetch(HN_API + '?slots=1');
                        const js = await rs.json();
                        if (js.ok) {
                            window._MORNING_SLOTS   = js.data.manana        || [];
                            window._AFTERNOON_SLOTS = js.data.tarde         || [];
                            window._ALL_SLOTS       = js.data.todos         || [];
                            window._OPEN_DAYS       = js.data.dias_abiertos || [1,2,3,4,5,6];
                        }
                        hnShowStatus(true, 'Horario guardado correctamente.');
                        if (shFecha) shLoad();
                    } else {
                        hnShowStatus(false, j.error || 'Error al guardar.');
                    }
                } catch (e) {
                    hnShowStatus(false, 'Error de conexión.');
                } finally {
                    if (btn) { btn.disabled = false; btn.textContent = 'Guardar horario'; }
                }
            };

            function shOnTabOpen() {
                hnLoad();
                // Si no hay fecha seleccionada, poner hoy
                const input = document.getElementById('sh-fecha-input');
                if (input && !input.value) {
                    const today = new Date().toISOString().slice(0, 10);
                    input.value = today;
                    shFecha = today;
                    shLoad();
                }
            }

            // ── Cuando el input de fecha cambia ───────────────────────
            window.shOnFechaChange = function() {
                const input = document.getElementById('sh-fecha-input');
                if (!input) return;
                shFecha = input.value;
                if (!shFecha) return;
                // Resetear estado pendiente al cambiar de día
                shPendingBlock.clear();
                shPendingUnblock.clear();
                shLoad();
            };

            window.shSetToday = function() {
                const today = new Date().toISOString().slice(0, 10);
                const input = document.getElementById('sh-fecha-input');
                if (input) {
                    if (input._adp) input._adp._setValue(today);
                    else input.value = today;
                }
                shFecha = today;
                shPendingBlock.clear();
                shPendingUnblock.clear();
                shLoad();
            };

            // ── Cargar datos del día ──────────────────────────────────
            async function shLoad() {
                if (!shFecha) return;

                shShowLoading(true);
                shBlockedSlots.clear();
                shReservedSlots.clear();
                shDayBlocked = false;

                // Mostrar info del día
                const dayInfo = document.getElementById('sh-day-info');
                const dayLabel = document.getElementById('sh-day-label');
                if (dayInfo) dayInfo.style.display = 'block';
                if (dayLabel) dayLabel.textContent = shFormatFecha(shFecha);

                try {
                    // 1. ¿Día bloqueado por vacaciones?
                    // (Llamamos a slots.php con un barbero ficticio para detectar si el día está bloqueado)
                    const [resBlocked, resSlots] = await Promise.all([
                        fetch(`${SH_API}?fecha=${shFecha}`),
                        // Usamos el primer barbero disponible para detectar reservas
                        // (los slots bloqueados son globales, independientes del barbero)
                        fetch(`${SH_API}?fecha=${shFecha}`)
                    ]);

                    // 2. Slots bloqueados manualmente
                    const jsonBlocked = await resBlocked.json();
                    if (jsonBlocked.ok && Array.isArray(jsonBlocked.data)) {
                        jsonBlocked.data.forEach(item => shBlockedSlots.add(item.hora));
                    }

                    // 3. Reservas activas (todos los barberos)
                    await shLoadReservations();

                    // 4. ¿Día bloqueado por vacaciones?
                    await shCheckDayBlocked();

                } catch (e) {
                    console.error('shLoad error:', e);
                }

                shShowLoading(false);
                shRenderSlots();
                shUpdateSaveBtn();
            }

            async function shLoadReservations() {
                // Cargamos reservas de todos los barberos conocidos para marcar slots como reservados
                const barberos = SH_BARBEROS;
                const promises = barberos.map(b =>
                    fetch(`${SLOTS_API}?fecha=${shFecha}&barbero=${b}`)
                    .then(r => r.json())
                    .catch(() => null)
                );
                const results = await Promise.all(promises);
                results.forEach(json => {
                    if (json && json.ok && json.data && Array.isArray(json.data.ocupadas)) {
                        json.data.ocupadas.forEach(h => shReservedSlots.add(h));
                    }
                });
            }

            async function shCheckDayBlocked() {
                try {
                    // Usamos el endpoint de días bloqueados
                    const dt = new Date(shFecha + 'T00:00:00');
                    const year = dt.getFullYear(),
                        month = dt.getMonth() + 1;
                    const res = await fetch(`./api/blocked-days.php?year=${year}&month=${month}`);
                    const json = await res.json();
                    if (json.ok && json.data && json.data[shFecha] !== undefined) {
                        shDayBlocked = true;
                    } else {
                        shDayBlocked = false;
                    }
                } catch (e) {
                    shDayBlocked = false;
                }

                const warn = document.getElementById('sh-day-blocked-warn');
                if (warn) warn.style.display = shDayBlocked ? 'block' : 'none';
            }

            // ── Renderizar grid de slots ──────────────────────────────
            function shRenderSlots() {
                const container = document.getElementById('sh-slots-container');
                const empty = document.getElementById('sh-empty-state');
                if (!shFecha) {
                    if (container) container.style.display = 'none';
                    if (empty) empty.style.display = 'block';
                    return;
                }
                if (container) container.style.display = 'block';
                if (empty) empty.style.display = 'none';

                const dt = new Date(shFecha + 'T00:00:00');
                const diaCerrado = !(window._OPEN_DAYS || [1,2,3,4,5,6]).includes(dt.getDay());

                const morningSlots   = diaCerrado ? [] : (window._MORNING_SLOTS  || []);
                const afternoonSlots = diaCerrado ? [] : (window._AFTERNOON_SLOTS || []);

                shRenderGrid('sh-grid-morning', morningSlots);
                shRenderGrid('sh-grid-afternoon', afternoonSlots);

                if (diaCerrado) {
                    const grid = document.getElementById('sh-grid-morning');
                    if (grid) grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:1rem;color:#7a7880;font-size:.8rem;">Día cerrado según la configuración de horario.</div>';
                }
            }

            function shRenderGrid(gridId, slots) {
                const grid = document.getElementById(gridId);
                if (!grid) return;
                if (!slots.length) {
                    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:.5rem;color:#4a4a58;font-size:.75rem;font-style:italic;">Sin horarios</div>';
                    return;
                }
                grid.innerHTML = slots.map(slot => shSlotHtml(slot)).join('');
            }

            function shSlotHtml(slot) {
                const isReserved = shReservedSlots.has(slot) && !shBlockedSlots.has(slot);
                const isBlocked = shBlockedSlots.has(slot) && !shPendingUnblock.has(slot);
                const isPendingBlock = shPendingBlock.has(slot);
                const isPendingUnblock = shPendingUnblock.has(slot);

                let cls = 'sh-slot';
                let title = slot;
                let onclick = '';

                if (isReserved) {
                    cls += ' sh-reserved';
                    title = slot + ' (reservado)';
                    // No editable
                } else if (isPendingUnblock) {
                    cls += ' sh-pending-unblock';
                    title = slot + ' — clic para cancelar desbloqueo';
                    onclick = `onclick="shToggleSlot('${slot}')"`;
                } else if (isBlocked) {
                    cls += ' sh-blocked';
                    title = slot + ' — bloqueado (clic para desbloquear)';
                    onclick = `onclick="shToggleSlot('${slot}')"`;
                } else if (isPendingBlock) {
                    cls += ' sh-pending-block';
                    title = slot + ' — pendiente de bloquear (clic para cancelar)';
                    onclick = `onclick="shToggleSlot('${slot}')"`;
                } else {
                    cls += ' sh-free';
                    title = slot + ' — libre (clic para bloquear)';
                    onclick = `onclick="shToggleSlot('${slot}')"`;
                }

                return `<div class="${cls}" title="${title}" ${onclick}>${slot}</div>`;
            }

            // ── Toggle de un slot ─────────────────────────────────────
            window.shToggleSlot = function(slot) {
                if (shReservedSlots.has(slot) && !shBlockedSlots.has(slot)) return; // reservado, no editable

                if (shBlockedSlots.has(slot)) {
                    // Ya bloqueado: marcar para desbloquear (o cancelar el desbloqueo)
                    if (shPendingUnblock.has(slot)) {
                        shPendingUnblock.delete(slot);
                    } else {
                        shPendingUnblock.add(slot);
                    }
                } else {
                    // Libre: marcar para bloquear (o cancelar el bloqueo pendiente)
                    if (shPendingBlock.has(slot)) {
                        shPendingBlock.delete(slot);
                    } else {
                        shPendingBlock.add(slot);
                    }
                }

                shRenderSlots();
                shUpdateSaveBtn();
            };

            // ── Bloquear todos los libres ─────────────────────────────
            window.shBlockAll = function() {
                const slots = window._ALL_SLOTS || [];

                slots.forEach(slot => {
                    if (!shBlockedSlots.has(slot) && !shReservedSlots.has(slot)) {
                        shPendingBlock.add(slot);
                    }
                });
                shRenderSlots();
                shUpdateSaveBtn();
            };

            // ── Liberar todos ─────────────────────────────────────────
            window.shUnblockAll = function() {
                shPendingBlock.clear();
                shBlockedSlots.forEach(slot => shPendingUnblock.add(slot));
                shRenderSlots();
                shUpdateSaveBtn();
            };

            // ── Actualizar estado del botón Guardar ───────────────────
            function shUpdateSaveBtn() {
                const btn = document.getElementById('sh-save-btn');
                if (!btn) return;
                const hasPending = shPendingBlock.size > 0 || shPendingUnblock.size > 0;
                btn.disabled = !hasPending;

                // Mostrar resumen de cambios pendientes
                const existingSummary = document.getElementById('sh-pending-summary');
                if (existingSummary) existingSummary.remove();

                if (hasPending) {
                    const summary = document.createElement('div');
                    summary.id = 'sh-pending-summary';
                    summary.className = 'sh-pending-summary';
                    const parts = [];
                    if (shPendingBlock.size > 0) parts.push(`🔒 ${shPendingBlock.size} a bloquear`);
                    if (shPendingUnblock.size > 0) parts.push(`🔓 ${shPendingUnblock.size} a liberar`);
                    summary.textContent = parts.join(' · ');
                    btn.insertAdjacentElement('beforebegin', summary);
                }
            }

            // ── Guardar cambios pendientes ────────────────────────────
            window.shSavePending = async function() {
                const motivo = (document.getElementById('sh-motivo-input')?.value || '').trim() || 'No disponible';
                const btn = document.getElementById('sh-save-btn');
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Guardando…';
                }

                try {
                    const ops = [];

                    // Bloquear slots pendientes
                    if (shPendingBlock.size > 0) {
                        ops.push(
                            fetch(SH_API, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    accion: 'bloquear_slots',
                                    fecha: shFecha,
                                    horas: Array.from(shPendingBlock),
                                    motivo
                                })
                            })
                        );
                    }

                    // Desbloquear slots pendientes (uno a uno)
                    shPendingUnblock.forEach(slot => {
                        ops.push(
                            fetch(SH_API, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    accion: 'desbloquear_slot',
                                    fecha: shFecha,
                                    hora: slot
                                })
                            })
                        );
                    });

                    await Promise.all(ops);

                    shPendingBlock.clear();
                    shPendingUnblock.clear();

                    shShowStatus(true, 'Cambios guardados correctamente.');
                    await shLoad(); // recargar estado real

                } catch (e) {
                    shShowStatus(false, 'Error al guardar. Inténtalo de nuevo.');
                } finally {
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = 'Guardar cambios';
                    }
                }
            };

            // ── Helpers ───────────────────────────────────────────────
            function shShowLoading(show) {
                const loading = document.getElementById('sh-loading');
                const container = document.getElementById('sh-slots-container');
                const empty = document.getElementById('sh-empty-state');
                if (loading) loading.style.display = show ? 'flex' : 'none';
                if (show) {
                    if (container) container.style.display = 'none';
                    if (empty) empty.style.display = 'none';
                }
            }

            function shShowStatus(ok, msg) {
                const el = document.getElementById('sh-status');
                if (!el) return;
                el.className = 'cfg-status visible ' + (ok ? 'ok' : 'err');
                el.textContent = (ok ? '✓ ' : '✕ ') + msg;
                setTimeout(() => el.classList.remove('visible'), 3500);
            }

        })();
    </script>
    <script>
        // ============================================================
//  PRADO BARBER CO. — admin-recordatorios.js
//  Pestaña "Recordatorios" dentro del panel de configuración
// ============================================================

(function initRecordatorios() {
    'use strict';

    const API_STATUS  = './api/reminder-status.php';
    const API_TRIGGER = './api/reminder.php';

    // ── Inyectar HTML de la pestaña en el panel de config ─────
    // Se llama una vez que el DOM está listo
    function injectTab() {
        // 1. Añadir el botón de la pestaña
        const tabsEl = document.querySelector('.cfg-tabs');
        if (!tabsEl) return;

        // Evitar duplicados
        if (document.getElementById('cfg-tab-reminders')) return;

        const tabBtn = document.createElement('button');
        tabBtn.className    = 'cfg-tab';
        tabBtn.id           = 'cfg-tab-reminders';
        tabBtn.textContent  = 'Avisos';
        tabBtn.title        = 'Recordatorios';
        tabBtn.setAttribute('onclick', "switchTab('recordatorios')");
        const cfgIndicator = document.getElementById('cfg-tab-indicator');
        if (cfgIndicator) tabsEl.insertBefore(tabBtn, cfgIndicator);
        else tabsEl.appendChild(tabBtn);

        // 2. Añadir el panel de contenido
        const cfgBody = document.querySelector('.cfg-body');
        if (!cfgBody) return;

        const pane = document.createElement('div');
        pane.className = 'cfg-pane';
        pane.id        = 'pane-recordatorios';
        pane.innerHTML = getPaneHTML();
        cfgBody.appendChild(pane);

        // 3. Parchear switchTab para cargar datos al abrir
        patchSwitchTab();
    }

    // ── HTML del panel ────────────────────────────────────────
    function getPaneHTML() {
        return `
        <!-- KPIs -->
        <div class="cfg-section-label">Esta semana</div>
        <div class="rm-kpi-grid">
            <div class="rd-stat-kpi rd-stat-accent" id="rm-kpi-enviados">
                <div class="rd-stat-val">—</div>
                <div class="rd-stat-lbl">Enviados</div>
            </div>
            <div class="rd-stat-kpi" id="rm-kpi-pendientes">
                <div class="rd-stat-val">—</div>
                <div class="rd-stat-lbl">Pendientes mañana</div>
            </div>
            <div class="rd-stat-kpi" id="rm-kpi-errores">
                <div class="rd-stat-val">—</div>
                <div class="rd-stat-lbl">Errores</div>
            </div>
        </div>

        <!-- Citas de mañana -->
        <div class="cfg-section-label rm-section-head">
            <span>Citas de mañana</span>
            <button onclick="rmLoadStatus()" id="rm-refresh-btn" class="rm-refresh-btn">
                ↻ Actualizar
            </button>
        </div>
        <div id="rm-manana-list" class="rm-list">
            <div class="datos-loading">Cargando…</div>
        </div>

        <!-- Disparador manual -->
        <div class="cfg-section-label">Envío manual</div>
        <div class="rm-panel-box">
            <p style="font-size:.8rem;color:#7a7880;line-height:1.6;margin-bottom:.85rem;">
                Lanza el script de recordatorios ahora mismo. Solo enviará a citas de mañana que aún no hayan recibido recordatorio.
            </p>
            <button id="rm-trigger-btn" onclick="rmTrigger()"
                style="width:100%;padding:.8rem;border-radius:7px;
                       background:linear-gradient(135deg,#c9a84c,#a17c2d);
                       border:none;color:#000;font-family:'DM Sans',sans-serif;
                       font-size:.78rem;font-weight:700;letter-spacing:.1em;
                       text-transform:uppercase;cursor:pointer;transition:all .25s;
                       box-shadow:0 4px 16px rgba(201,168,76,.2);">
                ▶ Enviar recordatorios ahora
            </button>
            <div id="rm-trigger-status" class="cfg-status"></div>
        </div>

        <!-- Configuración cron -->
        <div class="cfg-section-label">Configuración automática</div>
        <div style="background:#18181f;border:1px solid rgba(201,168,76,.15);border-radius:10px;padding:1rem 1.25rem;margin-bottom:1.5rem;">
            <div style="font-size:.75rem;color:#c9a84c;font-weight:600;letter-spacing:.08em;text-transform:uppercase;margin-bottom:.75rem;">
                ⚙ Cron job recomendado (diario a las 9:00)
            </div>
            <div style="background:#0d0d14;border:1px solid #1c1c26;border-radius:6px;padding:.65rem .85rem;margin-bottom:.75rem;position:relative;">
                <code id="rm-cron-cmd" style="font-family:monospace;font-size:.75rem;color:#c9a84c;word-break:break-all;">
                    Cargando…
                </code>
                <button onclick="rmCopyCmd()" title="Copiar"
                    style="position:absolute;top:6px;right:6px;width:26px;height:26px;
                           border-radius:5px;background:transparent;border:1px solid #252530;
                           color:#7a7880;cursor:pointer;font-size:.8rem;display:flex;
                           align-items:center;justify-content:center;transition:all .2s;"
                    onmouseover="this.style.borderColor='#c9a84c';this.style.color='#c9a84c';"
                    onmouseout="this.style.borderColor='#252530';this.style.color='#7a7880';">
                    ⎘
                </button>
            </div>
            <p style="font-size:.75rem;color:#7a7880;line-height:1.55;margin:0;">
                Si el hosting no permite cron jobs, usa un servicio externo como
                <a href="https://cron-job.org" target="_blank" style="color:#c9a84c;">cron-job.org</a>
                (gratuito) para llamar a la URL del script diariamente.
            </p>
            <div style="margin-top:.75rem;">
                <div style="font-size:.7rem;letter-spacing:.12em;text-transform:uppercase;color:#7a7880;margin-bottom:.4rem;">URL directa del script</div>
                <div style="background:#0d0d14;border:1px solid #1c1c26;border-radius:6px;padding:.65rem .85rem;position:relative;">
                    <code id="rm-cron-url" style="font-family:monospace;font-size:.72rem;color:#9ca3af;word-break:break-all;">
                        Cargando…
                    </code>
                    <button onclick="rmCopyUrl()" title="Copiar URL"
                        style="position:absolute;top:6px;right:6px;width:26px;height:26px;
                               border-radius:5px;background:transparent;border:1px solid #252530;
                               color:#7a7880;cursor:pointer;font-size:.8rem;display:flex;
                               align-items:center;justify-content:center;transition:all .2s;"
                        onmouseover="this.style.borderColor='#c9a84c';this.style.color='#c9a84c';"
                        onmouseout="this.style.borderColor='#252530';this.style.color='#7a7880';">
                        ⎘
                    </button>
                </div>
            </div>
        </div>

        <!-- Historial de envíos -->
        <div class="cfg-section-label">Historial reciente</div>
        <div id="rm-log-list">
            <div class="datos-loading">Cargando…</div>
        </div>

        <div class="cfg-status" id="rm-load-status"></div>
        `;
    }

    // ── Estado guardado ───────────────────────────────────────
    let rmData = null;

    // ── Cargar datos del API ──────────────────────────────────
    window.rmLoadStatus = async function () {
        const refreshBtn = document.getElementById('rm-refresh-btn');
        if (refreshBtn) { refreshBtn.textContent = '↻ …'; refreshBtn.disabled = true; }

        try {
            const res  = await fetch(API_STATUS);
            const json = await res.json();

            if (!json.ok) {
                showRmStatus('rm-load-status', false, json.error || 'Error al cargar');
                return;
            }

            rmData = json.data;
            renderKPIs(rmData.stats);
            renderManana(rmData.reservas_manana, rmData.manana);
            renderLog(rmData.log_recientes);
            renderCronInfo(rmData.cron_cmd, rmData.cron_url);

        } catch (e) {
            showRmStatus('rm-load-status', false, 'Error de conexión');
        } finally {
            if (refreshBtn) { refreshBtn.textContent = '↻ Actualizar'; refreshBtn.disabled = false; }
        }
    };

    // ── KPIs ──────────────────────────────────────────────────
    function renderKPIs(stats) {
        const env  = document.querySelector('#rm-kpi-enviados .rd-stat-val');
        const pend = document.querySelector('#rm-kpi-pendientes .rd-stat-val');
        const err  = document.querySelector('#rm-kpi-errores .rd-stat-val');

        if (env)  env.textContent  = stats.total_enviados;
        if (pend) { pend.textContent = stats.pendientes_manana; pend.style.color = stats.pendientes_manana > 0 ? '#f59e0b' : '#f0ece3'; }
        if (err)  { err.textContent  = stats.total_errores; err.style.color = stats.total_errores > 0 ? '#d42b2b' : '#f0ece3'; }
    }

    // ── Reservas de mañana ────────────────────────────────────
    function renderManana(reservas, fechaManana) {
        const el = document.getElementById('rm-manana-list');
        if (!el) return;

        if (!reservas || !reservas.length) {
            el.innerHTML = `<div style="text-align:center;padding:1.5rem;color:#7a7880;font-size:.8rem;border:1px dashed #252530;border-radius:8px;">No hay citas confirmadas para el ${fechaManana}</div>`;
            return;
        }

        el.innerHTML = reservas.map(r => {
            const enviado = r.recordatorio_enviado == 1;
            return `
            <div style="display:flex;align-items:center;gap:.75rem;background:#18181f;
                        border:1px solid ${enviado ? 'rgba(34,197,94,.25)' : 'rgba(245,158,11,.2)'};
                        border-radius:9px;padding:.65rem 1rem;margin-bottom:.4rem;">
                <div style="width:32px;height:32px;border-radius:7px;
                            background:${enviado ? 'rgba(34,197,94,.1)' : 'rgba(245,158,11,.1)'};
                            border:1px solid ${enviado ? 'rgba(34,197,94,.25)' : 'rgba(245,158,11,.25)'};
                            display:flex;align-items:center;justify-content:center;
                            font-size:.7rem;color:${enviado ? '#22c55e' : '#f59e0b'};
                            font-weight:700;flex-shrink:0;">
                    ${enviado ? '✓' : '⏳'}
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:.875rem;font-weight:500;color:#f0ece3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        ${escHtml(r.cliente_nombre)}
                    </div>
                    <div style="font-size:.72rem;color:#7a7880;margin-top:.1rem;">
                        ${escHtml(r.servicio)} · ${escHtml(r.barbero)}
                    </div>
                </div>
                <div style="text-align:right;flex-shrink:0;">
                    <div style="font-size:.95rem;font-weight:700;color:#c9a84c;">${r.hora}</div>
                    <div style="font-size:.68rem;color:${enviado ? '#22c55e' : '#7a7880'};">
                        ${enviado ? 'Recordatorio ✓' : 'Sin recordatorio'}
                    </div>
                </div>
            </div>`;
        }).join('');
    }

    // ── Historial de log ──────────────────────────────────────
    function renderLog(logs) {
        const el = document.getElementById('rm-log-list');
        if (!el) return;

        if (!logs || !logs.length) {
            el.innerHTML = '<div style="text-align:center;padding:1.5rem;color:#7a7880;font-size:.8rem;border:1px dashed #252530;border-radius:8px;">Sin registros aún. Los recordatorios aparecerán aquí una vez enviados.</div>';
            return;
        }

        el.innerHTML = `
        <div style="max-height:260px;overflow-y:auto;border:1px solid #252530;border-radius:8px;overflow-x:hidden;">
            ${logs.map((l, i) => {
                const ok = l.resultado === 'ok';
                const fechaEnvio = l.enviado_en ? l.enviado_en.slice(0, 16).replace('T', ' ') : '—';
                const bg = i % 2 === 0 ? '#18181f' : '#111119';
                return `
                <div style="display:flex;align-items:center;gap:.6rem;padding:.6rem 1rem;
                            background:${bg};border-bottom:1px solid #1a1a26;">
                    <span style="font-size:.75rem;color:${ok ? '#22c55e' : '#d42b2b'};
                                 flex-shrink:0;width:14px;">
                        ${ok ? '✓' : '✕'}
                    </span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:.78rem;color:#f0ece3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            ${escHtml(l.cliente_nombre || '#' + l.reserva_id)}
                        </div>
                        <div style="font-size:.68rem;color:#7a7880;">
                            Cita: ${l.fecha_cita || '?'} ${l.hora_cita || ''}
                        </div>
                    </div>
                    <div style="text-align:right;flex-shrink:0;">
                        <div style="font-size:.68rem;color:#7a7880;">${fechaEnvio}</div>
                    </div>
                </div>`;
            }).join('')}
        </div>`;
    }

    // ── Cron info ─────────────────────────────────────────────
    function renderCronInfo(cmd, url) {
        const cmdEl = document.getElementById('rm-cron-cmd');
        const urlEl = document.getElementById('rm-cron-url');
        if (cmdEl) cmdEl.textContent = cmd || '—';
        if (urlEl) urlEl.textContent = url || '—';
    }

    // ── Copiar al portapapeles ────────────────────────────────
    window.rmCopyCmd = function () {
        const el = document.getElementById('rm-cron-cmd');
        navigator.clipboard.writeText(el ? el.textContent.trim() : '').then(() => {
            showRmStatus('rm-load-status', true, '✓ Comando copiado al portapapeles.');
        });
    };
    window.rmCopyUrl = function () {
        const el = document.getElementById('rm-cron-url');
        navigator.clipboard.writeText(el ? el.textContent.trim() : '').then(() => {
            showRmStatus('rm-load-status', true, '✓ URL copiada al portapapeles.');
        });
    };

    // ── Disparar manualmente ──────────────────────────────────
    window.rmTrigger = async function () {
        if (!rmData) { showRmStatus('rm-trigger-status', false, 'Carga el estado primero.'); return; }

        const pendientes = rmData.stats.pendientes_manana;
        if (pendientes === 0) {
            showRmStatus('rm-trigger-status', false, 'No hay recordatorios pendientes para mañana.');
            return;
        }

        if (!confirm(`¿Enviar ahora ${pendientes} recordatorio${pendientes !== 1 ? 's' : ''} para las citas de mañana?`)) return;

        const btn = document.getElementById('rm-trigger-btn');
        if (btn) { btn.disabled = true; btn.textContent = '⏳ Enviando…'; }

        try {
            const secretEl = document.getElementById('rm-cron-url');
            const base = secretEl ? secretEl.textContent.trim() : API_TRIGGER;
            const url  = base + (base.includes('?') ? '&' : '?') + 'force=1';
            const res  = await fetch(url);
            const json = await res.json();

            if (json.ok) {
                showRmStatus('rm-trigger-status', true,
                    `✓ ${json.enviados} recordatorio${json.enviados !== 1 ? 's' : ''} enviado${json.enviados !== 1 ? 's' : ''}.` +
                    (json.errores > 0 ? ` ${json.errores} error${json.errores !== 1 ? 'es' : ''}.` : '')
                );
                // Recargar estado para reflejar los enviados
                await rmLoadStatus();
            } else {
                showRmStatus('rm-trigger-status', false, json.error || 'Error al enviar.');
            }
        } catch (e) {
            showRmStatus('rm-trigger-status', false, 'Error de conexión.');
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = '▶ Enviar recordatorios ahora'; }
        }
    };

    // ── Helpers ───────────────────────────────────────────────
    function escHtml(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function showRmStatus(id, ok, msg) {
        const el = document.getElementById(id);
        if (!el) return;
        el.className = 'cfg-status visible ' + (ok ? 'ok' : 'err');
        el.textContent = msg;
        clearTimeout(el._t);
        el._t = setTimeout(() => el.classList.remove('visible'), 4000);
    }

    // ── Parchear switchTab para detectar apertura ─────────────
    function patchSwitchTab() {
        if (typeof window.switchTab !== 'function') {
            setTimeout(patchSwitchTab, 60); return;
        }
        const _orig = window.switchTab;
        window.switchTab = function (tab) {
            _orig(tab);
            if (tab === 'recordatorios') rmLoadStatus();
        };
    }

    // ── Init ──────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectTab);
    } else {
        injectTab();
    }

})();
    </script>

    <!-- Admin Date Picker -->
    <script>
    (function () {
        const MONTHS_ES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        const DAYS_ES = ['L','M','X','J','V','S','D'];

        class AdminDatePicker {
            constructor(input, opts = {}) {
                this.input = input;
                this.allowPast = opts.allowPast !== false;
                this.onPick = opts.onPick || null;
                const init = input.value ? new Date(input.value + 'T00:00:00') : new Date();
                this.vy = init.getFullYear();
                this.vm = init.getMonth();
                this.sel = input.value || null;
                this._build();
            }

            _build() {
                const wrap = document.createElement('div');
                wrap.className = 'adp-wrap';
                this.input.parentNode.insertBefore(wrap, this.input);
                wrap.appendChild(this.input);
                this.input.style.cssText = 'position:absolute;opacity:0;width:0;height:0;pointer-events:none;';
                this.input._adp = this;

                this.btn = document.createElement('button');
                this.btn.type = 'button';
                this.btn.className = 'adp-trigger';
                this.btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><span class="adp-trigger-text"></span>';
                wrap.appendChild(this.btn);
                this._refreshBtn();

                this.pop = document.createElement('div');
                this.pop.className = 'adp-popover';
                wrap.appendChild(this.pop);
                this._renderPop();

                this.btn.addEventListener('click', e => { e.stopPropagation(); this.pop.classList.toggle('open'); });
                document.addEventListener('click', () => this.pop.classList.remove('open'));
                this.pop.addEventListener('click', e => e.stopPropagation());
            }

            _refreshBtn() {
                const s = this.btn.querySelector('.adp-trigger-text');
                if (this.sel) {
                    const [y, m, d] = this.sel.split('-');
                    s.textContent = parseInt(d) + ' ' + MONTHS_ES[parseInt(m) - 1] + ' ' + y;
                } else {
                    s.textContent = 'Seleccionar fecha';
                }
            }

            _renderPop() {
                const today = new Date(); today.setHours(0, 0, 0, 0);
                const first = new Date(this.vy, this.vm, 1);
                const last = new Date(this.vy, this.vm + 1, 0);
                let dow = first.getDay(); dow = dow === 0 ? 6 : dow - 1;

                let h = '<div class="adp-hdr"><div class="adp-title">' + MONTHS_ES[this.vm] + ' ' + this.vy + '</div>';
                h += '<div class="adp-nav"><button data-d="-1">‹</button><button data-d="1">›</button></div></div>';
                h += '<div class="adp-labels">' + DAYS_ES.map(d => '<div class="adp-dlabel">' + d + '</div>').join('') + '</div>';
                h += '<div class="adp-grid">';
                for (let i = 0; i < dow; i++) h += '<div class="adp-cell adp-empty"></div>';
                for (let d = 1; d <= last.getDate(); d++) {
                    const dt = new Date(this.vy, this.vm, d);
                    const ds = this.vy + '-' + String(this.vm + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
                    let c = 'adp-cell';
                    if (!this.allowPast && dt < today) c += ' adp-dis';
                    if (dt.getTime() === today.getTime()) c += ' adp-today';
                    if (this.sel === ds) c += ' adp-sel';
                    const pick = (this.allowPast || dt >= today) ? ' data-pick="' + ds + '"' : '';
                    h += '<div class="' + c + '"' + pick + '>' + d + '</div>';
                }
                h += '</div>';
                this.pop.innerHTML = h;

                this.pop.querySelectorAll('[data-d]').forEach(b => b.addEventListener('click', e => { e.stopPropagation(); this._nav(+b.dataset.d); }));
                this.pop.querySelectorAll('[data-pick]').forEach(b => b.addEventListener('click', () => this._pick(b.dataset.pick)));
            }

            _nav(d) {
                this.vm += d;
                if (this.vm > 11) { this.vm = 0; this.vy++; }
                else if (this.vm < 0) { this.vm = 11; this.vy--; }
                this._renderPop();
            }

            _pick(ds) {
                this.sel = ds; this.input.value = ds;
                this._refreshBtn(); this._renderPop();
                this.pop.classList.remove('open');
                if (this.onPick) this.onPick(ds);
                else this.input.dispatchEvent(new Event('change', { bubbles: true }));
            }

            _setValue(ds) {
                this.sel = ds; this.input.value = ds;
                this.vy = parseInt(ds.split('-')[0]);
                this.vm = parseInt(ds.split('-')[1]) - 1;
                this._refreshBtn(); this._renderPop();
            }
        }

        function initPickers() {
            const fc = document.getElementById('fecha-custom-input');
            if (fc) new AdminDatePicker(fc, { allowPast: true });

            const sh = document.getElementById('sh-fecha-input');
            if (sh) new AdminDatePicker(sh, { allowPast: false, onPick: () => { if (window.shOnFechaChange) shOnFechaChange(); } });
        }

        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initPickers);
        else initPickers();
    })();
    </script>
</body>

</html>
