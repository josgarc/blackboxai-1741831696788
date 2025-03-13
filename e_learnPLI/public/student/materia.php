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

// Obtener ID de la materia
$materiaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$materiaId) {
    header('Location: dashboard.php');
    exit;
}

// Verificar que el estudiante esté inscrito en la materia
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM estudiantes_materias 
        WHERE user_id = ? AND materia_id = ? AND estado = 'inscrito'
    ");
    $stmt->execute([$_SESSION['user_id'], $materiaId]);
    if (!$stmt->fetchColumn()) {
        header('Location: dashboard.php');
        exit;
    }

    // Obtener información de la materia
    $stmt = $pdo->prepare("
        SELECT m.*, u.full_Name as profesor_nombre
        FROM materias m
        LEFT JOIN maestros_materias mm ON m.id = mm.materia_id
        LEFT JOIN users u ON mm.user_id = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$materiaId]);
    $materia = $stmt->fetch();

    // Obtener contenidos de la materia
    $stmt = $pdo->prepare("
        SELECT * FROM contenidos 
        WHERE materia_id = ? AND estado = 'publicado'
        ORDER BY orden ASC
    ");
    $stmt->execute([$materiaId]);
    $contenidos = $stmt->fetchAll();

    // Obtener tareas
    $stmt = $pdo->prepare("
        SELECT t.*, 
               CASE WHEN et.id IS NOT NULL THEN 1 ELSE 0 END as entregada,
               et.calificacion,
               et.fecha_entrega as fecha_entregada
        FROM tareas t
        LEFT JOIN entregas_tareas et ON t.id = et.tarea_id AND et.user_id = ?
        WHERE t.materia_id = ? AND t.estado = 'publicada'
        ORDER BY t.fecha_entrega ASC
    ");
    $stmt->execute([$_SESSION['user_id'], $materiaId]);
    $tareas = $stmt->fetchAll();

    // Obtener exámenes
    $stmt = $pdo->prepare("
        SELECT e.*,
               CASE WHEN re.id IS NOT NULL THEN 1 ELSE 0 END as realizado,
               re.puntaje_obtenido
        FROM examenes e
        LEFT JOIN respuestas_examenes re ON e.id = re.examen_id AND re.user_id = ?
        WHERE e.materia_id = ? AND e.estado = 'publicado'
        ORDER BY e.fecha_inicio ASC
    ");
    $stmt->execute([$_SESSION['user_id'], $materiaId]);
    $examenes = $stmt->fetchAll();

    // Obtener próximas clases de Zoom
    $stmt = $pdo->prepare("
        SELECT * FROM zoom_meetings
        WHERE materia_id = ? AND estado = 'programada' AND start_time > NOW()
        ORDER BY start_time ASC
    ");
    $stmt->execute([$materiaId]);
    $proximasClases = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Error en materia.php: " . $e->getMessage());
    header('Location: dashboard.php');
    exit;
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
        <!-- Encabezado de la materia -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <h1 class="text-3xl font-bold text-gray-900">
                    <?php echo htmlspecialchars($materia['nombre']); ?>
                </h1>
                <p class="mt-1 text-sm text-gray-500">
                    Profesor: <?php echo htmlspecialchars($materia['profesor_nombre']); ?>
                </p>
                <p class="mt-2 text-gray-600">
                    <?php echo htmlspecialchars($materia['descripcion']); ?>
                </p>
            </div>
        </div>

        <!-- Contenido principal -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Columna principal -->
            <div class="lg:col-span-2">
                <!-- Contenidos -->
                <div class="bg-white shadow rounded-lg mb-6">
                    <div class="px-4 py-5 sm:p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">
                            Contenidos del Curso
                        </h2>
                        <div class="space-y-4">
                            <?php foreach ($contenidos as $contenido): ?>
                            <div class="border rounded-lg p-4">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <?php
                                        $icon = 'file-alt';
                                        switch ($contenido['tipo']) {
                                            case 'pdf':
                                                $icon = 'file-pdf';
                                                break;
                                            case 'video':
                                                $icon = 'video';
                                                break;
                                            case 'link_zoom':
                                                $icon = 'video-camera';
                                                break;
                                        }
                                        ?>
                                        <i class="fas fa-<?php echo $icon; ?> text-2xl text-blue-500"></i>
                                    </div>
                                    <div class="ml-4 flex-1">
                                        <h3 class="text-lg font-medium text-gray-900">
                                            <?php echo htmlspecialchars($contenido['titulo']); ?>
                                        </h3>
                                        <p class="mt-1 text-sm text-gray-500">
                                            <?php echo htmlspecialchars($contenido['descripcion']); ?>
                                        </p>
                                        <div class="mt-3">
                                            <?php if ($contenido['tipo'] === 'pdf' || $contenido['tipo'] === 'video'): ?>
                                                <a href="ver-contenido.php?id=<?php echo $contenido['id']; ?>" 
                                                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                                    Ver contenido
                                                </a>
                                            <?php elseif ($contenido['tipo'] === 'link_zoom'): ?>
                                                <a href="<?php echo htmlspecialchars($contenido['url']); ?>" 
                                                   target="_blank"
                                                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                                    Unirse a la clase
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($contenidos)): ?>
                            <p class="text-gray-500 text-center py-4">
                                No hay contenidos disponibles.
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tareas -->
                <div class="bg-white shadow rounded-lg mb-6">
                    <div class="px-4 py-5 sm:p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">
                            Tareas
                        </h2>
                        <div class="space-y-4">
                            <?php foreach ($tareas as $tarea): ?>
                            <div class="border rounded-lg p-4">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-tasks text-2xl text-yellow-500"></i>
                                    </div>
                                    <div class="ml-4 flex-1">
                                        <div class="flex justify-between">
                                            <h3 class="text-lg font-medium text-gray-900">
                                                <?php echo htmlspecialchars($tarea['titulo']); ?>
                                            </h3>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $tarea['entregada'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                <?php echo $tarea['entregada'] ? 'Entregada' : 'Pendiente'; ?>
                                            </span>
                                        </div>
                                        <p class="mt-1 text-sm text-gray-500">
                                            <?php echo htmlspecialchars($tarea['descripcion']); ?>
                                        </p>
                                        <div class="mt-2 text-sm text-gray-500">
                                            <span class="font-medium">Fecha de entrega:</span>
                                            <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_entrega'])); ?>
                                        </div>
                                        <?php if ($tarea['entregada']): ?>
                                            <div class="mt-2">
                                                <span class="font-medium">Calificación:</span>
                                                <?php echo $tarea['calificacion'] !== null ? $tarea['calificacion'] : 'Pendiente de calificar'; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="mt-3">
                                            <?php if (!$tarea['entregada']): ?>
                                                <a href="entregar-tarea.php?id=<?php echo $tarea['id']; ?>" 
                                                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                                    Entregar tarea
                                                </a>
                                            <?php else: ?>
                                                <a href="ver-entrega.php?id=<?php echo $tarea['id']; ?>" 
                                                   class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                                    Ver entrega
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($tareas)): ?>
                            <p class="text-gray-500 text-center py-4">
                                No hay tareas asignadas.
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Exámenes -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">
                            Exámenes
                        </h2>
                        <div class="space-y-4">
                            <?php foreach ($examenes as $examen): ?>
                            <div class="border rounded-lg p-4">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-file-alt text-2xl text-red-500"></i>
                                    </div>
                                    <div class="ml-4 flex-1">
                                        <div class="flex justify-between">
                                            <h3 class="text-lg font-medium text-gray-900">
                                                <?php echo htmlspecialchars($examen['titulo']); ?>
                                            </h3>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $examen['realizado'] ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                <?php echo $examen['realizado'] ? 'Completado' : 'Disponible'; ?>
                                            </span>
                                        </div>
                                        <p class="mt-1 text-sm text-gray-500">
                                            <?php echo htmlspecialchars($examen['descripcion']); ?>
                                        </p>
                                        <div class="mt-2 text-sm text-gray-500">
                                            <span class="font-medium">Disponible desde:</span>
                                            <?php echo date('d/m/Y H:i', strtotime($examen['fecha_inicio'])); ?>
                                            <br>
                                            <span class="font-medium">Hasta:</span>
                                            <?php echo date('d/m/Y H:i', strtotime($examen['fecha_fin'])); ?>
                                        </div>
                                        <?php if ($examen['realizado']): ?>
                                            <div class="mt-2">
                                                <span class="font-medium">Calificación:</span>
                                                <?php echo $examen['puntaje_obtenido']; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="mt-3">
                                            <?php if (!$examen['realizado']): ?>
                                                <a href="realizar-examen.php?id=<?php echo $examen['id']; ?>" 
                                                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                                    Realizar examen
                                                </a>
                                            <?php else: ?>
                                                <a href="ver-examen.php?id=<?php echo $examen['id']; ?>" 
                                                   class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                                    Ver resultados
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($examenes)): ?>
                            <p class="text-gray-500 text-center py-4">
                                No hay exámenes disponibles.
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Barra lateral -->
            <div class="lg:col-span-1">
                <!-- Próximas clases -->
                <div class="bg-white shadow rounded-lg mb-6">
                    <div class="px-4 py-5 sm:p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">
                            Próximas Clases
                        </h2>
                        <div class="space-y-4">
                            <?php foreach ($proximasClases as $clase): ?>
                            <div class="border rounded-lg p-4">
                                <h3 class="font-medium text-gray-900">
                                    <?php echo htmlspecialchars($clase['topic']); ?>
                                </h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    <?php echo date('d/m/Y H:i', strtotime($clase['start_time'])); ?>
                                </p>
                                <div class="mt-3">
                                    <a href="<?php echo htmlspecialchars($clase['join_url']); ?>" 
                                       target="_blank"
                                       class="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                        Unirse
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($proximasClases)): ?>
                            <p class="text-gray-500 text-center">
                                No hay clases programadas
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recursos adicionales -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">
                            Recursos Adicionales
                        </h2>
                        <ul class="space-y-3">
                            <li>
                                <a href="#" class="flex items-center text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-book mr-2"></i>
                                    Biblioteca Virtual
                                </a>
                            </li>
                            <li>
                                <a href="#" class="flex items-center text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-question-circle mr-2"></i>
                                    Preguntas Frecuentes
                                </a>
                            </li>
                            <li>
                                <a href="#" class="flex items-center text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-comments mr-2"></i>
                                    Foro de Discusión
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
