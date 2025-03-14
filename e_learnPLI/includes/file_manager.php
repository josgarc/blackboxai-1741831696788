<?php
class FileManager {
    private static $instance = null;
    private $uploadDir;
    private $allowedTypes;
    private $maxFileSize;

    private function __construct() {
        $this->uploadDir = __DIR__ . '/../uploads/';
        $this->allowedTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png',
            'image/gif'
        ];
        $this->maxFileSize = 10 * 1024 * 1024; // 10MB

        // Crear directorio de uploads si no existe
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Sube un archivo al servidor
     * @param array $file Archivo del array $_FILES
     * @param string $subdir Subdirectorio opcional dentro de uploads/
     * @return array Información del archivo subido
     * @throws Exception Si hay error en la subida
     */
    public function uploadFile($file, $subdir = '') {
        // Validar que el archivo existe y no hay errores
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new Exception('Parámetro de archivo inválido');
        }

        // Verificar el código de error
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new Exception('El archivo excede el tamaño permitido');
            case UPLOAD_ERR_PARTIAL:
                throw new Exception('El archivo se subió parcialmente');
            case UPLOAD_ERR_NO_FILE:
                throw new Exception('No se subió ningún archivo');
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new Exception('Falta la carpeta temporal');
            case UPLOAD_ERR_CANT_WRITE:
                throw new Exception('Error al escribir el archivo');
            case UPLOAD_ERR_EXTENSION:
                throw new Exception('Una extensión PHP detuvo la subida');
            default:
                throw new Exception('Error desconocido en la subida');
        }

        // Verificar tamaño
        if ($file['size'] > $this->maxFileSize) {
            throw new Exception('El archivo excede el tamaño máximo permitido');
        }

        // Verificar tipo MIME
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        if (!in_array($mimeType, $this->allowedTypes)) {
            throw new Exception('Tipo de archivo no permitido');
        }

        // Generar nombre único
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $uniqueName = uniqid() . '.' . $extension;

        // Crear subdirectorio si se especifica
        $uploadPath = $this->uploadDir;
        if ($subdir) {
            $uploadPath .= trim($subdir, '/') . '/';
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
        }

        // Mover el archivo
        if (!move_uploaded_file($file['tmp_name'], $uploadPath . $uniqueName)) {
            throw new Exception('Error al mover el archivo subido');
        }

        // Retornar información del archivo
        return [
            'nombre_original' => $file['name'],
            'nombre_sistema' => $uniqueName,
            'ruta' => ($subdir ? $subdir . '/' : '') . $uniqueName,
            'tipo' => $mimeType,
            'tamano' => $file['size'],
            'extension' => $extension
        ];
    }

    /**
     * Elimina un archivo del servidor
     * @param string $path Ruta relativa del archivo dentro de uploads/
     * @return bool true si se eliminó correctamente
     */
    public function deleteFile($path) {
        $fullPath = $this->uploadDir . $path;
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return false;
    }

    /**
     * Obtiene la URL pública de un archivo
     * @param string $path Ruta relativa del archivo
     * @return string URL pública del archivo
     */
    public function getFileUrl($path) {
        return '/uploads/' . $path;
    }

    /**
     * Verifica si un archivo existe
     * @param string $path Ruta relativa del archivo
     * @return bool true si el archivo existe
     */
    public function fileExists($path) {
        return file_exists($this->uploadDir . $path);
    }

    /**
     * Obtiene el tamaño de un archivo
     * @param string $path Ruta relativa del archivo
     * @return int|false Tamaño en bytes o false si no existe
     */
    public function getFileSize($path) {
        $fullPath = $this->uploadDir . $path;
        if (file_exists($fullPath)) {
            return filesize($fullPath);
        }
        return false;
    }

    /**
     * Obtiene el tipo MIME de un archivo
     * @param string $path Ruta relativa del archivo
     * @return string|false Tipo MIME o false si no existe
     */
    public function getFileMimeType($path) {
        $fullPath = $this->uploadDir . $path;
        if (file_exists($fullPath)) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            return $finfo->file($fullPath);
        }
        return false;
    }
}
