<?php
/**
 * Configuración y funciones para el envío de correos electrónicos
 * utilizando PHPMailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require __DIR__ . '/../vendor/autoload.php';

class EmailService {
    private $mailer;
    private static $instance = null;

    private function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->configureSMTP();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function configureSMTP() {
        try {
            $this->mailer->isSMTP();
            $this->mailer->Host = 'smtp.hostinger.com';
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = 'notificaciones@buildingbridgesrn.org';
            $this->mailer->Password = ''; // Configurar en producción
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $this->mailer->Port = 465;
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->setFrom('notificaciones@buildingbridgesrn.org', 'E-Learning PLI');
        } catch (Exception $e) {
            error_log("Error configurando SMTP: " . $e->getMessage());
            throw new Exception('Error en la configuración del servidor de correo');
        }
    }

    /**
     * Envía un correo electrónico
     * 
     * @param string|array $to Destinatario(s)
     * @param string $subject Asunto del correo
     * @param string $body Cuerpo del correo en HTML
     * @param string $altBody Cuerpo alternativo en texto plano
     * @param array $attachments Array de archivos adjuntos [['path' => '', 'name' => '']]
     * @return bool
     */
    public function sendEmail($to, $subject, $body, $altBody = '', $attachments = []) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

            // Agregar destinatarios
            if (is_array($to)) {
                foreach ($to as $email) {
                    $this->mailer->addAddress(trim($email));
                }
            } else {
                $this->mailer->addAddress(trim($to));
            }

            // Configurar contenido
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $this->getEmailTemplate($subject, $body);
            $this->mailer->AltBody = $altBody ?: strip_tags($body);

            // Agregar archivos adjuntos
            foreach ($attachments as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $this->mailer->addAttachment(
                        $attachment['path'],
                        $attachment['name'] ?? basename($attachment['path'])
                    );
                }
            }

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Error enviando correo: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envía notificación de nueva tarea
     */
    public function sendNewTaskNotification($studentEmail, $taskData) {
        $subject = "Nueva Tarea Asignada: {$taskData['titulo']}";
        $body = "
            <h2>Nueva Tarea Asignada</h2>
            <p>Se ha asignado una nueva tarea en la materia {$taskData['materia']}.</p>
            <ul>
                <li><strong>Título:</strong> {$taskData['titulo']}</li>
                <li><strong>Fecha de entrega:</strong> {$taskData['fecha_entrega']}</li>
                <li><strong>Ponderación:</strong> {$taskData['ponderacion']}%</li>
            </ul>
            <p>Por favor, ingresa a la plataforma para ver los detalles y completar la tarea.</p>
        ";
        return $this->sendEmail($studentEmail, $subject, $body);
    }

    /**
     * Envía notificación de calificación
     */
    public function sendGradeNotification($studentEmail, $gradeData) {
        $subject = "Calificación Registrada: {$gradeData['materia']}";
        $body = "
            <h2>Nueva Calificación Registrada</h2>
            <p>Se ha registrado una calificación para la tarea/examen en {$gradeData['materia']}.</p>
            <ul>
                <li><strong>Actividad:</strong> {$gradeData['actividad']}</li>
                <li><strong>Calificación:</strong> {$gradeData['calificacion']}</li>
                <li><strong>Comentarios:</strong> {$gradeData['comentarios']}</li>
            </ul>
            <p>Ingresa a la plataforma para ver más detalles.</p>
        ";
        return $this->sendEmail($studentEmail, $subject, $body);
    }

    /**
     * Envía recordatorio de tarea próxima a vencer
     */
    public function sendTaskReminder($studentEmail, $taskData) {
        $subject = "Recordatorio: Tarea próxima a vencer";
        $body = "
            <h2>Recordatorio de Entrega</h2>
            <p>Tu tarea está próxima a vencer:</p>
            <ul>
                <li><strong>Materia:</strong> {$taskData['materia']}</li>
                <li><strong>Tarea:</strong> {$taskData['titulo']}</li>
                <li><strong>Fecha límite:</strong> {$taskData['fecha_entrega']}</li>
            </ul>
            <p>No olvides entregar tu tarea a tiempo.</p>
        ";
        return $this->sendEmail($studentEmail, $subject, $body);
    }

    /**
     * Obtiene la plantilla HTML base para los correos
     */
    private function getEmailTemplate($subject, $content) {
        return '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . $subject . '</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .header {
                    background-color: #4a90e2;
                    color: white;
                    padding: 20px;
                    text-align: center;
                    border-radius: 5px 5px 0 0;
                }
                .content {
                    background-color: #ffffff;
                    padding: 20px;
                    border: 1px solid #ddd;
                    border-radius: 0 0 5px 5px;
                }
                .footer {
                    text-align: center;
                    margin-top: 20px;
                    padding: 20px;
                    font-size: 12px;
                    color: #666;
                }
                @media only screen and (max-width: 600px) {
                    body {
                        padding: 10px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>E-Learning PLI</h1>
            </div>
            <div class="content">
                ' . $content . '
            </div>
            <div class="footer">
                <p>Este es un correo automático, por favor no responder.</p>
                <p>E-Learning PLI © ' . date('Y') . ' Todos los derechos reservados.</p>
            </div>
        </body>
        </html>';
    }
}
