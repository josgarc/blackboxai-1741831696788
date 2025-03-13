<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

$auth = Auth::getInstance();

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
$role = isset($_GET['role']) ? trim($_GET['role']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

        switch ($action) {
            case 'activate':
                $stmt = $pdo->prepare("UPDATE users SET status = 'activo' WHERE id = ?");
                $stmt->execute([$userId]);
                break;

            case 'deactivate':
                $stmt = $pdo->prepare("UPDATE users SET status = 'inactivo' WHERE id = ?");
                $stmt->execute([$userId]);
                break;

            case 'delete':
                // Verificar si el usuario tiene registros relacionados
                $stmt = $pdo->prepare("
                    SELECT 
                        (SELECT COUNT(*) FROM estudiantes_materias WHERE user_id = ?) +
                        (SELECT COUNT(*) FROM maestros_materias WHERE user_id = ?) +
                        (SELECT COUNT(*) FROM entregas_tareas WHERE user_id = ?) as total
                ");
                $stmt->execute([$userId, $userId, $userId]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('No se puede eliminar el usuario porque tiene registros asociados.');
                }
                
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                break;

            case 'update_role':
                $newRole = $_POST['role'] ?? '';
                if (!in_array($newRole, ['Administrador', 'Maestro', 'Estudiante'])) {
                    throw new Exception('Rol no válido.');
                }
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$newRole, $userId]);
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
    $whereConditions[] = "(full_Name LIKE ? OR email LIKE ? OR username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role) {
    $whereConditions[] = "role = ?";
    $params[] = $role;
}

if ($status) {
    $whereConditions[] = "status = ?";
    $params[] = $status;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Obtener total de registros
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM users 
    $whereClause
");
$stmt->execute($params);
$totalRecords = $stmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Obtener usuarios
$stmt = $pdo->prepare("
    SELECT * 
    FROM users 
    $whereClause
    ORDER BY createdAt DESC 
    LIMIT ? OFFSET ?
");
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
$usuarios = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - E-Learning PLI</title>
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
                        <a href="usuarios.php" class="border-blue-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Usuarios
                        </a>
                        <a href="materias.php" class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
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
                Gestión de Usuarios
            </h1>
            <p class="mt-1 text-sm text-gray-600">
                Administra los usuarios del sistema
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
                <form action="" method="GET" class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700">Buscar</label>
                        <input type="text" name="search" id="search" 
                            value="<?php echo htmlspecialchars($search); ?>"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                            placeholder="Nombre, email o usuario">
                    </div>
                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700">Rol</label>
                        <select name="role" id="role" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <option value="">Todos</option>
                            <option value="Administrador" <?php echo $role === 'Administrador' ? 'selected' : ''; ?>>Administrador</option>
                            <option value="Maestro" <?php echo $role === 'Maestro' ? 'selected' : ''; ?>>Maestro</option>
                            <option value="Estudiante" <?php echo $role === 'Estudiante' ? 'selected' : ''; ?>>Estudiante</option>
                        </select>
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

        <!-- Lista de usuarios -->
        <div class="bg-white shadow rounded-lg">
            <div class="p-4 sm:p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-900">
                        Usuarios (<?php echo $totalRecords; ?>)
                    </h2>
                    <a href="nuevo-usuario.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i> Nuevo Usuario
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Usuario
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Rol
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estado
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Último acceso
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <img class="h-10 w-10 rounded-full" 
                                                src="https://ui-avatars.com/api/?name=<?php echo urlencode($usuario['full_Name']); ?>&background=random" 
                                                alt="">
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($usuario['full_Name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($usuario['email']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <form action="" method="POST" class="role-form">
                                        <input type="hidden" name="action" value="update_role">
                                        <input type="hidden" name="user_id" value="<?php echo $usuario['id']; ?>">
                                        <select name="role" class="role-select text-sm rounded-full px-3 py-1 border-gray-300" 
                                            <?php echo $usuario['id'] === $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                            <option value="Administrador" <?php echo $usuario['role'] === 'Administrador' ? 'selected' : ''; ?>>
                                                Administrador
                                            </option>
                                            <option value="Maestro" <?php echo $usuario['role'] === 'Maestro' ? 'selected' : ''; ?>>
                                                Maestro
                                            </option>
                                            <option value="Estudiante" <?php echo $usuario['role'] === 'Estudiante' ? 'selected' : ''; ?>>
                                                Estudiante
                                            </option>
                                        </select>
                                    </form>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $usuario['status'] === 'activo' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo ucfirst($usuario['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $usuario['last_login'] ? date('d/m/Y H:i', strtotime($usuario['last_login'])) : 'Nunca'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <?php if ($usuario['id'] !== $_SESSION['user_id']): ?>
                                        <form action="" method="POST" class="inline-block">
                                            <input type="hidden" name="user_id" value="<?php echo $usuario['id']; ?>">
                                            <?php if ($usuario['status'] === 'activo'): ?>
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
                                        <form action="" method="POST" class="inline-block delete-form">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo $usuario['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
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
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role); ?>&status=<?php echo urlencode($status); ?>" 
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
                if (!confirm('¿Estás seguro de que deseas eliminar este usuario?')) {
                    e.preventDefault();
                }
            });
        });

        // Actualización automática de rol
        document.querySelectorAll('.role-select').forEach(select => {
            select.addEventListener('change', function() {
                if (confirm('¿Estás seguro de que deseas cambiar el rol de este usuario?')) {
                    this.closest('form').submit();
                } else {
                    this.value = this.getAttribute('data-original');
                }
            });
        });

        // Guardar valor original del rol
        document.querySelectorAll('.role-select').forEach(select => {
            select.setAttribute('data-original', select.value);
        });
    </script>
</body>
</html>
