<?php
/**
 * Configuración principal del sistema e-learning
 * @version 1.0.0
 */

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'building_bridges';
$user = 'root';
$password = '';

// Configuración del sistema
define('SITE_NAME', 'E-Learning PLI');
define('BASE_URL', 'http://localhost:8000');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB en bytes

// Configuración de zona horaria
date_default_timezone_set('America/Mexico_City');

// Configuración de sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// Conexión a la base de datos usando PDO
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    error_log('Error de conexión: ' . $e->getMessage());
    die('Lo sentimos, ha ocurrido un error al conectar con la base de datos.');
}

// Funciones de utilidad
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function is_authenticated() {
    return isset($_SESSION['user_id']);
}

function get_user_role() {
    return $_SESSION['role'] ?? null;
}

function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit();
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function is_ajax_request() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// Manejo de errores personalizado
function custom_error_handler($errno, $errstr, $errfile, $errline) {
    $error_message = date('Y-m-d H:i:s') . " Error [$errno]: $errstr en $errfile:$errline\n";
    error_log($error_message, 3, __DIR__ . '/../logs/error.log');
    
    if (ini_get('display_errors')) {
        printf("<pre>Error: %s\nFile: %s\nLine: %d</pre>", $errstr, $errfile, $errline);
    } else {
        echo "Ha ocurrido un error. Por favor, contacte al administrador.";
    }
}

set_error_handler('custom_error_handler');

// Crear directorios necesarios si no existen
$directories = [
    UPLOAD_PATH,
    UPLOAD_PATH . 'documents/',
    UPLOAD_PATH . 'images/',
    UPLOAD_PATH . 'videos/',
    __DIR__ . '/../logs/',
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}
