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

// Parámetros de paginación y filtrado
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        $materiaId = isset($_POST['materia_id']) ? (int)$_POST['materia_id'] : 0;

        switch ($action) {
            case 'activate':
                $stmt = $pdo->prepare("UPDATE materias SET estado = 'activo' WHERE id = ?");
                $stmt->execute([$materiaId]);
                break;

            case 'deactivate':
                $stmt = $pdo->prepare("UPDATE materias SET estado = 'inactivo' WHERE id = ?");
                $stmt->execute([$materiaId]);
                break;

            case 'delete':
                // Verificar si hay registros relacionados
                $stmt = $pdo->prepare("
                    SELECT 
                        (SELECT COUNT(*) FROM estudiantes_materias WHERE materia_id = ?) +
                        (SELECT COUNT(*) FROM maestros_materias WHERE materia_id = ?) +
                        (SELECT COUNT(*) FROM contenidos WHERE materia_id = ?) +
                        (SELECT COUNT(*) FROM tareas WHERE materia_id = ?) +
                        (SELECT COUNT(*) FROM examenes WHERE materia_id = ?) as total
                ");
                $stmt->execute([$materiaId, $materiaId, $materiaId, $materiaId, $materiaId]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('No se puede eliminar la materia porque tiene registros asociados.');
                }
                
                $stmt = $pdo->prepare("DELETE FROM materias WHERE id = ?");
                $stmt->execute([$materiaId]);
                break;
        }

        $success = 'Operación realizada con éxito.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Construir consulta
$params = [];
$whereConditions = [];

if ($search) {
    $whereConditions[] = "(nombre LIKE ? OR codigo LIKE ? OR descripcion LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status) {
    $whereConditions[] = "estado = ?";
    $params[] = $status;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Obtener total de registros
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM materias 
    $whereClause
");
$stmt->execute($params);
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Obtener materias
$stmt = $pdo->prepare("
    SELECT m.*, 
           u.full_Name as profesor_nombre,
           (SELECT COUNT(*) FROM estudiantes_materias WHERE materia_id = m.id) as total_estudiantes
    FROM materias m
    LEFT JOIN maestros_materias mm ON m.id = mm.materia_id
    LEFT JOIN users u ON mm.user_id = u.id
    $whereClause
    ORDER BY m.created_at DESC 
    LIMIT ? OFFSET ?
");
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
$materias = $stmt->fetchAll();

// Obtener lista de profesores para el selector
$stmt = $pdo->prepare("
    SELECT id, full_Name 
    FROM users 
    WHERE role = 'Maestro' 
    ORDER BY full_Name
");
$stmt->execute();
$profesores = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Materias - E-Learning PLI</title>
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
            <h1 class="text-3xl font-bold text-gray-900">
                Gestión de Materias
            </h1>
            <p class="mt-1 text-sm text-gray-600">
                Administra las materias del sistema
            </p>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <!-- Filtros y búsqueda -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="p-4 sm:p-6">
                <form action="" method="GET" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700">Buscar</label>
                        <input type="text" name="search" id="search" 
                            value="<?php echo htmlspecialchars($search); ?>"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                            placeholder="Nombre, código o descripción">
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Estado</label>
                        <select name="status" id="status" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <option value="">Todos</option>
                            <option value="activo" <?php echo $status === 'activo' ? 'selected' : ''; ?>>Activo</option>
                            <option value="inactivo" <?php echo $status === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            <i class="fas fa-search mr-2"></i> Buscar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de materias -->
        <div class="bg-white shadow rounded-lg">
            <div class="p-4 sm:p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-900">
                        Materias (<?php echo $totalRecords; ?>)
                    </h2>
                    <a href="nueva-materia.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i> Nueva Materia
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Materia
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Profesor
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estudiantes
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estado
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($materias as $materia): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-book text-blue-500"></i>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($materia['nombre']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                Código: <?php echo htmlspecialchars($materia['codigo']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <select class="profesor-select text-sm rounded-full px-3 py-1 border-gray-300" 
                                            data-materia-id="<?php echo $materia['id']; ?>">
                                        <option value="">Sin asignar</option>
                                        <?php foreach ($profesores as $profesor): ?>
                                            <option value="<?php echo $profesor['id']; ?>" 
                                                <?php echo $profesor['full_Name'] === $materia['profesor_nombre'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($profesor['full_Name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $materia['total_estudiantes']; ?> estudiantes
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $materia['estado'] === 'activo' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo ucfirst($materia['estado']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <form action="" method="POST" class="inline-block">
                                        <input type="hidden" name="materia_id" value="<?php echo $materia['id']; ?>">
                                        <?php if ($materia['estado'] === 'activo'): ?>
                                            <input type="hidden" name="action" value="deactivate">
                                            <button type="submit" class="text-red-600 hover:text-red-900 mr-2">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="action" value="activate">
                                            <button type="submit" class="text-green-600 hover:text-green-900 mr-2">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                    <a href="editar-materia.php?id=<?php echo $materia['id']; ?>" 
                                       class="text-blue-600 hover:text-blue-900 mr-2">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="" method="POST" class="inline-block delete-form">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="materia_id" value="<?php echo $materia['id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <?php if ($totalPages > 1): ?>
                <div class="mt-4 flex justify-between items-center">
                    <div class="text-sm text-gray-500">
                        Mostrando <?php echo $offset + 1; ?> a <?php echo min($offset + $limit, $totalRecords); ?> de <?php echo $totalRecords; ?> resultados
                    </div>
                    <div class="flex space-x-2">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" 
                               class="px-3 py-1 rounded-md <?php echo $page === $i ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Confirmación de eliminación
        document.querySelectorAll('.delete-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm('¿Estás seguro de que deseas eliminar esta materia?')) {
                    e.preventDefault();
                }
            });
        });

        // Actualización de profesor
        document.querySelectorAll('.profesor-select').forEach(select => {
            select.addEventListener('change', function() {
                const materiaId = this.getAttribute('data-materia-id');
                const profesorId = this.value;

                if (confirm('¿Estás seguro de que deseas cambiar el profesor de esta materia?')) {
                    fetch('asignar-profesor.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `materia_id=${materiaId}&profesor_id=${profesorId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Profesor asignado correctamente.');
                        } else {
                            alert('Error al asignar el profesor.');
                            // Revertir la selección
                            this.value = this.getAttribute('data-original');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error al procesar la solicitud.');
                        // Revertir la selección
                        this.value = this.getAttribute('data-original');
                    });
                } else {
                    // Revertir la selección si se cancela
                    this.value = this.getAttribute('data-original');
                }
            });

            // Guardar valor original
            select.setAttribute('data-original', select.value);
        });
    </script>
</body>
</html>
