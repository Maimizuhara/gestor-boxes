<?php
require_once 'config/db.php';
require_once 'config/motor_tiempo.php';

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'estudiante') {
    header("Location: index.php");
    exit;
}

ejecutarReglasTemporales($pdo);
$ahoraStr = obtenerHoraSimulada($pdo);
$stmtU = $pdo->prepare("SELECT * FROM usuario WHERE id_usuario = ?");
$stmtU->execute([$_SESSION['usuario']['id_usuario']]);
$user = $_SESSION['usuario'] = $stmtU->fetch();

$stmtBoxes = $pdo->prepare("
    SELECT b.*,
           (SELECT CONCAT(DATE_FORMAT(r.fecha_hora_inicio, '%H:%i'), ' - ', DATE_FORMAT(r.fecha_hora_fin, '%H:%i'), ' (', r.estado, ')')
            FROM reserva r 
            WHERE r.id_box = b.id_box 
              AND r.estado IN ('pendiente', 'confirmada')
              AND ? >= r.fecha_hora_inicio AND ? < r.fecha_hora_fin
            LIMIT 1) AS ocupacion_actual
    FROM box b ORDER BY b.codigo ASC
");
$stmtBoxes->execute([$ahoraStr, $ahoraStr]);
$boxes = $stmtBoxes->fetchAll();

$stmtMisReservas = $pdo->prepare("
    SELECT r.*, b.codigo, b.ubicacion 
    FROM reserva r 
    JOIN box b ON r.id_box = b.id_box 
    WHERE r.id_usuario = ? 
    ORDER BY r.fecha_hora_inicio DESC
");
$stmtMisReservas->execute([$user['id_usuario']]);
$misReservas = $stmtMisReservas->fetchAll();

$stmtCountMail = $pdo->prepare("SELECT COUNT(*) FROM correo_simulado WHERE destinatario = ? AND leido = 0");
$stmtCountMail->execute([$user['correo_institucional']]);
$correosNuevos = $stmtCountMail->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal del Estudiante - Gestor de Boxes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container">
        <span class="navbar-brand fw-bold">Portal Estudiante | Boxes Biblioteca</span>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="correos.php" class="btn btn-warning btn-sm fw-semibold">
                Bandeja Correos (<?= $correosNuevos ?>)
            </a>
            <span class="text-white small me-2"><?= htmlspecialchars($user['nombre']) ?></span>
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
            <input type="hidden" name="origen" value="../portal.php">
            <button type="submit" name="minutos" value="15" class="btn btn-sm btn-outline-light">+15 min</button>
            <button type="submit" name="minutos" value="30" class="btn btn-sm btn-outline-light">+30 min (Aviso)</button>
            <button type="submit" name="minutos" value="45" class="btn btn-sm btn-outline-warning">+45 min (Expira 10m)</button>
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

    <?php if ($user['estado'] === 'bloqueado'): ?>
        <div class="alert alert-danger fw-bold">
            Su cuenta se encuentra SUSPENDIDA temporalmente por acumular 2 inasistencias en el mes calendario. No puede realizar nuevas reservas.
        </div>
    <?php endif; ?>

    <h5 class="fw-bold mb-3">Disponibilidad Actual de los 9 Boxes</h5>
    <div class="row g-3 mb-4">
        <?php foreach ($boxes as $b): ?>
            <?php
            if ($b['estado'] === 'fuera_de_servicio') {
                $badge = 'bg-secondary'; $texto = 'Fuera de servicio';
            } elseif ($b['ocupacion_actual']) {
                $badge = 'bg-danger'; $texto = 'Ocupado: ' . $b['ocupacion_actual'];
            } else {
                $badge = 'bg-success'; $texto = 'Disponible ahora';
            }
            ?>
            <div class="col-12 col-sm-6 col-md-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h6 class="card-title fw-bold mb-0"><?= htmlspecialchars($b['codigo']) ?></h6>
                            <span class="badge <?= $badge ?>"><?= $texto ?></span>
                        </div>
                        <p class="card-text text-muted small mb-0"><?= htmlspecialchars($b['ubicacion']) ?></p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold">Reservar un Box de Estudio</div>
                <div class="card-body">
                    <form action="api/acciones.php" method="POST">
                        <input type="hidden" name="accion" value="crear_reserva">
                        <div class="mb-3">
                            <label class="form-label">Box de Estudio</label>
                            <select name="id_box" class="form-select" required>
                                <?php foreach ($boxes as $b): if ($b['estado'] === 'habilitado'): ?>
                                    <option value="<?= $b['id_box'] ?>"><?= $b['codigo'] ?> - <?= $b['ubicacion'] ?></option>
                                <?php endif; endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Fecha</label>
                            <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d', strtotime($ahoraStr)) ?>" required>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Hora Inicio</label>
                                <input type="time" name="hora_inicio" class="form-control" value="<?= date('H:00', strtotime($ahoraStr . ' +1 hour')) ?>" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Duracion (Max. 2h)</label>
                                <select name="duracion" class="form-select" required>
                                    <option value="1">1 Hora</option>
                                    <option value="2">2 Horas</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success w-100" <?= $user['estado'] === 'bloqueado' ? 'disabled' : '' ?>>
                            Confirmar Solicitud de Reserva
                        </button>
                    </form>
                    <hr>
                    <ul class="small text-muted mb-0 ps-3">
                        <li>Maximo 2 horas por reserva; sin bloques consecutivos.</li>
                        <li>Recibiras un correo 30 min antes. Debes confirmar dentro de los primeros 10 min del inicio.</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold">Mis Reservas</div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Box</th>
                                <th>Horario</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($misReservas)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">No tienes reservas registradas.</td></tr>
                            <?php else: foreach ($misReservas as $r): ?>
                                <tr>
                                    <td>#<?= $r['id_reserva'] ?></td>
                                    <td class="fw-bold"><?= htmlspecialchars($r['codigo']) ?></td>
                                    <td>
                                        <?= date('d/m/Y H:i', strtotime($r['fecha_hora_inicio'])) ?> - 
                                        <?= date('H:i', strtotime($r['fecha_hora_fin'])) ?>
                                    </td>
                                    <td>
                                        <?php
                                        $colores = [
                                            'pendiente' => 'warning text-dark',
                                            'confirmada' => 'success',
                                            'cancelada' => 'secondary',
                                            'inasistencia' => 'danger',
                                            'finalizada' => 'info text-dark'
                                        ];
                                        ?>
                                        <span class="badge bg-<?= $colores[$r['estado']] ?>"><?= strtoupper($r['estado']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($r['estado'] === 'pendiente'): ?>
                                            <form action="api/acciones.php" method="POST" class="d-inline">
                                                <input type="hidden" name="accion" value="confirmar_reserva">
                                                <input type="hidden" name="id_reserva" value="<?= $r['id_reserva'] ?>">
                                                <button class="btn btn-sm btn-primary">Confirmar</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (in_array($r['estado'], ['pendiente', 'confirmada'])): ?>
                                            <form action="api/acciones.php" method="POST" class="d-inline">
                                                <input type="hidden" name="accion" value="cancelar_reserva">
                                                <input type="hidden" name="id_reserva" value="<?= $r['id_reserva'] ?>">
                                                <button class="btn btn-sm btn-outline-danger">Cancelar</button>
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
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
