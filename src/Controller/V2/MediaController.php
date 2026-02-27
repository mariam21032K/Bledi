<?php

namespace App\Controller;

use App\Entity\Media;
use App\Entity\User;
use App\Entity\AuditLog;
use App\Enum\MediaType;
use App\Repository\SignalementRepository;
use App\Repository\MediaRepository;
use App\Service\FileUploadService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v2/media')]
#[IsGranted('ROLE_USER')]
class MediaV2Controller extends AbstractController
{
    public function __construct(
        private FileUploadService $fileUploadService,
        private SignalementRepository $signalementRepository,
        private MediaRepository $mediaRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/upload', name: 'upload_media_v2', methods: ['POST'])]
    public function uploadMedia(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            $file = $request->files->get('file');
            $signalementId = $request->request->get('signalementId');

            $errors = [];

            if (!$file) {
                $errors['file'] = 'File is required';
            }
            if (!$signalementId) {
                $errors['signalementId'] = 'Signalement ID is required';
            }

            if (!empty($errors)) {
                return $this->json([
                    'success' => false,
                    'errors' => $errors,
                    'code' => 'VALIDATION_ERROR',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_BAD_REQUEST);
            }

            $signalement = $this->signalementRepository->find($signalementId);
            if (!$signalement || !$signalement->isActive()) {
                return $this->json([
                    'success' => false,
                    'error' => 'Signalement not found',
                    'code' => 'SIGNALEMENT_NOT_FOUND',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_NOT_FOUND);
            }

            $mimeType = $file->getMimeType();
            $mediaType = $this->detectMediaType($mimeType);

            if (!$mediaType) {
                return $this->json([
                    'success' => false,
                    'error' => 'Unsupported file type',
                    'code' => 'UNSUPPORTED_FILE_TYPE',
                    'details' => ['mimeType' => $mimeType],
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_BAD_REQUEST);
            }

            try {
                $this->fileUploadService->validateFile($file, $mediaType);
            } catch (\Exception $e) {
                return $this->json([
                    'success' => false,
                    'error' => 'File validation failed',
                    'code' => 'FILE_VALIDATION_ERROR',
                    'details' => ['message' => $e->getMessage()],
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_BAD_REQUEST);
            }

            try {
                $media = $this->fileUploadService->uploadMedia($file, $signalement, $mediaType);

                $auditLog = new AuditLog();
                $auditLog->setAction('UPLOAD_MEDIA');
                $auditLog->setEntityName('Media');
                $auditLog->setEntityId($media->getId());
                $auditLog->setChanges([
                    'filename' => $file->getClientOriginalName(),
                    'type' => $mediaType->value,
                    'size' => $file->getSize()
                ]);
                $auditLog->setTimestamp(new \DateTimeImmutable());
                $auditLog->setUser($user);
                $auditLog->setIsActive(true);
                $this->entityManager->persist($auditLog);
                $this->entityManager->flush();

                return $this->json([
                    'success' => true,
                    'code' => 'MEDIA_UPLOADED',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                    'data' => $this->serializeMedia($media)
                ], Response::HTTP_CREATED);
            } catch (\Exception $e) {
                return $this->json([
                    'success' => false,
                    'error' => 'Upload failed',
                    'code' => 'UPLOAD_ERROR',
                    'details' => ['message' => $e->getMessage()],
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Upload operation failed',
                'code' => 'OPERATION_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/download', name: 'download_media_v2', methods: ['GET'])]
    public function downloadMedia(Media $media): Response
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            $signalement = $media->getSignalement();

            // Check access
            if ($signalement->getUser() !== $user && !in_array($user->getUserRole()->value, ['ADMIN', 'MUNICIPAL_AGENT'])) {
                return $this->json([
                    'success' => false,
                    'error' => 'Access denied to this media',
                    'code' => 'ACCESS_DENIED',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_FORBIDDEN);
            }

            $filePath = $media->getFilePath();
            $fullPath = $this->getParameter('kernel.project_dir') . '/public' . $filePath;

            if (!file_exists($fullPath)) {
                return $this->json([
                    'success' => false,
                    'error' => 'File not found',
                    'code' => 'FILE_NOT_FOUND',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_NOT_FOUND);
            }

            $auditLog = new AuditLog();
            $auditLog->setAction('DOWNLOAD_MEDIA');
            $auditLog->setEntityName('Media');
            $auditLog->setEntityId($media->getId());
            $auditLog->setChanges(['downloaded' => true]);
            $auditLog->setTimestamp(new \DateTimeImmutable());
            $auditLog->setUser($user);
            $auditLog->setIsActive(true);
            $this->entityManager->persist($auditLog);
            $this->entityManager->flush();

            $response = new BinaryFileResponse($fullPath);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($fullPath));

            return $response;
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Download failed',
                'code' => 'DOWNLOAD_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'delete_media_v2', methods: ['DELETE'])]
    public function deleteMedia(Media $media): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            $signalement = $media->getSignalement();

            // Check access
            if ($signalement->getUser() !== $user && !in_array($user->getUserRole()->value, ['ADMIN', 'MUNICIPAL_AGENT'])) {
                return $this->json([
                    'success' => false,
                    'error' => 'Access denied to this media',
                    'code' => 'ACCESS_DENIED',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_FORBIDDEN);
            }

            try {
                $this->fileUploadService->deleteMedia($media);

                $media->setIsActive(false);
                $media->setUpdatedAt(new \DateTimeImmutable());
                $this->entityManager->flush();

                $auditLog = new AuditLog();
                $auditLog->setAction('DELETE_MEDIA');
                $auditLog->setEntityName('Media');
                $auditLog->setEntityId($media->getId());
                $auditLog->setChanges(['deleted' => true]);
                $auditLog->setTimestamp(new \DateTimeImmutable());
                $auditLog->setUser($user);
                $auditLog->setIsActive(true);
                $this->entityManager->persist($auditLog);
                $this->entityManager->flush();

                return $this->json([
                    'success' => true,
                    'code' => 'MEDIA_DELETED',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                    'data' => ['mediaId' => $media->getId()]
                ], Response::HTTP_OK);
            } catch (\Exception $e) {
                return $this->json([
                    'success' => false,
                    'error' => 'Delete failed',
                    'code' => 'DELETE_ERROR',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Delete operation failed',
                'code' => 'OPERATION_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/signalement/{signalementId}', name: 'list_signalement_media_v2', methods: ['GET'])]
    public function listSignalementMedia(int $signalementId): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            $signalement = $this->signalementRepository->find($signalementId);

            if (!$signalement || !$signalement->isActive()) {
                return $this->json([
                    'success' => false,
                    'error' => 'Signalement not found',
                    'code' => 'SIGNALEMENT_NOT_FOUND',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_NOT_FOUND);
            }

            // Check access
            if ($signalement->getUser() !== $user && !in_array($user->getUserRole()->value, ['ADMIN', 'MUNICIPAL_AGENT'])) {
                return $this->json([
                    'success' => false,
                    'error' => 'Access denied to this signalement',
                    'code' => 'ACCESS_DENIED',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_FORBIDDEN);
            }

            $mediaFiles = $this->mediaRepository->findBy(
                ['signalement' => $signalement, 'isActive' => true],
                ['createdAt' => 'DESC']
            );
            $data = array_map(fn(Media $m) => $this->serializeMedia($m), $mediaFiles);

            return $this->json([
                'success' => true,
                'code' => 'MEDIA_RETRIEVED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'data' => [
                    'signalementId' => $signalementId,
                    'mediaFiles' => $data,
                    'total' => count($data),
                    'totalSize' => array_sum(array_map(fn(Media $m) => $m->getFileSize(), $mediaFiles)),
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve media',
                'code' => 'RETRIEVAL_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function detectMediaType(string $mimeType): ?MediaType
    {
        $imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $videoTypes = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska'];

        return in_array($mimeType, $imageTypes) ? MediaType::IMAGE : (in_array($mimeType, $videoTypes) ? MediaType::VIDEO : null);
    }

    private function serializeMedia(Media $media): array
    {
        return [
            'id' => $media->getId(),
            'type' => $media->getType()->value,
            'filePath' => $media->getFilePath(),
            'fileUrl' => '/uploads' . $media->getFilePath(),
            'fileName' => basename($media->getFilePath()),
            'signalementId' => $media->getSignalement()->getId(),
            'size' => $media->getFileSize(),
            'sizeFormatted' => $this->formatBytes($media->getFileSize()),
            'createdAt' => $media->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $media->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
