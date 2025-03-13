<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = Auth::getInstance();

// Registrar la hora de salida
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET last_logout = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {
        error_log("Error registrando logout: " . $e->getMessage());
    }
}

// Cerrar sesión
$auth->logout();

// Redirigir al login con mensaje de éxito
header('Location: login.php?logout=success');
exit;
