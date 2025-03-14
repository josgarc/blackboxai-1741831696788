<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/file_manager.php';

$auth = Auth::getInstance();
$fileManager = FileManager::getInstance();

// Verificar autenticación y rol de profesor
if (!$auth->isAuthenticated() || !$auth->hasRole('Maestro')) {
    header('Location: ../login.php');
    exit;
}

$error = '';
$success = '';
$userId = $_SESSION['user_id'];

try {
    // Obtener información del usuario
    $stmt = $pdo->prepare("
        SELECT u.*, 
               (SELECT COUNT(*) FROM maestros_materias WHERE user_id = u.id) as total_materias,
               (SELECT COUNT(DISTINCT et.id) 
                FROM entregas_tareas et 
                INNER JOIN tareas t ON et.tarea_id = t.id 
                INNER JOIN materias m ON t.materia_id = m.id 
                INNER JOIN maestros_materias mm ON m.id = mm.materia_id 
                WHERE mm.user_id = u.id) as total_entregas,
               (SELECT COUNT(DISTINCT zm.id) 
                FROM zoom_meetings zm 
                INNER JOIN materias m ON zm.materia_id = m.id 
                INNER JOIN maestros_materias mm ON m.id = mm.materia_id 
                WHERE mm.user_id = u.id) as total_clases
        FROM users u 
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    // Procesar actualización de perfil
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'update_profile':
                $fullName = trim($_POST['full_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $bio = trim($_POST['bio'] ?? '');
                $country = trim($_POST['country'] ?? '');

                if (empty($fullName) || empty($email)) {
                    throw new Exception('El nombre completo y el correo electrónico son obligatorios.');
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('El correo electrónico no es válido.');
                }

                // Verificar si el email ya existe (excluyendo el usuario actual)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $userId]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('El correo electrónico ya está registrado.');
                }

                // Actualizar perfil
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET full_Name = ?, email = ?, phone = ?, bio = ?, country = ?
                    WHERE id = ?
                ");
                $stmt->execute([$fullName, $email, $phone, $bio, $country, $userId]);

                // Actualizar datos de sesión
                $_SESSION['full_name'] = $fullName;
                $_SESSION['email'] = $email;

                $success = 'Perfil actualizado correctamente.';
                break;

            case 'change_password':
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';

                if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                    throw new Exception('Todos los campos son obligatorios.');
                }

                if ($newPassword !== $confirmPassword) {
                    throw new Exception('Las contraseñas no coinciden.');
                }

                if (strlen($newPassword) < 8) {
                    throw new Exception('La contraseña debe tener al menos 8 caracteres.');
                }

                // Verificar contraseña actual
                if (!$auth->verifyPassword($userId, $currentPassword)) {
                    throw new Exception('La contraseña actual no es correcta.');
                }

                // Actualizar contraseña
                $auth->updatePassword($userId, $newPassword);
                $success = 'Contraseña actualizada correctamente.';
                break;
        }

        // Recargar información del usuario
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
    }

} catch (Exception $e) {
    error_log("Error en profile.php: " . $e->getMessage());
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - E-Learning PLI</title>
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
                        <a href="dashboard.php" class="text-gray-700">
                            <i class="fas fa-arrow-left mr-2"></i> Volver al Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Contenido principal -->
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
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

        <!-- Perfil del profesor -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 h-24 w-24">
                        <img class="h-24 w-24 rounded-full" 
                            src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['full_Name']); ?>&size=96&background=random" 
                            alt="Profile">
                    </div>
                    <div class="ml-6">
                        <h1 class="text-2xl font-bold text-gray-900">
                            <?php echo htmlspecialchars($user['full_Name']); ?>
                        </h1>
                        <p class="text-sm text-gray-500">
                            <?php echo htmlspecialchars($user['email']); ?>
                        </p>
                        <p class="mt-1 text-sm text-gray-500">
                            Miembro desde <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 mb-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">
                        Materias Asignadas
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900">
                        <?php echo $user['total_materias']; ?>
                    </dd>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">
                        Entregas por Revisar
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900">
                        <?php echo $user['total_entregas']; ?>
                    </dd>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">
                        Clases Programadas
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900">
                        <?php echo $user['total_clases']; ?>
                    </dd>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Formulario de información personal -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        Información Personal
                    </h3>
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="grid grid-cols-1 gap-6">
                            <div>
                                <label for="full_name" class="block text-sm font-medium text-gray-700">
                                    Nombre completo *
                                </label>
                                <input type="text" name="full_name" id="full_name" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    value="<?php echo htmlspecialchars($user['full_Name']); ?>">
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">
                                    Correo electrónico *
                                </label>
                                <input type="email" name="email" id="email" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    value="<?php echo htmlspecialchars($user['email']); ?>">
                            </div>

                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700">
                                    Teléfono
                                </label>
                                <input type="tel" name="phone" id="phone"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>

                            <div>
                                <label for="country" class="block text-sm font-medium text-gray-700">
                                    País
                                </label>
                                <select name="country" id="country"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                    <option value="">Seleccionar país</option>
                                    <option value="MX" <?php echo ($user['country'] ?? '') === 'MX' ? 'selected' : ''; ?>>México</option>
                                    <option value="US" <?php echo ($user['country'] ?? '') === 'US' ? 'selected' : ''; ?>>Estados Unidos</option>
                                    <option value="ES" <?php echo ($user['country'] ?? '') === 'ES' ? 'selected' : ''; ?>>España</option>
                                </select>
                            </div>

                            <div>
                                <label for="bio" class="block text-sm font-medium text-gray-700">
                                    Biografía
                                </label>
                                <textarea name="bio" id="bio" rows="4"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit"
                                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Guardar Cambios
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Formulario de cambio de contraseña -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        Cambiar Contraseña
                    </h3>
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <div class="space-y-6">
                            <div>
                                <label for="current_password" class="block text-sm font-medium text-gray-700">
                                    Contraseña actual *
                                </label>
                                <input type="password" name="current_password" id="current_password" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            </div>

                            <div>
                                <label for="new_password" class="block text-sm font-medium text-gray-700">
                                    Nueva contraseña *
                                </label>
                                <input type="password" name="new_password" id="new_password" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    minlength="8">
                            </div>

                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700">
                                    Confirmar nueva contraseña *
                                </label>
                                <input type="password" name="confirm_password" id="confirm_password" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    minlength="8">
                            </div>

                            <div class="flex justify-end">
                                <button type="submit"
                                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Cambiar Contraseña
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Validación del formulario de cambio de contraseña
        document.querySelector('form[action="change_password"]').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Las contraseñas no coinciden.');
            }

            if (newPassword.length < 8) {
                e.preventDefault();
                alert('La contraseña debe tener al menos 8 caracteres.');
            }
        });
    </script>
</body>
</html>
