# Almacenamiento de Archivos

La gestión de archivos sigue una arquitectura descompuesta para manejar múltiples tipos de entrada y drivers de almacenamiento sin fisuras.

Componentes Clave:
- **`app/Services/Files/FileService.php`**: Orquesta el almacenamiento y la persistencia en base de datos. Expone el método unificado `destroy` para limpieza atómica.
- **`app/Interfaces/Files/FileRepositoryInterface.php`**: Estandariza la recuperación y persistencia de metadatos.
- **`app/Libraries/Files/MultipartProcessor.php`**: Maneja las cargas de archivos HTTP estándar.
- **`app/Libraries/Files/Base64Processor.php`**: Decodifica y valida Data URIs y Base64 puro.
- **`app/Libraries/Files/FilenameGenerator.php`**: Sanea nombres y previene colisiones de almacenamiento.
- **`app/Support/Files/ProcessedFile.php`**: Value Object estandarizado para transferencias basadas en streams.

Drivers de Almacenamiento (`app/Libraries/Storage/`):
- **LocalDriver**: Almacena archivos en `writable/uploads/`.
- **S3Driver**: Se integra con AWS S3 usando flysystem.

Variables de Entorno:
- `FILE_STORAGE_DRIVER`: `local` o `s3`.
- `FILE_MAX_SIZE`: Límite en bytes.
- `FILE_ALLOWED_TYPES`: Extensiones separadas por comas (ej. `jpg,png,pdf`).

Validación:
Todas las operaciones de archivos utilizan validación basada en DTOs. Los procesadores garantizan que los archivos sean estructuralmente sólidos y seguros antes de que el `FileService` intente la persistencia.
