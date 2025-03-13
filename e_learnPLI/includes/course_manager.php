<?php
/**
 * Gestor de materias y contenidos académicos
 * Maneja la creación y gestión de materias, contenidos, tareas y evaluaciones
 */

class CourseManager {
    private static $instance = null;
    private $pdo;
    private $fileManager;
    private $emailService;

    private function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->fileManager = FileManager::getInstance();
        $this->emailService = EmailService::getInstance();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Crea una nueva materia
     * 
     * @param array $courseData Datos de la materia
     * @return array|false Datos de la materia creada o false si falla
     */
    public function createCourse($courseData) {
        try {
            $sql = "INSERT INTO materias (
                codigo, nombre, descripcion, creditos, estado
            ) VALUES (
                :codigo, :nombre, :descripcion, :creditos, :estado
            )";

            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([
                'codigo' => $courseData['codigo'],
                'nombre' => $courseData['nombre'],
                'descripcion' => $courseData['descripcion'],
                'creditos' => $courseData['creditos'] ?? 0,
                'estado' => $courseData['estado'] ?? 'activo'
            ]);

            if ($success) {
                $courseId = $this->pdo->lastInsertId();

                // Asignar maestro si se especifica
                if (!empty($courseData['maestro_id'])) {
                    $this->assignTeacher($courseId, $courseData['maestro_id']);
                }

                return $this->getCourseById($courseId);
            }

            return false;
        } catch (Exception $e) {
            error_log("Error creando materia: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Asigna un maestro a una materia
     */
    public function assignTeacher($courseId, $teacherId) {
        try {
            $sql = "INSERT INTO maestros_materias (
                materia_id, user_id, fecha_asignacion, estado
            ) VALUES (?, ?, NOW(), 'activo')";

            $stmt = $this->pdo->prepare($sql);
            if ($stmt->execute([$courseId, $teacherId])) {
                // Notificar al maestro
                $teacher = Auth::getInstance()->getUserById($teacherId);
                $course = $this->getCourseById($courseId);
                
                $this->emailService->sendTeacherAssignmentNotification(
                    $teacher['email'],
                    $course['nombre']
                );
                
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("Error asignando maestro: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Inscribe un estudiante en una materia
     */
    public function enrollStudent($courseId, $studentId) {
        try {
            $sql = "INSERT INTO estudiantes_materias (
                materia_id, user_id, fecha_inscripcion, estado
            ) VALUES (?, ?, NOW(), 'inscrito')";

            $stmt = $this->pdo->prepare($sql);
            if ($stmt->execute([$courseId, $studentId])) {
                // Notificar al estudiante
                $student = Auth::getInstance()->getUserById($studentId);
                $course = $this->getCourseById($courseId);
                
                $this->emailService->sendEnrollmentNotification(
                    $student['email'],
                    $course['nombre']
                );
                
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("Error inscribiendo estudiante: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Agrega contenido a una materia
     */
    public function addContent($courseId, $contentData, $file = null) {
        try {
            // Procesar archivo si existe
            $fileInfo = null;
            if ($file && isset($file['tmp_name'])) {
                $fileInfo = $this->fileManager->uploadFile($file, $contentData['tipo']);
                if (!$fileInfo) {
                    throw new Exception('Error al procesar el archivo.');
                }
            }

            $sql = "INSERT INTO contenidos (
                materia_id, titulo, descripcion, tipo,
                url, archivo, contenido, orden, estado
            ) VALUES (
                :materia_id, :titulo, :descripcion, :tipo,
                :url, :archivo, :contenido, :orden, :estado
            )";

            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([
                'materia_id' => $courseId,
                'titulo' => $contentData['titulo'],
                'descripcion' => $contentData['descripcion'],
                'tipo' => $contentData['tipo'],
                'url' => $contentData['url'] ?? null,
                'archivo' => $fileInfo ? $fileInfo['ruta'] : null,
                'contenido' => $contentData['contenido'] ?? null,
                'orden' => $contentData['orden'] ?? 0,
                'estado' => $contentData['estado'] ?? 'borrador'
            ]);

            if ($success) {
                $contentId = $this->pdo->lastInsertId();

                // Notificar a estudiantes si el contenido está publicado
                if ($contentData['estado'] === 'publicado') {
                    $this->notifyNewContent($courseId, $contentId);
                }

                return $this->getContentById($contentId);
            }

            return false;
        } catch (Exception $e) {
            error_log("Error agregando contenido: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Crea una nueva tarea
     */
    public function createTask($courseId, $taskData) {
        try {
            $sql = "INSERT INTO tareas (
                materia_id, titulo, descripcion, fecha_inicio,
                fecha_entrega, ponderacion, tipo_entrega, estado
            ) VALUES (
                :materia_id, :titulo, :descripcion, :fecha_inicio,
                :fecha_entrega, :ponderacion, :tipo_entrega, :estado
            )";

            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([
                'materia_id' => $courseId,
                'titulo' => $taskData['titulo'],
                'descripcion' => $taskData['descripcion'],
                'fecha_inicio' => $taskData['fecha_inicio'],
                'fecha_entrega' => $taskData['fecha_entrega'],
                'ponderacion' => $taskData['ponderacion'],
                'tipo_entrega' => $taskData['tipo_entrega'],
                'estado' => $taskData['estado'] ?? 'borrador'
            ]);

            if ($success) {
                $taskId = $this->pdo->lastInsertId();

                // Notificar a estudiantes si la tarea está publicada
                if ($taskData['estado'] === 'publicada') {
                    $this->notifyNewTask($courseId, $taskId);
                }

                return $this->getTaskById($taskId);
            }

            return false;
        } catch (Exception $e) {
            error_log("Error creando tarea: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Crea un nuevo examen
     */
    public function createExam($courseId, $examData) {
        try {
            $this->pdo->beginTransaction();

            // Insertar examen
            $sql = "INSERT INTO examenes (
                materia_id, titulo, descripcion, fecha_inicio,
                fecha_fin, duracion_minutos, ponderacion,
                intentos_permitidos, estado
            ) VALUES (
                :materia_id, :titulo, :descripcion, :fecha_inicio,
                :fecha_fin, :duracion_minutos, :ponderacion,
                :intentos_permitidos, :estado
            )";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'materia_id' => $courseId,
                'titulo' => $examData['titulo'],
                'descripcion' => $examData['descripcion'],
                'fecha_inicio' => $examData['fecha_inicio'],
                'fecha_fin' => $examData['fecha_fin'],
                'duracion_minutos' => $examData['duracion_minutos'],
                'ponderacion' => $examData['ponderacion'],
                'intentos_permitidos' => $examData['intentos_permitidos'] ?? 1,
                'estado' => $examData['estado'] ?? 'borrador'
            ]);

            $examId = $this->pdo->lastInsertId();

            // Insertar preguntas
            if (!empty($examData['preguntas'])) {
                $this->addExamQuestions($examId, $examData['preguntas']);
            }

            $this->pdo->commit();

            // Notificar a estudiantes si el examen está publicado
            if ($examData['estado'] === 'publicado') {
                $this->notifyNewExam($courseId, $examId);
            }

            return $this->getExamById($examId);
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error creando examen: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Agrega preguntas a un examen
     */
    private function addExamQuestions($examId, $questions) {
        $sql = "INSERT INTO preguntas (
            examen_id, tipo, pregunta, opciones,
            respuesta_correcta, puntaje, orden
        ) VALUES (
            :examen_id, :tipo, :pregunta, :opciones,
            :respuesta_correcta, :puntaje, :orden
        )";

        $stmt = $this->pdo->prepare($sql);

        foreach ($questions as $order => $question) {
            $stmt->execute([
                'examen_id' => $examId,
                'tipo' => $question['tipo'],
                'pregunta' => $question['pregunta'],
                'opciones' => json_encode($question['opciones'] ?? null),
                'respuesta_correcta' => $question['respuesta_correcta'],
                'puntaje' => $question['puntaje'],
                'orden' => $order + 1
            ]);
        }
    }

    /**
     * Califica una tarea
     */
    public function gradeTask($taskId, $studentId, $grade, $comments = '') {
        try {
            $sql = "UPDATE entregas_tareas 
                    SET calificacion = ?, comentario_profesor = ?, estado = 'calificado'
                    WHERE tarea_id = ? AND user_id = ?";

            $stmt = $this->pdo->prepare($sql);
            if ($stmt->execute([$grade, $comments, $taskId, $studentId])) {
                // Obtener información para la notificación
                $task = $this->getTaskById($taskId);
                $student = Auth::getInstance()->getUserById($studentId);
                
                // Notificar al estudiante
                $this->emailService->sendGradeNotification(
                    $student['email'],
                    [
                        'materia' => $task['materia_nombre'],
                        'actividad' => $task['titulo'],
                        'calificacion' => $grade,
                        'comentarios' => $comments
                    ]
                );
                
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("Error calificando tarea: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtiene una materia por ID
     */
    public function getCourseById($courseId) {
        $sql = "SELECT m.*, 
                (SELECT COUNT(*) FROM estudiantes_materias WHERE materia_id = m.id) as total_estudiantes,
                (SELECT COUNT(*) FROM contenidos WHERE materia_id = m.id) as total_contenidos
                FROM materias m 
                WHERE m.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$courseId]);
        return $stmt->fetch();
    }

    /**
     * Obtiene contenido por ID
     */
    public function getContentById($contentId) {
        $stmt = $this->pdo->prepare("SELECT * FROM contenidos WHERE id = ?");
        $stmt->execute([$contentId]);
        return $stmt->fetch();
    }

    /**
     * Obtiene tarea por ID
     */
    public function getTaskById($taskId) {
        $sql = "SELECT t.*, m.nombre as materia_nombre 
                FROM tareas t 
                JOIN materias m ON t.materia_id = m.id 
                WHERE t.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$taskId]);
        return $stmt->fetch();
    }

    /**
     * Obtiene examen por ID
     */
    public function getExamById($examId) {
        $sql = "SELECT e.*, 
                (SELECT JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'id', p.id,
                        'tipo', p.tipo,
                        'pregunta', p.pregunta,
                        'opciones', p.opciones,
                        'puntaje', p.puntaje,
                        'orden', p.orden
                    )
                ) FROM preguntas p WHERE p.examen_id = e.id) as preguntas
                FROM examenes e 
                WHERE e.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$examId]);
        return $stmt->fetch();
    }

    /**
     * Notifica nuevo contenido a los estudiantes
     */
    private function notifyNewContent($courseId, $contentId) {
        $content = $this->getContentById($contentId);
        $course = $this->getCourseById($courseId);
        
        $sql = "SELECT u.email 
                FROM users u 
                JOIN estudiantes_materias em ON u.id = em.user_id 
                WHERE em.materia_id = ? AND em.estado = 'inscrito'";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$courseId]);
        $students = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($students as $email) {
            $this->emailService->sendNewContentNotification(
                $email,
                [
                    'materia' => $course['nombre'],
                    'titulo' => $content['titulo'],
                    'tipo' => $content['tipo']
                ]
            );
        }
    }

    /**
     * Notifica nueva tarea a los estudiantes
     */
    private function notifyNewTask($courseId, $taskId) {
        $task = $this->getTaskById($taskId);
        
        $sql = "SELECT u.email 
                FROM users u 
                JOIN estudiantes_materias em ON u.id = em.user_id 
                WHERE em.materia_id = ? AND em.estado = 'inscrito'";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$courseId]);
        $students = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($students as $email) {
            $this->emailService->sendNewTaskNotification($email, [
                'materia' => $task['materia_nombre'],
                'titulo' => $task['titulo'],
                'fecha_entrega' => $task['fecha_entrega'],
                'ponderacion' => $task['ponderacion']
            ]);
        }
    }

    /**
     * Notifica nuevo examen a los estudiantes
     */
    private function notifyNewExam($courseId, $examId) {
        $exam = $this->getExamById($examId);
        $course = $this->getCourseById($courseId);
        
        $sql = "SELECT u.email 
                FROM users u 
                JOIN estudiantes_materias em ON u.id = em.user_id 
                WHERE em.materia_id = ? AND em.estado = 'inscrito'";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$courseId]);
        $students = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($students as $email) {
            $this->emailService->sendNewExamNotification($email, [
                'materia' => $course['nombre'],
                'titulo' => $exam['titulo'],
                'fecha_inicio' => $exam['fecha_inicio'],
                'fecha_fin' => $exam['fecha_fin'],
                'duracion' => $exam['duracion_minutos']
            ]);
        }
    }
}
