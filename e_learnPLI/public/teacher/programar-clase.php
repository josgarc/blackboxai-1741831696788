<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/zoom_manager.php';

$auth = Auth::getInstance();
$zoomManager = ZoomManager::getInstance();

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

    // Procesar formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $topic = trim($_POST['topic'] ?? '');
        $fecha = trim($_POST['fecha'] ?? '');
        $hora = trim($_POST['hora'] ?? '');
        $duracion = (int)($_POST['duracion'] ?? 60);
        $descripcion = trim($_POST['descripcion'] ?? '');
        $requiereRegistro = isset($_POST['requiere_registro']);

        if (empty($topic) || empty($fecha) || empty($hora)) {
            throw new Exception('Todos los campos marcados con * son obligatorios.');
        }

        // Combinar fecha y hora
        $startTime = date('Y-m-d\TH:i:s', strtotime("$fecha $hora"));

        // Crear reunión en Zoom
        $meeting = $zoomManager->createMeeting([
            'topic' => $topic,
            'start_time' => $startTime,
            'duration' => $duracion,
            'type' => 2, // Reunión programada
            'settings' => [
                'host_video' => true,
                'participant_video' => true,
                'join_before_host' => false,
                'mute_upon_entry' => true,
                'waiting_room' => true,
                'registration_type' => $requiereRegistro ? 2 : 1 // 1: No requiere registro, 2: Requiere registro
            ]
        ]);

        if ($meeting) {
            // Guardar información de la reunión
            $stmt = $pdo->prepare("
                INSERT INTO zoom_meetings (
                    materia_id, meeting_id, topic, start_time, duration,
                    join_url, start_url, estado, descripcion
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'programada', ?)
            ");
            $stmt->execute([
                $materiaId,
                $meeting['id'],
                $topic,
                $startTime,
                $duracion,
                $meeting['join_url'],
                $meeting['start_url'],
                $descripcion
            ]);

            // Notificar a los estudiantes
            $stmt = $pdo->prepare("
                SELECT u.email, u.full_Name
                FROM users u
                INNER JOIN estudiantes_materias em ON u.id = em.user_id
                WHERE em.materia_id = ? AND em.estado = 'inscrito'
            ");
            $stmt->execute([$materiaId]);
            $estudiantes = $stmt->fetchAll();

            foreach ($estudiantes as $estudiante) {
                // Enviar correo de notificación
                $emailService = EmailService::getInstance();
                $emailService->sendClassScheduledNotification(
                    $estudiante['email'],
                    [
                        'nombre' => $estudiante['full_Name'],
                        'materia' => $materia['nombre'],
                        'tema' => $topic,
                        'fecha' => date('d/m/Y', strtotime($startTime)),
                        'hora' => date('H:i', strtotime($startTime)),
                        'duracion' => $duracion,
                        'link' => $meeting['join_url']
                    ]
                );
            }

            $success = 'Clase programada correctamente.';
            // Redirigir después de 2 segundos
            header("refresh:2;url=materia.php?id=" . $materiaId);
        } else {
            throw new Exception('Error al programar la reunión en Zoom.');
        }
    }

} catch (Exception $e) {
    error_log("Error en programar-clase.php: " . $e->getMessage());
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programar Clase - <?php echo htmlspecialchars($materia['nombre']); ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
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

        <!-- Formulario -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Programar Nueva Clase Virtual
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Programa una clase virtual para <?php echo htmlspecialchars($materia['nombre']); ?>
                </p>

                <form action="" method="POST" class="mt-6 space-y-6">
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <!-- Tema -->
                        <div class="sm:col-span-2">
                            <label for="topic" class="block text-sm font-medium text-gray-700">
                                Tema de la clase *
                            </label>
                            <input type="text" name="topic" id="topic" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>

                        <!-- Fecha -->
                        <div>
                            <label for="fecha" class="block text-sm font-medium text-gray-700">
                                Fecha *
                            </label>
                            <input type="date" name="fecha" id="fecha" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                min="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <!-- Hora -->
                        <div>
                            <label for="hora" class="block text-sm font-medium text-gray-700">
                                Hora *
                            </label>
                            <input type="time" name="hora" id="hora" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>

                        <!-- Duración -->
                        <div>
                            <label for="duracion" class="block text-sm font-medium text-gray-700">
                                Duración (minutos)
                            </label>
                            <input type="number" name="duracion" id="duracion"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                value="60" min="15" max="240" step="15">
                        </div>

                        <!-- Requiere registro -->
                        <div>
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input type="checkbox" name="requiere_registro" id="requiere_registro"
                                        class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="requiere_registro" class="font-medium text-gray-700">
                                        Requiere registro previo
                                    </label>
                                    <p class="text-gray-500">
                                        Los estudiantes deberán registrarse antes de unirse a la clase
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Descripción -->
                        <div class="sm:col-span-2">
                            <label for="descripcion" class="block text-sm font-medium text-gray-700">
                                Descripción
                            </label>
                            <textarea name="descripcion" id="descripcion" rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                placeholder="Describe el contenido de la clase..."></textarea>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="flex justify-end space-x-3">
                        <a href="materia.php?id=<?php echo $materiaId; ?>"
                           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Cancelar
                        </a>
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-video mr-2"></i>
                            Programar Clase
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Inicializar Flatpickr para el selector de fecha
        flatpickr("#fecha", {
            minDate: "today",
            dateFormat: "Y-m-d"
        });

        // Inicializar Flatpickr para el selector de hora
        flatpickr("#hora", {
            enableTime: true,
            noCalendar: true,
            dateFormat: "H:i",
            time_24hr: true,
            minuteIncrement: 15
        });

        // Validación del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const fecha = document.getElementById('fecha').value;
            const hora = document.getElementById('hora').value;
            const fechaHora = new Date(fecha + ' ' + hora);
            const ahora = new Date();

            if (fechaHora < ahora) {
                e.preventDefault();
                alert('La fecha y hora de la clase debe ser posterior a la actual.');
            }
        });
    </script>
</body>
</html>
