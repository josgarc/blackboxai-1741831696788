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
        SELECT m.*
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

    // Procesar acciones
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $accion = $_POST['accion'] ?? '';
        $estudianteId = (int)($_POST['estudiante_id'] ?? 0);

        switch ($accion) {
            case 'dar_baja':
                $stmt = $pdo->prepare("
                    UPDATE estudiantes_materias 
                    SET estado = 'baja', fecha_baja = NOW()
                    WHERE materia_id = ? AND user_id = ?
                ");
                $stmt->execute([$materiaId, $estudianteId]);

                // Notificar al estudiante
                $stmt = $pdo->prepare("SELECT email, full_Name FROM users WHERE id = ?");
                $stmt->execute([$estudianteId]);
                $estudiante = $stmt->fetch();

                $emailService = EmailService::getInstance();
                $emailService->sendCourseUnenrollmentNotification(
                    $estudiante['email'],
                    [
                        'nombre' => $estudiante['full_Name'],
                        'materia' => $materia['nombre']
                    ]
                );

                $success = 'Estudiante dado de baja correctamente.';
                break;

            case 'reactivar':
                $stmt = $pdo->prepare("
                    UPDATE estudiantes_materias 
                    SET estado = 'inscrito', fecha_baja = NULL
                    WHERE materia_id = ? AND user_id = ?
                ");
                $stmt->execute([$materiaId, $estudianteId]);

                // Notificar al estudiante
                $stmt = $pdo->prepare("SELECT email, full_Name FROM users WHERE id = ?");
                $stmt->execute([$estudianteId]);
                $estudiante = $stmt->fetch();

                $emailService = EmailService::getInstance();
                $emailService->sendCourseReactivationNotification(
                    $estudiante['email'],
                    [
                        'nombre' => $estudiante['full_Name'],
                        'materia' => $materia['nombre']
                    ]
                );

                $success = 'Estudiante reactivado correctamente.';
                break;
        }
    }

    // Obtener lista de estudiantes
    $stmt = $pdo->prepare("
        SELECT 
            u.id, u.full_Name, u.email, u.created_at,
            em.estado, em.fecha_inscripcion, em.fecha_baja,
            (SELECT COUNT(*) FROM entregas_tareas et 
             INNER JOIN tareas t ON et.tarea_id = t.id 
             WHERE t.materia_id = ? AND et.user_id = u.id) as total_entregas,
            (SELECT COUNT(*) FROM respuestas_examenes re 
             INNER JOIN examenes e ON re.examen_id = e.id 
             WHERE e.materia_id = ? AND re.user_id = u.id) as total_examenes,
            (SELECT AVG(et.calificacion) FROM entregas_tareas et 
             INNER JOIN tareas t ON et.tarea_id = t.id 
             WHERE t.materia_id = ? AND et.user_id = u.id AND et.calificacion IS NOT NULL) as promedio_tareas,
            (SELECT AVG(re.puntaje_obtenido) FROM respuestas_examenes re 
             INNER JOIN examenes e ON re.examen_id = e.id 
             WHERE e.materia_id = ? AND re.user_id = u.id) as promedio_examenes
        FROM users u
        INNER JOIN estudiantes_materias em ON u.id = em.user_id
        WHERE em.materia_id = ?
        ORDER BY em.estado DESC, u.full_Name ASC
    ");
    $stmt->execute([$materiaId, $materiaId, $materiaId, $materiaId, $materiaId]);
    $estudiantes = $stmt->fetchAll();

    // Estadísticas
    $totalEstudiantes = count($estudiantes);
    $estudiantesActivos = array_filter($estudiantes, fn($e) => $e['estado'] === 'inscrito');
    $totalActivos = count($estudiantesActivos);
    $promedioGeneral = array_reduce($estudiantes, function($carry, $e) {
        $promedio = ($e['promedio_tareas'] + $e['promedio_examenes']) / 2;
        return $carry + ($promedio ?: 0);
    }, 0) / ($totalEstudiantes ?: 1);

} catch (Exception $e) {
    error_log("Error en estudiantes.php: " . $e->getMessage());
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estudiantes - <?php echo htmlspecialchars($materia['nombre']); ?></title>
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
                        <a href="materia.php?id=<?php echo $materiaId; ?>" class="text-gray-700">
                            <i class="fas fa-arrow-left mr-2"></i> Volver a la Materia
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

        <!-- Estadísticas -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 mb-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">
                        Total de Estudiantes
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900">
                        <?php echo $totalEstudiantes; ?>
                    </dd>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">
                        Estudiantes Activos
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900">
                        <?php echo $totalActivos; ?>
                    </dd>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">
                        Promedio General
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900">
                        <?php echo number_format($promedioGeneral, 1); ?>
                    </dd>
                </div>
            </div>
        </div>

        <!-- Lista de estudiantes -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    Estudiantes Inscritos
                </h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estudiante
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estado
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actividades
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Promedio
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Acciones
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
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $estudiante['estado'] === 'inscrito' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo ucfirst($estudiante['estado']); ?>
                                    </span>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <?php if ($estudiante['estado'] === 'inscrito'): ?>
                                            Desde: <?php echo date('d/m/Y', strtotime($estudiante['fecha_inscripcion'])); ?>
                                        <?php else: ?>
                                            Baja: <?php echo date('d/m/Y', strtotime($estudiante['fecha_baja'])); ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo $estudiante['total_entregas']; ?> tareas entregadas
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo $estudiante['total_examenes']; ?> exámenes realizados
                                    </div>
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
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <?php if ($estudiante['estado'] === 'inscrito'): ?>
                                        <form action="" method="POST" class="inline" onsubmit="return confirm('¿Estás seguro de dar de baja a este estudiante?');">
                                            <input type="hidden" name="accion" value="dar_baja">
                                            <input type="hidden" name="estudiante_id" value="<?php echo $estudiante['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-user-minus"></i> Dar de baja
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form action="" method="POST" class="inline">
                                            <input type="hidden" name="accion" value="reactivar">
                                            <input type="hidden" name="estudiante_id" value="<?php echo $estudiante['id']; ?>">
                                            <button type="submit" class="text-green-600 hover:text-green-900">
                                                <i class="fas fa-user-plus"></i> Reactivar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($estudiantes)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No hay estudiantes inscritos en esta materia
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
