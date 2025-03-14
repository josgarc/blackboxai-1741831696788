<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/file_manager.php';

$auth = Auth::getInstance();
$fileManager = FileManager::getInstance();

// Verificar autenticación y rol de profesor
if (!$auth->isAuthenticated() || !$auth->hasRole('Maestro')) {
    header('Location: ../login.php');
    exit;
}

$error = '';
$success = '';
$tareaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    // Verificar que la tarea existe y pertenece a una materia del profesor
    $stmt = $pdo->prepare("
        SELECT t.*, m.nombre as materia_nombre, m.id as materia_id
        FROM tareas t
        INNER JOIN materias m ON t.materia_id = m.id
        INNER JOIN maestros_materias mm ON m.id = mm.materia_id
        WHERE t.id = ? AND mm.user_id = ?
    ");
    $stmt->execute([$tareaId, $_SESSION['user_id']]);
    $tarea = $stmt->fetch();

    if (!$tarea) {
        header('Location: dashboard.php');
        exit;
    }

    // Procesar calificación
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $entregaId = (int)$_POST['entrega_id'];
        $calificacion = (float)$_POST['calificacion'];
        $retroalimentacion = trim($_POST['retroalimentacion']);

        if ($calificacion < 0 || $calificacion > $tarea['puntaje_maximo']) {
            throw new Exception('La calificación debe estar entre 0 y ' . $tarea['puntaje_maximo']);
        }

        // Actualizar calificación
        $stmt = $pdo->prepare("
            UPDATE entregas_tareas 
            SET calificacion = ?, retroalimentacion = ?, fecha_calificacion = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$calificacion, $retroalimentacion, $entregaId]);

        // Obtener información del estudiante para notificación
        $stmt = $pdo->prepare("
            SELECT u.email, u.full_Name
            FROM entregas_tareas et
            INNER JOIN users u ON et.user_id = u.id
            WHERE et.id = ?
        ");
        $stmt->execute([$entregaId]);
        $estudiante = $stmt->fetch();

        // Enviar notificación por correo
        $emailService = EmailService::getInstance();
        $emailService->sendGradeNotification(
            $estudiante['email'],
            [
                'nombre' => $estudiante['full_Name'],
                'materia' => $tarea['materia_nombre'],
                'tarea' => $tarea['titulo'],
                'calificacion' => $calificacion,
                'retroalimentacion' => $retroalimentacion
            ]
        );

        $success = 'Calificación guardada correctamente.';
    }

    // Obtener entregas
    $stmt = $pdo->prepare("
        SELECT et.*, u.full_Name as estudiante_nombre, u.email as estudiante_email
        FROM entregas_tareas et
        INNER JOIN users u ON et.user_id = u.id
        WHERE et.tarea_id = ?
        ORDER BY et.fecha_entrega DESC
    ");
    $stmt->execute([$tareaId]);
    $entregas = $stmt->fetchAll();

    // Estadísticas
    $totalEntregas = count($entregas);
    $entregasCalificadas = array_filter($entregas, fn($e) => $e['calificacion'] !== null);
    $totalCalificadas = count($entregasCalificadas);
    $promedioCalificaciones = $totalCalificadas > 0 
        ? array_sum(array_column($entregasCalificadas, 'calificacion')) / $totalCalificadas 
        : 0;

} catch (Exception $e) {
    error_log("Error en revisar-entregas.php: " . $e->getMessage());
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revisar Entregas - <?php echo htmlspecialchars($tarea['titulo']); ?></title>
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
                        <a href="materia.php?id=<?php echo $tarea['materia_id']; ?>" class="text-gray-700">
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

        <!-- Información de la tarea -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <h2 class="text-2xl font-bold text-gray-900">
                    <?php echo htmlspecialchars($tarea['titulo']); ?>
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    <?php echo htmlspecialchars($tarea['materia_nombre']); ?>
                </p>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <span class="block text-sm font-medium text-gray-500">Fecha de entrega</span>
                        <span class="block mt-1 text-sm text-gray-900">
                            <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_entrega'])); ?>
                        </span>
                    </div>
                    <div>
                        <span class="block text-sm font-medium text-gray-500">Puntaje máximo</span>
                        <span class="block mt-1 text-sm text-gray-900">
                            <?php echo $tarea['puntaje_maximo']; ?> puntos
                        </span>
                    </div>
                    <div>
                        <span class="block text-sm font-medium text-gray-500">Estado</span>
                        <span class="block mt-1">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                <?php echo ucfirst($tarea['estado']); ?>
                            </span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 mb-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">
                        Total de Entregas
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900">
                        <?php echo $totalEntregas; ?>
                    </dd>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">
                        Entregas Calificadas
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900">
                        <?php echo $totalCalificadas; ?>/<?php echo $totalEntregas; ?>
                    </dd>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">
                        Promedio de Calificaciones
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900">
                        <?php echo number_format($promedioCalificaciones, 1); ?>
                    </dd>
                </div>
            </div>
        </div>

        <!-- Lista de entregas -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                    Entregas de los Estudiantes
                </h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estudiante
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Fecha de Entrega
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estado
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Calificación
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($entregas as $entrega): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <img class="h-10 w-10 rounded-full" 
                                                src="https://ui-avatars.com/api/?name=<?php echo urlencode($entrega['estudiante_nombre']); ?>&background=random" 
                                                alt="">
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($entrega['estudiante_nombre']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($entrega['estudiante_email']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo date('d/m/Y H:i', strtotime($entrega['fecha_entrega'])); ?>
                                    </div>
                                    <?php if (strtotime($entrega['fecha_entrega']) > strtotime($tarea['fecha_entrega'])): ?>
                                    <div class="text-xs text-red-500">
                                        Entrega tardía
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $entrega['calificacion'] !== null ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo $entrega['calificacion'] !== null ? 'Calificado' : 'Por calificar'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($entrega['calificacion'] !== null): ?>
                                        <?php echo $entrega['calificacion']; ?>/<?php echo $tarea['puntaje_maximo']; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <?php if ($entrega['archivo']): ?>
                                    <a href="<?php echo htmlspecialchars($entrega['archivo']); ?>" target="_blank"
                                       class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <?php endif; ?>
                                    <button type="button" 
                                            onclick="mostrarFormularioCalificacion(<?php echo htmlspecialchars(json_encode($entrega)); ?>)"
                                            class="text-indigo-600 hover:text-indigo-900">
                                        <i class="fas fa-check-circle"></i> Calificar
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($entregas)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No hay entregas para esta tarea
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de calificación -->
    <div id="modal-calificacion" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form id="form-calificacion" action="" method="POST">
                    <input type="hidden" name="entrega_id" id="entrega_id">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                    Calificar Entrega
                                </h3>
                                <div class="mt-4 space-y-4">
                                    <div>
                                        <label for="calificacion" class="block text-sm font-medium text-gray-700">
                                            Calificación (0-<?php echo $tarea['puntaje_maximo']; ?>)
                                        </label>
                                        <input type="number" name="calificacion" id="calificacion" required
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                            min="0" max="<?php echo $tarea['puntaje_maximo']; ?>" step="0.1">
                                    </div>
                                    <div>
                                        <label for="retroalimentacion" class="block text-sm font-medium text-gray-700">
                                            Retroalimentación
                                        </label>
                                        <textarea name="retroalimentacion" id="retroalimentacion" rows="4"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Guardar Calificación
                        </button>
                        <button type="button" onclick="ocultarFormularioCalificacion()"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function mostrarFormularioCalificacion(entrega) {
            document.getElementById('entrega_id').value = entrega.id;
            document.getElementById('calificacion').value = entrega.calificacion || '';
            document.getElementById('retroalimentacion').value = entrega.retroalimentacion || '';
            document.getElementById('modal-calificacion').classList.remove('hidden');
        }

        function ocultarFormularioCalificacion() {
            document.getElementById('modal-calificacion').classList.add('hidden');
        }

        // Validación del formulario
        document.getElementById('form-calificacion').addEventListener('submit', function(e) {
            const calificacion = parseFloat(document.getElementById('calificacion').value);
            const maxPuntaje = <?php echo $tarea['puntaje_maximo']; ?>;

            if (calificacion < 0 || calificacion > maxPuntaje) {
                e.preventDefault();
                alert(`La calificación debe estar entre 0 y ${maxPuntaje}`);
            }
        });
    </script>
</body>
</html>
