<?php

namespace App\Service;

use App\Entity\Signalement;
use App\Entity\User;
use App\Entity\Intervention;
use App\Enum\SignalementStatus;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Email Notification Service
 * 
 * Triggers email notifications on critical signalement events:
 * - Signalement Creation: Notifies user when their report is created
 * - Status Changes: Citizen notified of progress (NEW → IN_PROGRESS → RESOLVED/REJECTED)
 * - Intervention Assignment: Agent assigned to investigate receives notification
 * - Overdue Interventions: Admin notified if intervention exceeds expected timeframe
 * 
 * Supports multilingual notifications based on user language preference
 */
class NotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private TranslationService $translationService
    ) {}

    /**
     * Send confirmation email when citizen creates a signalement
     */
    public function notifySignalementCreated(Signalement $signalement): void
    {
        try {
            $user = $signalement->getUser();
            $this->translationService->setLanguage($user->getLanguage());

            $email = (new Email())
                ->from('noreply@bledi.local')
                ->to($user->getEmail())
                ->subject($this->translationService->get('signalment_created_subject', 'notifications'))
                ->html($this->getSignalementCreatedTemplate($signalement));

            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Log error but don't fail the signalement creation
            error_log('Email notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Notify citizen of signalement status changes
     */
    public function notifyStatusChanged(Signalement $signalement, SignalementStatus $oldStatus): void
    {
        try {
            $user = $signalement->getUser();
            $this->translationService->setLanguage($user->getLanguage());

            $email = (new Email())
                ->from('noreply@bledi.local')
                ->to($user->getEmail())
                ->subject($this->translationService->get('status_changed_subject', 'notifications'))
                ->html($this->getStatusChangeTemplate($signalement));

            $this->mailer->send($email);
        } catch (\Exception $e) {
            error_log('Email notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Notify agent when intervention is assigned to them
     */
    public function notifyInterventionAssigned(Intervention $intervention): void
    {
        try {
            $agent = $intervention->getAssignedAgent();
            if (!$agent) {
                return;
            }

            $this->translationService->setLanguage($agent->getLanguage());

            $email = (new Email())
                ->from('noreply@bledi.local')
                ->to($agent->getEmail())
                ->subject($this->translationService->get('intervention_assigned_subject', 'notifications'))
                ->html($this->getInterventionAssignedTemplate($intervention));

            $this->mailer->send($email);
        } catch (\Exception $e) {
            error_log('Email notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Notify admin of overdue interventions
     */
    public function notifyOverdueIntervention(Intervention $intervention): void
    {
        try {
            // Use English for admin notifications by default
            $this->translationService->setLanguage('en');

            $email = (new Email())
                ->from('noreply@bledi.local')
                ->to('admin@bledi.local')
                ->subject($this->translationService->get('overdue_intervention_subject', 'notifications'))
                ->html($this->getOverdueTemplate($intervention));

            $this->mailer->send($email);
        } catch (\Exception $e) {
            error_log('Email notification failed: ' . $e->getMessage());
        }
    }

    private function getSignalementCreatedTemplate(Signalement $signalement): string
    {
        $greeting = $this->translationService->get('signalment_created_greeting', 'notifications');
        $body = $this->translationService->get('signalment_created_body', 'notifications');
        $details = $this->translationService->get('signalment_created_details', 'notifications');
        $titleLabel = $this->translationService->get('signalment_created_title', 'notifications');
        $categoryLabel = $this->translationService->get('signalment_created_category', 'notifications');
        $statusLabel = $this->translationService->get('signalment_created_status', 'notifications');
        $dateLabel = $this->translationService->get('signalment_created_date', 'notifications');
        $updates = $this->translationService->get('signalment_created_updates', 'notifications');
        $received = $this->translationService->get('signalment_created_received', 'notifications');

        return <<<HTML
        <h2>{$received}</h2>
        <p>{$greeting} {$signalement->getUser()->getFirstName()},</p>
        <p>{$body}</p>
        <p><strong>{$details}</strong></p>
        <ul>
            <li>{$titleLabel} {$signalement->getTitle()}</li>
            <li>{$categoryLabel} {$signalement->getCategory()->getName()}</li>
            <li>{$statusLabel} New</li>
            <li>{$dateLabel} {$signalement->getCreatedAt()->format('m/d/Y H:i')}</li>
        </ul>
        <p>{$updates}</p>
        HTML;
    }

    private function getStatusChangeTemplate(Signalement $signalement): string
    {
        $greeting = $this->translationService->get('status_changed_greeting', 'notifications');
        $title = $this->translationService->get('status_changed_title', 'notifications');
        $details = $this->translationService->get('status_changed_details', 'notifications');
        $titleLabel = $this->translationService->get('status_changed_title_label', 'notifications');
        $statusLabel = $this->translationService->get('status_changed_status', 'notifications');
        $updatedLabel = $this->translationService->get('status_changed_updated', 'notifications');

        $statusKey = 'status_' . strtolower($signalement->getStatus()->value);
        $statusMessage = $this->translationService->get($statusKey, 'notifications');

        return <<<HTML
        <h2>{$title}</h2>
        <p>{$greeting} {$signalement->getUser()->getFirstName()},</p>
        <p>{$statusMessage}</p>
        <p><strong>{$details}</strong></p>
        <ul>
            <li>{$titleLabel} {$signalement->getTitle()}</li>
            <li>{$statusLabel} {$signalement->getStatus()->value}</li>
            <li>{$updatedLabel} {$signalement->getUpdatedAt()->format('m/d/Y H:i')}</li>
        </ul>
        HTML;
    }

    private function getInterventionAssignedTemplate(Intervention $intervention): string
    {
        $signalement = $intervention->getSignalement();
        $greeting = $this->translationService->get('intervention_assigned_greeting', 'notifications');
        $body = $this->translationService->get('intervention_assigned_body', 'notifications');
        $reportDetails = $this->translationService->get('intervention_assigned_report_details', 'notifications');
        $titleLabel = $this->translationService->get('intervention_assigned_title', 'notifications');
        $priorityLabel = $this->translationService->get('intervention_assigned_priority', 'notifications');
        $locationLabel = $this->translationService->get('intervention_assigned_location', 'notifications');
        $descriptionLabel = $this->translationService->get('intervention_assigned_description', 'notifications');

        return <<<HTML
        <h2>New Intervention Assigned</h2>
        <p>{$greeting}</p>
        <p>{$body}</p>
        <p><strong>{$reportDetails}</strong></p>
        <ul>
            <li>{$titleLabel} {$signalement->getTitle()}</li>
            <li>{$priorityLabel} {$signalement->getPriority()->value}</li>
            <li>{$locationLabel} {$signalement->getAddress()}</li>
            <li>{$descriptionLabel} {$signalement->getDescription()}</li>
        </ul>
        HTML;
    }

    private function getOverdueTemplate(Intervention $intervention): string
    {
        $signalement = $intervention->getSignalement();
        $title = $this->translationService->get('overdue_intervention_title', 'notifications');
        $body = $this->translationService->get('overdue_intervention_body', 'notifications');
        $reportDetails = $this->translationService->get('overdue_intervention_report_details', 'notifications');
        $titleLabel = $this->translationService->get('overdue_intervention_title_label', 'notifications');
        $priorityLabel = $this->translationService->get('overdue_intervention_priority', 'notifications');
        $startDateLabel = $this->translationService->get('overdue_intervention_start_date', 'notifications');
        $action = $this->translationService->get('overdue_intervention_action', 'notifications');

        $startDate = $intervention->getStartDate();
        return <<<HTML
        <h2>{$title}</h2>
        <p>{$body}</p>
        <p><strong>{$reportDetails}</strong></p>
        <ul>
            <li>{$titleLabel} {$signalement->getTitle()}</li>
            <li>{$priorityLabel} {$signalement->getPriority()->value}</li>
            <li>{$startDateLabel} {$startDate->format('m/d/Y H:i')}</li>
        </ul>
        <p>{$action}</p>
        HTML;
    }
}
