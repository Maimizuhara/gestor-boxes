<?php
require_once 'config/db.php';
require_once 'config/motor_tiempo.php';

ejecutarReglasTemporales($pdo);
$ahoraStr = obtenerHoraSimulada($pdo);
$usuario = $_SESSION['usuario'] ?? null;

if ($usuario && $usuario['rol'] === 'estudiante') {
    $stmt = $pdo->prepare("SELECT * FROM correo_simulado WHERE destinatario = ? ORDER BY id_correo DESC");
    $stmt->execute([$usuario['correo_institucional']]);
    $correos = $stmt->fetchAll();
    $pdo->prepare("UPDATE correo_simulado SET leido = 1 WHERE destinatario = ?")->execute([$usuario['correo_institucional']]);
    $volver = 'portal.php';
} else {
    $correos = $pdo->query("SELECT * FROM correo_simulado ORDER BY id_correo DESC")->fetchAll();
    $volver = $usuario ? 'admin.php' : 'index.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bandeja Correo Institucional Simulada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <span class="navbar-brand">Correo Institucional Simulado (Outlook / Exchange)</span>
        <div class="d-flex align-items-center gap-3">
            <span class="text-info small">Reloj: <?= $ahoraStr ?></span>
            <a href="<?= $volver ?>" class="btn btn-sm btn-outline-light">Volver</a>
        </div>
    </div>
</nav>

<div class="container my-4">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-bold d-flex justify-content-between">
            <span>Mensajes Procesados por el Servicio de Notificaciones (HU-08, HU-11)</span>
            <span class="badge bg-success">SLA Envio &lt; 5 min: OK</span>
        </div>
        <div class="list-group list-group-flush">
            <?php if (empty($correos)): ?>
                <div class="p-4 text-center text-muted">No hay correos generados aun. Crea una reserva y avanza el reloj simulado.</div>
            <?php else: foreach ($correos as $c): ?>
                <div class="list-group-item p-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div>
                            <span class="fw-bold text-primary"><?= htmlspecialchars($c['asunto']) ?></span>
                            <?php if ($c['estado_envio'] === 'fallido_reintento'): ?>
                                <span class="badge bg-danger ms-2">FALLA DE ENVIO (PENDIENTE REINTENTO)</span>
                            <?php else: ?>
                                <span class="badge bg-success ms-2">ENTREGADO</span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted"><?= $c['fecha_envio'] ?></small>
                    </div>
                    <div class="small text-muted mb-2">Para: <strong><?= htmlspecialchars($c['destinatario']) ?></strong></div>
                    <p class="mb-2 small" style="white-space: pre-line;"><?= htmlspecialchars($c['cuerpo']) ?></p>

                    <div class="d-flex gap-2 flex-wrap">
                        <?php if ($c['id_reserva'] && str_starts_with($c['asunto'], 'Confirmacion requerida') && $usuario && $usuario['rol'] === 'estudiante'): ?>
                            <form action="api/acciones.php" method="POST">
                                <input type="hidden" name="accion" value="confirmar_reserva">
                                <input type="hidden" name="id_reserva" value="<?= $c['id_reserva'] ?>">
                                <input type="hidden" name="origen" value="../correos.php">
                                <button class="btn btn-sm btn-success">Confirmar Asistencia Ahora (Enlace Institucional)</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($c['estado_envio'] === 'enviado'): ?>
                            <form action="api/acciones.php" method="POST">
                                <input type="hidden" name="accion" value="simular_fallo_correo">
                                <input type="hidden" name="id_correo" value="<?= $c['id_correo'] ?>">
                                <button class="btn btn-sm btn-outline-secondary">Simular Falla de Envio (HU-11)</button>
                            </form>
                        <?php else: ?>
                            <form action="api/acciones.php" method="POST">
                                <input type="hidden" name="accion" value="reintentar_correo">
                                <input type="hidden" name="id_correo" value="<?= $c['id_correo'] ?>">
                                <button class="btn btn-sm btn-warning">Ejecutar Reintento de Envio</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>
</body>
</html>
