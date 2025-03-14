<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/course_manager.php';

$auth = Auth::getInstance();
$courseManager = CourseManager::getInstance();

// Verificar autenticación y rol de profesor
if (!$auth->isAuthenticated() || !$auth->hasRole('Maestro')) {
    header('Location: ../login.php');
    exit;
}

// Obtener información del profesor
$userId = $_SESSION['user_id'];
$user = $auth->getUserById($userId);

try {
    // Obtener materias asignadas al profesor
    $stmt = $pdo->prepare("
        SELECT m.*, 
               (SELECT COUNT(*) FROM estudiantes_materias WHERE materia_id = m.id AND estado = 'inscrito') as total_estudiantes,
               (SELECT COUNT(*) FROM tareas WHERE materia_id = m.id) as total_tareas,
               (SELECT COUNT(*) FROM examenes WHERE materia_id = m.id) as total_examenes,
               (SELECT COUNT(*) FROM entregas_tareas et 
                INNER JOIN tareas t ON et.tarea_id = t.id 
                WHERE t.materia_id = m.id AND et.estado = 'entregado') as tareas_pendientes,
               (SELECT COUNT(*) FROM zoom_meetings WHERE materia_id = m.id AND estado = 'programada') as clases_programadas
        FROM materias m
        INNER JOIN maestros_materias mm ON m.id = mm.materia_id
        WHERE mm.user_id = ?
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$userId]);
    $materias = $stmt->fetchAll();

    // Obtener próximas clases
    $stmt = $pdo->prepare("
        SELECT zm.*, m.nombre as materia_nombre
        FROM zoom_meetings zm
        INNER JOIN materias m ON zm.materia_id = m.id
        INNER JOIN maestros_materias mm ON m.id = mm.materia_id
        WHERE mm.user_id = ?
        AND zm.start_time > NOW()
        AND zm.estado = 'programada'
        ORDER BY zm.start_time ASC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $proximasClases = $stmt->fetchAll();

    // Obtener últimas entregas pendientes
    $stmt = $pdo->prepare("
        SELECT et.*, t.titulo as tarea_titulo, m.nombre as materia_nombre, u.full_Name as estudiante_nombre
        FROM entregas_tareas et
        INNER JOIN tareas t ON et.tarea_id = t.id
        INNER JOIN materias m ON t.materia_id = m.id
        INNER JOIN users u ON et.user_id = u.id
        INNER JOIN maestros_materias mm ON m.id = mm.materia_id
        WHERE mm.user_id = ? AND et.estado = 'entregado'
        ORDER BY et.fecha_entrega DESC
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $ultimasEntregas = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Error en dashboard profesor: " . $e->getMessage());
    $error = "Ha ocurrido un error al cargar la información.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Profesor - E-Learning PLI</title>
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
                Panel de control del profesor
            </p>
        </div>

        <!-- Estadísticas generales -->
        <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Materias asignadas -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-book fa-2x text-blue-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">
                                    Materias Asignadas
                                </dt>
                                <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                    <?php echo count($materias); ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total estudiantes -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-users fa-2x text-green-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">
                                    Total Estudiantes
                                </dt>
                                <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                    <?php 
                                    $totalEstudiantes = array_sum(array_column($materias, 'total_estudiantes'));
                                    echo $totalEstudiantes;
                                    ?>
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
                                    Tareas por Calificar
                                </dt>
                                <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                    <?php 
                                    $totalTareasPendientes = array_sum(array_column($materias, 'tareas_pendientes'));
                                    echo $totalTareasPendientes;
                                    ?>
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
                            <i class="fas fa-video fa-2x text-purple-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">
                                    Clases Programadas
                                </dt>
                                <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                    <?php echo count($proximasClases); ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contenido principal -->
        <div class="mt-6 grid grid-cols-1 gap-5 lg:grid-cols-2">
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
                                            <i class="fas fa-video text-purple-500"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate">
                                                <?php echo htmlspecialchars($clase['topic']); ?>
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($clase['materia_nombre']); ?>
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                <?php echo date('d/m/Y H:i', strtotime($clase['start_time'])); ?>
                                            </p>
                                        </div>
                                        <div>
                                            <a href="<?php echo htmlspecialchars($clase['start_url']); ?>" target="_blank"
                                               class="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                                Iniciar
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
                        <div class="mt-6">
                            <a href="programar-clase.php" class="text-sm font-medium text-blue-600 hover:text-blue-500">
                                Programar nueva clase <span aria-hidden="true">&rarr;</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Últimas entregas -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Últimas Entregas
                    </h3>
                    <div class="mt-5">
                        <div class="flow-root">
                            <ul class="-my-4 divide-y divide-gray-200">
                                <?php foreach ($ultimasEntregas as $entrega): ?>
                                <li class="py-4">
                                    <div class="flex items-center space-x-4">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-file-alt text-blue-500"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate">
                                                <?php echo htmlspecialchars($entrega['tarea_titulo']); ?>
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($entrega['estudiante_nombre']); ?> - 
                                                <?php echo htmlspecialchars($entrega['materia_nombre']); ?>
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                Entregado: <?php echo date('d/m/Y H:i', strtotime($entrega['fecha_entrega'])); ?>
                                            </p>
                                        </div>
                                        <div>
                                            <a href="calificar-entrega.php?id=<?php echo $entrega['id']; ?>" 
                                               class="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                                                Calificar
                                            </a>
                                        </div>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                                <?php if (empty($ultimasEntregas)): ?>
                                <li class="py-4">
                                    <p class="text-sm text-gray-500 text-center">No hay entregas pendientes</p>
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
                            Código: <?php echo htmlspecialchars($materia['codigo']); ?>
                        </p>
                        <div class="mt-4 flex justify-between text-sm text-gray-500">
                            <span>
                                <i class="fas fa-users"></i>
                                <?php echo $materia['total_estudiantes']; ?> estudiantes
                            </span>
                            <span>
                                <i class="fas fa-tasks"></i>
                                <?php echo $materia['total_tareas']; ?> tareas
                            </span>
                            <span>
                                <i class="fas fa-file-alt"></i>
                                <?php echo $materia['total_examenes']; ?> exámenes
                            </span>
                        </div>
                        <div class="mt-4 flex justify-between">
                            <a href="materia.php?id=<?php echo $materia['id']; ?>" 
                               class="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200">
                                Ver detalles
                            </a>
                            <a href="contenido.php?id=<?php echo $materia['id']; ?>" 
                               class="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-4 font-medium rounded-md text-green-700 bg-green-100 hover:bg-green-200">
                                Gestionar contenido
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
