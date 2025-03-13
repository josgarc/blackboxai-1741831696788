<?php
/**
 * Gestor de archivos y optimización de imágenes
 * Maneja la carga, validación y optimización de archivos
 */

class FileManager {
    private static $instance = null;
    private $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif'];
    private $allowedDocTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation'
    ];
    private $maxFileSize = 10485760; // 10MB en bytes
    private $uploadPath;

    private function __construct() {
        $this->uploadPath = dirname(__DIR__) . '/uploads/';
        $this->createUploadDirectories();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Crea los directorios necesarios para las cargas
     */
    private function createUploadDirectories() {
        $directories = [
            $this->uploadPath,
            $this->uploadPath . 'images/',
            $this->uploadPath . 'documents/',
            $this->uploadPath . 'temp/'
        ];

        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Sube y procesa un archivo
     * 
     * @param array $file Archivo del formulario ($_FILES['input_name'])
     * @param string $type Tipo de archivo ('image' o 'document')
     * @param array $options Opciones adicionales de procesamiento
     * @return array|false Información del archivo o false si falla
     */
    public function uploadFile($file, $type = 'document', $options = []) {
        try {
            // Validaciones básicas
            if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
                throw new Exception('No se ha proporcionado ningún archivo.');
            }

            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Error en la carga del archivo: ' . $this->getUploadErrorMessage($file['error']));
            }

            if ($file['size'] > $this->maxFileSize) {
                throw new Exception('El archivo excede el tamaño máximo permitido.');
            }

            // Validar tipo de archivo
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $isValid = $type === 'image' ? 
                      in_array($mimeType, $this->allowedImageTypes) : 
                      in_array($mimeType, $this->allowedDocTypes);

            if (!$isValid) {
                throw new Exception('Tipo de archivo no permitido.');
            }

            // Generar nombre único para el archivo
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = uniqid() . '_' . time() . '.' . $extension;
            $subDir = $type === 'image' ? 'images/' : 'documents/';
            $targetPath = $this->uploadPath . $subDir . $fileName;

            // Procesar imagen si es necesario
            if ($type === 'image' && !empty($options['optimize'])) {
                $this->optimizeImage($file['tmp_name'], $targetPath, $options);
            } else {
                if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                    throw new Exception('Error al mover el archivo.');
                }
            }

            // Registrar en la base de datos
            return $this->registerFileInDatabase([
                'nombre_original' => $file['name'],
                'nombre_sistema' => $fileName,
                'ruta' => $subDir . $fileName,
                'tipo' => $mimeType,
                'tamano' => $file['size'],
                'extension' => $extension,
                'user_id' => $_SESSION['user_id'] ?? 0
            ]);

        } catch (Exception $e) {
            error_log("Error en FileManager::uploadFile: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Optimiza una imagen
     */
    private function optimizeImage($sourcePath, $targetPath, $options = []) {
        $quality = $options['quality'] ?? 85;
        $maxWidth = $options['maxWidth'] ?? 1920;
        $maxHeight = $options['maxHeight'] ?? 1080;

        list($width, $height, $type) = getimagesize($sourcePath);

        // Calcular nuevas dimensiones manteniendo proporción
        if ($width > $maxWidth || $height > $maxHeight) {
            $ratio = min($maxWidth / $width, $maxHeight / $height);
            $newWidth = round($width * $ratio);
            $newHeight = round($height * $ratio);
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }

        // Crear nueva imagen
        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        // Manejar transparencia para PNG
        if ($type === IMAGETYPE_PNG) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
        }

        // Cargar imagen original
        switch ($type) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
            default:
                throw new Exception('Formato de imagen no soportado.');
        }

        // Redimensionar
        imagecopyresampled(
            $newImage, $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $width, $height
        );

        // Guardar imagen optimizada
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($newImage, $targetPath, $quality);
                break;
            case IMAGETYPE_PNG:
                imagepng($newImage, $targetPath, round(9 * $quality / 100));
                break;
            case IMAGETYPE_GIF:
                imagegif($newImage, $targetPath);
                break;
        }

        // Liberar memoria
        imagedestroy($sourceImage);
        imagedestroy($newImage);
    }

    /**
     * Registra el archivo en la base de datos
     */
    private function registerFileInDatabase($fileData) {
        global $pdo;

        try {
            $sql = "INSERT INTO archivos (
                nombre_original, nombre_sistema, ruta, tipo, 
                tamano, extension, user_id
            ) VALUES (
                :nombre_original, :nombre_sistema, :ruta, :tipo,
                :tamano, :extension, :user_id
            )";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($fileData);

            $fileData['id'] = $pdo->lastInsertId();
            return $fileData;

        } catch (PDOException $e) {
            error_log("Error al registrar archivo en BD: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Elimina un archivo
     */
    public function deleteFile($fileId) {
        global $pdo;

        try {
            // Obtener información del archivo
            $stmt = $pdo->prepare("SELECT * FROM archivos WHERE id = ?");
            $stmt->execute([$fileId]);
            $file = $stmt->fetch();

            if (!$file) {
                throw new Exception('Archivo no encontrado.');
            }

            // Eliminar archivo físico
            $filePath = $this->uploadPath . $file['ruta'];
            if (file_exists($filePath) && !unlink($filePath)) {
                throw new Exception('No se pudo eliminar el archivo físico.');
            }

            // Eliminar registro de la base de datos
            $stmt = $pdo->prepare("DELETE FROM archivos WHERE id = ?");
            return $stmt->execute([$fileId]);

        } catch (Exception $e) {
            error_log("Error en FileManager::deleteFile: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene mensaje de error de carga
     */
    private function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'El archivo excede el tamaño máximo permitido por PHP.';
            case UPLOAD_ERR_FORM_SIZE:
                return 'El archivo excede el tamaño máximo permitido por el formulario.';
            case UPLOAD_ERR_PARTIAL:
                return 'El archivo se subió parcialmente.';
            case UPLOAD_ERR_NO_FILE:
                return 'No se subió ningún archivo.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Falta la carpeta temporal.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Error al escribir el archivo en el disco.';
            case UPLOAD_ERR_EXTENSION:
                return 'Una extensión de PHP detuvo la carga del archivo.';
            default:
                return 'Error desconocido al subir el archivo.';
        }
    }

    /**
     * Obtiene la URL pública de un archivo
     */
    public function getFileUrl($filePath) {
        return BASE_URL . '/uploads/' . $filePath;
    }

    /**
     * Verifica si un archivo existe
     */
    public function fileExists($filePath) {
        return file_exists($this->uploadPath . $filePath);
    }

    /**
     * Obtiene el tamaño de un archivo en formato legible
     */
    public function getFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
