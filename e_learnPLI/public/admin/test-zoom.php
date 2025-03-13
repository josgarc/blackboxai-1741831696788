<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/zoom_manager.php';

$auth = Auth::getInstance();

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
    // Obtener datos de la solicitud
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        throw new Exception('Datos no válidos');
    }

    // Validar datos requeridos
    $required = ['zoom_api_key', 'zoom_api_secret', 'zoom_email'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("El campo $field es requerido");
        }
    }

    // Configurar servicio de Zoom con los nuevos datos
    $zoomManager = ZoomManager::getInstance();
    $zoomManager->setConfig([
        'api_key' => $data['zoom_api_key'],
        'api_secret' => $data['zoom_api_secret'],
        'email' => $data['zoom_email']
    ]);

    // Intentar obtener el usuario de Zoom para verificar la conexión
    $zoomUser = $zoomManager->getUserInfo($data['zoom_email']);

    if (!$zoomUser) {
        throw new Exception('No se pudo obtener la información del usuario de Zoom');
    }

    // Crear una reunión de prueba
    $meeting = $zoomManager->createMeeting([
        'topic' => 'Reunión de Prueba',
        'start_time' => date('Y-m-d\TH:i:s'),
        'duration' => 30,
        'type' => 2, // Reunión programada
        'settings' => [
            'host_video' => true,
            'participant_video' => true,
            'join_before_host' => false,
            'mute_upon_entry' => true,
            'waiting_room' => true
        ]
    ]);

    if (!$meeting) {
        throw new Exception('No se pudo crear una reunión de prueba');
    }

    // Eliminar la reunión de prueba
    $zoomManager->deleteMeeting($meeting['id']);

    // Respuesta exitosa
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Conexión con Zoom establecida correctamente',
        'user_info' => [
            'id' => $zoomUser['id'],
            'email' => $zoomUser['email'],
            'account_type' => $zoomUser['type']
        ]
    ]);

} catch (Exception $e) {
    error_log("Error en test-zoom.php: " . $e->getMessage());

    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
