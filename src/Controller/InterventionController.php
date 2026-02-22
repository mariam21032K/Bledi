<?php

namespace App\Controller;

use App\Entity\Intervention;
use App\Entity\User;
use App\Entity\AuditLog;
use App\Enum\SignalementStatus;
use App\Repository\SignalementRepository;
use App\Repository\InterventionRepository;
use App\Service\ValidationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/interventions')]
#[IsGranted('ROLE_MUNICIPAL_AGENT')]
class InterventionController extends AbstractController
{
    public function __construct(
        private InterventionRepository $interventionRepository,
        private SignalementRepository $signalementRepository,
        private EntityManagerInterface $entityManager,
        private ValidationService $validationService,
        private ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'list_interventions', methods: ['GET'])]
    public function listInterventions(Request $request): JsonResponse
    {
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

        return $this->json(['success' => true, 'data' => $data, 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => ceil($total / $limit)]]);
    }

    #[Route('/{id}', name: 'get_intervention', methods: ['GET'])]
    public function getIntervention(Intervention $intervention): JsonResponse
    {
        if (!$intervention->isActive()) {
            return $this->json(['success' => false, 'error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['success' => true, 'data' => $this->serializeIntervention($intervention)]);
    }

    #[Route('', name: 'create_intervention', methods: ['POST'])]
    public function createIntervention(Request $request): JsonResponse
    {
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
            return $this->json(['success' => false, 'errors' => $this->validationService->formatErrors($violations)], Response::HTTP_BAD_REQUEST);
        }

        $signalement = $this->signalementRepository->find($data['signalementId']);
        if (!$signalement || !$signalement->isActive()) {
            return $this->json(['success' => false, 'error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $startDate = new \DateTime($data['startDate']);
            $endDate = isset($data['endDate']) ? new \DateTime($data['endDate']) : null;

            if ($endDate && $endDate <= $startDate) {
                return $this->json(['success' => false, 'error' => 'End date must be after start'], Response::HTTP_BAD_REQUEST);
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

            return $this->json(['success' => true, 'message' => 'Created', 'data' => $this->serializeIntervention($intervention)], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'error' => 'Invalid date format'], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'update_intervention', methods: ['PUT'])]
    public function updateIntervention(Intervention $intervention, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];
        $changes = [];

        if (isset($data['endDate'])) {
            try {
                $endDate = new \DateTime($data['endDate']);
                if ($endDate <= $intervention->getStartDate()) {
                    return $this->json(['success' => false, 'error' => 'Invalid date'], Response::HTTP_BAD_REQUEST);
                }
                $changes['endDate'] = ['from' => $intervention->getEndDate()?->format('Y-m-d'), 'to' => $endDate->format('Y-m-d')];
                $intervention->setEndDate($endDate);
            } catch (\Exception $e) {
                return $this->json(['success' => false, 'error' => 'Invalid format'], Response::HTTP_BAD_REQUEST);
            }
        }

        if (isset($data['notes'])) {
            if (strlen($data['notes']) < 5) {
                return $this->json(['success' => false, 'error' => 'Invalid notes'], Response::HTTP_BAD_REQUEST);
            }
            $changes['notes'] = ['from' => $intervention->getNotes(), 'to' => $data['notes']];
            $intervention->setNotes($data['notes']);
        }

        if (empty($changes)) {
            return $this->json(['success' => false, 'error' => 'No changes'], Response::HTTP_BAD_REQUEST);
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

        return $this->json(['success' => true, 'message' => 'Updated', 'data' => $this->serializeIntervention($intervention)]);
    }

    #[Route('/{id}', name: 'delete_intervention', methods: ['DELETE'])]
    public function deleteIntervention(Intervention $intervention): JsonResponse
    {
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

        return $this->json(['success' => true, 'message' => 'Deleted']);
    }

    private function serializeIntervention(Intervention $intervention): array
    {
        return [
            'id' => $intervention->getId(),
            'signalementId' => $intervention->getSignalement()->getId(),
            'startDate' => $intervention->getStartDate()?->format('Y-m-d H:i:s'),
            'endDate' => $intervention->getEndDate()?->format('Y-m-d H:i:s'),
            'notes' => $intervention->getNotes(),
            'createdAt' => $intervention->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $intervention->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
