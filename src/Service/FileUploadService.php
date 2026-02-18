<?php

namespace App\Service;

use App\Entity\Media;
use App\Entity\Signalement;
use App\Enum\MediaType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileUploadService
{
    private string $uploadsDir;
    private int $maxFileSize = 52428800; // 50MB

    public function __construct(
        private SluggerInterface $slugger,
        string $uploadsDir = '%kernel.project_dir%/public/uploads',
    ) {
        $this->uploadsDir = str_replace('%kernel.project_dir%', dirname(__DIR__, 2), $uploadsDir);
    }

    /**
     * Upload a file and create a Media entity
     */
    public function uploadMedia(UploadedFile $file, Signalement $signalement, MediaType $mediaType): Media
    {
        // Validate file
        $this->validateFile($file, $mediaType);

        // Generate safe filename
        $fileName = $this->generateFileName($file);

        // Create media directory if it doesn't exist
        $mediaDir = $this->uploadsDir . '/' . date('Y/m/d');
        if (!is_dir($mediaDir)) {
            mkdir($mediaDir, 0755, true);
        }

        // Move uploaded file
        $file->move($mediaDir, $fileName);

        // Create Media entity
        $media = new Media();
        $media->setType($mediaType);
        $media->setFilePath('/uploads/' . date('Y/m/d') . '/' . $fileName);
        $media->setFileSize($file->getSize());
        $media->setSignalement($signalement);
        $media->setIsActive(true);

        return $media;
    }

    /**
     * Validate uploaded file
     */
    public function validateFile(UploadedFile $file, MediaType $mediaType): bool
    {
        // Check file size
        if ($file->getSize() > $this->maxFileSize) {
            throw new \InvalidArgumentException('File size exceeds maximum allowed size of 50MB');
        }

        $mimeType = $file->getMimeType();
        $allowedMimes = $this->getAllowedMimeTypes($mediaType);

        if (!in_array($mimeType, $allowedMimes)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid file type. Allowed types for %s: %s',
                $mediaType->label(),
                implode(', ', $allowedMimes)
            ));
        }

        return true;
    }

    /**
     * Get allowed MIME types by media type
     */
    private function getAllowedMimeTypes(MediaType $mediaType): array
    {
        return match ($mediaType) {
            MediaType::IMAGE => [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
            ],
            MediaType::VIDEO => [
                'video/mp4',
                'video/quicktime',
                'video/x-msvideo',
                'video/x-matroska',
            ],
        };
    }

    /**
     * Generate a safe filename
     */
    private function generateFileName(UploadedFile $file): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        
        return sprintf(
            '%s_%s.%s',
            $safeFilename,
            uniqid(),
            $file->guessExtension()
        );
    }

    /**
     * Delete a media file
     */
    public function deleteMedia(Media $media): void
    {
        $filePath = $this->uploadsDir . $media->getFilePath();

        if (file_exists($filePath) && is_file($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * Get file URL
     */
    public function getFileUrl(Media $media): string
    {
        return $media->getFilePath();
    }

    /**
     * Check if file exists
     */
    public function fileExists(Media $media): bool
    {
        $filePath = $this->uploadsDir . $media->getFilePath();
        return file_exists($filePath) && is_file($filePath);
    }

    /**
     * Get file info
     */
    public function getFileInfo(Media $media): ?array
    {
        $filePath = $this->uploadsDir . $media->getFilePath();

        if (!file_exists($filePath)) {
            return null;
        }

        return [
            'path' => $media->getFilePath(),
            'size' => filesize($filePath),
            'type' => $media->getType(),
            'mimeType' => mime_content_type($filePath),
            'createdAt' => $media->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
