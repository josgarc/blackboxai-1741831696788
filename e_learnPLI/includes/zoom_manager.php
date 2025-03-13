<?php
/**
 * Gestor de integración con Zoom
 * Maneja la creación de reuniones y seguimiento de asistencia
 */

class ZoomManager {
    private static $instance = null;
    private $apiKey;
    private $apiSecret;
    private $baseUrl = 'https://api.zoom.us/v2';
    private $jwt;

    private function __construct() {
        global $pdo;
        
        // Obtener credenciales de la base de datos
        try {
            $stmt = $pdo->prepare("SELECT valor FROM configuraciones WHERE clave IN ('zoom_api_key', 'zoom_api_secret')");
            $stmt->execute();
            $config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $this->apiKey = $config['zoom_api_key'] ?? '';
            $this->apiSecret = $config['zoom_api_secret'] ?? '';
            
            if (empty($this->apiKey) || empty($this->apiSecret)) {
                throw new Exception('Credenciales de Zoom no configuradas');
            }
            
            $this->generateJWT();
        } catch (Exception $e) {
            error_log("Error inicializando ZoomManager: " . $e->getMessage());
            throw $e;
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Genera el token JWT para la autenticación con Zoom
     */
    private function generateJWT() {
        $now = time();
        $payload = [
            'iss' => $this->apiKey,
            'exp' => $now + 3600 // Token válido por 1 hora
        ];

        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($payload);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $this->apiSecret, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        $this->jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * Realiza una petición a la API de Zoom
     */
    private function makeRequest($endpoint, $method = 'GET', $data = null) {
        $ch = curl_init();
        
        $url = $this->baseUrl . $endpoint;
        $headers = [
            'Authorization: Bearer ' . $this->jwt,
            'Content-Type: application/json'
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Para desarrollo

        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception('Error Curl: ' . curl_error($ch));
        }
        
        curl_close($ch);

        if ($httpCode >= 400) {
            $error = json_decode($response, true);
            throw new Exception('Error API Zoom: ' . ($error['message'] ?? 'Error desconocido'));
        }

        return json_decode($response, true);
    }

    /**
     * Crea una nueva reunión de Zoom
     * 
     * @param array $meetingData Datos de la reunión
     * @return array Información de la reunión creada
     */
    public function createMeeting($meetingData) {
        global $pdo;

        try {
            // Crear reunión en Zoom
            $zoomData = [
                'topic' => $meetingData['topic'],
                'type' => 2, // Reunión programada
                'start_time' => $meetingData['start_time'],
                'duration' => $meetingData['duration'],
                'timezone' => 'America/Mexico_City',
                'settings' => [
                    'host_video' => true,
                    'participant_video' => true,
                    'join_before_host' => false,
                    'mute_upon_entry' => true,
                    'waiting_room' => true,
                    'audio' => 'both'
                ]
            ];

            $response = $this->makeRequest('/users/me/meetings', 'POST', $zoomData);

            // Guardar en la base de datos
            $stmt = $pdo->prepare("
                INSERT INTO zoom_meetings (
                    materia_id, meeting_id, topic, start_time, 
                    duration, join_url, password, estado
                ) VALUES (
                    :materia_id, :meeting_id, :topic, :start_time,
                    :duration, :join_url, :password, 'programada'
                )
            ");

            $stmt->execute([
                'materia_id' => $meetingData['materia_id'],
                'meeting_id' => $response['id'],
                'topic' => $response['topic'],
                'start_time' => $response['start_time'],
                'duration' => $response['duration'],
                'join_url' => $response['join_url'],
                'password' => $response['password']
            ]);

            return array_merge($response, ['db_id' => $pdo->lastInsertId()]);

        } catch (Exception $e) {
            error_log("Error creando reunión Zoom: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtiene los participantes de una reunión
     * 
     * @param string $meetingId ID de la reunión
     * @return array Lista de participantes
     */
    public function getMeetingParticipants($meetingId) {
        try {
            return $this->makeRequest("/report/meetings/{$meetingId}/participants");
        } catch (Exception $e) {
            error_log("Error obteniendo participantes: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Registra la asistencia de una reunión
     * 
     * @param int $meetingId ID de la reunión
     * @return bool
     */
    public function registerAttendance($meetingId) {
        global $pdo;

        try {
            $participants = $this->getMeetingParticipants($meetingId);
            
            // Obtener información de la reunión
            $stmt = $pdo->prepare("
                SELECT m.*, zm.materia_id 
                FROM zoom_meetings zm 
                WHERE zm.meeting_id = ?
            ");
            $stmt->execute([$meetingId]);
            $meeting = $stmt->fetch();

            if (!$meeting) {
                throw new Exception('Reunión no encontrada');
            }

            // Registrar asistencia para cada participante
            $stmt = $pdo->prepare("
                INSERT INTO asistencias (
                    materia_id, user_id, fecha_clase, estado,
                    zoom_meeting_id, tiempo_conexion
                ) VALUES (
                    :materia_id, :user_id, :fecha_clase, :estado,
                    :zoom_meeting_id, :tiempo_conexion
                )
            ");

            foreach ($participants['participants'] as $participant) {
                // Buscar usuario por email
                $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $userStmt->execute([$participant['email']]);
                $userId = $userStmt->fetchColumn();

                if ($userId) {
                    $stmt->execute([
                        'materia_id' => $meeting['materia_id'],
                        'user_id' => $userId,
                        'fecha_clase' => date('Y-m-d'),
                        'estado' => 'presente',
                        'zoom_meeting_id' => $meetingId,
                        'tiempo_conexion' => $participant['duration'] // en minutos
                    ]);
                }
            }

            // Actualizar estado de la reunión
            $stmt = $pdo->prepare("
                UPDATE zoom_meetings 
                SET estado = 'finalizada' 
                WHERE meeting_id = ?
            ");
            $stmt->execute([$meetingId]);

            return true;

        } catch (Exception $e) {
            error_log("Error registrando asistencia: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualiza una reunión existente
     */
    public function updateMeeting($meetingId, $updateData) {
        try {
            $response = $this->makeRequest("/meetings/{$meetingId}", 'PATCH', $updateData);
            
            // Actualizar en la base de datos si es necesario
            if (isset($updateData['start_time']) || isset($updateData['duration'])) {
                global $pdo;
                $stmt = $pdo->prepare("
                    UPDATE zoom_meetings 
                    SET start_time = :start_time, duration = :duration 
                    WHERE meeting_id = :meeting_id
                ");
                $stmt->execute([
                    'start_time' => $updateData['start_time'] ?? null,
                    'duration' => $updateData['duration'] ?? null,
                    'meeting_id' => $meetingId
                ]);
            }

            return $response;
        } catch (Exception $e) {
            error_log("Error actualizando reunión: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Cancela una reunión
     */
    public function cancelMeeting($meetingId) {
        try {
            $this->makeRequest("/meetings/{$meetingId}", 'DELETE');
            
            // Actualizar estado en la base de datos
            global $pdo;
            $stmt = $pdo->prepare("
                UPDATE zoom_meetings 
                SET estado = 'cancelada' 
                WHERE meeting_id = ?
            ");
            return $stmt->execute([$meetingId]);
        } catch (Exception $e) {
            error_log("Error cancelando reunión: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtiene el reporte de asistencia de una materia
     */
    public function getAttendanceReport($materiaId, $startDate = null, $endDate = null) {
        global $pdo;

        try {
            $sql = "
                SELECT 
                    u.full_Name, u.email,
                    COUNT(CASE WHEN a.estado = 'presente' THEN 1 END) as presentes,
                    COUNT(CASE WHEN a.estado = 'ausente' THEN 1 END) as ausentes,
                    COUNT(CASE WHEN a.estado = 'tardanza' THEN 1 END) as tardanzas,
                    AVG(a.tiempo_conexion) as promedio_tiempo
                FROM users u
                LEFT JOIN asistencias a ON u.id = a.user_id
                WHERE a.materia_id = :materia_id
            ";

            $params = ['materia_id' => $materiaId];

            if ($startDate && $endDate) {
                $sql .= " AND a.fecha_clase BETWEEN :start_date AND :end_date";
                $params['start_date'] = $startDate;
                $params['end_date'] = $endDate;
            }

            $sql .= " GROUP BY u.id, u.full_Name, u.email";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error generando reporte de asistencia: " . $e->getMessage());
            throw $e;
        }
    }
}
