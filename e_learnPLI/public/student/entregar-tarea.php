<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/file_manager.php';

$auth = Auth::getInstance();
$fileManager = FileManager::getInstance();

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

$error = '';
$success = '';
$tareaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    // Verificar que la tarea existe y pertenece a una materia en la que está inscrito el estudiante
    $stmt = $pdo->prepare("
        SELECT t.*, m.nombre as materia_nombre 
        FROM tareas t
        INNER JOIN materias m ON t.materia_id = m.id
        INNER JOIN estudiantes_materias em ON m.id = em.materia_id
        WHERE t.id = ? AND em.user_id = ? AND em.estado = 'inscrito'
        AND t.estado = 'publicada' AND t.fecha_entrega >= NOW()
    ");
    $stmt->execute([$tareaId, $_SESSION['user_id']]);
    $tarea = $stmt->fetch();

    if (!$tarea) {
        header('Location: dashboard.php');
        exit;
    }

    // Verificar si ya existe una entrega
    $stmt = $pdo->prepare("
        SELECT * FROM entregas_tareas 
        WHERE tarea_id = ? AND user_id = ?
    ");
    $stmt->execute([$tareaId, $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        header('Location: materia.php?id=' . $tarea['materia_id']);
        exit;
    }

    // Procesar el formulario de entrega
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $contenido = isset($_POST['contenido']) ? trim($_POST['contenido']) : '';
        $archivo = isset($_FILES['archivo']) ? $_FILES['archivo'] : null;

        if (empty($contenido) && empty($archivo['name'])) {
            throw new Exception('Debe proporcionar contenido o un archivo para la entrega.');
        }

        // Iniciar transacción
        $pdo->beginTransaction();

        // Procesar archivo si existe
        $archivoInfo = null;
        if ($archivo && !empty($archivo['name'])) {
            $archivoInfo = $fileManager->uploadFile($archivo, 'document');
            if (!$archivoInfo) {
                throw new Exception('Error al subir el archivo.');
            }
        }

        // Registrar entrega
        $stmt = $pdo->prepare("
            INSERT INTO entregas_tareas (
                tarea_id, user_id, contenido, archivo,
                fecha_entrega, estado
            ) VALUES (
                :tarea_id, :user_id, :contenido, :archivo,
                NOW(), 'entregado'
            )
        ");

        $stmt->execute([
            'tarea_id' => $tareaId,
            'user_id' => $_SESSION['user_id'],
            'contenido' => $contenido,
            'archivo' => $archivoInfo ? $archivoInfo['ruta'] : null
        ]);

        $pdo->commit();
        $success = 'Tarea entregada correctamente.';

        // Redirigir después de 2 segundos
        header("refresh:2;url=materia.php?id=" . $tarea['materia_id']);

    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = $e->getMessage();
    error_log("Error en entregar-tarea.php: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entregar Tarea - E-Learning PLI</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- TinyMCE -->
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        tinymce.init({
            selector: '#contenido',
            plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
            toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
            height: 300
        });
    </script>
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
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h1 class="text-2xl font-bold text-gray-900 mb-4">
                    Entregar Tarea
                </h1>

                <!-- Información de la tarea -->
                <div class="mb-6 bg-gray-50 rounded-lg p-4">
                    <h2 class="text-lg font-medium text-gray-900">
                        <?php echo htmlspecialchars($tarea['titulo']); ?>
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        <?php echo htmlspecialchars($tarea['materia_nombre']); ?>
                    </p>
                    <p class="mt-2 text-gray-600">
                        <?php echo htmlspecialchars($tarea['descripcion']); ?>
                    </p>
                    <div class="mt-2 text-sm text-gray-500">
                        <span class="font-medium">Fecha límite de entrega:</span>
                        <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_entrega'])); ?>
                    </div>
                </div>

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

                <!-- Formulario de entrega -->
                <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <!-- Editor de contenido -->
                    <div>
                        <label for="contenido" class="block text-sm font-medium text-gray-700">
                            Contenido de la entrega
                        </label>
                        <div class="mt-1">
                            <textarea id="contenido" name="contenido" rows="5"
                                class="shadow-sm focus:ring-blue-500 focus:border-blue-500 mt-1 block w-full sm:text-sm border border-gray-300 rounded-md"></textarea>
                        </div>
                        <p class="mt-2 text-sm text-gray-500">
                            Escribe aquí el contenido de tu tarea o comentarios adicionales.
                        </p>
                    </div>

                    <!-- Subida de archivo -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            Archivo adjunto
                        </label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                            <div class="space-y-1 text-center">
                                <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl mb-3"></i>
                                <div class="flex text-sm text-gray-600">
                                    <label for="archivo" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                        <span>Subir un archivo</span>
                                        <input id="archivo" name="archivo" type="file" class="sr-only">
                                    </label>
                                    <p class="pl-1">o arrastra y suelta</p>
                                </div>
                                <p class="text-xs text-gray-500">
                                    PDF, DOC, DOCX hasta 10MB
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Nombre del archivo seleccionado -->
                    <div id="selectedFile" class="hidden mt-2 text-sm text-gray-500">
                        Archivo seleccionado: <span></span>
                    </div>

                    <!-- Botón de envío -->
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-paper-plane mr-2"></i>
                            Entregar Tarea
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Mostrar nombre del archivo seleccionado
        document.getElementById('archivo').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            const selectedFileDiv = document.getElementById('selectedFile');
            if (fileName) {
                selectedFileDiv.querySelector('span').textContent = fileName;
                selectedFileDiv.classList.remove('hidden');
            } else {
                selectedFileDiv.classList.add('hidden');
            }
        });

        // Drag and drop
        const dropZone = document.querySelector('.border-dashed');
        const fileInput = document.getElementById('archivo');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults (e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            dropZone.classList.add('border-blue-500');
        }

        function unhighlight(e) {
            dropZone.classList.remove('border-blue-500');
        }

        dropZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            
            // Trigger change event
            const event = new Event('change');
            fileInput.dispatchEvent(event);
        }

        // Validación del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const contenido = tinymce.get('contenido').getContent();
            const archivo = document.getElementById('archivo').files[0];

            if (!contenido && !archivo) {
                e.preventDefault();
                alert('Debe proporcionar contenido o un archivo para la entrega.');
            }
        });
    </script>
</body>
</html>
