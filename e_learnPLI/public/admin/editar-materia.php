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

// Verificar que la materia existe
try {
    $stmt = $pdo->prepare("
        SELECT m.*, u.id as profesor_id
        FROM materias m
        LEFT JOIN maestros_materias mm ON m.id = mm.materia_id
        LEFT JOIN users u ON mm.user_id = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$materiaId]);
    $materia = $stmt->fetch();

    if (!$materia) {
        header('Location: materias.php');
        exit;
    }

    // Obtener lista de profesores
    $stmt = $pdo->prepare("
        SELECT id, full_Name 
        FROM users 
        WHERE role = 'Maestro' 
        ORDER BY full_Name
    ");
    $stmt->execute();
    $profesores = $stmt->fetchAll();

    // Procesar el formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // Validar datos
            $courseData = [
                'id' => $materiaId,
                'codigo' => trim($_POST['codigo'] ?? ''),
                'nombre' => trim($_POST['nombre'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'creditos' => (int)($_POST['creditos'] ?? 0),
                'estado' => trim($_POST['estado'] ?? 'activo'),
                'maestro_id' => (int)($_POST['maestro_id'] ?? 0)
            ];

            // Validaciones
            if (empty($courseData['codigo']) || empty($courseData['nombre'])) {
                throw new Exception('El código y nombre de la materia son obligatorios.');
            }

            // Verificar si el código ya existe (excluyendo la materia actual)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM materias WHERE codigo = ? AND id != ?");
            $stmt->execute([$courseData['codigo'], $materiaId]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('El código de la materia ya existe.');
            }

            // Actualizar materia
            if ($courseManager->updateCourse($courseData)) {
                $success = '¡Materia actualizada exitosamente!';
                
                // Actualizar datos en memoria
                $stmt = $pdo->prepare("
                    SELECT m.*, u.id as profesor_id
                    FROM materias m
                    LEFT JOIN maestros_materias mm ON m.id = mm.materia_id
                    LEFT JOIN users u ON mm.user_id = u.id
                    WHERE m.id = ?
                ");
                $stmt->execute([$materiaId]);
                $materia = $stmt->fetch();
            } else {
                throw new Exception('Error al actualizar la materia.');
            }

        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

} catch (Exception $e) {
    error_log("Error en editar-materia.php: " . $e->getMessage());
    header('Location: materias.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Materia - E-Learning PLI</title>
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
                        <a href="../index.php">
                            <img class="h-8 w-auto" src="../assets/images/logo.png" alt="Logo">
                        </a>
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="dashboard.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Dashboard
                        </a>
                        <a href="usuarios.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Usuarios
                        </a>
                        <a href="materias.php" class="border-blue-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Materias
                        </a>
                        <a href="configuracion.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Configuración
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Contenido principal -->
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Encabezado -->
        <div class="px-4 py-6 sm:px-0">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">
                        Editar Materia
                    </h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Modificar información de la materia
                    </p>
                </div>
                <div>
                    <a href="materias.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Volver
                    </a>
                </div>
            </div>
        </div>

        <!-- Formulario -->
        <div class="bg-white shadow rounded-lg">
            <div class="p-4 sm:p-6">
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

                <form action="" method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <!-- Código -->
                        <div>
                            <label for="codigo" class="block text-sm font-medium text-gray-700">
                                Código *
                            </label>
                            <input type="text" name="codigo" id="codigo" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                value="<?php echo htmlspecialchars($materia['codigo']); ?>">
                        </div>

                        <!-- Nombre -->
                        <div>
                            <label for="nombre" class="block text-sm font-medium text-gray-700">
                                Nombre *
                            </label>
                            <input type="text" name="nombre" id="nombre" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                value="<?php echo htmlspecialchars($materia['nombre']); ?>">
                        </div>

                        <!-- Descripción -->
                        <div class="sm:col-span-2">
                            <label for="descripcion" class="block text-sm font-medium text-gray-700">
                                Descripción
                            </label>
                            <textarea name="descripcion" id="descripcion" rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"><?php echo htmlspecialchars($materia['descripcion']); ?></textarea>
                        </div>

                        <!-- Créditos -->
                        <div>
                            <label for="creditos" class="block text-sm font-medium text-gray-700">
                                Créditos
                            </label>
                            <input type="number" name="creditos" id="creditos" min="0"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                value="<?php echo (int)$materia['creditos']; ?>">
                        </div>

                        <!-- Estado -->
                        <div>
                            <label for="estado" class="block text-sm font-medium text-gray-700">
                                Estado
                            </label>
                            <select name="estado" id="estado"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                <option value="activo" <?php echo $materia['estado'] === 'activo' ? 'selected' : ''; ?>>Activo</option>
                                <option value="inactivo" <?php echo $materia['estado'] === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>

                        <!-- Profesor -->
                        <div class="sm:col-span-2">
                            <label for="maestro_id" class="block text-sm font-medium text-gray-700">
                                Profesor
                            </label>
                            <select name="maestro_id" id="maestro_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                <option value="">Seleccionar profesor</option>
                                <?php foreach ($profesores as $profesor): ?>
                                    <option value="<?php echo $profesor['id']; ?>" 
                                        <?php echo $profesor['id'] === $materia['profesor_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($profesor['full_Name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="flex justify-end space-x-3">
                        <a href="materias.php" 
                           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Cancelar
                        </a>
                        <button type="submit" 
                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-save mr-2"></i>
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Validación del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const codigo = document.getElementById('codigo').value.trim();
            const nombre = document.getElementById('nombre').value.trim();

            if (!codigo || !nombre) {
                e.preventDefault();
                alert('El código y nombre de la materia son obligatorios.');
            }
        });

        // Confirmar cambios no guardados
        let formChanged = false;
        const form = document.querySelector('form');

        form.addEventListener('change', function() {
            formChanged = true;
        });

        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        form.addEventListener('submit', function() {
            formChanged = false;
        });
    </script>
</body>
</html>
