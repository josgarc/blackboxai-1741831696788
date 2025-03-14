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

    // Procesar formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $titulo = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $fechaInicio = trim($_POST['fecha_inicio'] ?? '');
        $horaInicio = trim($_POST['hora_inicio'] ?? '');
        $fechaFin = trim($_POST['fecha_fin'] ?? '');
        $horaFin = trim($_POST['hora_fin'] ?? '');
        $duracion = (int)($_POST['duracion'] ?? 0);
        $intentosPermitidos = (int)($_POST['intentos_permitidos'] ?? 1);
        $puntajeAprobatorio = (float)($_POST['puntaje_aprobatorio'] ?? 60);
        $preguntas = $_POST['preguntas'] ?? [];

        if (empty($titulo) || empty($fechaInicio) || empty($horaInicio) || empty($fechaFin) || empty($horaFin)) {
            throw new Exception('Todos los campos marcados con * son obligatorios.');
        }

        if (empty($preguntas)) {
            throw new Exception('Debe agregar al menos una pregunta al examen.');
        }

        // Combinar fechas y horas
        $fechaHoraInicio = date('Y-m-d H:i:s', strtotime("$fechaInicio $horaInicio"));
        $fechaHoraFin = date('Y-m-d H:i:s', strtotime("$fechaFin $horaFin"));

        if ($fechaHoraInicio >= $fechaHoraFin) {
            throw new Exception('La fecha de inicio debe ser anterior a la fecha de fin.');
        }

        // Iniciar transacción
        $pdo->beginTransaction();

        // Crear examen
        $stmt = $pdo->prepare("
            INSERT INTO examenes (
                materia_id, titulo, descripcion, fecha_inicio, fecha_fin,
                duracion, intentos_permitidos, puntaje_aprobatorio,
                estado, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'publicado', NOW())
        ");
        $stmt->execute([
            $materiaId,
            $titulo,
            $descripcion,
            $fechaHoraInicio,
            $fechaHoraFin,
            $duracion,
            $intentosPermitidos,
            $puntajeAprobatorio
        ]);
        $examenId = $pdo->lastInsertId();

        // Procesar preguntas
        foreach ($preguntas as $index => $pregunta) {
            $stmt = $pdo->prepare("
                INSERT INTO preguntas_examen (
                    examen_id, pregunta, tipo, puntaje, orden
                ) VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $examenId,
                $pregunta['pregunta'],
                $pregunta['tipo'],
                $pregunta['puntaje'],
                $index + 1
            ]);
            $preguntaId = $pdo->lastInsertId();

            // Procesar opciones para preguntas de opción múltiple
            if ($pregunta['tipo'] === 'multiple' && !empty($pregunta['opciones'])) {
                foreach ($pregunta['opciones'] as $opcion) {
                    $stmt = $pdo->prepare("
                        INSERT INTO opciones_pregunta (
                            pregunta_id, opcion, es_correcta
                        ) VALUES (?, ?, ?)
                    ");
                    $stmt->execute([
                        $preguntaId,
                        $opcion['texto'],
                        $opcion['correcta'] ? 1 : 0
                    ]);
                }
            }
        }

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
            $emailService->sendNewExamNotification(
                $estudiante['email'],
                [
                    'nombre' => $estudiante['full_Name'],
                    'materia' => $materia['nombre'],
                    'examen' => $titulo,
                    'fecha_inicio' => date('d/m/Y H:i', strtotime($fechaHoraInicio)),
                    'fecha_fin' => date('d/m/Y H:i', strtotime($fechaHoraFin))
                ]
            );
        }

        $pdo->commit();
        $success = '¡Examen creado exitosamente!';
        // Redirigir después de 2 segundos
        header("refresh:2;url=materia.php?id=" . $materiaId);

    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error en nuevo-examen.php: " . $e->getMessage());
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Examen - <?php echo htmlspecialchars($materia['nombre']); ?></title>
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
                    Nuevo Examen
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Crear un nuevo examen para <?php echo htmlspecialchars($materia['nombre']); ?>
                </p>

                <form action="" method="POST" id="form-examen" class="mt-6 space-y-6">
                    <!-- Información básica -->
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="titulo" class="block text-sm font-medium text-gray-700">
                                Título *
                            </label>
                            <input type="text" name="titulo" id="titulo" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>

                        <div class="sm:col-span-2">
                            <label for="descripcion" class="block text-sm font-medium text-gray-700">
                                Descripción
                            </label>
                            <textarea name="descripcion" id="descripcion" rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"></textarea>
                        </div>

                        <div>
                            <label for="fecha_inicio" class="block text-sm font-medium text-gray-700">
                                Fecha de inicio *
                            </label>
                            <input type="date" name="fecha_inicio" id="fecha_inicio" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                min="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div>
                            <label for="hora_inicio" class="block text-sm font-medium text-gray-700">
                                Hora de inicio *
                            </label>
                            <input type="time" name="hora_inicio" id="hora_inicio" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="fecha_fin" class="block text-sm font-medium text-gray-700">
                                Fecha de fin *
                            </label>
                            <input type="date" name="fecha_fin" id="fecha_fin" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                min="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div>
                            <label for="hora_fin" class="block text-sm font-medium text-gray-700">
                                Hora de fin *
                            </label>
                            <input type="time" name="hora_fin" id="hora_fin" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>

                        <div>
                            <label for="duracion" class="block text-sm font-medium text-gray-700">
                                Duración (minutos)
                            </label>
                            <input type="number" name="duracion" id="duracion"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                value="60" min="1">
                        </div>

                        <div>
                            <label for="intentos_permitidos" class="block text-sm font-medium text-gray-700">
                                Intentos permitidos
                            </label>
                            <input type="number" name="intentos_permitidos" id="intentos_permitidos"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                value="1" min="1">
                        </div>

                        <div>
                            <label for="puntaje_aprobatorio" class="block text-sm font-medium text-gray-700">
                                Puntaje aprobatorio
                            </label>
                            <input type="number" name="puntaje_aprobatorio" id="puntaje_aprobatorio"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                value="60" min="0" max="100">
                        </div>
                    </div>

                    <!-- Preguntas -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-900">
                            Preguntas
                        </h3>
                        <div id="preguntas-container" class="mt-4 space-y-4">
                            <!-- Las preguntas se agregarán aquí dinámicamente -->
                        </div>
                        <div class="mt-4">
                            <button type="button" onclick="agregarPregunta()"
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                                <i class="fas fa-plus mr-2"></i>
                                Agregar Pregunta
                            </button>
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
                            <i class="fas fa-save mr-2"></i>
                            Crear Examen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Template para pregunta -->
    <template id="template-pregunta">
        <div class="pregunta bg-gray-50 p-4 rounded-lg">
            <div class="flex justify-between items-start mb-4">
                <h4 class="text-sm font-medium text-gray-900">
                    Pregunta #<span class="numero-pregunta"></span>
                </h4>
                <button type="button" onclick="eliminarPregunta(this)"
                    class="text-red-600 hover:text-red-900">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Pregunta *
                    </label>
                    <input type="text" name="preguntas[][pregunta]" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Tipo de pregunta *
                    </label>
                    <select name="preguntas[][tipo]" required onchange="cambiarTipoPregunta(this)"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="multiple">Opción múltiple</option>
                        <option value="abierta">Respuesta abierta</option>
                        <option value="verdadero_falso">Verdadero/Falso</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Puntaje *
                    </label>
                    <input type="number" name="preguntas[][puntaje]" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                        value="10" min="0">
                </div>
                <div class="opciones-container">
                    <!-- Las opciones se agregarán aquí dinámicamente -->
                </div>
                <div class="opcion-buttons">
                    <button type="button" onclick="agregarOpcion(this)"
                        class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-full text-blue-700 bg-blue-100 hover:bg-blue-200">
                        <i class="fas fa-plus mr-1"></i>
                        Agregar Opción
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- Template para opción -->
    <template id="template-opcion">
        <div class="opcion flex items-center space-x-3 mt-2">
            <input type="text" name="preguntas[][opciones][][texto]" required
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                placeholder="Opción de respuesta">
            <div class="flex items-center">
                <input type="checkbox" name="preguntas[][opciones][][correcta]"
                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                <label class="ml-2 block text-sm text-gray-900">
                    Correcta
                </label>
            </div>
            <button type="button" onclick="eliminarOpcion(this)"
                class="text-red-600 hover:text-red-900">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </template>

    <script>
        // Inicializar Flatpickr
        flatpickr("#fecha_inicio", {
            minDate: "today",
            dateFormat: "Y-m-d"
        });

        flatpickr("#fecha_fin", {
            minDate: "today",
            dateFormat: "Y-m-d"
        });

        flatpickr("#hora_inicio", {
            enableTime: true,
            noCalendar: true,
            dateFormat: "H:i",
            time_24hr: true
        });

        flatpickr("#hora_fin", {
            enableTime: true,
            noCalendar: true,
            dateFormat: "H:i",
            time_24hr: true
        });

        // Variables globales
        let contadorPreguntas = 0;

        // Funciones para manejar preguntas
        function agregarPregunta() {
            contadorPreguntas++;
            const template = document.getElementById('template-pregunta');
            const container = document.getElementById('preguntas-container');
            const pregunta = template.content.cloneNode(true);

            // Actualizar número de pregunta
            pregunta.querySelector('.numero-pregunta').textContent = contadorPreguntas;

            // Actualizar nombres de campos
            const inputs = pregunta.querySelectorAll('input, select');
            inputs.forEach(input => {
                const name = input.getAttribute('name');
                if (name) {
                    input.setAttribute('name', name.replace('[]', `[${contadorPreguntas-1}]`));
                }
            });

            container.appendChild(pregunta);
        }

        function eliminarPregunta(button) {
            const pregunta = button.closest('.pregunta');
            pregunta.remove();
            actualizarNumerosPreguntas();
        }

        function actualizarNumerosPreguntas() {
            const preguntas = document.querySelectorAll('.pregunta');
            preguntas.forEach((pregunta, index) => {
                pregunta.querySelector('.numero-pregunta').textContent = index + 1;
            });
            contadorPreguntas = preguntas.length;
        }

        // Funciones para manejar opciones
        function agregarOpcion(button) {
            const pregunta = button.closest('.pregunta');
            const container = pregunta.querySelector('.opciones-container');
            const template = document.getElementById('template-opcion');
            const opcion = template.content.cloneNode(true);

            // Actualizar nombres de campos
            const preguntaIndex = Array.from(pregunta.parentNode.children).indexOf(pregunta);
            const opcionIndex = container.children.length;
            const inputs = opcion.querySelectorAll('input');
            inputs.forEach(input => {
                const name = input.getAttribute('name');
                if (name) {
                    input.setAttribute('name', name
                        .replace('[]', `[${preguntaIndex}]`)
                        .replace('[]', `[${opcionIndex}]`));
                }
            });

            container.appendChild(opcion);
        }

        function eliminarOpcion(button) {
            const opcion = button.closest('.opcion');
            opcion.remove();
        }

        function cambiarTipoPregunta(select) {
            const pregunta = select.closest('.pregunta');
            const opcionesContainer = pregunta.querySelector('.opciones-container');
            const opcionButtons = pregunta.querySelector('.opcion-buttons');

            opcionesContainer.innerHTML = '';
            
            switch (select.value) {
                case 'multiple':
                    opcionButtons.style.display = 'block';
                    agregarOpcion(select); // Agregar primera opción
                    break;
                case 'verdadero_falso':
                    opcionButtons.style.display = 'none';
                    // Agregar opciones Verdadero/Falso
                    const template = document.getElementById('template-opcion');
                    ['Verdadero', 'Falso'].forEach((texto, index) => {
                        const opcion = template.content.cloneNode(true);
                        const preguntaIndex = Array.from(pregunta.parentNode.children).indexOf(pregunta);
                        const inputs = opcion.querySelectorAll('input');
                        inputs.forEach(input => {
                            const name = input.getAttribute('name');
                            if (name) {
                                input.setAttribute('name', name
                                    .replace('[]', `[${preguntaIndex}]`)
                                    .replace('[]', `[${index}]`));
                            }
                            if (input.type === 'text') {
                                input.value = texto;
                                input.readOnly = true;
                            }
                        });
                        opcionesContainer.appendChild(opcion);
                    });
                    break;
                case 'abierta':
                    opcionButtons.style.display = 'none';
                    break;
            }
        }

        // Validación del formulario
        document.getElementById('form-examen').addEventListener('submit', function(e) {
            const preguntas = document.querySelectorAll('.pregunta');
            if (preguntas.length === 0) {
                e.preventDefault();
                alert('Debe agregar al menos una pregunta al examen.');
                return;
            }

            const fechaInicio = new Date(document.getElementById('fecha_inicio').value + ' ' + document.getElementById('hora_inicio').value);
            const fechaFin = new Date(document.getElementById('fecha_fin').value + ' ' + document.getElementById('hora_fin').value);

            if (fechaInicio >= fechaFin) {
                e.preventDefault();
                alert('La fecha de inicio debe ser anterior a la fecha de fin.');
                return;
            }

            // Validar que las preguntas de opción múltiple tengan al menos una opción correcta
            let valid = true;
            preguntas.forEach(pregunta => {
                const tipo = pregunta.querySelector('select').value;
                if (tipo === 'multiple') {
                    const opciones = pregunta.querySelectorAll('input[type="checkbox"]');
                    const correctas = Array.from(opciones).some(opt => opt.checked);
                    if (!correctas) {
                        valid = false;
                        const numeroPregunta = pregunta.querySelector('.numero-pregunta').textContent;
                        alert(`La pregunta #${numeroPregunta} debe tener al menos una opción correcta.`);
                    }
                }
            });

            if (!valid) {
                e.preventDefault();
            }
        });

        // Agregar primera pregunta al cargar la página
        window.addEventListener('load', function() {
            agregarPregunta();
        });
    </script>
</body>
</html>
