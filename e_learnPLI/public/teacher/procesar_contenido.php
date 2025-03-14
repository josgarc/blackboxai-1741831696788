<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/file_manager.php';

$auth = Auth::getInstance();
$fileManager = FileManager::getInstance();

// Verificar autenticación y rol de profesor
if (!$auth->isAuthenticated() || !$auth->hasRole('Maestro')) {
    header('Location: ../login.php');
    exit;
}

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $accion = $_GET['accion'] ?? '';

        if ($accion === 'obtener_tema') {
            $temaId = (int)($_GET['id'] ?? 0);
            
            if (!$temaId) {
                throw new Exception('ID de tema no válido.');
            }

            $stmt = $pdo->prepare("
                SELECT t.* 
                FROM temas t
                INNER JOIN maestros_materias mm ON t.materia_id = mm.materia_id
                WHERE t.id = ? AND mm.user_id = ?
            ");
            $stmt->execute([$temaId, $_SESSION['user_id']]);
            $tema = $stmt->fetch();

            if (!$tema) {
                throw new Exception('Tema no encontrado o no tiene permisos para acceder.');
            }

            $response['success'] = true;
            $response['tema'] = $tema;

        } else if ($accion === 'obtener_contenido') {
            $contenidoId = (int)($_GET['id'] ?? 0);
            
            if (!$contenidoId) {
                throw new Exception('ID de contenido no válido.');
            }

            $stmt = $pdo->prepare("
                SELECT c.*, t.materia_id 
                FROM contenidos c
                INNER JOIN temas t ON c.tema_id = t.id
                INNER JOIN maestros_materias mm ON t.materia_id = mm.materia_id
                WHERE c.id = ? AND mm.user_id = ?
            ");
            $stmt->execute([$contenidoId, $_SESSION['user_id']]);
            $contenido = $stmt->fetch();

            if (!$contenido) {
                throw new Exception('Contenido no encontrado o no tiene permisos para acceder.');
            }

            $response['success'] = true;
            $response['contenido'] = $contenido;
        }

    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $accion = $_POST['accion'] ?? '';
        
        if ($accion === 'editar_tema') {
            $temaId = (int)($_POST['tema_id'] ?? 0);
            $titulo = trim($_POST['titulo'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');

            if (!$temaId || empty($titulo)) {
                throw new Exception('El título del tema es obligatorio.');
            }

            // Verificar permisos
            $stmt = $pdo->prepare("
                SELECT t.materia_id 
                FROM temas t
                INNER JOIN maestros_materias mm ON t.materia_id = mm.materia_id
                WHERE t.id = ? AND mm.user_id = ?
            ");
            $stmt->execute([$temaId, $_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                throw new Exception('No tiene permisos para editar este tema.');
            }

            // Actualizar tema
            $stmt = $pdo->prepare("
                UPDATE temas 
                SET titulo = ?, descripcion = ?
                WHERE id = ?
            ");
            $stmt->execute([$titulo, $descripcion, $temaId]);

            $response['success'] = true;
            $response['message'] = 'Tema actualizado correctamente.';

        } else if ($accion === 'eliminar_tema') {
            $temaId = (int)($_POST['tema_id'] ?? 0);

            if (!$temaId) {
                throw new Exception('ID de tema no válido.');
            }

            // Verificar permisos y obtener información
            $stmt = $pdo->prepare("
                SELECT t.*, t.materia_id 
                FROM temas t
                INNER JOIN maestros_materias mm ON t.materia_id = mm.materia_id
                WHERE t.id = ? AND mm.user_id = ?
            ");
            $stmt->execute([$temaId, $_SESSION['user_id']]);
            $tema = $stmt->fetch();

            if (!$tema) {
                throw new Exception('Tema no encontrado o no tiene permisos para eliminar.');
            }

            // Obtener y eliminar archivos asociados
            $stmt = $pdo->prepare("
                SELECT archivo FROM contenidos 
                WHERE tema_id = ? AND archivo IS NOT NULL
            ");
            $stmt->execute([$temaId]);
            $archivos = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($archivos as $archivo) {
                $fileManager->deleteFile($archivo);
            }

            // Eliminar tema y sus contenidos (la restricción FK se encarga de los contenidos)
            $stmt = $pdo->prepare("DELETE FROM temas WHERE id = ?");
            $stmt->execute([$temaId]);

            $response['success'] = true;
            $response['message'] = 'Tema y sus contenidos eliminados correctamente.';

        } else if ($accion === 'agregar_tema') {
            $materiaId = (int)($_POST['materia_id'] ?? 0);
            $titulo = trim($_POST['titulo'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');

            if (!$materiaId || empty($titulo)) {
                throw new Exception('El título del tema es obligatorio.');
            }

            // Obtener el siguiente orden disponible para el tema
            $stmt = $pdo->prepare("
                SELECT COALESCE(MAX(orden), 0) + 1 as siguiente_orden 
                FROM temas 
                WHERE materia_id = ?
            ");
            $stmt->execute([$materiaId]);
            $orden = $stmt->fetch()['siguiente_orden'];

            // Insertar nuevo tema
            $stmt = $pdo->prepare("
                INSERT INTO temas (materia_id, titulo, descripcion, orden)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$materiaId, $titulo, $descripcion, $orden]);

            $response['success'] = true;
            $response['message'] = 'Tema creado correctamente.';

        } else if ($accion === 'actualizar_orden_tema') {
            $temaId = (int)($_POST['tema_id'] ?? 0);
            $materiaId = (int)($_POST['materia_id'] ?? 0);
            $nuevoOrden = (int)($_POST['nuevo_orden'] ?? 0);

            if (!$temaId || !$materiaId) {
                throw new Exception('Datos de ordenamiento no válidos.');
            }

            // Verificar permisos
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM maestros_materias 
                WHERE materia_id = ? AND user_id = ?
            ");
            $stmt->execute([$materiaId, $_SESSION['user_id']]);
            if (!$stmt->fetchColumn()) {
                throw new Exception('No tiene permisos para modificar esta materia.');
            }

            // Actualizar orden
            $pdo->beginTransaction();
            try {
                // Mover todos los temas después del nuevo orden un lugar hacia abajo
                $stmt = $pdo->prepare("
                    UPDATE temas 
                    SET orden = orden + 1 
                    WHERE materia_id = ? AND orden >= ?
                ");
                $stmt->execute([$materiaId, $nuevoOrden]);

                // Colocar el tema en su nueva posición
                $stmt = $pdo->prepare("
                    UPDATE temas 
                    SET orden = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$nuevoOrden, $temaId]);

                $pdo->commit();
                $response['success'] = true;
                $response['message'] = 'Orden actualizado correctamente.';
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }

        } else if ($accion === 'actualizar_orden') {
            $contenidoId = (int)($_POST['contenido_id'] ?? 0);
            $temaId = (int)($_POST['tema_id'] ?? 0);
            $nuevoOrden = (int)($_POST['nuevo_orden'] ?? 0);

            if (!$contenidoId || !$temaId) {
                throw new Exception('Datos de ordenamiento no válidos.');
            }

            // Verificar permisos
            $stmt = $pdo->prepare("
                SELECT t.materia_id 
                FROM temas t
                INNER JOIN maestros_materias mm ON t.materia_id = mm.materia_id
                WHERE t.id = ? AND mm.user_id = ?
            ");
            $stmt->execute([$temaId, $_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                throw new Exception('No tiene permisos para modificar este tema.');
            }

            // Actualizar orden
            $pdo->beginTransaction();
            try {
                // Mover todos los items después del nuevo orden un lugar hacia abajo
                $stmt = $pdo->prepare("
                    UPDATE contenidos 
                    SET orden = orden + 1 
                    WHERE tema_id = ? AND orden >= ?
                ");
                $stmt->execute([$temaId, $nuevoOrden]);

                // Colocar el item en su nueva posición
                $stmt = $pdo->prepare("
                    UPDATE contenidos 
                    SET orden = ?, tema_id = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$nuevoOrden, $temaId, $contenidoId]);

                $pdo->commit();
                $response['success'] = true;
                $response['message'] = 'Orden actualizado correctamente.';
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }

        } else if ($accion === 'editar_contenido') {
            $contenidoId = (int)($_POST['contenido_id'] ?? 0);
            $titulo = trim($_POST['titulo'] ?? '');
            $tipo = trim($_POST['tipo'] ?? '');
            
            if (!$contenidoId || empty($titulo) || empty($tipo)) {
                throw new Exception('Todos los campos son obligatorios.');
            }

            // Verificar permisos y obtener información actual
            $stmt = $pdo->prepare("
                SELECT c.*, t.materia_id 
                FROM contenidos c
                INNER JOIN temas t ON c.tema_id = t.id
                INNER JOIN maestros_materias mm ON t.materia_id = mm.materia_id
                WHERE c.id = ? AND mm.user_id = ?
            ");
            $stmt->execute([$contenidoId, $_SESSION['user_id']]);
            $contenidoActual = $stmt->fetch();

            if (!$contenidoActual) {
                throw new Exception('Contenido no encontrado o no tiene permisos para editar.');
            }

            $archivo = null;
            $datos_adicionales = [];
            $contenido = trim($_POST['contenido'] ?? '');

            // Procesar según el tipo de contenido
            switch ($tipo) {
                case 'documento':
                    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
                        $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                        if (!in_array($_FILES['archivo']['type'], $allowedTypes)) {
                            throw new Exception('Tipo de archivo no permitido. Use PDF, DOC o DOCX.');
                        }
                        // Eliminar archivo anterior si existe
                        if ($contenidoActual['archivo']) {
                            $fileManager->deleteFile($contenidoActual['archivo']);
                        }
                        $archivo = $fileManager->uploadFile($_FILES['archivo'], 'documentos');
                    }
                    break;

                case 'imagen':
                    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
                        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                        if (!in_array($_FILES['archivo']['type'], $allowedTypes)) {
                            throw new Exception('Tipo de archivo no permitido. Use JPG, PNG o GIF.');
                        }
                        // Eliminar imagen anterior si existe
                        if ($contenidoActual['archivo']) {
                            $fileManager->deleteFile($contenidoActual['archivo']);
                        }
                        $archivo = $fileManager->uploadFile($_FILES['archivo'], 'imagenes');
                    }
                    $datos_adicionales['descripcion'] = $contenido;
                    break;

                case 'boton':
                    if (!filter_var($contenido, FILTER_VALIDATE_URL)) {
                        throw new Exception('URL de redirección no válida.');
                    }
                    $datos_adicionales['texto'] = $_POST['boton_texto'] ?? 'Click aquí';
                    $datos_adicionales['icono'] = $_POST['boton_icono'] ?? '';
                    break;

                default:
                    // Para otros tipos, validar según el caso
                    if (empty($contenido) && $tipo !== 'documento') {
                        throw new Exception('El contenido es obligatorio.');
                    }
            }

            // Preparar contenido final
            $contenido_final = !empty($datos_adicionales) ? json_encode($datos_adicionales) : $contenido;

            // Actualizar contenido
            $stmt = $pdo->prepare("
                UPDATE contenidos 
                SET titulo = ?,
                    tipo = ?,
                    contenido = ?" . 
                    ($archivo ? ", archivo = ?" : "") . "
                WHERE id = ?
            ");

            $params = [$titulo, $tipo, $contenido_final];
            if ($archivo) {
                $params[] = $archivo['ruta'];
            }
            $params[] = $contenidoId;
            $stmt->execute($params);

            $response['success'] = true;
            $response['message'] = 'Contenido actualizado correctamente.';

        } else if ($accion === 'eliminar_contenido') {
            $contenidoId = (int)($_POST['id'] ?? 0);
            
            if (!$contenidoId) {
                throw new Exception('ID de contenido no válido.');
            }

            // Verificar permisos y obtener información del archivo
            $stmt = $pdo->prepare("
                SELECT c.*, t.materia_id 
                FROM contenidos c
                INNER JOIN temas t ON c.tema_id = t.id
                INNER JOIN maestros_materias mm ON t.materia_id = mm.materia_id
                WHERE c.id = ? AND mm.user_id = ?
            ");
            $stmt->execute([$contenidoId, $_SESSION['user_id']]);
            $contenido = $stmt->fetch();

            if (!$contenido) {
                throw new Exception('Contenido no encontrado o no tiene permisos para eliminar.');
            }

            // Eliminar archivo si existe
            if ($contenido['archivo']) {
                $fileManager->deleteFile($contenido['archivo']);
            }

            // Eliminar contenido
            $stmt = $pdo->prepare("DELETE FROM contenidos WHERE id = ?");
            $stmt->execute([$contenidoId]);

            $response['success'] = true;
            $response['message'] = 'Contenido eliminado correctamente.';

        } else if ($accion === 'agregar_contenido') {
            $temaId = (int)($_POST['tema_id'] ?? 0);
            $titulo = trim($_POST['titulo'] ?? '');
            $tipo = trim($_POST['tipo'] ?? '');
            $contenido = trim($_POST['contenido'] ?? '');

            if (!$temaId || empty($titulo) || empty($tipo)) {
                throw new Exception('Todos los campos son obligatorios.');
            }

            // Obtener el siguiente orden disponible
            $stmt = $pdo->prepare("
                SELECT COALESCE(MAX(orden), 0) + 1 as siguiente_orden 
                FROM contenidos 
                WHERE tema_id = ?
            ");
            $stmt->execute([$temaId]);
            $orden = $stmt->fetch()['siguiente_orden'];

            // Validar y procesar según el tipo de contenido
            $archivo = null;
            $datos_adicionales = [];

            switch ($tipo) {
                case 'documento':
                    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception('El archivo es obligatorio para contenido tipo documento.');
                    }
                    // Validar tipo de archivo
                    $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                    if (!in_array($_FILES['archivo']['type'], $allowedTypes)) {
                        throw new Exception('Tipo de archivo no permitido. Use PDF, DOC o DOCX.');
                    }
                    $archivo = $fileManager->uploadFile($_FILES['archivo'], 'documentos');
                    $contenido = null; // No hay contenido para documentos
                    break;

                case 'video':
                    if (!preg_match('/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be|vimeo\.com)\/.+$/', $contenido)) {
                        throw new Exception('URL de video no válida. Use YouTube o Vimeo.');
                    }
                    break;

                case 'enlace':
                    if (!filter_var($contenido, FILTER_VALIDATE_URL)) {
                        throw new Exception('URL no válida.');
                    }
                    break;

                case 'texto':
                case 'acordeon':
                    if (empty($contenido)) {
                        throw new Exception('El contenido es obligatorio para texto enriquecido y acordeón.');
                    }
                    // El contenido HTML ya está sanitizado por TinyMCE
                    break;

                case 'imagen':
                    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception('La imagen es obligatoria.');
                    }
                    // Validar tipo de imagen
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                    if (!in_array($_FILES['archivo']['type'], $allowedTypes)) {
                        throw new Exception('Tipo de archivo no permitido. Use JPG, PNG o GIF.');
                    }
                    $archivo = $fileManager->uploadFile($_FILES['archivo'], 'imagenes');
                    // Guardar descripción de la imagen si existe
                    $datos_adicionales['descripcion'] = $contenido;
                    break;

                case 'boton':
                    if (empty($contenido)) {
                        throw new Exception('La URL de redirección es obligatoria para el botón.');
                    }
                    if (!filter_var($contenido, FILTER_VALIDATE_URL)) {
                        throw new Exception('URL de redirección no válida.');
                    }
                    // Guardar datos adicionales del botón
                    $datos_adicionales['texto'] = $_POST['boton_texto'] ?? 'Click aquí';
                    $datos_adicionales['icono'] = $_POST['boton_icono'] ?? '';
                    break;
            }

            // Convertir datos adicionales a JSON si existen
            $contenido_final = !empty($datos_adicionales) ? json_encode($datos_adicionales) : $contenido;

            $stmt = $pdo->prepare("
                INSERT INTO contenidos (tema_id, titulo, tipo, contenido, archivo, orden)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $temaId,
                $titulo,
                $tipo,
                $contenido_final,
                $archivo ? $archivo['ruta'] : null,
                $orden
            ]);

            $response['success'] = true;
            $response['message'] = 'Contenido agregado correctamente.';
        }
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
