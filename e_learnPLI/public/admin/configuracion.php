<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

$auth = Auth::getInstance();

// Verificar autenticación y rol de administrador
if (!$auth->isAuthenticated() || !$auth->hasRole('Administrador')) {
    header('Location: ../login.php');
    exit;
}

$error = '';
$success = '';

// Obtener configuración actual
try {
    $stmt = $pdo->query("SELECT * FROM configuraciones");
    $config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    error_log("Error al obtener configuración: " . $e->getMessage());
    $config = [];
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Configuración general
        $configGeneral = [
            'site_name' => trim($_POST['site_name'] ?? ''),
            'site_description' => trim($_POST['site_description'] ?? ''),
            'admin_email' => trim($_POST['admin_email'] ?? ''),
            'max_file_size' => (int)($_POST['max_file_size'] ?? 5),
            'allowed_file_types' => trim($_POST['allowed_file_types'] ?? '')
        ];

        // Configuración de correo
        $configEmail = [
            'smtp_host' => trim($_POST['smtp_host'] ?? ''),
            'smtp_port' => (int)($_POST['smtp_port'] ?? 587),
            'smtp_user' => trim($_POST['smtp_user'] ?? ''),
            'smtp_password' => trim($_POST['smtp_password'] ?? ''),
            'smtp_secure' => trim($_POST['smtp_secure'] ?? 'tls')
        ];

        // Configuración de Zoom
        $configZoom = [
            'zoom_api_key' => trim($_POST['zoom_api_key'] ?? ''),
            'zoom_api_secret' => trim($_POST['zoom_api_secret'] ?? ''),
            'zoom_email' => trim($_POST['zoom_email'] ?? '')
        ];

        // Validaciones
        if (!filter_var($configGeneral['admin_email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('El correo del administrador no es válido.');
        }

        // Actualizar configuración
        $stmt = $pdo->prepare("
            INSERT INTO configuraciones (clave, valor) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE valor = VALUES(valor)
        ");

        foreach (array_merge($configGeneral, $configEmail, $configZoom) as $key => $value) {
            $stmt->execute([$key, $value]);
        }

        $pdo->commit();
        $success = 'Configuración actualizada correctamente.';

        // Actualizar la configuración en memoria
        $stmt = $pdo->query("SELECT * FROM configuraciones");
        $config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
        error_log("Error en configuración: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del Sistema - E-Learning PLI</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Navbar -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <a href="../index.php">
                            <img class="h-8 w-auto" src="../assets/images/logo.png" alt="Logo">
                        </a>
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="dashboard.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Dashboard
                        </a>
                        <a href="usuarios.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Usuarios
                        </a>
                        <a href="materias.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Materias
                        </a>
                        <a href="configuracion.php" class="border-blue-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Configuración
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Contenido principal -->
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Encabezado -->
        <div class="px-4 py-6 sm:px-0">
            <h1 class="text-3xl font-bold text-gray-900">
                Configuración del Sistema
            </h1>
            <p class="mt-1 text-sm text-gray-600">
                Administra la configuración general del sistema
            </p>
        </div>

        <!-- Formulario de configuración -->
        <div class="bg-white shadow rounded-lg">
            <div class="p-4 sm:p-6">
                <?php if ($error): ?>
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" class="space-y-8">
                    <!-- Configuración General -->
                    <div>
                        <h2 class="text-lg font-medium text-gray-900 border-b pb-2">
                            Configuración General
                        </h2>
                        <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <label for="site_name" class="block text-sm font-medium text-gray-700">
                                    Nombre del Sitio
                                </label>
                                <input type="text" name="site_name" id="site_name"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    value="<?php echo htmlspecialchars($config['site_name'] ?? ''); ?>">
                            </div>

                            <div>
                                <label for="admin_email" class="block text-sm font-medium text-gray-700">
                                    Correo del Administrador
                                </label>
                                <input type="email" name="admin_email" id="admin_email"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    value="<?php echo htmlspecialchars($config['admin_email'] ?? ''); ?>">
                            </div>

                            <div class="sm:col-span-2">
                                <label for="site_description" class="block text-sm font-medium text-gray-700">
                                    Descripción del Sitio
                                </label>
                                <textarea name="site_description" id="site_description" rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"><?php echo htmlspecialchars($config['site_description'] ?? ''); ?></textarea>
                            </div>

                            <div>
                                <label for="max_file_size" class="block text-sm font-medium text-gray-700">
                                    Tamaño Máximo de Archivo (MB)
                                </label>
                                <input type="number" name="max_file_size" id="max_file_size"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    value="<?php echo htmlspecialchars($config['max_file_size'] ?? '5'); ?>">
                            </div>

                            <div>
                                <label for="allowed_file_types" class="block text-sm font-medium text-gray-700">
                                    Tipos de Archivo Permitidos
                                </label>
                                <input type="text" name="allowed_file_types" id="allowed_file_types"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    value="<?php echo htmlspecialchars($config['allowed_file_types'] ?? 'pdf,doc,docx,jpg,png'); ?>"
                                    placeholder="pdf,doc,docx,jpg,png">
                            </div>
                        </div>
                    </div>

                    <!-- Configuración de Correo -->
                    <div>
                        <h2 class="text-lg font-medium text-gray-900 border-b pb-2">
                            Configuración de Correo (SMTP)
                        </h2>
                        <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <label for="smtp_host" class="block text-sm font-medium text-gray-700">
                                    Servidor SMTP
                                </label>
                                <input type="text" name="smtp_host" id="smtp_host"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    value="<?php echo htmlspecialchars($config['smtp_host'] ?? ''); ?>">
                            </div>

                            <div>
                                <label for="smtp_port" class="block text-sm font-medium text-gray-700">
                                    Puerto SMTP
                                </label>
                                <input type="number" name="smtp_port" id="smtp_port"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    value="<?php echo htmlspecialchars($config['smtp_port'] ?? '587'); ?>">
                            </div>

                            <div>
                                <label for="smtp_user" class="block text-sm font-medium text-gray-700">
                                    Usuario SMTP
                                </label>
                                <input type="text" name="smtp_user" id="smtp_user"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    value="<?php echo htmlspecialchars($config['smtp_user'] ?? ''); ?>">
                            </div>

                            <div>
                                <label for="smtp_password" class="block text-sm font-medium text-gray-700">
                                    Contraseña SMTP
                                </label>
                                <input type="password" name="smtp_password" id="smtp_password"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    value="<?php echo htmlspecialchars($config['smtp_password'] ?? ''); ?>">
                            </div>

                            <div>
                                <label for="smtp_secure" class="block text-sm font-medium text-gray-700">
                                    Seguridad SMTP
                                </label>
                                <select name="smtp_secure" id="smtp_secure"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                    <option value="tls" <?php echo ($config['smtp_secure'] ?? '') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                    <option value="ssl" <?php echo ($config['smtp_secure'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                    <option value="" <?php echo empty($config['smtp_secure'] ?? '') ? 'selected' : ''; ?>>Ninguna</option>
                                </select>
                            </div>

                            <div class="sm:col-span-2">
                                <button type="button" id="test-email" 
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    <i class="fas fa-paper-plane mr-2"></i>
                                    Probar Configuración de Correo
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Configuración de Zoom -->
                    <div>
                        <h2 class="text-lg font-medium text-gray-900 border-b pb-2">
                            Configuración de Zoom
                        </h2>
                        <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <label for="zoom_api_key" class="block text-sm font-medium text-gray-700">
                                    API Key de Zoom
                                </label>
                                <input type="text" name="zoom_api_key" id="zoom_api_key"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    value="<?php echo htmlspecialchars($config['zoom_api_key'] ?? ''); ?>">
                            </div>

                            <div>
                                <label for="zoom_api_secret" class="block text-sm font-medium text-gray-700">
                                    API Secret de Zoom
                                </label>
                                <input type="password" name="zoom_api_secret" id="zoom_api_secret"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    value="<?php echo htmlspecialchars($config['zoom_api_secret'] ?? ''); ?>">
                            </div>

                            <div>
                                <label for="zoom_email" class="block text-sm font-medium text-gray-700">
                                    Correo de Zoom
                                </label>
                                <input type="email" name="zoom_email" id="zoom_email"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    value="<?php echo htmlspecialchars($config['zoom_email'] ?? ''); ?>">
                            </div>

                            <div class="sm:col-span-2">
                                <button type="button" id="test-zoom" 
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    <i class="fas fa-video mr-2"></i>
                                    Probar Configuración de Zoom
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="flex justify-end space-x-3">
                        <button type="reset" 
                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Restaurar
                        </button>
                        <button type="submit" 
                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-save mr-2"></i>
                            Guardar Configuración
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Probar configuración de correo
        document.getElementById('test-email').addEventListener('click', function() {
            const data = {
                smtp_host: document.getElementById('smtp_host').value,
                smtp_port: document.getElementById('smtp_port').value,
                smtp_user: document.getElementById('smtp_user').value,
                smtp_password: document.getElementById('smtp_password').value,
                smtp_secure: document.getElementById('smtp_secure').value
            };

            fetch('test-email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Configuración de correo correcta. Email de prueba enviado.');
                } else {
                    alert('Error en la configuración de correo: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al probar la configuración de correo');
            });
        });

        // Probar configuración de Zoom
        document.getElementById('test-zoom').addEventListener('click', function() {
            const data = {
                zoom_api_key: document.getElementById('zoom_api_key').value,
                zoom_api_secret: document.getElementById('zoom_api_secret').value,
                zoom_email: document.getElementById('zoom_email').value
            };

            fetch('test-zoom.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Configuración de Zoom correcta. Conexión establecida.');
                } else {
                    alert('Error en la configuración de Zoom: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al probar la configuración de Zoom');
            });
        });

        // Confirmar cambios no guardados
        let formChanged = false;
        const form = document.querySelector('form');

        form.addEventListener('change', function() {
            formChanged = true;
        });

        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        form.addEventListener('submit', function() {
            formChanged = false;
        });
    </script>
</body>
</html>
