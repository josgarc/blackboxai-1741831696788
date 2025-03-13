<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/course_manager.php';

$auth = Auth::getInstance();
$courseManager = CourseManager::getInstance();

// Verificar autenticación y rol de administrador
if (!$auth->isAuthenticated() || !$auth->hasRole('Administrador')) {
    header('Location: ../login.php');
    exit;
}

// Obtener estadísticas generales
try {
    // Total de usuarios por rol
    $stmt = $pdo->query("
        SELECT role, COUNT(*) as total 
        FROM users 
        GROUP BY role
    ");
    $usuariosPorRol = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Total de materias activas
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM materias 
        WHERE estado = 'activo'
    ");
    $materiasActivas = $stmt->fetchColumn();

    // Total de estudiantes activos
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT user_id) 
        FROM estudiantes_materias 
        WHERE estado = 'inscrito'
    ");
    $estudiantesActivos = $stmt->fetchColumn();

    // Últimos usuarios registrados
    $stmt = $pdo->query("
        SELECT * FROM users 
        ORDER BY createdAt DESC 
        LIMIT 5
    ");
    $ultimosUsuarios = $stmt->fetchAll();

    // Últimas materias creadas
    $stmt = $pdo->query("
        SELECT m.*, u.full_Name as profesor_nombre
        FROM materias m
        LEFT JOIN maestros_materias mm ON m.id = mm.materia_id
        LEFT JOIN users u ON mm.user_id = u.id
        ORDER BY m.created_at DESC
        LIMIT 5
    ");
    $ultimasMaterias = $stmt->fetchAll();

    // Estadísticas de actividad
    $stmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM tareas WHERE estado = 'publicada') as total_tareas,
            (SELECT COUNT(*) FROM examenes WHERE estado = 'publicado') as total_examenes,
            (SELECT COUNT(*) FROM zoom_meetings WHERE estado = 'programada') as clases_programadas,
            (SELECT COUNT(*) FROM entregas_tareas WHERE estado = 'entregado') as tareas_entregadas
    ");
    $estadisticas = $stmt->fetch();

} catch (Exception $e) {
    error_log("Error en dashboard admin: " . $e->getMessage());
    $error = "Error al cargar las estadísticas.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - E-Learning PLI</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <a href="dashboard.php" class="border-blue-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Dashboard
                        </a>
                        <a href="usuarios.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
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
                <div class="flex items-center">
                    <div class="ml-3 relative">
                        <div class="flex items-center">
                            <span class="text-gray-700 mr-4"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                            <button type="button" class="bg-gray-800 flex text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-800 focus:ring-white" id="user-menu-button">
                                <img class="h-8 w-8 rounded-full" src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['full_name']); ?>&background=random" alt="Profile">
                            </button>
                        </div>
                        <!-- Menú desplegable -->
                        <div class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5" id="user-menu">
                            <a href="perfil.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Mi Perfil</a>
                            <a href="../logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Cerrar Sesión</a>
                        </div>
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
                Panel de Administración
            </h1>
            <p class="mt-1 text-sm text-gray-600">
                Vista general del sistema y estadísticas
            </p>
        </div>

        <!-- Estadísticas generales -->
        <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Total de usuarios -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-users fa-2x text-blue-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">
                                    Total Usuarios
                                </dt>
                                <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                    <?php echo array_sum($usuariosPorRol); ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Materias activas -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-book fa-2x text-green-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">
                                    Materias Activas
                                </dt>
                                <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                    <?php echo $materiasActivas; ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estudiantes activos -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-user-graduate fa-2x text-purple-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">
                                    Estudiantes Activos
                                </dt>
                                <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                    <?php echo $estudiantesActivos; ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Clases programadas -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-video fa-2x text-yellow-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">
                                    Clases Programadas
                                </dt>
                                <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                    <?php echo $estadisticas['clases_programadas']; ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos y tablas -->
        <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Gráfico de usuarios por rol -->
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">
                    Distribución de Usuarios
                </h2>
                <canvas id="usuariosChart" class="w-full" height="300"></canvas>
            </div>

            <!-- Actividad reciente -->
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">
                    Actividad del Sistema
                </h2>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Tareas Activas</span>
                        <span class="font-medium"><?php echo $estadisticas['total_tareas']; ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Exámenes Publicados</span>
                        <span class="font-medium"><?php echo $estadisticas['total_examenes']; ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Tareas Entregadas</span>
                        <span class="font-medium"><?php echo $estadisticas['tareas_entregadas']; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Últimos registros -->
        <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Últimos usuarios -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">
                        Últimos Usuarios Registrados
                    </h2>
                    <div class="flow-root">
                        <ul class="-my-5 divide-y divide-gray-200">
                            <?php foreach ($ultimosUsuarios as $usuario): ?>
                            <li class="py-4">
                                <div class="flex items-center space-x-4">
                                    <div class="flex-shrink-0">
                                        <img class="h-8 w-8 rounded-full" src="https://ui-avatars.com/api/?name=<?php echo urlencode($usuario['full_Name']); ?>&background=random" alt="">
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">
                                            <?php echo htmlspecialchars($usuario['full_Name']); ?>
                                        </p>
                                        <p class="text-sm text-gray-500 truncate">
                                            <?php echo htmlspecialchars($usuario['email']); ?>
                                        </p>
                                    </div>
                                    <div>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $usuario['role'] === 'Estudiante' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                            <?php echo htmlspecialchars($usuario['role']); ?>
                                        </span>
                                    </div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="mt-6">
                        <a href="usuarios.php" class="text-sm font-medium text-blue-600 hover:text-blue-500">
                            Ver todos los usuarios <span aria-hidden="true">&rarr;</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Últimas materias -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">
                        Últimas Materias Creadas
                    </h2>
                    <div class="flow-root">
                        <ul class="-my-5 divide-y divide-gray-200">
                            <?php foreach ($ultimasMaterias as $materia): ?>
                            <li class="py-4">
                                <div class="flex items-center space-x-4">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-book text-blue-500"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">
                                            <?php echo htmlspecialchars($materia['nombre']); ?>
                                        </p>
                                        <p class="text-sm text-gray-500 truncate">
                                            Profesor: <?php echo htmlspecialchars($materia['profesor_nombre'] ?? 'Sin asignar'); ?>
                                        </p>
                                    </div>
                                    <div>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $materia['estado'] === 'activo' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                            <?php echo ucfirst($materia['estado']); ?>
                                        </span>
                                    </div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="mt-6">
                        <a href="materias.php" class="text-sm font-medium text-blue-600 hover:text-blue-500">
                            Ver todas las materias <span aria-hidden="true">&rarr;</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle del menú de usuario
        const userMenuButton = document.getElementById('user-menu-button');
        const userMenu = document.getElementById('user-menu');
        
        userMenuButton.addEventListener('click', () => {
            userMenu.classList.toggle('hidden');
        });

        // Cerrar el menú cuando se hace clic fuera
        document.addEventListener('click', (event) => {
            if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                userMenu.classList.add('hidden');
            }
        });

        // Gráfico de usuarios por rol
        const ctx = document.getElementById('usuariosChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($usuariosPorRol)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($usuariosPorRol)); ?>,
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)', // Azul
                        'rgba(16, 185, 129, 0.8)', // Verde
                        'rgba(139, 92, 246, 0.8)'  // Morado
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>
