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

$error = '';
$success = '';
$materiaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    // Obtener información de la materia
    $stmt = $pdo->prepare("SELECT * FROM materias WHERE id = ?");
    $stmt->execute([$materiaId]);
    $materia = $stmt->fetch();

    if (!$materia) {
        header('Location: materias.php');
        exit;
    }

    // Procesar formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $accion = $_POST['accion'] ?? '';
        $estudianteId = (int)($_POST['estudiante_id'] ?? 0);

        switch ($accion) {
            case 'inscribir':
                $courseManager->enrollStudent($materiaId, $estudianteId);
                $success = 'Estudiante inscrito correctamente.';
                break;

            case 'dar_baja':
                $courseManager->unenrollStudent($materiaId, $estudianteId);
                $success = 'Estudiante dado de baja correctamente.';
                break;

            case 'inscribir_multiple':
                $estudiantes = $_POST['estudiantes'] ?? [];
                foreach ($estudiantes as $estudianteId) {
                    try {
                        $courseManager->enrollStudent($materiaId, (int)$estudianteId);
                    } catch (Exception $e) {
                        // Ignorar errores de estudiantes ya inscritos
                        continue;
                    }
                }
                $success = 'Estudiantes inscritos correctamente.';
                break;
        }
    }

    // Obtener lista de estudiantes inscritos
    $stmt = $pdo->prepare("
        SELECT u.*, em.estado, em.fecha_inscripcion, em.fecha_baja
        FROM users u
        INNER JOIN estudiantes_materias em ON u.id = em.user_id
        WHERE em.materia_id = ?
        ORDER BY u.full_Name
    ");
    $stmt->execute([$materiaId]);
    $estudiantesInscritos = $stmt->fetchAll();

    // Obtener lista de estudiantes no inscritos
    $stmt = $pdo->prepare("
        SELECT u.*
        FROM users u
        WHERE u.role = 'Estudiante'
        AND u.status = 'activo'
        AND NOT EXISTS (
            SELECT 1 FROM estudiantes_materias em 
            WHERE em.user_id = u.id 
            AND em.materia_id = ?
            AND em.estado = 'inscrito'
        )
        ORDER BY u.full_Name
    ");
    $stmt->execute([$materiaId]);
    $estudiantesDisponibles = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Error en inscribir-estudiantes.php: " . $e->getMessage());
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscribir Estudiantes - <?php echo htmlspecialchars($materia['nombre']); ?></title>
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
                        <a href="materias.php" class="text-gray-700">
                            <i class="fas fa-arrow-left mr-2"></i> Volver a Materias
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

        <!-- Información de la materia -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <h2 class="text-2xl font-bold text-gray-900">
                    <?php echo htmlspecialchars($materia['nombre']); ?>
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Código: <?php echo htmlspecialchars($materia['codigo']); ?>
                </p>
            </div>
        </div>

        <!-- Inscripción múltiple -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    Inscribir Estudiantes
                </h3>
                <form action="" method="POST">
                    <input type="hidden" name="accion" value="inscribir_multiple">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <?php foreach ($estudiantesDisponibles as $estudiante): ?>
                            <div class="relative flex items-start">
                                <div class="flex items-center h-5">
                                    <input type="checkbox" name="estudiantes[]" value="<?php echo $estudiante['id']; ?>"
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label class="font-medium text-gray-700">
                                        <?php echo htmlspecialchars($estudiante['full_Name']); ?>
                                    </label>
                                    <p class="text-gray-500">
                                        <?php echo htmlspecialchars($estudiante['email']); ?>
                                    </p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (empty($estudiantesDisponibles)): ?>
                            <p class="text-gray-500 text-center">
                                No hay estudiantes disponibles para inscribir.
                            </p>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($estudiantesDisponibles)): ?>
                        <div class="mt-4">
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                                <i class="fas fa-user-plus mr-2"></i>
                                Inscribir Seleccionados
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Lista de estudiantes inscritos -->
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
                                    Fecha Inscripción
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($estudiantesInscritos as $estudiante): ?>
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
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('d/m/Y', strtotime($estudiante['fecha_inscripcion'])); ?>
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
                                            <input type="hidden" name="accion" value="inscribir">
                                            <input type="hidden" name="estudiante_id" value="<?php echo $estudiante['id']; ?>">
                                            <button type="submit" class="text-green-600 hover:text-green-900">
                                                <i class="fas fa-user-plus"></i> Reactivar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($estudiantesInscritos)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
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
