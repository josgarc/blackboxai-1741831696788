<?php
function renderContenido($contenido) {
    $html = '';
    switch ($contenido['tipo']) {
        case 'documento':
            $html = sprintf(
                '<a href="%s" target="_blank" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-file-pdf mr-2"></i>Ver documento
                </a>',
                htmlspecialchars($contenido['archivo'])
            );
            break;

        case 'video':
            // Extraer ID del video de YouTube/Vimeo
            $videoId = '';
            if (strpos($contenido['contenido'], 'youtube.com') !== false || strpos($contenido['contenido'], 'youtu.be') !== false) {
                preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $contenido['contenido'], $matches);
                $videoId = $matches[1];
                $html = sprintf(
                    '<iframe width="560" height="315" src="https://www.youtube.com/embed/%s" 
                        frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                        allowfullscreen class="rounded-lg shadow-lg"></iframe>',
                    htmlspecialchars($videoId)
                );
            } elseif (strpos($contenido['contenido'], 'vimeo.com') !== false) {
                preg_match('/vimeo\.com\/([0-9]+)/', $contenido['contenido'], $matches);
                $videoId = $matches[1];
                $html = sprintf(
                    '<iframe src="https://player.vimeo.com/video/%s" width="560" height="315" 
                        frameborder="0" allow="autoplay; fullscreen" allowfullscreen 
                        class="rounded-lg shadow-lg"></iframe>',
                    htmlspecialchars($videoId)
                );
            }
            break;

        case 'enlace':
            $html = sprintf(
                '<a href="%s" target="_blank" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-external-link-alt mr-2"></i>%s
                </a>',
                htmlspecialchars($contenido['contenido']),
                htmlspecialchars($contenido['titulo'])
            );
            break;

        case 'texto':
            $html = $contenido['contenido']; // El contenido ya está sanitizado por TinyMCE
            break;

        case 'acordeon':
            $html = sprintf(
                '<div class="border rounded-lg mb-4 shadow-sm hover:shadow-md transition-shadow duration-200">
                    <button class="w-full text-left px-4 py-3 bg-gray-50 hover:bg-gray-100 focus:outline-none flex justify-between items-center" 
                        onclick="toggleAcordeon(this)" aria-expanded="false">
                        <span class="font-medium">%s</span>
                        <i class="fas fa-chevron-down transition-transform duration-200"></i>
                    </button>
                    <div class="hidden px-4 py-3 bg-white" aria-hidden="true">%s</div>
                </div>',
                htmlspecialchars($contenido['titulo']),
                $contenido['contenido'] // El contenido ya está sanitizado por TinyMCE
            );
            break;

        case 'imagen':
            $datos = json_decode($contenido['contenido'], true);
            $html = sprintf(
                '<figure class="mb-4">
                    <img src="%s" alt="%s" class="max-w-full h-auto rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-200">
                    %s
                </figure>',
                htmlspecialchars($contenido['archivo']),
                htmlspecialchars($datos['descripcion'] ?? $contenido['titulo']),
                !empty($datos['descripcion']) ? sprintf(
                    '<figcaption class="text-sm text-gray-600 mt-2 text-center">%s</figcaption>',
                    htmlspecialchars($datos['descripcion'])
                ) : ''
            );
            break;

        case 'boton':
            $datos = json_decode($contenido['contenido'], true);
            $html = sprintf(
                '<a href="%s" target="_blank" 
                    class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg 
                    transition-colors duration-200 shadow-md hover:shadow-lg">
                    %s
                    <span>%s</span>
                </a>',
                htmlspecialchars($contenido['contenido']),
                !empty($datos['icono']) ? sprintf('<i class="%s mr-2"></i>', htmlspecialchars($datos['icono'])) : '',
                htmlspecialchars($datos['texto'] ?? 'Click aquí')
            );
            break;
    }
    return $html;
}

// Función JavaScript para manejar los acordeones
$script = <<<'JS'
<script>
function toggleAcordeon(button) {
    const content = button.nextElementSibling;
    const icon = button.querySelector('i');
    const isExpanded = button.getAttribute('aria-expanded') === 'true';
    
    // Actualizar atributos ARIA
    button.setAttribute('aria-expanded', !isExpanded);
    content.setAttribute('aria-hidden', isExpanded);
    
    // Toggle visibilidad y rotación del icono
    content.classList.toggle('hidden');
    icon.style.transform = !isExpanded ? 'rotate(180deg)' : 'rotate(0deg)';
}
</script>
JS;

// Asegurarse de que el script se incluya solo una vez
if (!defined('ACORDEON_SCRIPT_INCLUDED')) {
    echo $script;
    define('ACORDEON_SCRIPT_INCLUDED', true);
}
?>
