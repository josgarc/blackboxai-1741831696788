<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = Auth::getInstance();
$error = '';
$success = '';

// Si ya está autenticado, redirigir al dashboard
if ($auth->isAuthenticated()) {
    header('Location: index.php');
    exit;
}

// Procesar el formulario de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar y sanitizar datos
        $userData = [
            'full_Name' => sanitize_input($_POST['full_name']),
            'username' => sanitize_input($_POST['username']),
            'email' => sanitize_input($_POST['email']),
            'password' => $_POST['password'],
            'password_confirm' => $_POST['password_confirm'],
            'phone' => sanitize_input($_POST['phone']),
            'country' => sanitize_input($_POST['country']),
            'termsAccepted' => isset($_POST['terms']) ? 1 : 0,
            'role' => 'Estudiante' // Rol por defecto
        ];

        // Validaciones
        if (empty($userData['full_Name']) || empty($userData['username']) || 
            empty($userData['email']) || empty($userData['password']) || 
            empty($userData['phone']) || empty($userData['country'])) {
            throw new Exception('Por favor, complete todos los campos obligatorios.');
        }

        if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Por favor, ingrese un correo electrónico válido.');
        }

        if ($userData['password'] !== $userData['password_confirm']) {
            throw new Exception('Las contraseñas no coinciden.');
        }

        if (strlen($userData['password']) < 8) {
            throw new Exception('La contraseña debe tener al menos 8 caracteres.');
        }

        if (!$userData['termsAccepted']) {
            throw new Exception('Debe aceptar los términos y condiciones.');
        }

        // Intentar registrar al usuario
        if ($auth->register($userData)) {
            $success = '¡Registro exitoso! Por favor, inicie sesión.';
            // Redirigir después de 3 segundos
            header("refresh:3;url=login.php");
        } else {
            throw new Exception('Error al crear la cuenta. Por favor, intente nuevamente.');
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
    <title>Registro - E-Learning PLI</title>
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
    <div class="min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <a href="index.php" class="flex justify-center mb-6">
                <img class="h-12 w-auto" src="assets/images/logo.png" alt="Logo">
            </a>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Crear una cuenta
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                ¿Ya tienes una cuenta?
                <a href="login.php" class="font-medium text-blue-600 hover:text-blue-500">
                    Inicia sesión
                </a>
            </p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
                    </div>
                <?php endif; ?>

                <form class="space-y-6" method="POST" action="" id="registerForm">
                    <!-- Nombre completo -->
                    <div>
                        <label for="full_name" class="block text-sm font-medium text-gray-700">
                            Nombre completo
                        </label>
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                            <input type="text" id="full_name" name="full_name" required
                                class="appearance-none block w-full px-3 py-2 pl-10 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                        </div>
                    </div>

                    <!-- Nombre de usuario -->
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">
                            Nombre de usuario
                        </label>
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-at text-gray-400"></i>
                            </div>
                            <input type="text" id="username" name="username" required
                                class="appearance-none block w-full px-3 py-2 pl-10 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                    </div>

                    <!-- Correo electrónico -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">
                            Correo electrónico
                        </label>
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-envelope text-gray-400"></i>
                            </div>
                            <input type="email" id="email" name="email" required
                                class="appearance-none block w-full px-3 py-2 pl-10 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                    </div>

                    <!-- Contraseña -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">
                            Contraseña
                        </label>
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input type="password" id="password" name="password" required
                                class="appearance-none block w-full px-3 py-2 pl-10 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                    </div>

                    <!-- Confirmar Contraseña -->
                    <div>
                        <label for="password_confirm" class="block text-sm font-medium text-gray-700">
                            Confirmar contraseña
                        </label>
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input type="password" id="password_confirm" name="password_confirm" required
                                class="appearance-none block w-full px-3 py-2 pl-10 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                    </div>

                    <!-- Teléfono -->
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700">
                            Teléfono
                        </label>
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-phone text-gray-400"></i>
                            </div>
                            <input type="tel" id="phone" name="phone" required
                                class="appearance-none block w-full px-3 py-2 pl-10 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>
                    </div>

                    <!-- País -->
                    <div>
                        <label for="country" class="block text-sm font-medium text-gray-700">
                            País
                        </label>
                        <div class="mt-1 relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-globe text-gray-400"></i>
                            </div>
                            <select id="country" name="country" required
                                class="appearance-none block w-full px-3 py-2 pl-10 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="">Seleccione un país</option>
                                <option value="MX" <?php echo (isset($_POST['country']) && $_POST['country'] === 'MX') ? 'selected' : ''; ?>>México</option>
                                <option value="US" <?php echo (isset($_POST['country']) && $_POST['country'] === 'US') ? 'selected' : ''; ?>>Estados Unidos</option>
                                <option value="ES" <?php echo (isset($_POST['country']) && $_POST['country'] === 'ES') ? 'selected' : ''; ?>>España</option>
                                <!-- Agregar más países según sea necesario -->
                            </select>
                        </div>
                    </div>

                    <!-- Términos y condiciones -->
                    <div class="flex items-center">
                        <input type="checkbox" id="terms" name="terms" required
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="terms" class="ml-2 block text-sm text-gray-900">
                            Acepto los <a href="#" class="text-blue-600 hover:text-blue-500">términos y condiciones</a>
                        </label>
                    </div>

                    <!-- Botón de registro -->
                    <div>
                        <button type="submit"
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Crear cuenta
                        </button>
                    </div>
                </form>

                <!-- Separador -->
                <div class="mt-6">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white text-gray-500">
                                O regístrate con
                            </span>
                        </div>
                    </div>

                    <!-- Botones de redes sociales -->
                    <div class="mt-6 grid grid-cols-2 gap-3">
                        <div>
                            <a href="#"
                                class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <i class="fab fa-google text-red-500"></i>
                                <span class="ml-2">Google</span>
                            </a>
                        </div>
                        <div>
                            <a href="#"
                                class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <i class="fab fa-microsoft text-blue-500"></i>
                                <span class="ml-2">Microsoft</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Validación del formulario en el lado del cliente
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;
            const email = document.getElementById('email').value;
            const terms = document.getElementById('terms').checked;
            let isValid = true;
            let errorMessage = '';

            // Validar contraseña
            if (password.length < 8) {
                errorMessage = 'La contraseña debe tener al menos 8 caracteres.';
                isValid = false;
            }

            // Validar coincidencia de contraseñas
            if (password !== passwordConfirm) {
                errorMessage = 'Las contraseñas no coinciden.';
                isValid = false;
            }

            // Validar email
            if (!email.includes('@')) {
                errorMessage = 'Por favor, ingrese un correo electrónico válido.';
                isValid = false;
            }

            // Validar términos y condiciones
            if (!terms) {
                errorMessage = 'Debe aceptar los términos y condiciones.';
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
                const errorDiv = document.querySelector('.bg-red-100');
                if (errorDiv) {
                    errorDiv.querySelector('span').textContent = errorMessage;
                } else {
                    const newErrorDiv = document.createElement('div');
                    newErrorDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4';
                    newErrorDiv.innerHTML = `<span class="block sm:inline">${errorMessage}</span>`;
                    document.querySelector('form').insertBefore(newErrorDiv, document.querySelector('form').firstChild);
                }
            }
        });
    </script>
</body>
</html>
