<?php
function obtenerHoraSimulada(PDO $pdo): string {
    $stmt = $pdo->query("SELECT fecha_hora_simulada FROM sistema_reloj WHERE id = 1");
    return $stmt->fetchColumn();
}

function ejecutarReglasTemporales(PDO $pdo): void {
    $ahoraStr = obtenerHoraSimulada($pdo);
    $ahora = new DateTime($ahoraStr);

    $stmtSancVencidas = $pdo->prepare("SELECT s.id_sancion, r.id_usuario FROM sancion s JOIN reserva r ON s.id_reserva = r.id_reserva WHERE s.estado = 'vigente' AND s.fecha_termino <= ?");
    $stmtSancVencidas->execute([$ahoraStr]);
    foreach ($stmtSancVencidas->fetchAll() as $sv) {
        $pdo->prepare("UPDATE sancion SET estado = 'cumplida' WHERE id_sancion = ?")->execute([$sv['id_sancion']]);
        $pdo->prepare("UPDATE usuario SET estado = 'habilitado' WHERE id_usuario = ?")->execute([$sv['id_usuario']]);
    }

    $limite30Min = (clone $ahora)->modify('+30 minutes')->format('Y-m-d H:i:s');
    $stmtPorNotificar = $pdo->prepare("
        SELECT r.*, u.correo_institucional, u.nombre, b.codigo 
        FROM reserva r
        JOIN usuario u ON r.id_usuario = u.id_usuario
        JOIN box b ON r.id_box = b.id_box
        WHERE r.estado = 'pendiente' 
          AND r.fecha_hora_inicio <= ? 
          AND r.fecha_hora_inicio > ?
          AND NOT EXISTS (
              SELECT 1 FROM correo_simulado c 
              WHERE c.id_reserva = r.id_reserva AND c.asunto LIKE 'Confirmacion requerida%'
          )
    ");
    $stmtPorNotificar->execute([$limite30Min, $ahoraStr]);
    foreach ($stmtPorNotificar->fetchAll() as $res) {
        $asunto = "Confirmacion requerida: Reserva #{$res['id_reserva']} ({$res['codigo']})";
        $cuerpo = "Estimado/a {$res['nombre']},\n\nSu reserva para el {$res['codigo']} inicia a las " . date('H:i', strtotime($res['fecha_hora_inicio'])) . ".\nTiene plazo hasta 10 minutos despues del inicio para confirmar asistencia desde el Portal o haciendo clic en el boton de confirmacion de este correo, de lo contrario se cancelara automaticamente.";
        $pdo->prepare("INSERT INTO correo_simulado (id_reserva, destinatario, asunto, cuerpo, fecha_envio, estado_envio) VALUES (?, ?, ?, ?, ?, 'enviado')")
            ->execute([$res['id_reserva'], $res['correo_institucional'], $asunto, $cuerpo, $ahoraStr]);
    }

    $limite10MinAtras = (clone $ahora)->modify('-10 minutes')->format('Y-m-d H:i:s');
    $stmtExpiradas = $pdo->prepare("
        SELECT r.*, u.correo_institucional, u.nombre, u.id_usuario, b.codigo 
        FROM reserva r
        JOIN usuario u ON r.id_usuario = u.id_usuario
        JOIN box b ON r.id_box = b.id_box
        WHERE r.estado = 'pendiente' AND r.fecha_hora_inicio <= ?
    ");
    $stmtExpiradas->execute([$limite10MinAtras]);
    foreach ($stmtExpiradas->fetchAll() as $exp) {
        $pdo->prepare("UPDATE reserva SET estado = 'inasistencia' WHERE id_reserva = ?")->execute([$exp['id_reserva']]);
        
        $pdo->prepare("INSERT INTO historial_acciones (id_usuario, id_reserva, accion, fecha_hora, detalle) VALUES (?, ?, 'CANCELACION_AUTOMATICA_10MIN', ?, ?)")
            ->execute([$exp['id_usuario'], $exp['id_reserva'], $ahoraStr, "Reserva #{$exp['id_reserva']} ({$exp['codigo']}) marcada como inasistencia por no confirmar dentro de los primeros 10 minutos."]);

        $pdo->prepare("INSERT INTO correo_simulado (id_reserva, destinatario, asunto, cuerpo, fecha_envio, estado_envio) VALUES (?, ?, ?, ?, ?, 'enviado')")
            ->execute([
                $exp['id_reserva'],
                $exp['correo_institucional'],
                "Reserva #{$exp['id_reserva']} cancelada por falta de confirmacion",
                "Hola {$exp['nombre']}, han transcurrido 10 minutos desde el inicio de su reserva en {$exp['codigo']} sin confirmacion. El espacio ha sido liberado y se registro 1 inasistencia.",
                $ahoraStr
            ]);

        $mesActual = $ahora->format('Y-m');
        $stmtInasist = $pdo->prepare("
            SELECT COUNT(*) FROM reserva 
            WHERE id_usuario = ? AND estado = 'inasistencia' AND DATE_FORMAT(fecha_hora_inicio, '%Y-%m') = ?
        ");
        $stmtInasist->execute([$exp['id_usuario'], $mesActual]);
        $totalInasistencias = (int)$stmtInasist->fetchColumn();

        if ($totalInasistencias >= 2) {
            $stmtTieneSanc = $pdo->prepare("
                SELECT COUNT(*) FROM sancion s 
                JOIN reserva r ON s.id_reserva = r.id_reserva 
                WHERE r.id_usuario = ? AND s.estado = 'vigente'
            ");
            $stmtTieneSanc->execute([$exp['id_usuario']]);
            if ((int)$stmtTieneSanc->fetchColumn() === 0) {
                $finSancion = (clone $ahora)->modify('+7 days')->format('Y-m-d H:i:s');
                $pdo->prepare("INSERT INTO sancion (id_reserva, motivo, fecha_inicio, fecha_termino, estado) VALUES (?, ?, ?, ?, 'vigente')")
                    ->execute([$exp['id_reserva'], "Acumulacion de {$totalInasistencias} inasistencias en el mes {$mesActual}", $ahoraStr, $finSancion]);
                $pdo->prepare("UPDATE usuario SET estado = 'bloqueado' WHERE id_usuario = ?")->execute([$exp['id_usuario']]);
                $pdo->prepare("INSERT INTO historial_acciones (id_usuario, id_reserva, accion, fecha_hora, detalle) VALUES (?, ?, 'SANCION_AUTOMATICA', ?, ?)")
                    ->execute([$exp['id_usuario'], $exp['id_reserva'], $ahoraStr, "Usuario bloqueado automaticamente hasta {$finSancion} por 2 inasistencias en el mes."]);
            }
        }
    }

    $pdo->prepare("UPDATE reserva SET estado = 'finalizada' WHERE estado = 'confirmada' AND fecha_hora_fin <= ?")
        ->execute([$ahoraStr]);
}
?>
