<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = Auth::getInstance();
$error = '';

// Si ya está autenticado, redirigir al dashboard
if ($auth->isAuthenticated()) {
    header('Location: index.php');
    exit;
}

// Procesar el formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $email = sanitize_input($_POST['email']);
        $password = $_POST['password'];

        if (empty($email) || empty($password)) {
            throw new Exception('Por favor, complete todos los campos.');
        }

        if ($auth->login($email, $password)) {
            // Redirigir según el rol del usuario
            $role = $_SESSION['role'];
            switch ($role) {
                case 'Administrador':
                    header('Location: admin/dashboard.php');
                    break;
                case 'Maestro':
                    header('Location: teacher/dashboard.php');
                    break;
                default:
                    header('Location: student/dashboard.php');
            }
            exit;
        } else {
            throw new Exception('Credenciales inválidas.');
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
    <title>Iniciar Sesión - E-Learning PLI</title>
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
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <!-- Logo y Título -->
            <div>
                <a href="index.php" class="flex justify-center mb-6">
                    <img class="h-12 w-auto" src="assets/images/logo.png" alt="Logo">
                </a>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    Iniciar Sesión
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    ¿No tienes una cuenta?
                    <a href="register.php" class="font-medium text-blue-600 hover:text-blue-500">
                        Regístrate aquí
                    </a>
                </p>
            </div>

            <!-- Formulario -->
            <form class="mt-8 space-y-6" method="POST" action="">
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                        <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <div class="rounded-md shadow-sm -space-y-px">
                    <div>
                        <label for="email" class="sr-only">Correo electrónico</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-envelope text-gray-400"></i>
                            </div>
                            <input id="email" name="email" type="email" required 
                                class="appearance-none rounded-none relative block w-full px-3 py-2 pl-10 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm" 
                                placeholder="Correo electrónico"
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                    </div>
                    <div>
                        <label for="password" class="sr-only">Contraseña</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input id="password" name="password" type="password" required 
                                class="appearance-none rounded-none relative block w-full px-3 py-2 pl-10 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm" 
                                placeholder="Contraseña">
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember_me" name="remember_me" type="checkbox" 
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="remember_me" class="ml-2 block text-sm text-gray-900">
                            Recordarme
                        </label>
                    </div>

                    <div class="text-sm">
                        <a href="forgot-password.php" class="font-medium text-blue-600 hover:text-blue-500">
                            ¿Olvidaste tu contraseña?
                        </a>
                    </div>
                </div>

                <div>
                    <button type="submit" 
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <i class="fas fa-sign-in-alt text-blue-500 group-hover:text-blue-400"></i>
                        </span>
                        Iniciar Sesión
                    </button>
                </div>
            </form>

            <!-- Enlaces adicionales -->
            <div class="mt-6">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-gray-100 text-gray-500">
                            O continúa con
                        </span>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-3">
                    <div>
                        <a href="#" class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <i class="fab fa-google text-red-500"></i>
                            <span class="ml-2">Google</span>
                        </a>
                    </div>
                    <div>
                        <a href="#" class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                            <i class="fab fa-microsoft text-blue-500"></i>
                            <span class="ml-2">Microsoft</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer simple -->
    <footer class="absolute bottom-0 w-full py-4">
        <div class="text-center text-gray-500 text-sm">
            &copy; <?php echo date('Y'); ?> E-Learning PLI. Todos los derechos reservados.
        </div>
    </footer>

    <script>
        // Validación del formulario en el lado del cliente
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            let isValid = true;
            let errorMessage = '';

            if (!email) {
                errorMessage = 'Por favor, ingrese su correo electrónico.';
                isValid = false;
            } else if (!email.includes('@')) {
                errorMessage = 'Por favor, ingrese un correo electrónico válido.';
                isValid = false;
            }

            if (!password) {
                errorMessage = 'Por favor, ingrese su contraseña.';
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
                const errorDiv = document.querySelector('.bg-red-100');
                if (errorDiv) {
                    errorDiv.querySelector('span').textContent = errorMessage;
                } else {
                    const newErrorDiv = document.createElement('div');
                    newErrorDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative';
                    newErrorDiv.innerHTML = `<span class="block sm:inline">${errorMessage}</span>`;
                    document.querySelector('form').insertBefore(newErrorDiv, document.querySelector('form').firstChild);
                }
            }
        });
    </script>
</body>
</html>
