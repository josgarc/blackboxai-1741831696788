<?php
/**
 * Gestión de autenticación y usuarios
 * Maneja el registro, login, y gestión de sesiones
 */

class Auth {
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

    /**
     * Registra un nuevo usuario
     * 
     * @param array $userData Datos del usuario
     * @return array|false Datos del usuario creado o false si falla
     */
    public function register($userData) {
        try {
            // Validar datos requeridos
            $requiredFields = ['full_Name', 'username', 'email', 'password', 'phone', 'country'];
            foreach ($requiredFields as $field) {
                if (empty($userData[$field])) {
                    throw new Exception("El campo {$field} es requerido.");
                }
            }

            // Validar email único
            if ($this->emailExists($userData['email'])) {
                throw new Exception('El correo electrónico ya está registrado.');
            }

            // Validar username único
            if ($this->usernameExists($userData['username'])) {
                throw new Exception('El nombre de usuario ya está en uso.');
            }

            // Hashear contraseña
            $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);

            // Insertar usuario
            $sql = "INSERT INTO users (
                full_Name, username, email, password, phone,
                country, termsAccepted, role, createdAt
            ) VALUES (
                :full_Name, :username, :email, :password, :phone,
                :country, :termsAccepted, :role, NOW()
            )";

            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([
                'full_Name' => $userData['full_Name'],
                'username' => $userData['username'],
                'email' => $userData['email'],
                'password' => $hashedPassword,
                'phone' => $userData['phone'],
                'country' => $userData['country'],
                'termsAccepted' => $userData['termsAccepted'] ?? 0,
                'role' => $userData['role'] ?? 'Estudiante'
            ]);

            if ($success) {
                // Enviar correo de bienvenida
                $emailService = EmailService::getInstance();
                $emailService->sendWelcomeEmail($userData['email'], $userData['full_Name']);

                return $this->getUserByEmail($userData['email']);
            }

            return false;
        } catch (Exception $e) {
            error_log("Error en registro: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Inicia sesión de usuario
     * 
     * @param string $email Email del usuario
     * @param string $password Contraseña
     * @return array|false Datos del usuario o false si falla
     */
    public function login($email, $password) {
        try {
            $user = $this->getUserByEmail($email);

            if (!$user) {
                throw new Exception('Usuario no encontrado.');
            }

            if (!password_verify($password, $user['password'])) {
                throw new Exception('Contraseña incorrecta.');
            }

            // Iniciar sesión
            $this->startSession($user);

            // Registrar último acceso
            $this->updateLastLogin($user['id']);

            return $user;
        } catch (Exception $e) {
            error_log("Error en login: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Inicia la sesión del usuario
     */
    private function startSession($user) {
        // Regenerar ID de sesión para prevenir session fixation
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_Name'];
        $_SESSION['last_activity'] = time();
    }

    /**
     * Cierra la sesión del usuario
     */
    public function logout() {
        session_unset();
        session_destroy();
        return true;
    }

    /**
     * Verifica si el email existe
     */
    private function emailExists($email) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Verifica si el username existe
     */
    private function usernameExists($username) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Obtiene usuario por email
     */
    public function getUserByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    /**
     * Obtiene usuario por ID
     */
    public function getUserById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Actualiza último acceso
     */
    private function updateLastLogin($userId) {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET last_login = NOW() 
            WHERE id = ?
        ");
        return $stmt->execute([$userId]);
    }

    /**
     * Actualiza contraseña
     */
    public function updatePassword($userId, $currentPassword, $newPassword) {
        try {
            $user = $this->getUserById($userId);

            if (!password_verify($currentPassword, $user['password'])) {
                throw new Exception('La contraseña actual es incorrecta.');
            }

            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET password = ? 
                WHERE id = ?
            ");
            
            return $stmt->execute([$hashedPassword, $userId]);
        } catch (Exception $e) {
            error_log("Error actualizando contraseña: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Inicia proceso de recuperación de contraseña
     */
    public function initiatePasswordReset($email) {
        try {
            $user = $this->getUserByEmail($email);
            
            if (!$user) {
                throw new Exception('Usuario no encontrado.');
            }

            // Generar token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

            // Guardar token
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET reset_token = ?, reset_token_expiry = ? 
                WHERE id = ?
            ");
            
            if ($stmt->execute([$token, $expiry, $user['id']])) {
                // Enviar correo con link de recuperación
                $emailService = EmailService::getInstance();
                return $emailService->sendPasswordResetEmail($email, $token);
            }

            return false;
        } catch (Exception $e) {
            error_log("Error iniciando reset de contraseña: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Completa el proceso de recuperación de contraseña
     */
    public function completePasswordReset($token, $newPassword) {
        try {
            // Verificar token válido y no expirado
            $stmt = $this->pdo->prepare("
                SELECT id 
                FROM users 
                WHERE reset_token = ? 
                AND reset_token_expiry > NOW()
            ");
            $stmt->execute([$token]);
            $userId = $stmt->fetchColumn();

            if (!$userId) {
                throw new Exception('Token inválido o expirado.');
            }

            // Actualizar contraseña y limpiar token
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $stmt = $this->pdo->prepare("
                UPDATE users 
                SET password = ?, reset_token = NULL, reset_token_expiry = NULL 
                WHERE id = ?
            ");
            
            return $stmt->execute([$hashedPassword, $userId]);
        } catch (Exception $e) {
            error_log("Error completando reset de contraseña: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualiza perfil de usuario
     */
    public function updateProfile($userId, $userData) {
        try {
            $allowedFields = ['full_Name', 'phone', 'country'];
            $updates = [];
            $params = [];

            foreach ($allowedFields as $field) {
                if (isset($userData[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $userData[$field];
                }
            }

            if (empty($updates)) {
                throw new Exception('No hay datos para actualizar.');
            }

            $params[] = $userId;
            
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute($params);
        } catch (Exception $e) {
            error_log("Error actualizando perfil: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verifica si el usuario está autenticado
     */
    public function isAuthenticated() {
        return isset($_SESSION['user_id']);
    }

    /**
     * Verifica si el usuario tiene un rol específico
     */
    public function hasRole($role) {
        return isset($_SESSION['role']) && $_SESSION['role'] === $role;
    }

    /**
     * Verifica si la sesión ha expirado
     */
    public function checkSessionExpiry() {
        $maxLifetime = 30 * 60; // 30 minutos
        
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity'] > $maxLifetime)) {
            $this->logout();
            return true;
        }
        
        $_SESSION['last_activity'] = time();
        return false;
    }
}
