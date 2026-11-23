<?php
require_once '../config/db.php';
require_once '../config/motor_tiempo.php';

ejecutarReglasTemporales($pdo);
$ahoraStr = obtenerHoraSimulada($pdo);
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

function redirigir(string $url, string $msg, string $tipo = 'success'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'tipo' => $tipo];
    header("Location: $url");
    exit;
}

function verificarClave(string $password, string $hashGuardado): bool {
    if ($password === '' || $hashGuardado === '') {
        return false;
    }
    if (str_starts_with($hashGuardado, '$2y$') || str_starts_with($hashGuardado, '$2a$') || str_starts_with($hashGuardado, '$argon2')) {
        return password_verify($password, $hashGuardado);
    }
    return hash_equals($hashGuardado, hash('sha256', $password));
}

if ($accion === 'login_sso') {
    $id_usuario = (int)($_POST['id_usuario'] ?? 0);
    $password = trim((string)($_POST['password'] ?? ''));

    $stmt = $pdo->prepare("SELECT * FROM usuario WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    $user = $stmt->fetch();

    if (!$user) {
        redirigir('../index.php', 'Cuenta institucional no valida en el proveedor OIDC.', 'danger');
    }

    if (!verificarClave($password, (string)$user['clave_hash'])) {
        redirigir('../index.php', 'Contrasena institucional incorrecta. Intente nuevamente.', 'danger');
    }

    session_regenerate_id(true);
    $_SESSION['usuario'] = $user;
    $pdo->prepare("INSERT INTO historial_acciones (id_usuario, id_reserva, accion, fecha_hora, detalle) VALUES (?, NULL, 'LOGIN_SSO_OIDC', ?, ?)")
        ->execute([$user['id_usuario'], $ahoraStr, "Inicio de sesion federado SSO/OIDC validado con contrasena como {$user['rol']}"]);

    if ($user['rol'] === 'bibliotecario') {
        header("Location: ../admin.php");
    } else {
        header("Location: ../portal.php");
    }
    exit;
}

if ($accion === 'logout') {
    session_destroy();
    header("Location: ../index.php");
    exit;
}

if ($accion === 'avanzar_tiempo') {
    $minutos = (int)($_POST['minutos'] ?? 15);
    $origen = $_POST['origen'] ?? '../portal.php';
    $nuevaHora = (new DateTime($ahoraStr))->modify("+{$minutos} minutes")->format('Y-m-d H:i:s');
    $pdo->prepare("UPDATE sistema_reloj SET fecha_hora_simulada = ? WHERE id = 1")->execute([$nuevaHora]);
    ejecutarReglasTemporales($pdo);
    redirigir($origen, "Reloj simulado avanzado +{$minutos} minutos (Hora actual del sistema: {$nuevaHora}).", 'info');
}

if ($accion === 'simular_fallo_correo') {
    $id_correo = (int)($_POST['id_correo'] ?? 0);
    $pdo->prepare("UPDATE correo_simulado SET estado_envio = 'fallido_reintento' WHERE id_correo = ?")->execute([$id_correo]);
    if (isset($_SESSION['usuario'])) {
        $pdo->prepare("INSERT INTO historial_acciones (id_usuario, id_reserva, accion, fecha_hora, detalle) VALUES (?, NULL, 'FALLO_ENVIO_CORREO', ?, ?)")
            ->execute([$_SESSION['usuario']['id_usuario'], $ahoraStr, "Falla simulada en envio de correo #{$id_correo}; marcado para reintento (HU-11)."]);
    }
    redirigir('../correos.php', "Correo #{$id_correo} marcado con falla de entrega institucional (en cola de reintento).", 'warning');
}

if ($accion === 'reintentar_correo') {
    $id_correo = (int)($_POST['id_correo'] ?? 0);
    $pdo->prepare("UPDATE correo_simulado SET estado_envio = 'enviado', fecha_envio = ? WHERE id_correo = ?")->execute([$ahoraStr, $id_correo]);
    if (isset($_SESSION['usuario'])) {
        $pdo->prepare("INSERT INTO historial_acciones (id_usuario, id_reserva, accion, fecha_hora, detalle) VALUES (?, NULL, 'REINTENTO_CORREO_OK', ?, ?)")
            ->execute([$_SESSION['usuario']['id_usuario'], $ahoraStr, "Reintento exitoso de envio para correo #{$id_correo} (HU-11)."]);
    }
    redirigir('../correos.php', "Reintento exitoso: Correo #{$id_correo} entregado correctamente.", 'success');
}

if (!isset($_SESSION['usuario'])) {
    header("Location: ../index.php");
    exit;
}

$stmtU = $pdo->prepare("SELECT * FROM usuario WHERE id_usuario = ?");
$stmtU->execute([$_SESSION['usuario']['id_usuario']]);
$_SESSION['usuario'] = $stmtU->fetch();
$usuarioActual = $_SESSION['usuario'];

if ($accion === 'crear_reserva') {
    $esAdmin = ($usuarioActual['rol'] === 'bibliotecario' && isset($_POST['modo_admin']));
    $redirect = $esAdmin ? '../admin.php' : '../portal.php';

    $id_usuario_reserva = $esAdmin ? (int)$_POST['id_usuario_target'] : (int)$usuarioActual['id_usuario'];
    $id_box = (int)($_POST['id_box'] ?? 0);
    $fecha = $_POST['fecha'] ?? '';
    $hora_inicio = $_POST['hora_inicio'] ?? '';
    $duracion_horas = (int)($_POST['duracion'] ?? 1);

    if (!$id_box || !$fecha || !$hora_inicio || !in_array($duracion_horas, [1, 2])) {
        redirigir($redirect, 'Debe completar todos los campos y seleccionar una duracion valida (1 o 2 horas).', 'danger');
    }

    $inicioDT = new DateTime("$fecha $hora_inicio:00");
    $finDT = (clone $inicioDT)->modify("+{$duracion_horas} hours");
    $ahoraDT = new DateTime($ahoraStr);

    if ($inicioDT < $ahoraDT) {
        redirigir($redirect, 'No puede reservar en una fecha/hora pasada respecto al reloj del sistema.', 'danger');
    }

    $stmtTarget = $pdo->prepare("SELECT * FROM usuario WHERE id_usuario = ?");
    $stmtTarget->execute([$id_usuario_reserva]);
    $targetUser = $stmtTarget->fetch();
    if (!$targetUser || $targetUser['estado'] === 'bloqueado') {
        redirigir($redirect, 'El estudiante se encuentra suspendido temporalmente por acumulacion de inasistencias.', 'danger');
    }

    try {
        $pdo->beginTransaction();

        $stmtBox = $pdo->prepare("SELECT * FROM box WHERE id_box = ? FOR UPDATE");
        $stmtBox->execute([$id_box]);
        $box = $stmtBox->fetch();

        if (!$box || $box['estado'] !== 'habilitado') {
            $pdo->rollBack();
            redirigir($redirect, 'El box seleccionado no existe o esta fuera de servicio.', 'danger');
        }

        $iniStr = $inicioDT->format('Y-m-d H:i:s');
        $finStr = $finDT->format('Y-m-d H:i:s');

        $stmtSolape = $pdo->prepare("
            SELECT COUNT(*) FROM reserva 
            WHERE id_box = ? 
              AND estado IN ('pendiente', 'confirmada')
              AND (fecha_hora_inicio < ? AND fecha_hora_fin > ?)
            FOR UPDATE
        ");
        $stmtSolape->execute([$id_box, $finStr, $iniStr]);
        if ((int)$stmtSolape->fetchColumn() > 0) {
            $pdo->rollBack();
            redirigir($redirect, "Conflicto de horario: {$box['codigo']} ya tiene una reserva vigente en ese bloque.", 'danger');
        }

        $stmtConsec = $pdo->prepare("
            SELECT COUNT(*) FROM reserva 
            WHERE id_usuario = ? 
              AND estado IN ('pendiente', 'confirmada', 'finalizada')
              AND (
                  (fecha_hora_inicio < ? AND fecha_hora_fin > ?)
                  OR fecha_hora_fin = ?
                  OR fecha_hora_inicio = ?
              )
        ");
        $stmtConsec->execute([$id_usuario_reserva, $finStr, $iniStr, $iniStr, $finStr]);
        if ((int)$stmtConsec->fetchColumn() > 0) {
            $pdo->rollBack();
            redirigir($redirect, 'Regla de uso (HU-06): No se permiten reservas simultaneas ni inmediatamente consecutivas para el mismo usuario.', 'danger');
        }

        $estadoInicial = $esAdmin ? 'confirmada' : 'pendiente';
        $fechaConf = $esAdmin ? $ahoraStr : null;

        $stmtIns = $pdo->prepare("
            INSERT INTO reserva (id_usuario, id_box, fecha_hora_inicio, fecha_hora_fin, estado, fecha_confirmacion, fecha_creacion)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtIns->execute([$id_usuario_reserva, $id_box, $iniStr, $finStr, $estadoInicial, $fechaConf, $ahoraStr]);
        $idNuevaReserva = $pdo->lastInsertId();

        $tipoAccion = $esAdmin ? 'CREACION_MANUAL_MOSTRADOR' : 'CREACION_RESERVA_ESTUDIANTE';
        $pdo->prepare("INSERT INTO historial_acciones (id_usuario, id_reserva, accion, fecha_hora, detalle) VALUES (?, ?, ?, ?, ?)")
            ->execute([$usuarioActual['id_usuario'], $idNuevaReserva, $tipoAccion, $ahoraStr, "Reserva #{$idNuevaReserva} en {$box['codigo']} ({$iniStr} a {$finStr}) para {$targetUser['nombre']}"]);

        $pdo->prepare("INSERT INTO correo_simulado (id_reserva, destinatario, asunto, cuerpo, fecha_envio, estado_envio) VALUES (?, ?, ?, ?, ?, 'enviado')")
            ->execute([
                $idNuevaReserva,
                $targetUser['correo_institucional'],
                "Comprobante de Reserva #{$idNuevaReserva} - {$box['codigo']}",
                "Hola {$targetUser['nombre']}, su reserva para {$box['codigo']} el {$fecha} de {$hora_inicio} a " . $finDT->format('H:i') . " ha sido registrada.",
                $ahoraStr
            ]);

        $pdo->commit();
        ejecutarReglasTemporales($pdo);
        redirigir($redirect, "Reserva #{$idNuevaReserva} registrada exitosamente en {$box['codigo']}.", 'success');

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        redirigir($redirect, 'Error al procesar la reserva: ' . $e->getMessage(), 'danger');
    }
}

if ($accion === 'confirmar_reserva') {
    $id_reserva = (int)($_POST['id_reserva'] ?? $_GET['id_reserva'] ?? 0);
    $origen = $_POST['origen'] ?? $_GET['origen'] ?? '../portal.php';

    $stmt = $pdo->prepare("SELECT * FROM reserva WHERE id_reserva = ?");
    $stmt->execute([$id_reserva]);
    $res = $stmt->fetch();

    if (!$res || ($usuarioActual['rol'] === 'estudiante' && $res['id_usuario'] != $usuarioActual['id_usuario'])) {
        redirigir($origen, 'No tiene permisos para confirmar esta reserva.', 'danger');
    }

    if ($res['estado'] !== 'pendiente') {
        redirigir($origen, 'La reserva ya no se encuentra en estado pendiente (Estado actual: ' . $res['estado'] . ').', 'warning');
    }

    $ahoraDT = new DateTime($ahoraStr);
    $ventanaInicio = (new DateTime($res['fecha_hora_inicio']))->modify('-30 minutes');
    $ventanaFin = (new DateTime($res['fecha_hora_inicio']))->modify('+10 minutes');

    if ($ahoraDT < $ventanaInicio) {
        redirigir($origen, 'Aun es muy pronto. La confirmacion se habilita 30 minutos antes del inicio (' . $ventanaInicio->format('H:i') . ').', 'warning');
    }
    if ($ahoraDT > $ventanaFin) {
        redirigir($origen, 'El plazo de 10 minutos para confirmar ha expirado.', 'danger');
    }

    $pdo->prepare("UPDATE reserva SET estado = 'confirmada', fecha_confirmacion = ? WHERE id_reserva = ?")
        ->execute([$ahoraStr, $id_reserva]);

    $pdo->prepare("INSERT INTO historial_acciones (id_usuario, id_reserva, accion, fecha_hora, detalle) VALUES (?, ?, 'CONFIRMACION_ASISTENCIA', ?, ?)")
        ->execute([$usuarioActual['id_usuario'], $id_reserva, $ahoraStr, "Asistencia confirmada para Reserva #{$id_reserva}"]);

    redirigir($origen, "¡Asistencia confirmada exitosamente para la Reserva #{$id_reserva}!", 'success');
}

if ($accion === 'cancelar_reserva') {
    $id_reserva = (int)($_POST['id_reserva'] ?? 0);
    $motivo = trim($_POST['motivo'] ?? 'Cancelada por el estudiante');
    $esAdmin = ($usuarioActual['rol'] === 'bibliotecario');
    $redirect = $esAdmin ? '../admin.php' : '../portal.php';

    $stmt = $pdo->prepare("SELECT * FROM reserva WHERE id_reserva = ?");
    $stmt->execute([$id_reserva]);
    $res = $stmt->fetch();

    if (!$res || (!$esAdmin && $res['id_usuario'] != $usuarioActual['id_usuario'])) {
        redirigir($redirect, 'Operacion no permitida (Aislamiento de datos).', 'danger');
    }

    $pdo->prepare("UPDATE reserva SET estado = 'cancelada' WHERE id_reserva = ?")->execute([$id_reserva]);

    $tipoAuditoria = $esAdmin ? 'ANULACION_ADMINISTRATIVA' : 'CANCELACION_ESTUDIANTE';
    $pdo->prepare("INSERT INTO historial_acciones (id_usuario, id_reserva, accion, fecha_hora, detalle) VALUES (?, ?, ?, ?, ?)")
        ->execute([$usuarioActual['id_usuario'], $id_reserva, $tipoAuditoria, $ahoraStr, "Reserva #{$id_reserva} anulada. Motivo: {$motivo}"]);

    redirigir($redirect, "Reserva #{$id_reserva} cancelada y box liberado.", 'success');
}

if ($usuarioActual['rol'] === 'bibliotecario') {
    if ($accion === 'toggle_box') {
        $id_box = (int)$_POST['id_box'];
        $nuevo_estado = $_POST['nuevo_estado'] === 'habilitado' ? 'habilitado' : 'fuera_de_servicio';
        $pdo->prepare("UPDATE box SET estado = ? WHERE id_box = ?")->execute([$nuevo_estado, $id_box]);
        $pdo->prepare("INSERT INTO historial_acciones (id_usuario, id_reserva, accion, fecha_hora, detalle) VALUES (?, NULL, 'CAMBIO_ESTADO_BOX', ?, ?)")
            ->execute([$usuarioActual['id_usuario'], $ahoraStr, "Box ID {$id_box} cambiado a estado: {$nuevo_estado}"]);
        redirigir('../admin.php', 'Estado del box actualizado y auditado.', 'success');
    }

    if ($accion === 'modificar_reserva_admin') {
        $id_reserva = (int)$_POST['id_reserva'];
        $nuevo_box = (int)$_POST['id_box'];
        $nuevo_estado = $_POST['estado'];

        $stmtRes = $pdo->prepare("SELECT * FROM reserva WHERE id_reserva = ?");
        $stmtRes->execute([$id_reserva]);
        $resActual = $stmtRes->fetch();

        if (!$resActual) {
            redirigir('../admin.php', 'Reserva no encontrada.', 'danger');
        }

        if (in_array($nuevo_estado, ['pendiente', 'confirmada'])) {
            $stmtB = $pdo->prepare("SELECT * FROM box WHERE id_box = ?");
            $stmtB->execute([$nuevo_box]);
            $boxDest = $stmtB->fetch();
            if (!$boxDest || $boxDest['estado'] !== 'habilitado') {
                redirigir('../admin.php', 'No se puede asignar: el box destino esta fuera de servicio.', 'danger');
            }

            $stmtSol = $pdo->prepare("
                SELECT COUNT(*) FROM reserva 
                WHERE id_box = ? 
                  AND id_reserva != ?
                  AND estado IN ('pendiente', 'confirmada')
                  AND (fecha_hora_inicio < ? AND fecha_hora_fin > ?)
            ");
            $stmtSol->execute([$nuevo_box, $id_reserva, $resActual['fecha_hora_fin'], $resActual['fecha_hora_inicio']]);
            if ((int)$stmtSol->fetchColumn() > 0) {
                redirigir('../admin.php', "Conflicto de horario: {$boxDest['codigo']} ya tiene otra reserva activa en ese bloque.", 'danger');
            }
        }

        $fechaConf = ($nuevo_estado === 'confirmada' && !$resActual['fecha_confirmacion']) ? $ahoraStr : $resActual['fecha_confirmacion'];
        $pdo->prepare("UPDATE reserva SET id_box = ?, estado = ?, fecha_confirmacion = ? WHERE id_reserva = ?")
            ->execute([$nuevo_box, $nuevo_estado, $fechaConf, $id_reserva]);

        $pdo->prepare("INSERT INTO historial_acciones (id_usuario, id_reserva, accion, fecha_hora, detalle) VALUES (?, ?, 'MODIFICACION_RESERVA_ADMIN', ?, ?)")
            ->execute([$usuarioActual['id_usuario'], $id_reserva, $ahoraStr, "Reserva #{$id_reserva} modificada en mostrador (Box ID: {$nuevo_box}, Estado: {$nuevo_estado})"]);
        redirigir('../admin.php', "Reserva #{$id_reserva} actualizada y validada correctamente.", 'success');
    }

    if ($accion === 'levantar_sancion') {
        $id_sancion = (int)$_POST['id_sancion'];
        $id_usuario_sanc = (int)$_POST['id_usuario'];
        $pdo->prepare("UPDATE sancion SET estado = 'anulada' WHERE id_sancion = ?")->execute([$id_sancion]);
        $pdo->prepare("UPDATE usuario SET estado = 'habilitado' WHERE id_usuario = ?")->execute([$id_usuario_sanc]);
        $pdo->prepare("INSERT INTO historial_acciones (id_usuario, id_reserva, accion, fecha_hora, detalle) VALUES (?, NULL, 'ANULACION_SANCION_ADMIN', ?, ?)")
            ->execute([$usuarioActual['id_usuario'], $ahoraStr, "Sancion #{$id_sancion} levantada manualmente por personal de biblioteca."]);
        redirigir('../admin.php', 'Sancion anulada y estudiante rehabilitado.', 'success');
    }
}

header("Location: ../index.php");
