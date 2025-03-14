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

try {
    // Obtener lista de materias
    $stmt = $pdo->prepare("
        SELECT m.*, 
               COALESCE(u.full_Name, 'Sin asignar') as profesor_nombre,
               (SELECT COUNT(*) FROM estudiantes_materias WHERE materia_id = m.id AND estado = 'inscrito') as total_estudiantes
        FROM materias m
        LEFT JOIN maestros_materias mm ON m.id = mm.materia_id AND mm.estado = 'activo'
        LEFT JOIN users u ON mm.user_id = u.id
        ORDER BY m.created_at DESC
    ");
    $stmt->execute();
    $materias = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Error en materias.php: " . $e->getMessage());
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Materias</title>
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

        <!-- Encabezado -->
        <div class="md:flex md:items-center md:justify-between mb-6">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                    Gestión de Materias
                </h2>
            </div>
            <div class="mt-4 flex md:mt-0 md:ml-4">
                <a href="nueva-materia.php" class="ml-3 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                    <i class="fas fa-plus mr-2"></i>
                    Nueva Materia
                </a>
            </div>
        </div>

        <!-- Lista de materias -->
        <div class="bg-white shadow overflow-hidden sm:rounded-md">
            <ul role="list" class="divide-y divide-gray-200">
                <?php foreach ($materias as $materia): ?>
                <li>
                    <div class="px-4 py-4 sm:px-6">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <h3 class="text-lg font-medium text-gray-900">
                                    <?php echo htmlspecialchars($materia['nombre']); ?>
                                    <span class="ml-2 text-sm text-gray-500">
                                        (<?php echo htmlspecialchars($materia['codigo']); ?>)
                                    </span>
                                </h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    Profesor: <?php echo htmlspecialchars($materia['profesor_nombre']); ?>
                                </p>
                                <p class="mt-1 text-sm text-gray-500">
                                    Estudiantes inscritos: <?php echo $materia['total_estudiantes']; ?>
                                </p>
                            </div>
                            <div class="flex space-x-2">
                                <a href="inscribir-estudiantes.php?id=<?php echo $materia['id']; ?>" 
                                   class="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-5 font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                                    <i class="fas fa-user-plus mr-1"></i>
                                    Inscribir Estudiantes
                                </a>
                                <a href="asignar-profesor.php?id=<?php echo $materia['id']; ?>" 
                                   class="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-5 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                    <i class="fas fa-chalkboard-teacher mr-1"></i>
                                    Asignar Profesor
                                </a>
                                <a href="editar-materia.php?id=<?php echo $materia['id']; ?>" 
                                   class="inline-flex items-center px-3 py-1 border border-gray-300 text-sm leading-5 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    <i class="fas fa-edit mr-1"></i>
                                    Editar
                                </a>
                            </div>
                        </div>
                        <div class="mt-2">
                            <div class="flex items-center text-sm text-gray-500">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $materia['estado'] === 'activo' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo ucfirst($materia['estado']); ?>
                                </span>
                                <span class="ml-2">
                                    <i class="far fa-clock mr-1"></i>
                                    Creada el <?php echo date('d/m/Y', strtotime($materia['created_at'])); ?>
                                </span>
                                <?php if ($materia['creditos']): ?>
                                <span class="ml-2">
                                    <i class="fas fa-graduation-cap mr-1"></i>
                                    <?php echo $materia['creditos']; ?> créditos
                                </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($materia['descripcion']): ?>
                            <p class="mt-2 text-sm text-gray-600">
                                <?php echo htmlspecialchars($materia['descripcion']); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
                <?php if (empty($materias)): ?>
                <li>
                    <div class="px-4 py-4 sm:px-6 text-center text-gray-500">
                        No hay materias registradas
                    </div>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</body>
</html>
