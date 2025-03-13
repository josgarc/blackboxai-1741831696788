<?php
$errorCode = isset($_GET['code']) ? (int)$_GET['code'] : 404;

$errorMessages = [
    400 => 'Solicitud incorrecta',
    401 => 'No autorizado',
    403 => 'Acceso denegado',
    404 => 'Página no encontrada',
    500 => 'Error interno del servidor'
];

$errorDescriptions = [
    400 => 'La solicitud no pudo ser procesada debido a un error en la sintaxis.',
    401 => 'Es necesario autenticarse para obtener acceso a este recurso.',
    403 => 'No tienes permiso para acceder a este recurso.',
    404 => 'Lo sentimos, la página que estás buscando no existe.',
    500 => 'Ha ocurrido un error interno en el servidor. Por favor, inténtalo más tarde.'
];

$errorMessage = $errorMessages[$errorCode] ?? 'Error desconocido';
$errorDescription = $errorDescriptions[$errorCode] ?? 'Ha ocurrido un error inesperado.';

// Establecer el código de estado HTTP correcto
http_response_code($errorCode);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $errorMessage; ?> - E-Learning PLI</title>
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
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-xl w-full mx-auto px-4">
        <div class="text-center">
            <!-- Icono de error -->
            <div class="mb-8">
                <?php if ($errorCode === 404): ?>
                    <i class="fas fa-search text-8xl text-blue-500"></i>
                <?php elseif ($errorCode === 403): ?>
                    <i class="fas fa-lock text-8xl text-red-500"></i>
                <?php elseif ($errorCode === 500): ?>
                    <i class="fas fa-exclamation-triangle text-8xl text-yellow-500"></i>
                <?php else: ?>
                    <i class="fas fa-exclamation-circle text-8xl text-gray-500"></i>
                <?php endif; ?>
            </div>

            <!-- Código de error -->
            <h1 class="text-6xl font-bold text-gray-900 mb-4">
                <?php echo $errorCode; ?>
            </h1>

            <!-- Mensaje de error -->
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">
                <?php echo htmlspecialchars($errorMessage); ?>
            </h2>

            <!-- Descripción del error -->
            <p class="text-gray-500 mb-8">
                <?php echo htmlspecialchars($errorDescription); ?>
            </p>

            <!-- Botones de acción -->
            <div class="space-x-4">
                <a href="javascript:history.back()" 
                   class="inline-flex items-center px-4 py-2 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Volver atrás
                </a>
                <a href="/" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fas fa-home mr-2"></i>
                    Ir al inicio
                </a>
            </div>

            <!-- Información adicional -->
            <div class="mt-8 text-sm text-gray-500">
                <p>Si el problema persiste, por favor contacta al soporte técnico:</p>
                <a href="mailto:soporte@e-learningpli.com" class="text-blue-600 hover:text-blue-800">
                    soporte@e-learningpli.com
                </a>
            </div>
        </div>
    </div>

    <!-- Script para reportar errores (opcional) -->
    <script>
        // Reportar error al servidor de analytics (implementar según necesidades)
        function reportError() {
            const errorData = {
                code: <?php echo $errorCode; ?>,
                url: window.location.href,
                referrer: document.referrer,
                userAgent: navigator.userAgent
            };

            // Implementar lógica de reporte de errores aquí
            console.log('Error reportado:', errorData);
        }

        // Reportar error cuando la página carga
        window.addEventListener('load', reportError);
    </script>
</body>
</html>
