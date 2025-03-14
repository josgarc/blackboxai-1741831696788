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

$error = '';
$success = '';
$materiaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    // Verificar que la materia existe y pertenece al profesor
    $stmt = $pdo->prepare("
        SELECT m.*,
               (SELECT COUNT(*) FROM estudiantes_materias WHERE materia_id = m.id AND estado = 'inscrito') as total_estudiantes
        FROM materias m
        INNER JOIN maestros_materias mm ON m.id = mm.materia_id
        WHERE m.id = ? AND mm.user_id = ?
    ");
    $stmt->execute([$materiaId, $_SESSION['user_id']]);
    $materia = $stmt->fetch();

    if (!$materia) {
        header('Location: dashboard.php');
        exit;
    }

    // Obtener estudiantes inscritos
    $stmt = $pdo->prepare("
        SELECT u.*, 
               (SELECT COUNT(*) FROM entregas_tareas et 
                INNER JOIN tareas t ON et.tarea_id = t.id 
                WHERE t.materia_id = ? AND et.user_id = u.id) as tareas_entregadas,
               (SELECT AVG(calificacion) FROM entregas_tareas et 
                INNER JOIN tareas t ON et.tarea_id = t.id 
                WHERE t.materia_id = ? AND et.user_id = u.id AND et.calificacion IS NOT NULL) as promedio_tareas,
               (SELECT AVG(puntaje_obtenido) FROM respuestas_examenes re 
                INNER JOIN examenes e ON re.examen_id = e.id 
                WHERE e.materia_id = ? AND re.user_id = u.id) as promedio_examenes
        FROM users u
        INNER JOIN estudiantes_materias em ON u.id = em.user_id
        WHERE em.materia_id = ? AND em.estado = 'inscrito'
        ORDER BY u.full_Name
    ");
    $stmt->execute([$materiaId, $materiaId, $materiaId, $materiaId]);
    $estudiantes = $stmt->fetchAll();

    // Obtener tareas
    $stmt = $pdo->prepare("
        SELECT t.*,
               (SELECT COUNT(*) FROM entregas_tareas WHERE tarea_id = t.id) as total_entregas,
               (SELECT COUNT(*) FROM entregas_tareas WHERE tarea_id = t.id AND calificacion IS NOT NULL) as total_calificadas
        FROM tareas t
        WHERE t.materia_id = ?
        ORDER BY t.fecha_entrega DESC
    ");
    $stmt->execute([$materiaId]);
    $tareas = $stmt->fetchAll();

    // Obtener exámenes
    $stmt = $pdo->prepare("
        SELECT e.*,
               (SELECT COUNT(*) FROM respuestas_examenes WHERE examen_id = e.id) as total_realizados,
               (SELECT AVG(puntaje_obtenido) FROM respuestas_examenes WHERE examen_id = e.id) as promedio
        FROM examenes e
        WHERE e.materia_id = ?
        ORDER BY e.fecha_inicio DESC
    ");
    $stmt->execute([$materiaId]);
    $examenes = $stmt->fetchAll();

    // Obtener clases programadas
    $stmt = $pdo->prepare("
        SELECT * FROM zoom_meetings
        WHERE materia_id = ?
        AND start_time >= NOW()
        ORDER BY start_time ASC
    ");
    $stmt->execute([$materiaId]);
    $clases = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Error en materia.php (profesor): " . $e->getMessage());
    $error = "Error al cargar la información de la materia.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($materia['nombre']); ?> - E-Learning PLI</title>
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
                <div class="flex items-center">
                    <span class="text-gray-700"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
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

        <!-- Encabezado de la materia -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">
                            <?php echo htmlspecialchars($materia['nombre']); ?>
                        </h1>
                        <p class="mt-1 text-sm text-gray-500">
                            Código: <?php echo htmlspecialchars($materia['codigo']); ?>
                        </p>
                        <p class="mt-2 text-gray-600">
                            <?php echo htmlspecialchars($materia['descripcion']); ?>
                        </p>
                    </div>
                    <div class="flex space-x-3">
                        <a href="contenido.php?id=<?php echo $materiaId; ?>" 
                           class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                            <i class="fas fa-book mr-2"></i>
                            Gestionar Contenido
                        </a>
                        <a href="programar-clase.php?id=<?php echo $materiaId; ?>" 
                           class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                            <i class="fas fa-video mr-2"></i>
                            Programar Clase
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas rápidas -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-users fa-2x text-blue-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">
                                    Estudiantes
                                </dt>
                                <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                    <?php echo $materia['total_estudiantes']; ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-tasks fa-2x text-green-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">
                                    Tareas
                                </dt>
                                <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                    <?php echo count($tareas); ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-file-alt fa-2x text-yellow-500"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">
                                    Exámenes
                                </dt>
                                <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                    <?php echo count($examenes); ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

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
                                    <?php echo count($clases); ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contenido principal -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Lista de estudiantes -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-medium text-gray-900">
                            Estudiantes Inscritos
                        </h2>
                        <a href="estudiantes.php?id=<?php echo $materiaId; ?>" 
                           class="text-sm font-medium text-blue-600 hover:text-blue-500">
                            Ver todos
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Estudiante
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Tareas
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Promedio
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($estudiantes as $estudiante): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <img class="h-10 w-10 rounded-full" 
                                                     src="https://ui-avatars.com/api/?name=<?php echo urlencode($estudiante['full_Name']); ?>&background=random" 
                                                     alt="">
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($estudiante['full_Name']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($estudiante['email']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-900">
                                            <?php echo $estudiante['tareas_entregadas']; ?> entregadas
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php 
                                        $promedio = ($estudiante['promedio_tareas'] + $estudiante['promedio_examenes']) / 2;
                                        $colorClass = $promedio >= 70 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $colorClass; ?>">
                                            <?php echo number_format($promedio, 1); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Actividades y evaluaciones -->
            <div class="space-y-6">
                <!-- Tareas -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-medium text-gray-900">
                                Tareas
                            </h2>
                            <a href="nueva-tarea.php?id=<?php echo $materiaId; ?>" 
                               class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                <i class="fas fa-plus mr-2"></i>
                                Nueva Tarea
                            </a>
                        </div>
                        <div class="space-y-4">
                            <?php foreach ($tareas as $tarea): ?>
                            <div class="border rounded-lg p-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($tarea['titulo']); ?>
                                        </h3>
                                        <p class="mt-1 text-xs text-gray-500">
                                            Fecha límite: <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_entrega'])); ?>
                                        </p>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-sm text-gray-500">
                                            <?php echo $tarea['total_calificadas']; ?>/<?php echo $tarea['total_entregas']; ?> calificadas
                                        </span>
                                        <a href="revisar-entregas.php?id=<?php echo $tarea['id']; ?>" 
                                           class="inline-flex items-center px-2.5 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                                            Revisar
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Exámenes -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-medium text-gray-900">
                                Exámenes
                            </h2>
                            <a href="nuevo-examen.php?id=<?php echo $materiaId; ?>" 
                               class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                <i class="fas fa-plus mr-2"></i>
                                Nuevo Examen
                            </a>
                        </div>
                        <div class="space-y-4">
                            <?php foreach ($examenes as $examen): ?>
                            <div class="border rounded-lg p-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($examen['titulo']); ?>
                                        </h3>
                                        <p class="mt-1 text-xs text-gray-500">
                                            Disponible: <?php echo date('d/m/Y H:i', strtotime($examen['fecha_inicio'])); ?> - 
                                            <?php echo date('d/m/Y H:i', strtotime($examen['fecha_fin'])); ?>
                                        </p>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-sm text-gray-500">
                                            Promedio: <?php echo number_format($examen['promedio'], 1); ?>
                                        </span>
                                        <a href="resultados-examen.php?id=<?php echo $examen['id']; ?>" 
                                           class="inline-flex items-center px-2.5 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                                            Ver resultados
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Clases programadas -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-medium text-gray-900">
                                Clases Programadas
                            </h2>
                            <a href="programar-clase.php?id=<?php echo $materiaId; ?>" 
                               class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                <i class="fas fa-plus mr-2"></i>
                                Programar Clase
                            </a>
                        </div>
                        <div class="space-y-4">
                            <?php foreach ($clases as $clase): ?>
                            <div class="border rounded-lg p-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($clase['topic']); ?>
                                        </h3>
                                        <p class="mt-1 text-xs text-gray-500">
                                            <?php echo date('d/m/Y H:i', strtotime($clase['start_time'])); ?>
                                        </p>
                                    </div>
                                    <div>
                                        <a href="<?php echo htmlspecialchars($clase['start_url']); ?>" target="_blank"
                                           class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-blue-600 hover:bg-blue-700">
                                            Iniciar clase
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
