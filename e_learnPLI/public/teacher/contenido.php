<?php
// ... [mantener todo el código PHP existente hasta el script] ...
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contenido - <?php echo htmlspecialchars($materia['nombre']); ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Sortable.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <!-- TinyMCE CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.7.2/tinymce.min.js" integrity="sha512-AE0-H6Vz19/Hf0XBgF32NKt6SnFXtEgQuBYGXeSWI+SSNC0k8XJwVSbYlBHMeXkqxBnNSE3TNl3pqgB3PsE5A==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        .sortable-ghost {
            opacity: 0.4;
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

    <?php require_once 'render_contenido.php'; ?>
    <!-- Contenido principal -->
    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Lista de temas y contenidos -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-lg font-medium text-gray-900">Contenido del Curso</h2>
                    <button onclick="mostrarFormularioTema()" 
                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i>
                        Nuevo Tema
                    </button>
                </div>

                <!-- Formulario para nuevo tema -->
                <div id="formulario-tema" class="hidden mb-6 bg-gray-50 rounded-lg p-4 border">
                    <form id="form-tema" class="space-y-4">
                        <div>
                            <label for="titulo_tema" class="block text-sm font-medium text-gray-700">
                                Título del Tema
                            </label>
                            <input type="text" name="titulo" id="titulo_tema" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="descripcion_tema" class="block text-sm font-medium text-gray-700">
                                Descripción (opcional)
                            </label>
                            <textarea name="descripcion" id="descripcion_tema" rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"></textarea>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="ocultarFormularioTema()"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                Cancelar
                            </button>
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                                Guardar Tema
                            </button>
                        </div>
                    </form>
                </div>
                <div id="temas-lista" class="space-y-4" data-materia-id="<?php echo $materiaId; ?>">
                    <?php
                    // Obtener temas y contenidos
                    $stmt = $pdo->prepare("
                        SELECT t.*, 
                               (SELECT COUNT(*) FROM contenidos WHERE tema_id = t.id) as total_contenidos
                        FROM temas t 
                        WHERE t.materia_id = ? 
                        ORDER BY t.orden
                    ");
                    $stmt->execute([$materiaId]);
                    $temas = $stmt->fetchAll();

                    foreach ($temas as $tema):
                        // Obtener contenidos del tema
                        $stmt = $pdo->prepare("
                            SELECT * FROM contenidos 
                            WHERE tema_id = ? 
                            ORDER BY orden
                        ");
                        $stmt->execute([$tema['id']]);
                        $contenidos = $stmt->fetchAll();
                    ?>
                    <div class="border rounded-lg p-4 bg-gray-50 tema-item" data-id="<?php echo $tema['id']; ?>">
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex items-center">
                                <span class="cursor-move handle-tema mr-2">
                                    <i class="fas fa-grip-vertical text-gray-400"></i>
                                </span>
                                <h3 class="text-lg font-medium text-gray-900">
                                    <?php echo htmlspecialchars($tema['titulo']); ?>
                                </h3>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button onclick="editarTema(<?php echo $tema['id']; ?>)" 
                                        class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="eliminarTema(<?php echo $tema['id']; ?>)" 
                                        class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button onclick="mostrarFormularioContenido(<?php echo $tema['id']; ?>)" 
                                        class="inline-flex items-center px-3 py-1 border border-transparent rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                                    <i class="fas fa-plus mr-2"></i>
                                    Agregar Contenido
                                </button>
                            </div>
                        </div>
                        <?php if (!empty($tema['descripcion'])): ?>
                        <p class="text-sm text-gray-600 mb-4">
                            <?php echo htmlspecialchars($tema['descripcion']); ?>
                        </p>
                        <?php endif; ?>
                        
                        <!-- Lista de contenidos del tema -->
                        <div class="space-y-3 mt-4 contenidos-lista" data-tema-id="<?php echo $tema['id']; ?>">
                            <?php foreach ($contenidos as $contenido): ?>
                            <div class="bg-white rounded-lg shadow p-4 contenido-item" data-id="<?php echo $contenido['id']; ?>">
                                <div class="flex justify-between items-start mb-2">
                                    <h4 class="text-md font-medium text-gray-900 cursor-move handle">
                                        <i class="fas fa-grip-vertical text-gray-400 mr-2"></i>
                                        <?php echo htmlspecialchars($contenido['titulo']); ?>
                                    </h4>
                                    <div class="flex space-x-2">
                                        <button onclick="editarContenido(<?php echo $contenido['id']; ?>)" 
                                                class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="eliminarContenido(<?php echo $contenido['id']; ?>)" 
                                                class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="prose max-w-none">
                                    <?php echo renderContenido($contenido); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <script>
                            // Inicializar Sortable para esta lista de contenidos
                            new Sortable(document.querySelector('.contenidos-lista[data-tema-id="<?php echo $tema['id']; ?>"]'), {
                                handle: '.handle',
                                animation: 150,
                                ghostClass: 'bg-blue-100',
                                onEnd: function(evt) {
                                    actualizarOrdenContenido(evt.item.getAttribute('data-id'), evt.newIndex, <?php echo $tema['id']; ?>);
                                }
                            });
                        </script>
                    </div>
                    <?php endforeach; ?>
                </div>
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

        <!-- Formulario de contenido -->
        <div class="bg-white shadow rounded-lg hidden" id="formulario-contenido">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    Nuevo Contenido
                </h3>
                <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="accion" value="agregar_contenido">
                    <input type="hidden" name="tema_id" id="tema_id">
                    <div>
                        <label for="titulo_contenido" class="block text-sm font-medium text-gray-700">
                            Título
                        </label>
                        <input type="text" name="titulo" id="titulo_contenido" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    </div>
                    <div>
                        <label for="tipo" class="block text-sm font-medium text-gray-700">
                            Tipo de contenido
                        </label>
                        <select name="tipo" id="tipo" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <option value="">Seleccionar tipo</option>
                            <option value="documento">Documento PDF/Word</option>
                            <option value="video">Video (URL de YouTube/Vimeo)</option>
                            <option value="enlace">Enlace web</option>
                            <option value="texto">Texto enriquecido</option>
                            <option value="imagen">Imagen</option>
                            <option value="acordeon">Acordeón (Contenido colapsable)</option>
                            <option value="boton">Botón de redirección</option>
                        </select>
                    </div>
                    <div id="campo-contenido" class="hidden">
                        <label for="contenido" class="block text-sm font-medium text-gray-700">
                            Contenido
                        </label>
                        <textarea name="contenido" id="contenido"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                            placeholder="Para videos: Pega la URL del video (YouTube/Vimeo)&#10;Para enlaces: Ingresa la URL completa&#10;Para texto: Escribe o pega el contenido"></textarea>
                    </div>
                    <div id="campo-archivo" class="hidden">
                        <label for="archivo" class="block text-sm font-medium text-gray-700">
                            Archivo (PDF, DOC, DOCX)
                        </label>
                        <input type="file" name="archivo" id="archivo"
                            accept=".pdf,.doc,.docx"
                            class="mt-1 block w-full text-sm text-gray-500
                                file:mr-4 file:py-2 file:px-4
                                file:rounded-full file:border-0
                                file:text-sm file:font-semibold
                                file:bg-blue-50 file:text-blue-700
                                hover:file:bg-blue-100">
                    </div>
                    <input type="hidden" name="orden" value="0">
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="ocultarFormularioContenido()"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Mensaje de notificación -->
    <div id="mensaje" class="hidden fixed top-4 right-4 p-4 rounded-lg z-50 transform transition-all duration-300"></div>

    <!-- Loading indicator -->
    <div id="loading" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center backdrop-blur-sm transition-all duration-300">
        <div class="bg-white p-6 rounded-xl flex items-center space-x-4 shadow-2xl transform transition-all scale-90 opacity-0" id="loading-content">
            <div class="relative">
                <div class="animate-spin rounded-full h-8 w-8 border-4 border-blue-500 border-t-transparent"></div>
                <div class="absolute inset-0 animate-ping rounded-full h-8 w-8 border-4 border-blue-500 opacity-20"></div>
            </div>
            <span class="text-gray-700 text-lg font-medium">Procesando...</span>
        </div>
    </div>

    <script>
        // Variables globales
        let currentContenidoId = null;
        let currentTemaId = null;
        let editor = null;

        // Funciones de utilidad
        function mostrarLoading() {
            const loading = document.getElementById('loading');
            const content = document.getElementById('loading-content');
            loading.classList.remove('hidden');
            // Dar tiempo al DOM para actualizar antes de añadir las clases de animación
            requestAnimationFrame(() => {
                content.classList.remove('scale-90', 'opacity-0');
                content.classList.add('scale-100', 'opacity-100');
            });
        }

        function ocultarLoading() {
            const loading = document.getElementById('loading');
            const content = document.getElementById('loading-content');
            content.classList.remove('scale-100', 'opacity-100');
            content.classList.add('scale-90', 'opacity-0');
            // Esperar a que termine la animación antes de ocultar
            setTimeout(() => {
                loading.classList.add('hidden');
            }, 300);
        }

        function mostrarMensaje(mensaje, tipo) {
            const mensajeDiv = document.getElementById('mensaje');
            mensajeDiv.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${tipo === 'success' ? 'fa-check-circle text-green-200' : 'fa-exclamation-circle text-red-200'} mr-3"></i>
                    <span>${mensaje}</span>
                </div>
            `;
            mensajeDiv.className = `fixed top-4 right-4 p-4 rounded-lg z-50 transform transition-all duration-300 shadow-lg ${
                tipo === 'success' 
                    ? 'bg-green-600 text-white border border-green-700' 
                    : 'bg-red-600 text-white border border-red-700'
            }`;
            
            // Mostrar el mensaje con una animación de deslizamiento
            mensajeDiv.style.opacity = '0';
            mensajeDiv.style.transform = 'translateX(100%)';
            mensajeDiv.style.display = 'block';
            
            requestAnimationFrame(() => {
                mensajeDiv.style.opacity = '1';
                mensajeDiv.style.transform = 'translateX(0)';
            });
            
            // Ocultar después de 3 segundos con una animación
            setTimeout(() => {
                mensajeDiv.style.opacity = '0';
                mensajeDiv.style.transform = 'translateX(100%)';
                setTimeout(() => mensajeDiv.style.display = 'none', 300);
            }, 3000);
        }

        // Funciones para gestión de temas
        function editarTema(temaId) {
            currentTemaId = temaId;
            mostrarLoading();
            // Obtener datos del tema mediante fetch
            fetch(`procesar_contenido.php?accion=obtener_tema&id=${temaId}`)
                .then(response => response.json())
                .finally(() => ocultarLoading())
                .then(data => {
                    if (data.success) {
                        const tema = data.tema;
                        document.getElementById('titulo_tema').value = tema.titulo;
                        document.getElementById('descripcion_tema').value = tema.descripcion || '';
                        document.getElementById('formulario-tema').classList.remove('hidden');
                    } else {
                        mostrarMensaje(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarMensaje('Error de conexión al cargar el tema. Por favor, inténtelo de nuevo.', 'error');
                })
                .finally(() => ocultarLoading());
        }

        function eliminarTema(temaId) {
            if (confirm('¿Está seguro de eliminar este tema y todo su contenido?')) {
                mostrarLoading();
                const formData = new FormData();
                formData.append('accion', 'eliminar_tema');
                formData.append('tema_id', temaId);

                fetch('procesar_contenido.php', {
                    method: 'POST',
                    body: formData
                })
                .finally(() => ocultarLoading())
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarMensaje(data.message, 'success');
                        location.reload();
                    } else {
                        mostrarMensaje(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarMensaje('Error de conexión al eliminar el tema. Por favor, inténtelo de nuevo.', 'error');
                })
                .finally(() => ocultarLoading());
            }
        }

        // Inicializar Sortable para la lista de temas
        new Sortable(document.getElementById('temas-lista'), {
            handle: '.handle-tema',
            animation: 150,
            ghostClass: 'bg-blue-100',
            onEnd: function(evt) {
                actualizarOrdenTema(evt.item.getAttribute('data-id'), evt.newIndex);
            }
        });

        // Función para actualizar el orden de los temas
        function actualizarOrdenTema(temaId, newIndex) {
            mostrarLoading();
            const formData = new FormData();
            formData.append('accion', 'actualizar_orden_tema');
            formData.append('tema_id', temaId);
            formData.append('nuevo_orden', newIndex);
            formData.append('materia_id', document.getElementById('temas-lista').getAttribute('data-materia-id'));

            fetch('procesar_contenido.php', {
                method: 'POST',
                body: formData
            })
            .finally(() => ocultarLoading())
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    mostrarMensaje(data.message, 'error');
                    location.reload(); // Recargar si hay error para restaurar el orden
                }
            })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarMensaje('Error de conexión al actualizar el orden. Por favor, inténtelo de nuevo.', 'error');
                    location.reload(); // Recargar si hay error para restaurar el orden
                })
                .finally(() => ocultarLoading());
        }

        // Funciones para gestión de contenido
        function editarContenido(contenidoId) {
            currentContenidoId = contenidoId;
            mostrarLoading();
            // Obtener datos del contenido mediante fetch
            fetch(`procesar_contenido.php?accion=obtener_contenido&id=${contenidoId}`)
                .then(response => response.json())
                .finally(() => ocultarLoading())
                .then(data => {
                    if (data.success) {
                        const contenido = data.contenido;
                        document.getElementById('titulo_contenido').value = contenido.titulo;
                        document.getElementById('tipo').value = contenido.tipo;
                        document.getElementById('contenido').value = contenido.contenido;
                        document.getElementById('formulario-contenido').classList.remove('hidden');
                        toggleEditor(contenido.tipo);
                    } else {
                        mostrarMensaje(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarMensaje('Error de conexión al cargar el contenido. Por favor, inténtelo de nuevo.', 'error');
                })
                .finally(() => ocultarLoading());
        }

        function eliminarContenido(contenidoId) {
            if (confirm('¿Está seguro de eliminar este contenido?')) {
                mostrarLoading();
                const formData = new FormData();
                formData.append('accion', 'eliminar_contenido');
                formData.append('id', contenidoId);

                fetch('procesar_contenido.php', {
                    method: 'POST',
                    body: formData
                })
                .finally(() => ocultarLoading())
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarMensaje(data.message, 'success');
                        location.reload();
                    } else {
                        mostrarMensaje(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarMensaje('Error de conexión al eliminar el contenido. Por favor, inténtelo de nuevo.', 'error');
                })
                .finally(() => ocultarLoading());
            }
        }

        function actualizarOrdenContenido(contenidoId, newIndex, temaId) {
            mostrarLoading();
            const formData = new FormData();
            formData.append('accion', 'actualizar_orden');
            formData.append('contenido_id', contenidoId);
            formData.append('tema_id', temaId);
            formData.append('nuevo_orden', newIndex);

            fetch('procesar_contenido.php', {
                method: 'POST',
                body: formData
            })
            .finally(() => ocultarLoading())
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    mostrarMensaje(data.message, 'error');
                    location.reload(); // Recargar si hay error para restaurar el orden
                }
            })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarMensaje('Error de conexión al actualizar el orden. Por favor, inténtelo de nuevo.', 'error');
                    location.reload(); // Recargar si hay error para restaurar el orden
                })
                .finally(() => ocultarLoading());
        }

        // Funciones para gestión de temas
        function mostrarFormularioTema() {
            document.getElementById('formulario-tema').classList.remove('hidden');
            document.getElementById('form-tema').reset();
        }

        // Función para limpiar formularios
        function limpiarFormulario(tipo) {
            if (tipo === 'tema') {
                document.getElementById('titulo_tema').value = '';
                document.getElementById('descripcion_tema').value = '';
                document.getElementById('formulario-tema').classList.add('hidden');
                currentTemaId = null;
            } else if (tipo === 'contenido') {
                document.querySelector('.bg-white.shadow.rounded-lg').classList.add('hidden');
                document.getElementById('titulo_contenido').value = '';
                document.getElementById('tipo').value = '';
                document.getElementById('contenido').value = '';
                document.getElementById('archivo').value = '';
                document.getElementById('campo-contenido').classList.add('hidden');
                document.getElementById('campo-archivo').classList.add('hidden');
                currentTemaId = null;
                currentContenidoId = null;
                if (editor) {
                    tinymce.remove('#contenido');
                    editor = null;
                }
            }
        }

        function ocultarFormularioTema() {
            limpiarFormulario('tema');
        }

        // Función para validar el formulario de tema
        function validarFormularioTema(titulo) {
            let validacion = {
                isValid: true,
                mensaje: ''
            };

            if (!titulo || !titulo.trim()) {
                validacion = {
                    isValid: false,
                    mensaje: 'El título del tema es obligatorio'
                };
            } else if (titulo.trim().length < 3) {
                validacion = {
                    isValid: false,
                    mensaje: 'El título debe tener al menos 3 caracteres'
                };
            } else if (titulo.trim().length > 100) {
                validacion = {
                    isValid: false,
                    mensaje: 'El título no puede exceder los 100 caracteres'
                };
            }

            return validacion;
        }

        // Función para manejar envíos de formularios
        async function enviarFormulario(formData, tipo) {
            try {
                mostrarLoading();
                const response = await fetch('procesar_contenido.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    mostrarMensaje(result.message, 'success');
                    limpiarFormulario(tipo);
                    location.reload();
                } else {
                    mostrarMensaje(result.message, 'error');
                }
            } catch (error) {
                mostrarMensaje('Error de conexión. Por favor, inténtelo de nuevo.', 'error');
                console.error('Error:', error);
            } finally {
                ocultarLoading();
            }
        }

        // Manejar envío del formulario de tema
        document.getElementById('form-tema').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const titulo = this.querySelector('[name="titulo"]').value;
            const validacion = validarFormularioTema(titulo);
            
            if (!validacion.isValid) {
                mostrarMensaje(validacion.mensaje, 'error');
                return;
            }

            const formData = new FormData(this);
            formData.append('materia_id', '<?php echo $materiaId; ?>');
            formData.append('accion', currentTemaId ? 'editar_tema' : 'agregar_tema');
            if (currentTemaId) {
                formData.append('tema_id', currentTemaId);
            }
            
            await enviarFormulario(formData, 'tema');
        });

        // Funciones para gestión de contenido
        function mostrarFormularioContenido(temaId) {
            currentTemaId = temaId;
            document.getElementById('tema_id').value = temaId;
            document.querySelector('.bg-white.shadow.rounded-lg').classList.remove('hidden');
            document.getElementById('tipo').value = '';
            document.getElementById('titulo_contenido').value = '';
            document.getElementById('campo-contenido').classList.add('hidden');
            document.getElementById('campo-archivo').classList.add('hidden');
            if (editor) {
                tinymce.remove('#contenido');
                editor = null;
            }
        }

        function ocultarFormularioContenido() {
            limpiarFormulario('contenido');
        }

        function ocultarFormularioTema() {
            limpiarFormulario('tema');
        }

        // Función para inicializar o destruir TinyMCE según el tipo de contenido
        function toggleEditor(tipo) {
            const contenidoField = document.getElementById('contenido');
            const campoContenido = document.getElementById('campo-contenido');
            
            // Destruir editor existente si hay uno
            if (editor) {
                tinymce.remove('#contenido');
                editor = null;
            }

            if (tipo === 'texto' || tipo === 'acordeon') {
                // Inicializar TinyMCE para texto enriquecido y acordeón
                editor = tinymce.init({
                    selector: '#contenido',
                    height: 400,
                    plugins: [
                        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                        'searchreplace', 'visualblocks', 'code', 'fullscreen', 'insertdatetime',
                        'media', 'table', 'help', 'wordcount', 'accordion'
                    ],
                    toolbar: 'undo redo | formatselect | ' +
                            'bold italic backcolor | alignleft aligncenter ' +
                            'alignright alignjustify | bullist numlist outdent indent | ' +
                            'link image media | accordion accordionremove | ' +
                            'removeformat | help',
                    content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif; font-size: 14px; }',
                    extended_valid_elements: 'button[class|id|onclick],i[class],div[class|id|data-*]',
                    images_upload_url: 'procesar_contenido.php',
                    automatic_uploads: true,
                    file_picker_types: 'image',
                    file_picker_callback: function(cb, value, meta) {
                        var input = document.createElement('input');
                        input.setAttribute('type', 'file');
                        input.setAttribute('accept', 'image/*');
                        input.onchange = function() {
                            var file = this.files[0];
                            var reader = new FileReader();
                            reader.onload = function () {
                                var id = 'blobid' + (new Date()).getTime();
                                var blobCache =  tinymce.activeEditor.editorUpload.blobCache;
                                var base64 = reader.result.split(',')[1];
                                var blobInfo = blobCache.create(id, file, base64);
                                blobCache.add(blobInfo);
                                cb(blobInfo.blobUri(), { title: file.name });
                            };
                            reader.readAsDataURL(file);
                        };
                        input.click();
                    }
                });
            }

            // Personalizar el campo según el tipo
            switch(tipo) {
                case 'imagen':
                    campoContenido.classList.remove('hidden');
                    contenidoField.placeholder = 'URL de la imagen (opcional)';
                    break;
                case 'boton':
                    campoContenido.classList.remove('hidden');
                    contenidoField.placeholder = 'URL de redirección';
                    // Agregar campo para el texto del botón
                    if (!document.getElementById('boton-texto')) {
                        const botonTextoDiv = document.createElement('div');
                        botonTextoDiv.id = 'boton-texto';
                        botonTextoDiv.innerHTML = `
                            <label class="block text-sm font-medium text-gray-700 mt-4">
                                Texto del botón
                            </label>
                            <input type="text" name="boton_texto" 
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                placeholder="Texto a mostrar en el botón">
                            <label class="block text-sm font-medium text-gray-700 mt-4">
                                Icono (opcional)
                            </label>
                            <input type="text" name="boton_icono" 
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                placeholder="Clase de Font Awesome (ej: fas fa-arrow-right)">
                        `;
                        campoContenido.appendChild(botonTextoDiv);
                    }
                    break;
            }
        }

        // Mostrar/ocultar campos según el tipo de contenido
        document.getElementById('tipo').addEventListener('change', function() {
            const tipo = this.value;
            const campoContenido = document.getElementById('campo-contenido');
            const campoArchivo = document.getElementById('campo-archivo');
            const contenidoField = document.getElementById('contenido');
            const botonTexto = document.getElementById('boton-texto');

            // Ocultar todos los campos primero
            campoContenido.classList.add('hidden');
            campoArchivo.classList.add('hidden');
            if (botonTexto) botonTexto.remove();

            // Limpiar campos
            contenidoField.value = '';
            document.getElementById('archivo').value = '';

            switch (tipo) {
                case 'documento':
                    campoArchivo.classList.remove('hidden');
                    contenidoField.placeholder = '';
                    break;
                case 'video':
                    campoContenido.classList.remove('hidden');
                    contenidoField.placeholder = 'Pega aquí la URL del video (YouTube/Vimeo)';
                    break;
                case 'enlace':
                    campoContenido.classList.remove('hidden');
                    contenidoField.placeholder = 'Ingresa la URL completa del enlace';
                    break;
                case 'texto':
                case 'acordeon':
                    campoContenido.classList.remove('hidden');
                    contenidoField.placeholder = tipo === 'texto' ? 'Escribe o pega el contenido aquí' : 'Escribe el contenido del acordeón';
                    break;
                case 'imagen':
                    campoArchivo.classList.remove('hidden');
                    document.querySelector('label[for="archivo"]').textContent = 'Imagen (JPG, PNG, GIF)';
                    document.getElementById('archivo').accept = 'image/*';
                    campoContenido.classList.remove('hidden');
                    contenidoField.placeholder = 'Descripción de la imagen (opcional)';
                    break;
                case 'boton':
                    campoContenido.classList.remove('hidden');
                    contenidoField.placeholder = 'URL de redirección';
                    break;
            }

            // Inicializar o destruir TinyMCE según el tipo
            toggleEditor(tipo);
        });

        // Función para previsualizar imagen
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    let preview = document.getElementById('image-preview');
                    if (!preview) {
                        preview = document.createElement('div');
                        preview.id = 'image-preview';
                        preview.className = 'mt-4';
                        input.parentNode.appendChild(preview);
                    }
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="Preview" class="max-w-xs rounded-lg shadow-md">
                    `;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Agregar evento para previsualización de imagen
        document.getElementById('archivo').addEventListener('change', function() {
            if (document.getElementById('tipo').value === 'imagen') {
                previewImage(this);
            }
        });


        // Función para validar el formulario de contenido
        function validarFormularioContenido(tipo, contenido, archivo) {
            let validacion = {
                isValid: true,
                mensaje: ''
            };

            if (!tipo) {
                return { isValid: false, mensaje: 'Por favor, seleccione un tipo de contenido' };
            }

            switch (tipo) {
                case 'video':
                    if (!contenido || !contenido.match(/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be|vimeo\.com)\/.+$/)) {
                        validacion = { isValid: false, mensaje: 'Por favor, ingresa una URL válida de YouTube o Vimeo' };
                    }
                    break;
                case 'enlace':
                    try {
                        new URL(contenido);
                    } catch (_) {
                        validacion = { isValid: false, mensaje: 'Por favor, ingresa una URL válida' };
                    }
                    break;
                case 'documento':
                    if (!archivo) {
                        validacion = { isValid: false, mensaje: 'Por favor, selecciona un archivo' };
                    } else {
                        const allowedTypes = ['.pdf', '.doc', '.docx'];
                        const fileExt = '.' + archivo.name.split('.').pop().toLowerCase();
                        if (!allowedTypes.includes(fileExt)) {
                            validacion = { isValid: false, mensaje: 'Tipo de archivo no permitido. Use PDF, DOC o DOCX' };
                        }
                    }
                    break;
                case 'texto':
                case 'acordeon':
                    if (!contenido || !contenido.trim()) {
                        validacion = { isValid: false, mensaje: 'Por favor, ingresa algún contenido' };
                    }
                    break;
                case 'imagen':
                    if (!archivo) {
                        validacion = { isValid: false, mensaje: 'Por favor, selecciona una imagen' };
                    } else {
                        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                        if (!allowedTypes.includes(archivo.type)) {
                            validacion = { isValid: false, mensaje: 'Tipo de archivo no permitido. Use JPG, PNG o GIF' };
                        }
                    }
                    break;
                case 'boton':
                    if (!contenido || !contenido.trim()) {
                        validacion = { isValid: false, mensaje: 'Por favor, ingresa la URL de redirección' };
                    }
                    try {
                        new URL(contenido);
                    } catch (_) {
                        validacion = { isValid: false, mensaje: 'Por favor, ingresa una URL válida para el botón' };
                    }
                    break;
            }

            return validacion;
        }

        // Manejar envío del formulario de contenido
        document.querySelector('form[enctype="multipart/form-data"]').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const tipo = document.getElementById('tipo').value;
            const contenido = document.getElementById('contenido').value;
            const archivo = document.getElementById('archivo').files[0];
            const formData = new FormData(this);

            // Agregar acción según si es edición o nuevo contenido
            formData.append('accion', currentContenidoId ? 'editar_contenido' : 'agregar_contenido');
            if (currentContenidoId) {
                formData.append('contenido_id', currentContenidoId);
            }
            
            // Validación del formulario
            const validacion = validarFormularioContenido(tipo, contenido, archivo);
            if (!validacion.isValid) {
                mostrarMensaje(validacion.mensaje, 'error');
                return;
            }

            await enviarFormulario(formData, 'contenido');
        });
    </script>
</body>
</html>
