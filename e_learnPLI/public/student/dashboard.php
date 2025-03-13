<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/course_manager.php';

$auth = Auth::getInstance();
$courseManager = CourseManager::getInstance();

// Verificar autenticación
if (!$auth->isAuthenticated()) {
    header('Location: ../login.php');
    exit;
}

// Verificar rol de estudiante
if (!$auth->hasRole('Estudiante')) {
    header('Location: ../index.php');
    exit;
}

// Obtener información del usuario
$userId = $_SESSION['user_id'];
$user = $auth->getUserById($userId);

// Obtener materias inscritas
try {
    $stmt = $pdo->prepare("
        SELECT m.*, 
               em.estado as estado_inscripcion,
               em.nota_final,
               (SELECT COUNT(*) FROM tareas t WHERE t.materia_id = m.id AND t.estado = 'publicada') as total_tareas,
               (SELECT COUNT(*) FROM entregas_tareas et 
                INNER JOIN tareas t ON et.tarea_id = t.id 
                WHERE t.materia_id = m.id AND et.user_id = ?) as tareas_entregadas,
               (SELECT COUNT(*) FROM examenes e WHERE e.materia_id = m.id AND e.estado = 'publicado') as total_examenes,
               (SELECT COUNT(*) FROM asistencias a WHERE a.materia_id = m.id AND a.user_id = ? AND a.estado = 'presente') as total_asistencias
        FROM materias m
        INNER JOIN estudiantes_materias em ON m.id = em.materia_id
        WHERE em.user_id = ? AND em.estado = 'inscrito'
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$userId, $userId, $userId]);
    $materias = $stmt->fetchAll();

    // Obtener próximas tareas
    $stmt = $pdo->prepare("
        SELECT t.*, m.nombre as materia_nombre 
        FROM tareas t
        INNER JOIN materias m ON t.materia_id = m.id
        INNER JOIN estudiantes_materias em ON m.id = em.materia_id
        WHERE em.user_id = ? 
        AND t.fecha_entrega > NOW()
        AND t.estado = 'publicada'
        ORDER BY t.fecha_entrega ASC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $proximasTareas = $stmt->fetchAll();

    // Obtener próximas clases (reuniones Zoom)
    $stmt = $pdo->prepare("
        SELECT zm.*, m.nombre as materia_nombre
        FROM zoom_meetings zm
        INNER JOIN materias m ON zm.materia_id = m.id
        INNER JOIN estudiantes_materias em ON m.id = em.materia_id
        WHERE em.user_id = ?
        AND zm.start_time > NOW()
        AND zm.estado = 'programada'
        ORDER BY zm.start_time ASC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $proximasClases = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Error en dashboard: " . $e->getMessage());
    $error = "Ha ocurrido un error al cargar la información.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - E-Learning PLI</title>
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
                </div>
                <div class="flex items-center">
                    <div class="ml-3 relative">
                        <div class="flex items-center">
                            <span class="text-gray-700 mr-4"><?php echo htmlspecialchars($user['full_Name']); ?></span>
                            <button type="button" class="bg-gray-800 flex text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-800 focus:ring-white" id="user-menu-button">
                                <img class="h-8 w-8 rounded-full" src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['full_Name']); ?>&background=random" alt="Profile">
                            </button>
                        </div>
                        <!-- Menú desplegable -->
                        <div class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5" id="user-menu">
                            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Mi Perfil</a>
                            <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Configuración</a>
                            <a href="../logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Cerrar Sesión</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Contenido principal -->
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Mensaje de bienvenida -->
        <div class="px-4 py-6 sm:px-0">
            <h1 class="text-3xl font-bold text-gray-900">
                Bienvenido, <?php echo htmlspecialchars($user['full_Name']); ?>
            </h1>
            <p class="mt-1 text-sm text-gray-600">
                Aquí tienes un resumen de tu actividad académica
            </p>
        </div>

        <!-- Estadísticas generales -->
        <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Materias inscritas -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-book fa-2x text-blue-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">
                                    Materias Inscritas
                                </dt>
                                <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                    <?php echo count($materias); ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tareas pendientes -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-tasks fa-2x text-yellow-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">
                                    Tareas Pendientes
                                </dt>
                                <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                    <?php echo count($proximasTareas); ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Próximas clases -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-video fa-2x text-green-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">
                                    Próximas Clases
                                </dt>
                                <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                    <?php echo count($proximasClases); ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Promedio general -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-chart-line fa-2x text-purple-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">
                                    Promedio General
                                </dt>
                                <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                    <?php
                                    $promedio = 0;
                                    $materiasConNota = 0;
                                    foreach ($materias as $materia) {
                                        if ($materia['nota_final'] !== null) {
                                            $promedio += $materia['nota_final'];
                                            $materiasConNota++;
                                        }
                                    }
                                    echo $materiasConNota > 0 ? number_format($promedio / $materiasConNota, 2) : 'N/A';
                                    ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contenido principal -->
        <div class="mt-6 grid grid-cols-1 gap-5 lg:grid-cols-2">
            <!-- Próximas tareas -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Próximas Tareas
                    </h3>
                    <div class="mt-5">
                        <div class="flow-root">
                            <ul class="-my-4 divide-y divide-gray-200">
                                <?php foreach ($proximasTareas as $tarea): ?>
                                <li class="py-4">
                                    <div class="flex items-center space-x-4">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-clipboard-list text-blue-500"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate">
                                                <?php echo htmlspecialchars($tarea['titulo']); ?>
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($tarea['materia_nombre']); ?>
                                            </p>
                                        </div>
                                        <div>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                Vence: <?php echo date('d/m/Y', strtotime($tarea['fecha_entrega'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                                <?php if (empty($proximasTareas)): ?>
                                <li class="py-4">
                                    <p class="text-sm text-gray-500 text-center">No hay tareas próximas</p>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Próximas clases -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Próximas Clases
                    </h3>
                    <div class="mt-5">
                        <div class="flow-root">
                            <ul class="-my-4 divide-y divide-gray-200">
                                <?php foreach ($proximasClases as $clase): ?>
                                <li class="py-4">
                                    <div class="flex items-center space-x-4">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-video text-green-500"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate">
                                                <?php echo htmlspecialchars($clase['topic']); ?>
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($clase['materia_nombre']); ?>
                                            </p>
                                        </div>
                                        <div>
                                            <a href="<?php echo htmlspecialchars($clase['join_url']); ?>" target="_blank"
                                               class="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                Unirse
                                            </a>
                                        </div>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                                <?php if (empty($proximasClases)): ?>
                                <li class="py-4">
                                    <p class="text-sm text-gray-500 text-center">No hay clases programadas</p>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Materias -->
        <div class="mt-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                Mis Materias
            </h3>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($materias as $materia): ?>
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="p-5">
                        <h4 class="text-xl font-semibold text-gray-900">
                            <?php echo htmlspecialchars($materia['nombre']); ?>
                        </h4>
                        <p class="mt-1 text-sm text-gray-500">
                            <?php echo htmlspecialchars($materia['descripcion']); ?>
                        </p>
                        <div class="mt-4">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Progreso</span>
                                <span class="text-gray-900 font-medium">
                                    <?php
                                    $progreso = $materia['total_tareas'] > 0 
                                        ? round(($materia['tareas_entregadas'] / $materia['total_tareas']) * 100) 
                                        : 0;
                                    echo $progreso . '%';
                                    ?>
                                </span>
                            </div>
                            <div class="mt-1">
                                <div class="bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 rounded-full h-2" style="width: <?php echo $progreso; ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 flex justify-between items-center">
                            <div class="flex space-x-4 text-sm text-gray-500">
                                <span>
                                    <i class="fas fa-tasks"></i>
                                    <?php echo $materia['tareas_entregadas']; ?>/<?php echo $materia['total_tareas']; ?> tareas
                                </span>
                                <span>
                                    <i class="fas fa-file-alt"></i>
                                    <?php echo $materia['total_examenes']; ?> exámenes
                                </span>
                            </div>
                            <a href="materia.php?id=<?php echo $materia['id']; ?>" 
                               class="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Ver detalles
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
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
    </script>
</body>
</html>
