<?php
require_once 'config/db.php';
require_once 'config/motor_tiempo.php';

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'bibliotecario') {
    header("Location: index.php");
    exit;
}

ejecutarReglasTemporales($pdo);
$ahoraStr = obtenerHoraSimulada($pdo);
$admin = $_SESSION['usuario'];

$boxes = $pdo->query("SELECT * FROM box ORDER BY codigo ASC")->fetchAll();
$estudiantes = $pdo->query("SELECT * FROM usuario WHERE rol = 'estudiante' ORDER BY nombre ASC")->fetchAll();
$reservas = $pdo->query("
    SELECT r.*, u.nombre AS estudiante, u.identificador_institucional, b.codigo AS box_codigo 
    FROM reserva r 
    JOIN usuario u ON r.id_usuario = u.id_usuario 
    JOIN box b ON r.id_box = b.id_box 
    ORDER BY r.fecha_hora_inicio DESC
")->fetchAll();

$sanciones = $pdo->query("
    SELECT s.*, u.id_usuario, u.nombre, u.identificador_institucional 
    FROM sancion s 
    JOIN reserva r ON s.id_reserva = r.id_reserva 
    JOIN usuario u ON r.id_usuario = u.id_usuario 
    ORDER BY s.id_sancion DESC
")->fetchAll();

$auditoria = $pdo->query("
    SELECT h.*, u.nombre AS actor, u.rol 
    FROM historial_acciones h 
    JOIN usuario u ON h.id_usuario = u.id_usuario 
    ORDER BY h.id_historial DESC LIMIT 25
")->fetchAll();

$statsUso = $pdo->query("
    SELECT b.codigo, 
           COUNT(r.id_reserva) AS total_reservas,
           SUM(CASE WHEN r.estado IN ('confirmada', 'finalizada') THEN 1 ELSE 0 END) AS efectivas,
           SUM(CASE WHEN r.estado = 'inasistencia' THEN 1 ELSE 0 END) AS inasistencias
    FROM box b
    LEFT JOIN reserva r ON b.id_box = r.id_box
    GROUP BY b.id_box, b.codigo
    ORDER BY b.codigo ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administracion Biblioteca - Gestor de Boxes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <span class="navbar-brand fw-bold">Panel Biblioteca | Administracion y Auditoria</span>
        <div class="d-flex align-items-center gap-2">
            <a href="correos.php" class="btn btn-warning btn-sm fw-semibold">Monitor Correos</a>
            <span class="text-white small me-2"><?= htmlspecialchars($admin['nombre']) ?></span>
            <a href="api/acciones.php?accion=logout" class="btn btn-outline-light btn-sm">Salir</a>
        </div>
    </div>
</nav>

<div class="bg-dark text-white py-2">
    <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <span class="badge bg-info text-dark me-2">RELOJ SIMULADO</span>
            <strong><?= $ahoraStr ?></strong>
        </div>
        <form action="api/acciones.php" method="POST" class="d-flex gap-1 flex-wrap">
            <input type="hidden" name="accion" value="avanzar_tiempo">
            <input type="hidden" name="origen" value="../admin.php">
            <button type="submit" name="minutos" value="15" class="btn btn-sm btn-outline-light">+15 min</button>
            <button type="submit" name="minutos" value="30" class="btn btn-sm btn-outline-light">+30 min</button>
            <button type="submit" name="minutos" value="45" class="btn btn-sm btn-outline-warning">+45 min</button>
            <button type="submit" name="minutos" value="120" class="btn btn-sm btn-outline-info">+2 horas</button>
        </form>
    </div>
</div>

<div class="container my-4">
    <?php if (isset($_SESSION['flash'])): ?>
        <div class="alert alert-<?= $_SESSION['flash']['tipo'] ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash']['msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <h5 class="fw-bold mb-3">Estado Operativo de los 9 Boxes</h5>
    <div class="row g-2 mb-4">
        <?php foreach ($boxes as $b): ?>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="card border-0 shadow-sm text-center p-2">
                    <div class="fw-bold"><?= $b['codigo'] ?></div>
                    <span class="badge mb-2 <?= $b['estado'] === 'habilitado' ? 'bg-success' : 'bg-secondary' ?>">
                        <?= $b['estado'] ?>
                    </span>
                    <form action="api/acciones.php" method="POST">
                        <input type="hidden" name="accion" value="toggle_box">
                        <input type="hidden" name="id_box" value="<?= $b['id_box'] ?>">
                        <input type="hidden" name="nuevo_estado" value="<?= $b['estado'] === 'habilitado' ? 'fuera_de_servicio' : 'habilitado' ?>">
                        <button class="btn btn-outline-dark btn-sm w-100" style="font-size: 0.75rem;">
                            <?= $b['estado'] === 'habilitado' ? 'Inhabilitar' : 'Habilitar' ?>
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">Reserva Manual en Mostrador</div>
                <div class="card-body">
                    <form action="api/acciones.php" method="POST">
                        <input type="hidden" name="accion" value="crear_reserva">
                        <input type="hidden" name="modo_admin" value="1">
                        <div class="mb-2">
                            <label class="form-label small">Estudiante</label>
                            <select name="id_usuario_target" class="form-select form-select-sm" required>
                                <?php foreach ($estudiantes as $e): ?>
                                    <option value="<?= $e['id_usuario'] ?>"><?= htmlspecialchars($e['nombre']) ?> (<?= $e['identificador_institucional'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Box</label>
                            <select name="id_box" class="form-select form-select-sm" required>
                                <?php foreach ($boxes as $b): if ($b['estado'] === 'habilitado'): ?>
                                    <option value="<?= $b['id_box'] ?>"><?= $b['codigo'] ?></option>
                                <?php endif; endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-5">
                                <label class="form-label small">Fecha</label>
                                <input type="date" name="fecha" class="form-control form-control-sm" value="<?= date('Y-m-d', strtotime($ahoraStr)) ?>" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label small">Inicio</label>
                                <input type="time" name="hora_inicio" class="form-control form-control-sm" value="<?= date('H:00', strtotime($ahoraStr)) ?>" required>
                            </div>
                            <div class="col-3">
                                <label class="form-label small">Horas</label>
                                <select name="duracion" class="form-select form-select-sm">
                                    <option value="1">1h</option>
                                    <option value="2">2h</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100">Asignar Box en Mostrador</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">Reporte Estadistico de Ocupacion por Box</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 text-center small">
                        <thead class="table-light">
                            <tr>
                                <th>Box</th>
                                <th>Total Reservas</th>
                                <th>Efectivas (Confirmadas/Finalizadas)</th>
                                <th>Inasistencias</th>
                                <th>Tasa de Efectividad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($statsUso as $st): 
                                $tasa = $st['total_reservas'] > 0 ? round(($st['efectivas'] / $st['total_reservas']) * 100) : 0;
                            ?>
                                <tr>
                                    <td class="fw-bold"><?= $st['codigo'] ?></td>
                                    <td><?= $st['total_reservas'] ?></td>
                                    <td class="text-success fw-bold"><?= $st['efectivas'] ?? 0 ?></td>
                                    <td class="text-danger"><?= $st['inasistencias'] ?? 0 ?></td>
                                    <td><?= $tasa ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold">Gestion General de Reservas</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Estudiante</th>
                        <th>Box</th>
                        <th>Horario</th>
                        <th>Estado</th>
                        <th>Modificar / Anular (Auditado)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservas as $r): ?>
                        <tr>
                            <td>#<?= $r['id_reserva'] ?></td>
                            <td><?= htmlspecialchars($r['estudiante']) ?> <br><span class="text-muted"><?= $r['identificador_institucional'] ?></span></td>
                            <td class="fw-bold"><?= $r['box_codigo'] ?></td>
                            <td><?= date('d/m H:i', strtotime($r['fecha_hora_inicio'])) ?> - <?= date('H:i', strtotime($r['fecha_hora_fin'])) ?></td>
                            <td><span class="badge bg-dark"><?= strtoupper($r['estado']) ?></span></td>
                            <td>
                                <form action="api/acciones.php" method="POST" class="d-inline-flex gap-1">
                                    <input type="hidden" name="accion" value="modificar_reserva_admin">
                                    <input type="hidden" name="id_reserva" value="<?= $r['id_reserva'] ?>">
                                    <select name="id_box" class="form-select form-select-sm" style="width: 95px;">
                                        <?php foreach ($boxes as $b): ?>
                                            <option value="<?= $b['id_box'] ?>" <?= $b['id_box'] == $r['id_box'] ? 'selected' : '' ?>><?= $b['codigo'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select name="estado" class="form-select form-select-sm" style="width: 115px;">
                                        <?php foreach (['pendiente', 'confirmada', 'cancelada', 'inasistencia', 'finalizada'] as $est): ?>
                                            <option value="<?= $est ?>" <?= $est === $r['estado'] ? 'selected' : '' ?>><?= $est ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-outline-primary">Guardar</button>
                                </form>
                                <?php if (in_array($r['estado'], ['pendiente', 'confirmada'])): ?>
                                    <form action="api/acciones.php" method="POST" class="d-inline">
                                        <input type="hidden" name="accion" value="cancelar_reserva">
                                        <input type="hidden" name="id_reserva" value="<?= $r['id_reserva'] ?>">
                                        <input type="hidden" name="motivo" value="Anulacion administrativa desde mostrador">
                                        <button class="btn btn-sm btn-danger">Anular</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">Sanciones Registradas</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Estudiante</th>
                                <th>Termino</th>
                                <th>Estado</th>
                                <th>Accion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sanciones)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-2">Sin sanciones registradas.</td></tr>
                            <?php else: foreach ($sanciones as $s): ?>
                                <tr>
                                    <td><?= htmlspecialchars($s['nombre']) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($s['fecha_termino'])) ?></td>
                                    <td><span class="badge bg-<?= $s['estado'] === 'vigente' ? 'danger' : 'secondary' ?>"><?= $s['estado'] ?></span></td>
                                    <td>
                                        <?php if ($s['estado'] === 'vigente'): ?>
                                            <form action="api/acciones.php" method="POST">
                                                <input type="hidden" name="accion" value="levantar_sancion">
                                                <input type="hidden" name="id_sancion" value="<?= $s['id_sancion'] ?>">
                                                <input type="hidden" name="id_usuario" value="<?= $s['id_usuario'] ?>">
                                                <button class="btn btn-outline-success btn-sm">Anular</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">Historial de Acciones y Auditoria</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha/Hora</th>
                                <th>Usuario</th>
                                <th>Accion</th>
                                <th>Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($auditoria as $a): ?>
                                <tr>
                                    <td class="text-nowrap"><?= date('d/m H:i', strtotime($a['fecha_hora'])) ?></td>
                                    <td><?= htmlspecialchars($a['actor']) ?></td>
                                    <td><span class="badge bg-secondary"><?= $a['accion'] ?></span></td>
                                    <td><?= htmlspecialchars($a['detalle']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
