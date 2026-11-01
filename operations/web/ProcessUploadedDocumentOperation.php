<?php

namespace Operations\Web;

class ProcessUploadedDocumentOperation {
    
    private static array $allowedExtensions = ['pdf', 'doc', 'docx', 'rtf', 'txt', 'json', 'jpg', 'jpeg', 'png'];

    public static function handle(array $file, string $articleTitle = 'document'): array {
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception("File upload failed or no file was provided.");
        }

        $originalName = basename($file['name']);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, self::$allowedExtensions)) {
            throw new \Exception("Invalid file type (.$extension). Allowed: " . implode(', ', self::$allowedExtensions));
        }

        $uploadDir = APP_ROOT . '/public/uploads/files/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Slugify the article headline: lowercase, replace spaces/special chars with hyphens
        $slugTitle = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $articleTitle), '-'));
        if (empty($slugTitle)) {
            $slugTitle = 'document';
        }

        // Generate exactly 4 random digits (e.g., 4261)
        $randomFourDigits = rand(1000, 9999);
        
        // Combine headline + 4 random numbers + extension
        $uniqueFilename = $slugTitle . '-' . $randomFourDigits . '.' . $extension;
        
        $destination = $uploadDir . $uniqueFilename;
        $relativePath = '/public/uploads/files/' . $uniqueFilename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \Exception("Failed to move uploaded file to destination directory.");
        }

        $extractedText = self::extractTextContent($destination, $extension);

        return [
            'file_path' => $relativePath,
            'extracted_content' => $extractedText,
            'original_name' => $originalName
        ];
    }

    private static function extractTextContent(string $filePath, string $extension): string {
        switch ($extension) {
            case 'txt':
            case 'json':
                return file_get_contents($filePath) ?: '';
            case 'pdf':
                return "Uploaded PDF Document: " . basename($filePath) . "\n[Raw binary content indexed]";
            default:
                return "Uploaded file (" . strtoupper($extension) . "): " . basename($filePath);
        }
    }
}