<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = Auth::getInstance();

// Redirigir según el estado de autenticación
if ($auth->isAuthenticated()) {
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
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Learning PLI - Plataforma de Aprendizaje</title>
    <!-- Tailwind CSS via CDN -->
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
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <a href="/" class="flex items-center">
                        <img src="assets/images/logo.png" alt="Logo" class="h-8 w-auto">
                        <span class="ml-2 text-xl font-semibold text-gray-800">E-Learning PLI</span>
                    </a>
                </div>
                <div class="flex items-center">
                    <a href="login.php" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">
                        Iniciar Sesión
                    </a>
                    <a href="register.php" class="ml-4 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Registrarse
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="relative bg-white overflow-hidden">
        <div class="max-w-7xl mx-auto">
            <div class="relative z-10 pb-8 bg-white sm:pb-16 md:pb-20 lg:max-w-2xl lg:w-full lg:pb-28 xl:pb-32">
                <main class="mt-10 mx-auto max-w-7xl px-4 sm:mt-12 sm:px-6 md:mt-16 lg:mt-20 lg:px-8 xl:mt-28">
                    <div class="sm:text-center lg:text-left">
                        <h1 class="text-4xl tracking-tight font-extrabold text-gray-900 sm:text-5xl md:text-6xl">
                            <span class="block">Aprende a tu ritmo</span>
                            <span class="block text-blue-600">en cualquier momento</span>
                        </h1>
                        <p class="mt-3 text-base text-gray-500 sm:mt-5 sm:text-lg sm:max-w-xl sm:mx-auto md:mt-5 md:text-xl lg:mx-0">
                            Accede a cursos de calidad, interactúa con profesores expertos y desarrolla tus habilidades desde cualquier lugar.
                        </p>
                        <div class="mt-5 sm:mt-8 sm:flex sm:justify-center lg:justify-start">
                            <div class="rounded-md shadow">
                                <a href="register.php" class="w-full flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 md:py-4 md:text-lg md:px-10">
                                    Comenzar ahora
                                </a>
                            </div>
                            <div class="mt-3 sm:mt-0 sm:ml-3">
                                <a href="#features" class="w-full flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 md:py-4 md:text-lg md:px-10">
                                    Conocer más
                                </a>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
        <div class="lg:absolute lg:inset-y-0 lg:right-0 lg:w-1/2">
            <img class="h-56 w-full object-cover sm:h-72 md:h-96 lg:w-full lg:h-full" src="assets/images/hero.jpg" alt="Estudiantes aprendiendo">
        </div>
    </div>

    <!-- Features Section -->
    <div id="features" class="py-12 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="lg:text-center">
                <h2 class="text-base text-blue-600 font-semibold tracking-wide uppercase">Características</h2>
                <p class="mt-2 text-3xl leading-8 font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                    Una mejor manera de aprender
                </p>
                <p class="mt-4 max-w-2xl text-xl text-gray-500 lg:mx-auto">
                    Nuestra plataforma está diseñada para ofrecer la mejor experiencia de aprendizaje.
                </p>
            </div>

            <div class="mt-10">
                <div class="space-y-10 md:space-y-0 md:grid md:grid-cols-2 md:gap-x-8 md:gap-y-10">
                    <!-- Feature 1 -->
                    <div class="relative">
                        <div class="absolute flex items-center justify-center h-12 w-12 rounded-md bg-blue-500 text-white">
                            <i class="fas fa-video text-xl"></i>
                        </div>
                        <p class="ml-16 text-lg leading-6 font-medium text-gray-900">Clases en vivo</p>
                        <p class="mt-2 ml-16 text-base text-gray-500">
                            Participa en clases en vivo con profesores expertos a través de Zoom.
                        </p>
                    </div>

                    <!-- Feature 2 -->
                    <div class="relative">
                        <div class="absolute flex items-center justify-center h-12 w-12 rounded-md bg-blue-500 text-white">
                            <i class="fas fa-book text-xl"></i>
                        </div>
                        <p class="ml-16 text-lg leading-6 font-medium text-gray-900">Contenido multimedia</p>
                        <p class="mt-2 ml-16 text-base text-gray-500">
                            Accede a material didáctico en diferentes formatos: videos, PDFs, presentaciones y más.
                        </p>
                    </div>

                    <!-- Feature 3 -->
                    <div class="relative">
                        <div class="absolute flex items-center justify-center h-12 w-12 rounded-md bg-blue-500 text-white">
                            <i class="fas fa-tasks text-xl"></i>
                        </div>
                        <p class="ml-16 text-lg leading-6 font-medium text-gray-900">Evaluación continua</p>
                        <p class="mt-2 ml-16 text-base text-gray-500">
                            Realiza tareas y exámenes para medir tu progreso en cada curso.
                        </p>
                    </div>

                    <!-- Feature 4 -->
                    <div class="relative">
                        <div class="absolute flex items-center justify-center h-12 w-12 rounded-md bg-blue-500 text-white">
                            <i class="fas fa-chart-line text-xl"></i>
                        </div>
                        <p class="ml-16 text-lg leading-6 font-medium text-gray-900">Seguimiento de progreso</p>
                        <p class="mt-2 ml-16 text-base text-gray-500">
                            Visualiza tu avance y calificaciones en tiempo real.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800">
        <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <h3 class="text-white text-lg font-semibold mb-4">Sobre nosotros</h3>
                    <p class="text-gray-400">
                        E-Learning PLI es una plataforma educativa diseñada para facilitar el aprendizaje en línea.
                    </p>
                </div>
                <div>
                    <h3 class="text-white text-lg font-semibold mb-4">Enlaces rápidos</h3>
                    <ul class="space-y-2">
                        <li>
                            <a href="#" class="text-gray-400 hover:text-white">Inicio</a>
                        </li>
                        <li>
                            <a href="login.php" class="text-gray-400 hover:text-white">Iniciar Sesión</a>
                        </li>
                        <li>
                            <a href="register.php" class="text-gray-400 hover:text-white">Registrarse</a>
                        </li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-white text-lg font-semibold mb-4">Contacto</h3>
                    <ul class="space-y-2">
                        <li class="text-gray-400">
                            <i class="fas fa-envelope mr-2"></i> info@e-learningpli.com
                        </li>
                        <li class="text-gray-400">
                            <i class="fas fa-phone mr-2"></i> +1 234 567 890
                        </li>
                    </ul>
                </div>
            </div>
            <div class="mt-8 border-t border-gray-700 pt-8">
                <p class="text-center text-gray-400">
                    &copy; <?php echo date('Y'); ?> E-Learning PLI. Todos los derechos reservados.
                </p>
            </div>
        </div>
    </footer>

    <script>
        // Smooth scroll para los enlaces internos
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>
