<?php

namespace App\Controller;

use App\Entity\Media;
use App\Entity\User;
use App\Entity\AuditLog;
use App\Enum\MediaType;
use App\Repository\SignalementRepository;
use App\Repository\MediaRepository;
use App\Service\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/media')]
#[IsGranted('ROLE_USER')]
class MediaController extends AbstractController
{
    public function __construct(
        private FileUploadService $fileUploadService,
        private SignalementRepository $signalementRepository,
        private MediaRepository $mediaRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/upload', name: 'upload_media', methods: ['POST'])]
    public function uploadMedia(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $file = $request->files->get('file');
        $signalementId = $request->request->get('signalementId');

        if (!$file || !$signalementId) {
            return $this->json(['success' => false, 'error' => 'File and signalementId required'], Response::HTTP_BAD_REQUEST);
        }

        $signalement = $this->signalementRepository->find($signalementId);
        if (!$signalement || !$signalement->isActive()) {
            return $this->json(['success' => false, 'error' => 'Signalement not found'], Response::HTTP_NOT_FOUND);
        }

        $mimeType = $file->getMimeType();
        $mediaType = $this->detectMediaType($mimeType);

        if (!$mediaType) {
            return $this->json(['success' => false, 'error' => 'Unsupported file type'], Response::HTTP_BAD_REQUEST);
        }

        $this->fileUploadService->validateFile($file, $mediaType);

        try {
            $media = $this->fileUploadService->uploadMedia($file, $signalement, $mediaType);

            $auditLog = new AuditLog();
            $auditLog->setAction('UPLOAD_MEDIA');
            $auditLog->setEntityName('Media');
            $auditLog->setEntityId($media->getId());
            $auditLog->setChanges(['filename' => $file->getClientOriginalName(), 'type' => $mediaType->value, 'size' => $file->getSize()]);
            $auditLog->setTimestamp(new \DateTimeImmutable());
            $auditLog->setUser($user);
            $auditLog->setIsActive(true);
            $this->entityManager->persist($auditLog);
            $this->entityManager->flush();

            return $this->json(['success' => true, 'message' => 'Media uploaded', 'data' => $this->serializeMedia($media)], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => 'Upload failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/download', name: 'download_media', methods: ['GET'])]
    public function downloadMedia(Media $media): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $signalement = $media->getSignalement();

        if ($signalement->getUser() !== $user && !in_array($user->getUserRole()->value, ['ADMIN', 'MUNICIPAL_AGENT'])) {
            throw $this->createAccessDeniedException('No access');
        }

        $filePath = $media->getFilePath();
        $fullPath = $this->getParameter('kernel.project_dir') . '/public' . $filePath;

        if (!file_exists($fullPath)) {
            return $this->json(['success' => false, 'error' => 'File not found'], Response::HTTP_NOT_FOUND);
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
    }

    #[Route('/{id}', name: 'delete_media', methods: ['DELETE'])]
    public function deleteMedia(Media $media): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $signalement = $media->getSignalement();

        if ($signalement->getUser() !== $user && !in_array($user->getUserRole()->value, ['ADMIN', 'MUNICIPAL_AGENT'])) {
            throw $this->createAccessDeniedException('No access');
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

            return $this->json(['success' => true, 'message' => 'Media deleted']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => 'Delete failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/signalement/{signalementId}', name: 'list_signalement_media', methods: ['GET'])]
    public function listSignalementMedia(int $signalementId): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $signalement = $this->signalementRepository->find($signalementId);

        if (!$signalement || !$signalement->isActive()) {
            return $this->json(['success' => false, 'error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        if ($signalement->getUser() !== $user && !in_array($user->getUserRole()->value, ['ADMIN', 'MUNICIPAL_AGENT'])) {
            throw $this->createAccessDeniedException('No access');
        }

        $mediaFiles = $this->mediaRepository->findBy(['signalement' => $signalement, 'isActive' => true], ['createdAt' => 'DESC']);
        $data = array_map(fn(Media $m) => $this->serializeMedia($m), $mediaFiles);

        return $this->json(['success' => true, 'data' => $data, 'total' => count($data)]);
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
            'signalementId' => $media->getSignalement()->getId(),
            'size' => $media->getFileSize(),
            'createdAt' => $media->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
