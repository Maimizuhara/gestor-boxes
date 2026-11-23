<?php
require_once 'config/db.php';
require_once 'config/motor_tiempo.php';
ejecutarReglasTemporales($pdo);
$ahora = obtenerHoraSimulada($pdo);
$usuarios = $pdo->query("SELECT id_usuario, identificador_institucional, nombre, correo_institucional, rol, estado FROM usuario ORDER BY rol DESC, nombre ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Institucional SSO/OIDC - Gestor de Boxes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center min-vh-100">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white text-center py-3">
                    <h5 class="mb-0">Biblioteca Sede La Serena</h5>
                    <small>Sistema de Gestion de Boxes de Estudio</small>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-secondary py-2 small d-flex justify-content-between align-items-center">
                        <span><strong>Reloj Simulado:</strong> <?= $ahora ?></span>
                        <a href="correos.php" class="btn btn-sm btn-outline-dark">Bandeja Correos</a>
                    </div>

                    <?php if (isset($_SESSION['flash'])): ?>
                        <div class="alert alert-<?= $_SESSION['flash']['tipo'] ?> small">
                            <?= htmlspecialchars($_SESSION['flash']['msg']) ?>
                        </div>
                        <?php unset($_SESSION['flash']); ?>
                    <?php endif; ?>

                    <h6 class="fw-bold mb-2">Simulacion Proveedor de Identidad (SSO / OIDC)</h6>
                    <p class="text-muted small">Seleccione su cuenta institucional e ingrese su contrasena para iniciar sesion:</p>

                    <form action="api/acciones.php" method="POST">
                        <input type="hidden" name="accion" value="login_sso">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Cuenta Institucional</label>
                            <select name="id_usuario" class="form-select" required>
                                <?php foreach ($usuarios as $u): ?>
                                    <option value="<?= $u['id_usuario'] ?>">
                                        [<?= strtoupper($u['rol']) ?>] <?= htmlspecialchars($u['nombre']) ?> (<?= $u['correo_institucional'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Contrasena Institucional</label>
                            <input type="password" name="password" class="form-control" placeholder="Ingrese su contrasena" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100 fw-semibold">
                            Iniciar Sesion con Cuenta Institucional (OIDC)
                        </button>
                    </form>

                    <div class="mt-3 p-2 bg-light border rounded small text-muted">
                        <strong>Clave de prueba para todas las cuentas:</strong> <code>Box2026!</code>
                    </div>
                </div>
                <div class="card-footer text-center text-muted small">
                    Prototipo Academico - Ingenieria en Informatica (Santo Tomas)
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
