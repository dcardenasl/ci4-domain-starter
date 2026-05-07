# File Storage

File management follows a decomposed architecture to handle multiple input types and storage drivers seamlessly.

Key Components:
- **`app/Services/Files/FileService.php`**: Orchestrates storage and database persistence. Exposes the unified `destroy` method for atomic cleanup.
- **`app/Interfaces/Files/FileRepositoryInterface.php`**: Standardizes metadata retrieval and persistence.
- **`app/Libraries/Files/MultipartProcessor.php`**: Handles standard HTTP file uploads.
- **`app/Libraries/Files/Base64Processor.php`**: Decodes and validates Data URIs and raw Base64.
- **`app/Libraries/Files/FilenameGenerator.php`**: Sanitizes names and prevents storage collisions.
- **`app/Support/Files/ProcessedFile.php`**: Standardized value object for stream-based transfers.

Storage Drivers (`app/Libraries/Storage/`):
- **LocalDriver**: Stores files in `writable/uploads/`.
- **S3Driver**: Integrates with AWS S3 using flysystem.

Environment Variables:
- `FILE_STORAGE_DRIVER`: `local` or `s3`.
- `FILE_MAX_SIZE`: Limit in bytes.
- `FILE_ALLOWED_TYPES`: Comma-separated extensions (e.g., `jpg,png,pdf`).

Validation:
All file operations use DTO-based validation. The processors ensure that files are structurally sound and safe before the `FileService` attempts persistence.
