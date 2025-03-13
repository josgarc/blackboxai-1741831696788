<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/course_manager.php';

$auth = Auth::getInstance();
$courseManager = CourseManager::getInstance();

// Verificar autenticación y rol de administrador
if (!$auth->isAuthenticated() || !$auth->hasRole('Administrador')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar método de solicitud
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $materiaId = isset($_POST['materia_id']) ? (int)$_POST['materia_id'] : 0;
    $profesorId = isset($_POST['profesor_id']) ? (int)$_POST['profesor_id'] : 0;

    // Validar IDs
    if (!$materiaId) {
        throw new Exception('ID de materia no válido');
    }

    // Verificar que la materia existe
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM materias WHERE id = ?");
    $stmt->execute([$materiaId]);
    if (!$stmt->fetchColumn()) {
        throw new Exception('La materia no existe');
    }

    // Si se proporciona un profesor, verificar que existe y es un maestro
    if ($profesorId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'Maestro'");
        $stmt->execute([$profesorId]);
        if (!$stmt->fetchColumn()) {
            throw new Exception('El profesor seleccionado no es válido');
        }
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    // Eliminar asignaciones anteriores
    $stmt = $pdo->prepare("DELETE FROM maestros_materias WHERE materia_id = ?");
    $stmt->execute([$materiaId]);

    // Si se seleccionó un profesor, asignarlo
    if ($profesorId) {
        $stmt = $pdo->prepare("
            INSERT INTO maestros_materias (
                materia_id, user_id, fecha_asignacion, estado
            ) VALUES (?, ?, NOW(), 'activo')
        ");
        $stmt->execute([$materiaId, $profesorId]);

        // Notificar al profesor
        $stmt = $pdo->prepare("
            SELECT m.nombre, u.email, u.full_Name
            FROM materias m
            JOIN users u ON u.id = ?
            WHERE m.id = ?
        ");
        $stmt->execute([$profesorId, $materiaId]);
        $info = $stmt->fetch();

        if ($info) {
            // Enviar correo de notificación
            $emailService = EmailService::getInstance();
            $emailService->sendTeacherAssignmentNotification(
                $info['email'],
                [
                    'nombre' => $info['full_Name'],
                    'materia' => $info['nombre']
                ]
            );
        }
    }

    $pdo->commit();

    // Respuesta exitosa
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $profesorId ? 'Profesor asignado correctamente' : 'Profesor removido correctamente'
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Error en asignar-profesor.php: " . $e->getMessage());

    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
    ]);
}
