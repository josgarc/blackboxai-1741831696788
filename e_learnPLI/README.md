# E-Learning PLI - Plataforma de Aprendizaje en Línea

Sistema de gestión de aprendizaje (LMS) completo y moderno desarrollado en PHP, con una interfaz intuitiva y responsive utilizando Tailwind CSS.

## Características Principales

- 🎓 Gestión completa de cursos y materias
- 👥 Roles de usuario (Administrador, Maestro, Estudiante)
- 📝 Sistema de tareas y evaluaciones
- 📊 Seguimiento de progreso y calificaciones
- 🎯 Contenido multimedia (PDF, videos, texto enriquecido)
- 🔄 Integración con Zoom para clases en vivo
- 📧 Sistema de notificaciones por correo
- 📱 Diseño responsive y moderno
- 🔒 Sistema de autenticación seguro

## Requisitos del Sistema

- PHP 7.4 o superior
- MySQL 5.7 o superior
- Servidor web Apache/Nginx
- Composer (para gestión de dependencias)
- Extensiones PHP requeridas:
  - PDO
  - PDO_MySQL
  - GD (para procesamiento de imágenes)
  - FileInfo
  - Curl
  - OpenSSL
  - Mbstring

## Instalación

1. Clonar el repositorio:
```bash
git clone https://github.com/tu-usuario/e-learning-pli.git
cd e-learning-pli
```

2. Instalar dependencias:
```bash
composer install
```

3. Configurar la base de datos:
   - Crear una base de datos MySQL
   - Importar el archivo `database/e_learning.sql`
   - Copiar `config/config.example.php` a `config/config.php`
   - Actualizar las credenciales de la base de datos en `config/config.php`

4. Configurar el servidor web:
   - Configurar el DocumentRoot a la carpeta `public/`
   - Asegurar que el módulo mod_rewrite está habilitado
   - Dar permisos de escritura a las carpetas:
     ```bash
     chmod -R 775 uploads/
     chmod -R 775 logs/
     ```

5. Configurar el envío de correos:
   - Actualizar las credenciales SMTP en `config/config.php`
   - Probar el envío de correos con el script de prueba:
     ```bash
     php tests/mail_test.php
     ```

6. Configurar la integración con Zoom:
   - Crear una cuenta de desarrollador en Zoom
   - Obtener API Key y API Secret
   - Actualizar las credenciales en la tabla `configuraciones`

## Estructura del Proyecto

```
e_learnPLI/
├── config/             # Archivos de configuración
├── database/          # Scripts SQL y migraciones
├── includes/          # Clases y funciones principales
├── public/            # Archivos públicos accesibles
│   ├── assets/       # Recursos estáticos (CSS, JS, imágenes)
│   ├── student/      # Vistas del área de estudiantes
│   ├── teacher/      # Vistas del área de profesores
│   └── admin/        # Vistas del área administrativa
├── uploads/           # Archivos subidos por usuarios
├── logs/             # Registros del sistema
├── vendor/           # Dependencias de Composer
└── README.md         # Este archivo
```

## Configuración del Entorno de Desarrollo

1. Habilitar el modo de desarrollo en `config/config.php`:
```php
define('ENVIRONMENT', 'development');
```

2. Configurar el entorno local:
```bash
# Crear directorios necesarios
mkdir -p uploads/{documents,images,temp}
mkdir -p logs

# Establecer permisos
chmod -R 775 uploads/
chmod -R 775 logs/
```

3. Configurar virtual host en Apache:
```apache
<VirtualHost *:80>
    ServerName e-learning.local
    DocumentRoot /ruta/a/e-learning-pli/public
    
    <Directory /ruta/a/e-learning-pli/public>
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/e-learning-error.log
    CustomLog ${APACHE_LOG_DIR}/e-learning-access.log combined
</VirtualHost>
```

## Uso del Sistema

### Roles de Usuario

1. **Administrador**
   - Gestión completa del sistema
   - Creación de usuarios y asignación de roles
   - Configuración general del sistema

2. **Maestro**
   - Creación y gestión de materias
   - Carga de contenido y materiales
   - Creación de tareas y exámenes
   - Calificación de trabajos

3. **Estudiante**
   - Acceso a materias inscritas
   - Visualización de contenido
   - Entrega de tareas
   - Realización de exámenes

### Funcionalidades Principales

1. **Gestión de Materias**
   - Crear/editar materias
   - Asignar profesores
   - Inscribir estudiantes
   - Gestionar contenido

2. **Contenido Académico**
   - Carga de archivos PDF
   - Integración de videos
   - Editor de texto enriquecido
   - Programación de clases Zoom

3. **Evaluaciones**
   - Tareas con fecha límite
   - Exámenes cronometrados
   - Diferentes tipos de preguntas
   - Calificación automática/manual

4. **Seguimiento**
   - Progreso por materia
   - Registro de asistencia
   - Calificaciones y retroalimentación
   - Reportes y estadísticas

## Seguridad

- Protección contra CSRF
- Sanitización de entradas
- Encriptación de contraseñas
- Validación de sesiones
- Protección contra XSS
- Control de acceso por roles

## Mantenimiento

### Respaldos

1. Base de datos:
```bash
mysqldump -u usuario -p nombre_base > backup.sql
```

2. Archivos:
```bash
tar -czf uploads_backup.tar.gz uploads/
```

### Logs

Los archivos de registro se encuentran en `logs/`:
- error.log: Errores del sistema
- access.log: Registro de accesos
- mail.log: Registro de correos enviados

## Soporte

Para reportar problemas o solicitar soporte:
- Crear un issue en el repositorio
- Contactar a soporte@e-learningpli.com
- Consultar la documentación en línea

## Licencia

Este proyecto está licenciado bajo la Licencia MIT - ver el archivo [LICENSE](LICENSE) para más detalles.

## Contribuir

1. Fork el proyecto
2. Crear una rama para tu característica (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abrir un Pull Request

## Créditos

Desarrollado por el equipo de E-Learning PLI.

## Versión

1.0.0 - Lanzamiento inicial
