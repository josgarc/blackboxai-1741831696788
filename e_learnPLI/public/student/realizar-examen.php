<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

$auth = Auth::getInstance();

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
$examenId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    // Verificar que el examen existe y está disponible
    $stmt = $pdo->prepare("
        SELECT e.*, m.nombre as materia_nombre,
        (SELECT COUNT(*) FROM respuestas_examenes WHERE examen_id = e.id AND user_id = ?) as intentos_realizados
        FROM examenes e
        INNER JOIN materias m ON e.materia_id = m.id
        INNER JOIN estudiantes_materias em ON m.id = em.materia_id
        WHERE e.id = ? 
        AND em.user_id = ? 
        AND em.estado = 'inscrito'
        AND e.estado = 'publicado'
        AND NOW() BETWEEN e.fecha_inicio AND e.fecha_fin
    ");
    $stmt->execute([$_SESSION['user_id'], $examenId, $_SESSION['user_id']]);
    $examen = $stmt->fetch();

    if (!$examen) {
        header('Location: dashboard.php');
        exit;
    }

    // Verificar intentos permitidos
    if ($examen['intentos_realizados'] >= $examen['intentos_permitidos']) {
        header('Location: materia.php?id=' . $examen['materia_id']);
        exit;
    }

    // Obtener preguntas del examen
    $stmt = $pdo->prepare("
        SELECT * FROM preguntas 
        WHERE examen_id = ? 
        ORDER BY orden ASC
    ");
    $stmt->execute([$examenId]);
    $preguntas = $stmt->fetchAll();

    // Procesar envío del examen
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->beginTransaction();

        $puntajeTotal = 0;
        $respuestasCorrectas = 0;

        foreach ($preguntas as $pregunta) {
            $respuestaUsuario = isset($_POST['respuesta_' . $pregunta['id']]) ? 
                               $_POST['respuesta_' . $pregunta['id']] : '';

            // Verificar si la respuesta es correcta según el tipo de pregunta
            $esCorrecta = false;
            switch ($pregunta['tipo']) {
                case 'multiple':
                case 'seleccion_multiple':
                    $respuestasCorrectas = json_decode($pregunta['respuesta_correcta'], true);
                    if (is_array($respuestaUsuario)) {
                        $esCorrecta = count(array_diff($respuestasCorrectas, $respuestaUsuario)) === 0 &&
                                    count(array_diff($respuestaUsuario, $respuestasCorrectas)) === 0;
                    } else {
                        $esCorrecta = $respuestaUsuario === $pregunta['respuesta_correcta'];
                    }
                    break;
                case 'abierta':
                    // Para preguntas abiertas, se marca como pendiente de revisión
                    $esCorrecta = null;
                    break;
                default:
                    $esCorrecta = $respuestaUsuario === $pregunta['respuesta_correcta'];
            }

            // Calcular puntaje
            $puntajeObtenido = $esCorrecta ? $pregunta['puntaje'] : 0;
            if ($esCorrecta === null) {
                $puntajeObtenido = null; // Pendiente de revisión
            }
            $puntajeTotal += $puntajeObtenido ?? 0;

            // Registrar respuesta
            $stmt = $pdo->prepare("
                INSERT INTO respuestas_examenes (
                    examen_id, user_id, pregunta_id, respuesta,
                    es_correcta, puntaje_obtenido, fecha_respuesta
                ) VALUES (
                    :examen_id, :user_id, :pregunta_id, :respuesta,
                    :es_correcta, :puntaje_obtenido, NOW()
                )
            ");

            $stmt->execute([
                'examen_id' => $examenId,
                'user_id' => $_SESSION['user_id'],
                'pregunta_id' => $pregunta['id'],
                'respuesta' => is_array($respuestaUsuario) ? json_encode($respuestaUsuario) : $respuestaUsuario,
                'es_correcta' => $esCorrecta,
                'puntaje_obtenido' => $puntajeObtenido
            ]);
        }

        $pdo->commit();
        $success = 'Examen completado correctamente. Puntaje obtenido: ' . $puntajeTotal;
        
        // Redirigir después de 3 segundos
        header("refresh:3;url=materia.php?id=" . $examen['materia_id']);
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = $e->getMessage();
    error_log("Error en realizar-examen.php: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Realizar Examen - E-Learning PLI</title>
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
                        <a href="materia.php?id=<?php echo $examen['materia_id']; ?>" class="text-gray-700">
                            <i class="fas fa-arrow-left mr-2"></i> Volver a la Materia
                        </a>
                    </div>
                </div>
                <!-- Temporizador -->
                <div class="flex items-center" id="timer">
                    Tiempo restante: <span class="ml-2 font-medium">00:00:00</span>
                </div>
            </div>
        </div>
    </nav>

    <!-- Contenido principal -->
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h1 class="text-2xl font-bold text-gray-900 mb-4">
                    <?php echo htmlspecialchars($examen['titulo']); ?>
                </h1>

                <!-- Información del examen -->
                <div class="mb-6 bg-gray-50 rounded-lg p-4">
                    <p class="text-sm text-gray-500">
                        Materia: <?php echo htmlspecialchars($examen['materia_nombre']); ?>
                    </p>
                    <p class="mt-1 text-gray-600">
                        <?php echo htmlspecialchars($examen['descripcion']); ?>
                    </p>
                    <div class="mt-2 text-sm text-gray-500">
                        <p>Duración: <?php echo $examen['duracion_minutos']; ?> minutos</p>
                        <p>Intentos permitidos: <?php echo $examen['intentos_permitidos']; ?></p>
                        <p>Intentos realizados: <?php echo $examen['intentos_realizados']; ?></p>
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

                <!-- Formulario del examen -->
                <form action="" method="POST" id="examenForm" class="space-y-8">
                    <?php foreach ($preguntas as $index => $pregunta): ?>
                        <div class="bg-gray-50 rounded-lg p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">
                                <?php echo ($index + 1) . '. ' . htmlspecialchars($pregunta['pregunta']); ?>
                            </h3>

                            <?php
                            switch ($pregunta['tipo']):
                                case 'multiple': 
                                    $opciones = json_decode($pregunta['opciones'], true);
                            ?>
                                <div class="space-y-4">
                                    <?php foreach ($opciones as $opcion): ?>
                                        <div class="flex items-center">
                                            <input type="radio" 
                                                id="opcion_<?php echo $pregunta['id'] . '_' . md5($opcion); ?>"
                                                name="respuesta_<?php echo $pregunta['id']; ?>"
                                                value="<?php echo htmlspecialchars($opcion); ?>"
                                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                            <label for="opcion_<?php echo $pregunta['id'] . '_' . md5($opcion); ?>"
                                                class="ml-3 block text-sm font-medium text-gray-700">
                                                <?php echo htmlspecialchars($opcion); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php
                                break;
                                case 'seleccion_multiple':
                                    $opciones = json_decode($pregunta['opciones'], true);
                            ?>
                                <div class="space-y-4">
                                    <?php foreach ($opciones as $opcion): ?>
                                        <div class="flex items-center">
                                            <input type="checkbox"
                                                id="opcion_<?php echo $pregunta['id'] . '_' . md5($opcion); ?>"
                                                name="respuesta_<?php echo $pregunta['id']; ?>[]"
                                                value="<?php echo htmlspecialchars($opcion); ?>"
                                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <label for="opcion_<?php echo $pregunta['id'] . '_' . md5($opcion); ?>"
                                                class="ml-3 block text-sm font-medium text-gray-700">
                                                <?php echo htmlspecialchars($opcion); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php
                                break;
                                case 'abierta':
                            ?>
                                <div>
                                    <textarea name="respuesta_<?php echo $pregunta['id']; ?>"
                                        rows="4"
                                        class="shadow-sm focus:ring-blue-500 focus:border-blue-500 mt-1 block w-full sm:text-sm border border-gray-300 rounded-md"
                                        placeholder="Escribe tu respuesta aquí"></textarea>
                                </div>
                            <?php
                                break;
                                case 'fecha':
                            ?>
                                <div>
                                    <input type="date"
                                        name="respuesta_<?php echo $pregunta['id']; ?>"
                                        class="shadow-sm focus:ring-blue-500 focus:border-blue-500 mt-1 block w-full sm:text-sm border border-gray-300 rounded-md">
                                </div>
                            <?php
                                break;
                                default:
                            ?>
                                <div>
                                    <input type="text"
                                        name="respuesta_<?php echo $pregunta['id']; ?>"
                                        class="shadow-sm focus:ring-blue-500 focus:border-blue-500 mt-1 block w-full sm:text-sm border border-gray-300 rounded-md"
                                        placeholder="Tu respuesta">
                                </div>
                            <?php endswitch; ?>

                            <div class="mt-2 text-sm text-gray-500">
                                Puntos: <?php echo $pregunta['puntaje']; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Botón de envío -->
                    <div class="flex justify-end">
                        <button type="submit" 
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-paper-plane mr-2"></i>
                            Enviar Examen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Temporizador
        const duracionMinutos = <?php echo $examen['duracion_minutos']; ?>;
        const tiempoFinal = new Date().getTime() + (duracionMinutos * 60 * 1000);
        
        function actualizarTemporizador() {
            const ahora = new Date().getTime();
            const diferencia = tiempoFinal - ahora;
            
            if (diferencia <= 0) {
                document.getElementById('examenForm').submit();
                return;
            }

            const horas = Math.floor((diferencia % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutos = Math.floor((diferencia % (1000 * 60 * 60)) / (1000 * 60));
            const segundos = Math.floor((diferencia % (1000 * 60)) / 1000);

            document.querySelector('#timer span').textContent = 
                `${String(horas).padStart(2, '0')}:${String(minutos).padStart(2, '0')}:${String(segundos).padStart(2, '0')}`;
        }

        setInterval(actualizarTemporizador, 1000);
        actualizarTemporizador();

        // Confirmación antes de enviar
        document.getElementById('examenForm').addEventListener('submit', function(e) {
            if (!confirm('¿Estás seguro de que deseas enviar el examen? No podrás modificar tus respuestas después.')) {
                e.preventDefault();
            }
        });

        // Guardar respuestas en localStorage
        function guardarRespuestas() {
            const form = document.getElementById('examenForm');
            const formData = new FormData(form);
            const respuestas = {};
            
            for (let [key, value] of formData.entries()) {
                respuestas[key] = value;
            }
            
            localStorage.setItem('examen_<?php echo $examenId; ?>', JSON.stringify(respuestas));
        }

        // Cargar respuestas guardadas
        function cargarRespuestas() {
            const respuestas = JSON.parse(localStorage.getItem('examen_<?php echo $examenId; ?>') || '{}');
            
            for (let key in respuestas) {
                const elementos = document.getElementsByName(key);
                if (elementos.length > 0) {
                    elementos[0].value = respuestas[key];
                }
            }
        }

        // Guardar respuestas cada 30 segundos
        setInterval(guardarRespuestas, 30000);

        // Cargar respuestas al iniciar
        document.addEventListener('DOMContentLoaded', cargarRespuestas);

        // Limpiar localStorage al enviar el formulario
        document.getElementById('examenForm').addEventListener('submit', function() {
            localStorage.removeItem('examen_<?php echo $examenId; ?>');
        });
    </script>
</body>
</html>
