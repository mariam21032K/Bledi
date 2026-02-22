<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Signalement;
use App\Entity\Intervention;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;

class EmailNotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $senderEmail = 'noreply@bledi.com',
    ) {
    }

    /**
     * Send notification email for new signalement
     */
    public function sendSignalementCreatedEmail(Signalement $signalement, User $user): void
    {
        try {
            $email = (new Email())
                ->from($this->senderEmail)
                ->to($user->getEmail())
                ->subject('Votre signalement a été enregistré - Bledi')
                ->html($this->buildSignalementCreatedTemplate($signalement, $user));

            $this->mailer->send($email);
            $this->logger->info("Signalement creation email sent to {$user->getEmail()}");
        } catch (\Exception $e) {
            $this->logger->error("Failed to send signalement creation email: {$e->getMessage()}");
        }
    }

    /**
     * Send notification email when signalement status changes
     */
    public function sendSignalementStatusChangedEmail(Signalement $signalement, string $oldStatus, User $user): void
    {
        try {
            $newStatus = $signalement->getStatus()?->value ?? 'Unknown';
            
            $email = (new Email())
                ->from($this->senderEmail)
                ->to($user->getEmail())
                ->subject("Mise à jour de votre signalement: $newStatus - Bledi")
                ->html($this->buildStatusChangedTemplate($signalement, $oldStatus, $newStatus));

            $this->mailer->send($email);
            $this->logger->info("Status change email sent to {$user->getEmail()}");
        } catch (\Exception $e) {
            $this->logger->error("Failed to send status change email: {$e->getMessage()}");
        }
    }

    /**
     * Send notification email when intervention is assigned
     */
    public function sendInterventionAssignedEmail(Intervention $intervention, User $agent): void
    {
        try {
            $email = (new Email())
                ->from($this->senderEmail)
                ->to($agent->getEmail())
                ->subject('Nouvelle intervention assignée - Bledi')
                ->html($this->buildInterventionAssignedTemplate($intervention, $agent));

            $this->mailer->send($email);
            $this->logger->info("Intervention assigned email sent to {$agent->getEmail()}");
        } catch (\Exception $e) {
            $this->logger->error("Failed to send intervention assigned email: {$e->getMessage()}");
        }
    }

    /**
     * Send notification email for intervention completion
     */
    public function sendInterventionCompletedEmail(Intervention $intervention, User $user): void
    {
        try {
            $email = (new Email())
                ->from($this->senderEmail)
                ->to($user->getEmail())
                ->subject('Votre signalement a été traité - Bledi')
                ->html($this->buildInterventionCompletedTemplate($intervention, $user));

            $this->mailer->send($email);
            $this->logger->info("Intervention completed email sent to {$user->getEmail()}");
        } catch (\Exception $e) {
            $this->logger->error("Failed to send intervention completed email: {$e->getMessage()}");
        }
    }

    /**
     * Build HTML template for signalement creation
     */
    private function buildSignalementCreatedTemplate(Signalement $signalement, User $user): string
    {
        $title = htmlspecialchars($signalement->getTitle());
        $category = htmlspecialchars($signalement->getCategory()->getName());
        $location = htmlspecialchars($signalement->getAddress() ?? 'Non spécifiée');

        return <<<HTML
        <html>
            <body style="font-family: Arial, sans-serif; color: #333;">
                <h2>Merci pour votre signalement!</h2>
                <p>Bonjour {$user->getFirstName()},</p>
                <p>Votre signalement a été enregistré avec succès. Voici les détails:</p>
                <div style="background-color: #f5f5f5; padding: 15px; border-radius: 5px;">
                    <p><strong>Titre:</strong> $title</p>
                    <p><strong>Catégorie:</strong> $category</p>
                    <p><strong>Localisation:</strong> $location</p>
                    <p><strong>Date:</strong> {$signalement->getCreatedAt()?->format('d/m/Y H:i')}</p>
                </div>
                <p>Vous recevrez des mises à jour sur l'avancement de votre signalement.</p>
                <p>Cordialement,<br/>L'équipe Bledi</p>
            </body>
        </html>
        HTML;
    }

    /**
     * Build HTML template for status change
     */
    private function buildStatusChangedTemplate(Signalement $signalement, string $oldStatus, string $newStatus): string
    {
        $title = htmlspecialchars($signalement->getTitle());
        $statusMessages = [
            'NEW' => 'Nouveau',
            'IN_PROGRESS' => 'En cours de traitement',
            'COMPLETED' => 'Résolu',
            'REJECTED' => 'Rejeté',
            'ON_HOLD' => 'En attente',
        ];

        $newStatusLabel = $statusMessages[$newStatus] ?? $newStatus;

        return <<<HTML
        <html>
            <body style="font-family: Arial, sans-serif; color: #333;">
                <h2>Mise à jour de votre signalement</h2>
                <p>Bonjour,</p>
                <p>Le statut de votre signalement a été mis à jour:</p>
                <div style="background-color: #f5f5f5; padding: 15px; border-radius: 5px;">
                    <p><strong>Signalement:</strong> $title</p>
                    <p><strong>Nouveau statut:</strong> <span style="color: #0066cc; font-weight: bold;">$newStatusLabel</span></p>
                </div>
                <p>Consultez votre compte pour plus de détails.</p>
                <p>Cordialement,<br/>L'équipe Bledi</p>
            </body>
        </html>
        HTML;
    }

    /**
     * Build HTML template for intervention assignment
     */
    private function buildInterventionAssignedTemplate(Intervention $intervention, User $agent): string
    {
        $signalementTitle = htmlspecialchars($intervention->getSignalement()->getTitle());
        $category = htmlspecialchars($intervention->getSignalement()->getCategory()->getName());

        return <<<HTML
        <html>
            <body style="font-family: Arial, sans-serif; color: #333;">
                <h2>Nouvelle intervention assignée</h2>
                <p>Bonjour {$agent->getFirstName()},</p>
                <p>Une nouvelle intervention vous a été assignée:</p>
                <div style="background-color: #f5f5f5; padding: 15px; border-radius: 5px;">
                    <p><strong>Signalement:</strong> $signalementTitle</p>
                    <p><strong>Catégorie:</strong> $category</p>
                    <p><strong>Date assignation:</strong> {$intervention->getStartDate()?->format('d/m/Y H:i')}</p>
                </div>
                <p>Veuillez vous connecter à votre compte pour plus de détails.</p>
                <p>Cordialement,<br/>L'équipe Bledi</p>
            </body>
        </html>
        HTML;
    }

    /**
     * Build HTML template for intervention completion
     */
    private function buildInterventionCompletedTemplate(Intervention $intervention, User $user): string
    {
        $title = htmlspecialchars($intervention->getSignalement()->getTitle());
        $description = htmlspecialchars(substr($intervention->getNotes() ?? '', 0, 200));

        return <<<HTML
        <html>
            <body style="font-family: Arial, sans-serif; color: #333;">
                <h2>Votre signalement a été traité</h2>
                <p>Bonjour {$user->getFirstName()},</p>
                <p>L'intervention sur votre signalement a été complétée:</p>
                <div style="background-color: #f5f5f5; padding: 15px; border-radius: 5px;">
                    <p><strong>Signalement:</strong> $title</p>
                    <p><strong>Description:</strong> $description</p>
                    <p><strong>Date de fin:</strong> {$intervention->getEndDate()?->format('d/m/Y H:i')}</p>
                </div>
                <p>Nous vous remercions de votre participation à l'amélioration de notre communauté.</p>
                <p>Cordialement,<br/>L'équipe Bledi</p>
            </body>
        </html>
        HTML;
    }
}
