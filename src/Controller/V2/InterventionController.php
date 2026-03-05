<?php

namespace App\Controller\V2;

use App\Entity\Intervention;
use App\Entity\User;
use App\Entity\AuditLog;
use App\Enum\SignalementStatus;
use App\Repository\SignalementRepository;
use App\Repository\InterventionRepository;
use App\Service\ValidationService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
#v2 mahtouta 
#[Route('/api/interventions')]
#[IsGranted('ROLE_MUNICIPAL_AGENT')]
class InterventionV2Controller extends AbstractController
{
    public function __construct(
        private InterventionRepository $interventionRepository,
        private SignalementRepository $signalementRepository,
        private EntityManagerInterface $entityManager,
        private ValidationService $validationService,
        private ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'list_interventions_v2', methods: ['GET'])]
    public function listInterventions(Request $request): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
            $offset = ($page - 1) * $limit;

            $criteria = ['isActive' => true];
            $orderBy = ['startDate' => 'DESC'];

            if ($signalementId = $request->query->get('signalementId')) {
                if ($signalement = $this->signalementRepository->find($signalementId)) {
                    $criteria['signalement'] = $signalement;
                }
            }

            $total = $this->interventionRepository->count($criteria);
            $interventions = $this->interventionRepository->findBy($criteria, $orderBy, $limit, $offset);
            $data = array_map(fn(Intervention $i) => $this->serializeIntervention($i), $interventions);

            return $this->json([
                'success' => true,
                'code' => 'INTERVENTIONS_RETRIEVED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'data' => [
                    'interventions' => $data,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'pages' => ceil($total / $limit),
                        'hasNextPage' => $page < ceil($total / $limit),
                        'hasPreviousPage' => $page > 1,
                    ]
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve interventions',
                'code' => 'RETRIEVAL_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'get_intervention_v2', methods: ['GET'])]
    public function getIntervention(Intervention $intervention): JsonResponse
    {
        try {
            if (!$intervention->isActive()) {
                return $this->json([
                    'success' => false,
                    'error' => 'Intervention not found',
                    'code' => 'INTERVENTION_NOT_FOUND',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_NOT_FOUND);
            }

            return $this->json([
                'success' => true,
                'code' => 'INTERVENTION_RETRIEVED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'data' => $this->serializeIntervention($intervention)
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Intervention not found',
                'code' => 'INTERVENTION_NOT_FOUND',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('', name: 'create_intervention_v2', methods: ['POST'])]
    public function createIntervention(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            $data = json_decode($request->getContent(), true);

            $constraint = new Assert\Collection([
                'signalementId' => [new Assert\NotBlank(), new Assert\Type(['type' => 'integer'])],
                'startDate' => [new Assert\NotBlank()],
                'endDate' => new Assert\Optional(),
                'notes' => [new Assert\NotBlank(), new Assert\Length(min: 5)],
            ]);

            $violations = $this->validator->validate($data, $constraint);
            if (count($violations) > 0) {
                return $this->json([
                    'success' => false,
                    'errors' => $this->validationService->formatErrors($violations),
                    'code' => 'VALIDATION_ERROR',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_BAD_REQUEST);
            }

            $signalement = $this->signalementRepository->find($data['signalementId']);
            if (!$signalement || !$signalement->isActive()) {
                return $this->json([
                    'success' => false,
                    'error' => 'Signalement not found',
                    'code' => 'SIGNALEMENT_NOT_FOUND',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_NOT_FOUND);
            }

            try {
                $startDate = new \DateTime($data['startDate']);
                $endDate = isset($data['endDate']) ? new \DateTime($data['endDate']) : null;

                if ($endDate && $endDate <= $startDate) {
                    return $this->json([
                        'success' => false,
                        'error' => 'End date must be after start date',
                        'code' => 'INVALID_DATE_RANGE',
                        'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                    ], Response::HTTP_BAD_REQUEST);
                }

                $intervention = new Intervention();
                $intervention->setSignalement($signalement);
                $intervention->setStartDate($startDate);
                $intervention->setEndDate($endDate);
                $intervention->setNotes($data['notes']);
                $intervention->setIsActive(true);
                $intervention->setCreatedAt(new \DateTimeImmutable());
                $intervention->setUpdatedAt(new \DateTimeImmutable());
                $intervention->setCreatedBy($user->getEmail());
                $intervention->setUpdatedBy($user->getEmail());

                if ($signalement->getStatus() === SignalementStatus::NEW) {
                    $signalement->changeStatus(SignalementStatus::IN_PROGRESS);
                    $signalement->setUpdatedAt(new \DateTimeImmutable());
                    $signalement->setUpdatedBy($user);
                }

                $this->entityManager->persist($intervention);
                $this->entityManager->flush();

                $auditLog = new AuditLog();
                $auditLog->setAction('CREATE_INTERVENTION');
                $auditLog->setEntityName('Intervention');
                $auditLog->setEntityId($intervention->getId());
                $auditLog->setChanges(['signalement_id' => $data['signalementId']]);
                $auditLog->setTimestamp(new \DateTimeImmutable());
                $auditLog->setUser($user);
                $auditLog->setIsActive(true);
                $this->entityManager->persist($auditLog);
                $this->entityManager->flush();

                return $this->json([
                    'success' => true,
                    'code' => 'INTERVENTION_CREATED',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                    'data' => $this->serializeIntervention($intervention)
                ], Response::HTTP_CREATED);
            } catch (\Exception $e) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid date format',
                    'code' => 'INVALID_DATE_FORMAT',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_BAD_REQUEST);
            }
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to create intervention',
                'code' => 'CREATION_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'update_intervention_v2', methods: ['PUT'])]
    public function updateIntervention(Intervention $intervention, Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            $data = json_decode($request->getContent(), true) ?? [];
            $changes = [];
            $errors = [];

            if (isset($data['endDate'])) {
                try {
                    $endDate = new \DateTime($data['endDate']);
                    if ($endDate <= $intervention->getStartDate()) {
                        $errors['endDate'] = 'End date must be after start date';
                    } else {
                        $changes['endDate'] = ['from' => $intervention->getEndDate()?->format('Y-m-d'), 'to' => $endDate->format('Y-m-d')];
                        $intervention->setEndDate($endDate);
                    }
                } catch (\Exception $e) {
                    $errors['endDate'] = 'Invalid date format';
                }
            }

            if (isset($data['notes'])) {
                if (strlen($data['notes']) < 5) {
                    $errors['notes'] = 'Notes must be at least 5 characters';
                } else {
                    $changes['notes'] = ['from' => $intervention->getNotes(), 'to' => $data['notes']];
                    $intervention->setNotes($data['notes']);
                }
            }

            if (!empty($errors)) {
                return $this->json([
                    'success' => false,
                    'errors' => $errors,
                    'code' => 'VALIDATION_ERROR',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_BAD_REQUEST);
            }

            if (empty($changes)) {
                return $this->json([
                    'success' => false,
                    'error' => 'No changes provided',
                    'code' => 'NO_CHANGES',
                    'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
                ], Response::HTTP_BAD_REQUEST);
            }

            $intervention->setUpdatedAt(new \DateTimeImmutable());
            $intervention->setUpdatedBy($user->getEmail());
            $this->entityManager->flush();

            $auditLog = new AuditLog();
            $auditLog->setAction('UPDATE_INTERVENTION');
            $auditLog->setEntityName('Intervention');
            $auditLog->setEntityId($intervention->getId());
            $auditLog->setChanges($changes);
            $auditLog->setTimestamp(new \DateTimeImmutable());
            $auditLog->setUser($user);
            $auditLog->setIsActive(true);
            $this->entityManager->persist($auditLog);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'code' => 'INTERVENTION_UPDATED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'data' => [
                    'changes' => $changes,
                    'intervention' => $this->serializeIntervention($intervention)
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to update intervention',
                'code' => 'UPDATE_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'delete_intervention_v2', methods: ['DELETE'])]
    public function deleteIntervention(Intervention $intervention): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();

            $intervention->setIsActive(false);
            $intervention->setUpdatedAt(new \DateTimeImmutable());
            $intervention->setUpdatedBy($user->getEmail());
            $this->entityManager->flush();

            $auditLog = new AuditLog();
            $auditLog->setAction('DELETE_INTERVENTION');
            $auditLog->setEntityName('Intervention');
            $auditLog->setEntityId($intervention->getId());
            $auditLog->setChanges(['isActive' => false]);
            $auditLog->setTimestamp(new \DateTimeImmutable());
            $auditLog->setUser($user);
            $auditLog->setIsActive(true);
            $this->entityManager->persist($auditLog);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'code' => 'INTERVENTION_DELETED',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
                'data' => ['interventionId' => $intervention->getId()]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to delete intervention',
                'code' => 'DELETE_ERROR',
                'timestamp' => (new DateTime())->format('Y-m-d H:i:s')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function serializeIntervention(Intervention $intervention): array
    {
        return [
            'id' => $intervention->getId(),
            'signalementId' => $intervention->getSignalement()->getId(),
            'signalementTitle' => $intervention->getSignalement()->getTitle(),
            'startDate' => $intervention->getStartDate()?->format('Y-m-d H:i:s'),
            'endDate' => $intervention->getEndDate()?->format('Y-m-d H:i:s'),
            'notes' => $intervention->getNotes(),
            'duration' => $intervention->getEndDate() ? $intervention->getEndDate()->diff($intervention->getStartDate())->format('%h hours %i minutes') : null,
            'status' => $intervention->getEndDate() ? 'COMPLETED' : 'IN_PROGRESS',
            'createdAt' => $intervention->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $intervention->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'createdBy' => $intervention->getCreatedBy(),
            'updatedBy' => $intervention->getUpdatedBy(),
        ];
    }
}
