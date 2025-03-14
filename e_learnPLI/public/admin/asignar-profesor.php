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
    $stmt = $pdo->prepare("
        SELECT m.*, u.id as profesor_id, u.full_Name as profesor_nombre
        FROM materias m
        LEFT JOIN maestros_materias mm ON m.id = mm.materia_id AND mm.estado = 'activo'
        LEFT JOIN users u ON mm.user_id = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$materiaId]);
    $materia = $stmt->fetch();

    if (!$materia) {
        header('Location: materias.php');
        exit;
    }

    // Procesar formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $profesorId = (int)($_POST['profesor_id'] ?? 0);

        // Desasignar profesor actual si existe
        $stmt = $pdo->prepare("
            UPDATE maestros_materias 
            SET estado = 'inactivo' 
            WHERE materia_id = ? AND estado = 'activo'
        ");
        $stmt->execute([$materiaId]);

        // Asignar nuevo profesor si se seleccionó uno
        if ($profesorId > 0) {
            $courseManager->assignTeacher($materiaId, $profesorId);
            
            // Enviar notificación por correo al profesor
            $stmt = $pdo->prepare("SELECT email, full_Name FROM users WHERE id = ?");
            $stmt->execute([$profesorId]);
            $profesor = $stmt->fetch();

            if ($profesor) {
                require_once __DIR__ . '/../../includes/mail.php';
                $emailService = EmailService::getInstance();
                $emailService->sendTeacherAssignmentNotification(
                    $profesor['email'],
                    [
                        'nombre' => $profesor['full_Name'],
                        'materia' => $materia['nombre']
                    ]
                );
            }
        }

        $success = 'Profesor asignado correctamente.';
        
        // Recargar información de la materia
        $stmt->execute([$materiaId]);
        $materia = $stmt->fetch();
    }

    // Obtener lista de profesores disponibles
    $stmt = $pdo->prepare("
        SELECT u.* 
        FROM users u
        WHERE u.role = 'Maestro'
        AND u.status = 'activo'
        ORDER BY u.full_Name
    ");
    $stmt->execute();
    $profesores = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Error en asignar-profesor.php: " . $e->getMessage());
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignar Profesor - <?php echo htmlspecialchars($materia['nombre']); ?></title>
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
                <?php if ($materia['profesor_id']): ?>
                    <p class="mt-2 text-sm text-gray-700">
                        Profesor actual: <strong><?php echo htmlspecialchars($materia['profesor_nombre']); ?></strong>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Formulario de asignación -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    Asignar Profesor
                </h3>
                <form action="" method="POST">
                    <div class="space-y-4">
                        <div>
                            <label for="profesor_id" class="block text-sm font-medium text-gray-700">
                                Seleccionar Profesor
                            </label>
                            <select name="profesor_id" id="profesor_id" required
                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                <option value="">Seleccionar profesor</option>
                                <?php foreach ($profesores as $profesor): ?>
                                    <option value="<?php echo $profesor['id']; ?>" 
                                            <?php echo $profesor['id'] == $materia['profesor_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($profesor['full_Name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                                <i class="fas fa-save mr-2"></i>
                                Guardar Cambios
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
