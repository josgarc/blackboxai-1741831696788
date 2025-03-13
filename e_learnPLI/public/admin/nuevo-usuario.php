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

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar datos
        $userData = [
            'full_Name' => trim($_POST['full_name'] ?? ''),
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? '',
            'phone' => trim($_POST['phone'] ?? ''),
            'country' => trim($_POST['country'] ?? ''),
            'role' => trim($_POST['role'] ?? ''),
            'status' => 'activo',
            'termsAccepted' => 1
        ];

        // Validaciones
        if (empty($userData['full_Name']) || empty($userData['username']) || 
            empty($userData['email']) || empty($userData['password']) || 
            empty($userData['role'])) {
            throw new Exception('Todos los campos marcados con * son obligatorios.');
        }

        if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('El correo electrónico no es válido.');
        }

        if ($userData['password'] !== $userData['password_confirm']) {
            throw new Exception('Las contraseñas no coinciden.');
        }

        if (strlen($userData['password']) < 8) {
            throw new Exception('La contraseña debe tener al menos 8 caracteres.');
        }

        // Verificar si el email ya existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$userData['email']]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('El correo electrónico ya está registrado.');
        }

        // Verificar si el username ya existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$userData['username']]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('El nombre de usuario ya está en uso.');
        }

        // Crear usuario
        if ($auth->register($userData)) {
            $success = '¡Usuario creado exitosamente!';
            // Redirigir después de 2 segundos
            header("refresh:2;url=usuarios.php");
        } else {
            throw new Exception('Error al crear el usuario.');
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Usuario - E-Learning PLI</title>
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
                        <a href="usuarios.php" class="border-blue-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Usuarios
                        </a>
                        <a href="materias.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Materias
                        </a>
                        <a href="configuracion.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
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
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">
                        Nuevo Usuario
                    </h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Crear un nuevo usuario en el sistema
                    </p>
                </div>
                <div>
                    <a href="usuarios.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Volver
                    </a>
                </div>
            </div>
        </div>

        <!-- Formulario -->
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

                <form action="" method="POST" class="space-y-6">
                    <!-- Información básica -->
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label for="full_name" class="block text-sm font-medium text-gray-700">
                                Nombre completo *
                            </label>
                            <input type="text" name="full_name" id="full_name" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                        </div>

                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700">
                                Nombre de usuario *
                            </label>
                            <input type="text" name="username" id="username" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">
                                Correo electrónico *
                            </label>
                            <input type="email" name="email" id="email" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700">
                                Teléfono
                            </label>
                            <input type="tel" name="phone" id="phone"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">
                                Contraseña *
                            </label>
                            <input type="password" name="password" id="password" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                minlength="8">
                        </div>

                        <div>
                            <label for="password_confirm" class="block text-sm font-medium text-gray-700">
                                Confirmar contraseña *
                            </label>
                            <input type="password" name="password_confirm" id="password_confirm" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                minlength="8">
                        </div>

                        <div>
                            <label for="country" class="block text-sm font-medium text-gray-700">
                                País
                            </label>
                            <select name="country" id="country"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                <option value="">Seleccionar país</option>
                                <option value="MX" <?php echo (isset($_POST['country']) && $_POST['country'] === 'MX') ? 'selected' : ''; ?>>México</option>
                                <option value="US" <?php echo (isset($_POST['country']) && $_POST['country'] === 'US') ? 'selected' : ''; ?>>Estados Unidos</option>
                                <option value="ES" <?php echo (isset($_POST['country']) && $_POST['country'] === 'ES') ? 'selected' : ''; ?>>España</option>
                            </select>
                        </div>

                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700">
                                Rol *
                            </label>
                            <select name="role" id="role" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                <option value="">Seleccionar rol</option>
                                <option value="Administrador" <?php echo (isset($_POST['role']) && $_POST['role'] === 'Administrador') ? 'selected' : ''; ?>>Administrador</option>
                                <option value="Maestro" <?php echo (isset($_POST['role']) && $_POST['role'] === 'Maestro') ? 'selected' : ''; ?>>Maestro</option>
                                <option value="Estudiante" <?php echo (isset($_POST['role']) && $_POST['role'] === 'Estudiante') ? 'selected' : ''; ?>>Estudiante</option>
                            </select>
                        </div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="flex justify-end space-x-3">
                        <a href="usuarios.php" 
                           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Cancelar
                        </a>
                        <button type="submit" 
                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-save mr-2"></i>
                            Guardar Usuario
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Validación del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;

            if (password !== passwordConfirm) {
                e.preventDefault();
                alert('Las contraseñas no coinciden.');
                return;
            }

            if (password.length < 8) {
                e.preventDefault();
                alert('La contraseña debe tener al menos 8 caracteres.');
                return;
            }
        });
    </script>
</body>
</html>
