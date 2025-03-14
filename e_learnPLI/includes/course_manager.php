<?php
class CourseManager {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Crear una nueva materia
    public function createCourse($courseData) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO materias (
                    codigo, nombre, descripcion, creditos, estado, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $courseData['codigo'],
                $courseData['nombre'],
                $courseData['descripcion'],
                $courseData['creditos'],
                $courseData['estado'] ?? 'activo'
            ]);

            $materiaId = $this->pdo->lastInsertId();

            // Si se especifica un profesor, asignarlo
            if (!empty($courseData['maestro_id'])) {
                $this->assignTeacher($materiaId, $courseData['maestro_id']);
            }

            return $materiaId;
        } catch (Exception $e) {
            error_log("Error en createCourse: " . $e->getMessage());
            throw $e;
        }
    }

    // Actualizar una materia existente
    public function updateCourse($courseData) {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                UPDATE materias 
                SET codigo = ?, nombre = ?, descripcion = ?, 
                    creditos = ?, estado = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $courseData['codigo'],
                $courseData['nombre'],
                $courseData['descripcion'],
                $courseData['creditos'],
                $courseData['estado'] ?? 'activo',
                $courseData['id']
            ]);

            // Actualizar asignación de profesor si se especifica
            if (isset($courseData['maestro_id'])) {
                // Eliminar asignación anterior
                $stmt = $this->pdo->prepare("
                    DELETE FROM maestros_materias 
                    WHERE materia_id = ?
                ");
                $stmt->execute([$courseData['id']]);

                // Asignar nuevo profesor si se especifica uno
                if ($courseData['maestro_id']) {
                    $this->assignTeacher($courseData['id'], $courseData['maestro_id']);
                }
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Error en updateCourse: " . $e->getMessage());
            throw $e;
        }
    }

    // Asignar profesor a una materia
    public function assignTeacher($courseId, $teacherId) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO maestros_materias (
                    materia_id, user_id, fecha_asignacion, estado
                ) VALUES (?, ?, NOW(), 'activo')
            ");
            return $stmt->execute([$courseId, $teacherId]);
        } catch (Exception $e) {
            error_log("Error en assignTeacher: " . $e->getMessage());
            throw $e;
        }
    }

    // Inscribir estudiante a una materia
    public function enrollStudent($courseId, $studentId) {
        try {
            // Verificar si ya está inscrito
            $stmt = $this->pdo->prepare("
                SELECT estado 
                FROM estudiantes_materias 
                WHERE materia_id = ? AND user_id = ?
            ");
            $stmt->execute([$courseId, $studentId]);
            $inscripcion = $stmt->fetch();

            if ($inscripcion) {
                if ($inscripcion['estado'] === 'inscrito') {
                    throw new Exception('El estudiante ya está inscrito en esta materia.');
                }
                // Reactivar inscripción si estaba de baja
                $stmt = $this->pdo->prepare("
                    UPDATE estudiantes_materias 
                    SET estado = 'inscrito', fecha_inscripcion = NOW(), fecha_baja = NULL
                    WHERE materia_id = ? AND user_id = ?
                ");
            } else {
                // Nueva inscripción
                $stmt = $this->pdo->prepare("
                    INSERT INTO estudiantes_materias (
                        materia_id, user_id, estado, fecha_inscripcion
                    ) VALUES (?, ?, 'inscrito', NOW())
                ");
            }

            return $stmt->execute([$courseId, $studentId]);
        } catch (Exception $e) {
            error_log("Error en enrollStudent: " . $e->getMessage());
            throw $e;
        }
    }

    // Dar de baja a un estudiante de una materia
    public function unenrollStudent($courseId, $studentId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE estudiantes_materias 
                SET estado = 'baja', fecha_baja = NOW()
                WHERE materia_id = ? AND user_id = ?
            ");
            return $stmt->execute([$courseId, $studentId]);
        } catch (Exception $e) {
            error_log("Error en unenrollStudent: " . $e->getMessage());
            throw $e;
        }
    }

    // Obtener materias de un profesor
    public function getTeacherCourses($teacherId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT m.*, 
                       (SELECT COUNT(*) FROM estudiantes_materias 
                        WHERE materia_id = m.id AND estado = 'inscrito') as total_estudiantes
                FROM materias m
                INNER JOIN maestros_materias mm ON m.id = mm.materia_id
                WHERE mm.user_id = ? AND mm.estado = 'activo'
                ORDER BY m.created_at DESC
            ");
            $stmt->execute([$teacherId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error en getTeacherCourses: " . $e->getMessage());
            throw $e;
        }
    }

    // Obtener materias de un estudiante
    public function getStudentCourses($studentId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT m.*, 
                       u.full_Name as profesor_nombre,
                       em.fecha_inscripcion,
                       (SELECT COUNT(*) FROM tareas 
                        WHERE materia_id = m.id) as total_tareas,
                       (SELECT COUNT(*) FROM entregas_tareas et 
                        INNER JOIN tareas t ON et.tarea_id = t.id 
                        WHERE t.materia_id = m.id AND et.user_id = ?) as tareas_entregadas
                FROM materias m
                INNER JOIN estudiantes_materias em ON m.id = em.materia_id
                INNER JOIN maestros_materias mm ON m.id = mm.materia_id
                INNER JOIN users u ON mm.user_id = u.id
                WHERE em.user_id = ? AND em.estado = 'inscrito'
                ORDER BY m.created_at DESC
            ");
            $stmt->execute([$studentId, $studentId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error en getStudentCourses: " . $e->getMessage());
            throw $e;
        }
    }

    // Obtener detalles de una materia
    public function getCourseDetails($courseId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT m.*, 
                       u.id as profesor_id, u.full_Name as profesor_nombre,
                       (SELECT COUNT(*) FROM estudiantes_materias 
                        WHERE materia_id = m.id AND estado = 'inscrito') as total_estudiantes,
                       (SELECT COUNT(*) FROM tareas WHERE materia_id = m.id) as total_tareas,
                       (SELECT COUNT(*) FROM examenes WHERE materia_id = m.id) as total_examenes,
                       (SELECT COUNT(*) FROM zoom_meetings 
                        WHERE materia_id = m.id AND estado = 'programada') as total_clases
                FROM materias m
                LEFT JOIN maestros_materias mm ON m.id = mm.materia_id
                LEFT JOIN users u ON mm.user_id = u.id
                WHERE m.id = ?
            ");
            $stmt->execute([$courseId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Error en getCourseDetails: " . $e->getMessage());
            throw $e;
        }
    }

    // Obtener estudiantes de una materia
    public function getCourseStudents($courseId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.*, em.estado, em.fecha_inscripcion, em.fecha_baja,
                       (SELECT COUNT(*) FROM entregas_tareas et 
                        INNER JOIN tareas t ON et.tarea_id = t.id 
                        WHERE t.materia_id = ? AND et.user_id = u.id) as tareas_entregadas,
                       (SELECT AVG(et.calificacion) FROM entregas_tareas et 
                        INNER JOIN tareas t ON et.tarea_id = t.id 
                        WHERE t.materia_id = ? AND et.user_id = u.id) as promedio_tareas,
                       (SELECT AVG(re.puntaje_obtenido) FROM respuestas_examenes re 
                        INNER JOIN examenes e ON re.examen_id = e.id 
                        WHERE e.materia_id = ? AND re.user_id = u.id) as promedio_examenes
                FROM users u
                INNER JOIN estudiantes_materias em ON u.id = em.user_id
                WHERE em.materia_id = ?
                ORDER BY em.estado DESC, u.full_Name ASC
            ");
            $stmt->execute([$courseId, $courseId, $courseId, $courseId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error en getCourseStudents: " . $e->getMessage());
            throw $e;
        }
    }

    // Verificar si un estudiante está inscrito en una materia
    public function isStudentEnrolled($courseId, $studentId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT estado 
                FROM estudiantes_materias 
                WHERE materia_id = ? AND user_id = ?
            ");
            $stmt->execute([$courseId, $studentId]);
            $result = $stmt->fetch();
            return $result && $result['estado'] === 'inscrito';
        } catch (Exception $e) {
            error_log("Error en isStudentEnrolled: " . $e->getMessage());
            throw $e;
        }
    }

    // Verificar si un profesor está asignado a una materia
    public function isTeacherAssigned($courseId, $teacherId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT estado 
                FROM maestros_materias 
                WHERE materia_id = ? AND user_id = ?
            ");
            $stmt->execute([$courseId, $teacherId]);
            $result = $stmt->fetch();
            return $result && $result['estado'] === 'activo';
        } catch (Exception $e) {
            error_log("Error en isTeacherAssigned: " . $e->getMessage());
            throw $e;
        }
    }
}
