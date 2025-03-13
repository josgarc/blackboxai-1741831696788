<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/mail.php';

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
    $required = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_password'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("El campo $field es requerido");
        }
    }

    // Configurar servicio de correo con los nuevos datos
    $emailService = EmailService::getInstance();
    $emailService->setConfig([
        'host' => $data['smtp_host'],
        'port' => (int)$data['smtp_port'],
        'username' => $data['smtp_user'],
        'password' => $data['smtp_password'],
        'secure' => $data['smtp_secure'] ?? 'tls'
    ]);

    // Enviar correo de prueba
    $result = $emailService->sendTestEmail(
        $_SESSION['email'],
        [
            'nombre' => $_SESSION['full_name'],
            'fecha' => date('Y-m-d H:i:s')
        ]
    );

    if (!$result) {
        throw new Exception('Error al enviar el correo de prueba');
    }

    // Respuesta exitosa
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Correo de prueba enviado correctamente'
    ]);

} catch (Exception $e) {
    error_log("Error en test-email.php: " . $e->getMessage());

    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
