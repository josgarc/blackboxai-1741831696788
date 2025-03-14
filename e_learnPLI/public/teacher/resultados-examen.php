<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

$auth = Auth::getInstance();

// Verificar autenticación y rol de profesor
if (!$auth->isAuthenticated() || !$auth->hasRole('Maestro')) {
    header('Location: ../login.php');
    exit;
}

$error = '';
$success = '';
$examenId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    // Verificar que el examen existe y pertenece a una materia del profesor
    $stmt = $pdo->prepare("
        SELECT e.*, m.nombre as materia_nombre, m.id as materia_id
        FROM examenes e
        INNER JOIN materias m ON e.materia_id = m.id
        INNER JOIN maestros_materias mm ON m.id = mm.materia_id
        WHERE e.id = ? AND mm.user_id = ?
    ");
    $stmt->execute([$examenId, $_SESSION['user_id']]);
    $examen = $stmt->fetch();

    if (!$examen) {
        header('Location: dashboard.php');
        exit;
    }

    // Obtener estadísticas generales
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT re.user_id) as total_estudiantes,
            AVG(re.puntaje_obtenido) as promedio_general,
            MIN(re.puntaje_obtenido) as puntaje_minimo,
            MAX(re.puntaje_obtenido) as puntaje_maximo,
            COUNT(CASE WHEN re.puntaje_obtenido >= e.puntaje_aprobatorio THEN 1 END) as aprobados,
            COUNT(CASE WHEN re.puntaje_obtenido < e.puntaje_aprobatorio THEN 1 END) as reprobados
        FROM respuestas_examenes re
        INNER JOIN examenes e ON re.examen_id = e.id
        WHERE e.id = ?
    ");
    $stmt->execute([$examenId]);
    $estadisticas = $stmt->fetch();

    // Obtener resultados por estudiante
    $stmt = $pdo->prepare("
        SELECT 
            u.id as estudiante_id,
            u.full_Name as estudiante_nombre,
            u.email as estudiante_email,
            re.puntaje_obtenido,
            re.fecha_inicio,
            re.fecha_fin,
            re.intento_numero,
            (SELECT COUNT(*) 
             FROM respuestas_preguntas rp 
             INNER JOIN preguntas_examen pe ON rp.pregunta_id = pe.id 
             WHERE rp.respuesta_examen_id = re.id AND rp.es_correcta = 1) as respuestas_correctas,
            (SELECT COUNT(*) 
             FROM preguntas_examen 
             WHERE examen_id = ?) as total_preguntas
        FROM respuestas_examenes re
        INNER JOIN users u ON re.user_id = u.id
        WHERE re.examen_id = ?
        ORDER BY re.puntaje_obtenido DESC, re.fecha_fin ASC
    ");
    $stmt->execute([$examenId, $examenId]);
    $resultados = $stmt->fetchAll();

    // Obtener estadísticas por pregunta
    $stmt = $pdo->prepare("
        SELECT 
            pe.id,
            pe.pregunta,
            pe.tipo,
            COUNT(DISTINCT rp.respuesta_examen_id) as total_respuestas,
            COUNT(CASE WHEN rp.es_correcta = 1 THEN 1 END) as respuestas_correctas
        FROM preguntas_examen pe
        LEFT JOIN respuestas_preguntas rp ON pe.id = rp.pregunta_id
        WHERE pe.examen_id = ?
        GROUP BY pe.id
        ORDER BY pe.orden
    ");
    $stmt->execute([$examenId]);
    $estadisticasPreguntas = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Error en resultados-examen.php: " . $e->getMessage());
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultados del Examen - <?php echo htmlspecialchars($examen['titulo']); ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        <!-- Información del examen -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <h2 class="text-2xl font-bold text-gray-900">
                    <?php echo htmlspecialchars($examen['titulo']); ?>
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    <?php echo htmlspecialchars($examen['materia_nombre']); ?>
                </p>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div>
                        <span class="block text-sm font-medium text-gray-500">Fecha de inicio</span>
                        <span class="block mt-1 text-sm text-gray-900">
                            <?php echo date('d/m/Y H:i', strtotime($examen['fecha_inicio'])); ?>
                        </span>
                    </div>
                    <div>
                        <span class="block text-sm font-medium text-gray-500">Fecha de fin</span>
                        <span class="block mt-1 text-sm text-gray-900">
                            <?php echo date('d/m/Y H:i', strtotime($examen['fecha_fin'])); ?>
                        </span>
                    </div>
                    <div>
                        <span class="block text-sm font-medium text-gray-500">Duración</span>
                        <span class="block mt-1 text-sm text-gray-900">
                            <?php echo $examen['duracion']; ?> minutos
                        </span>
                    </div>
                    <div>
                        <span class="block text-sm font-medium text-gray-500">Puntaje aprobatorio</span>
                        <span class="block mt-1 text-sm text-gray-900">
                            <?php echo $examen['puntaje_aprobatorio']; ?> puntos
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas generales -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 mb-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">
                        Estudiantes que realizaron el examen
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900">
                        <?php echo $estadisticas['total_estudiantes']; ?>
                    </dd>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">
                        Promedio general
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900">
                        <?php echo number_format($estadisticas['promedio_general'], 1); ?>
                    </dd>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">
                        Índice de aprobación
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900">
                        <?php 
                        $porcentajeAprobacion = $estadisticas['total_estudiantes'] > 0 
                            ? ($estadisticas['aprobados'] / $estadisticas['total_estudiantes']) * 100 
                            : 0;
                        echo number_format($porcentajeAprobacion, 1) . '%';
                        ?>
                    </dd>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 mb-6">
            <!-- Distribución de calificaciones -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        Distribución de Calificaciones
                    </h3>
                    <canvas id="grafico-distribucion"></canvas>
                </div>
            </div>

            <!-- Porcentaje de aciertos por pregunta -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        Porcentaje de Aciertos por Pregunta
                    </h3>
                    <canvas id="grafico-preguntas"></canvas>
                </div>
            </div>
        </div>

        <!-- Resultados por estudiante -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    Resultados por Estudiante
                </h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estudiante
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Calificación
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Respuestas Correctas
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Tiempo Utilizado
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Intento
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($resultados as $resultado): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <img class="h-10 w-10 rounded-full" 
                                                src="https://ui-avatars.com/api/?name=<?php echo urlencode($resultado['estudiante_nombre']); ?>&background=random" 
                                                alt="">
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($resultado['estudiante_nombre']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($resultado['estudiante_email']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $resultado['puntaje_obtenido'] >= $examen['puntaje_aprobatorio'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $resultado['puntaje_obtenido']; ?>/100
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $resultado['respuestas_correctas']; ?>/<?php echo $resultado['total_preguntas']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php 
                                    $tiempoUtilizado = strtotime($resultado['fecha_fin']) - strtotime($resultado['fecha_inicio']);
                                    echo floor($tiempoUtilizado / 60) . ' minutos';
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $resultado['intento_numero']; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($resultados)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No hay resultados disponibles
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Datos para los gráficos
        const resultados = <?php echo json_encode($resultados); ?>;
        const estadisticasPreguntas = <?php echo json_encode($estadisticasPreguntas); ?>;

        // Gráfico de distribución de calificaciones
        const distribucionCtx = document.getElementById('grafico-distribucion').getContext('2d');
        const rangos = ['0-20', '21-40', '41-60', '61-80', '81-100'];
        const distribucion = new Array(5).fill(0);

        resultados.forEach(resultado => {
            const puntaje = resultado.puntaje_obtenido;
            const indice = Math.floor(puntaje / 20);
            distribucion[Math.min(indice, 4)]++;
        });

        new Chart(distribucionCtx, {
            type: 'bar',
            data: {
                labels: rangos,
                datasets: [{
                    label: 'Número de estudiantes',
                    data: distribucion,
                    backgroundColor: 'rgba(59, 130, 246, 0.5)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Gráfico de aciertos por pregunta
        const preguntasCtx = document.getElementById('grafico-preguntas').getContext('2d');
        const porcentajesAcierto = estadisticasPreguntas.map(pregunta => {
            return pregunta.total_respuestas > 0 
                ? (pregunta.respuestas_correctas / pregunta.total_respuestas) * 100 
                : 0;
        });

        new Chart(preguntasCtx, {
            type: 'bar',
            data: {
                labels: estadisticasPreguntas.map((_, index) => `Pregunta ${index + 1}`),
                datasets: [{
                    label: 'Porcentaje de aciertos',
                    data: porcentajesAcierto,
                    backgroundColor: 'rgba(16, 185, 129, 0.5)',
                    borderColor: 'rgb(16, 185, 129)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: value => value + '%'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
