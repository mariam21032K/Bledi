<?php

namespace App\Service;

/**
 * Translation Service
 * 
 * Handles multilingual support for the BLEDI platform
 * Supported languages: English, French, Arabic, Spanish
 * Fallback language: English
 */
class TranslationService
{
    private array $translations = [];
    private string $currentLanguage = 'en';
    private string $fallbackLanguage = 'en';
    private array $supportedLanguages = ['en', 'fr', 'ar', 'es'];

    public function __construct()
    {
        $this->loadTranslations();
    }

    /**
     * Set the current language for translations
     */
    public function setLanguage(string $language): self
    {
        if (in_array($language, $this->supportedLanguages)) {
            $this->currentLanguage = $language;
        }
        return $this;
    }

    /**
     * Get the current language
     */
    public function getCurrentLanguage(): string
    {
        return $this->currentLanguage;
    }

    /**
     * Get a translated message
     */
    public function get(string $key, string $domain = 'notifications'): string
    {
        $keys = explode('.', $key);
        $translation = $this->translations[$domain][$this->currentLanguage] ?? null;

        foreach ($keys as $k) {
            if (is_array($translation) && isset($translation[$k])) {
                $translation = $translation[$k];
            } else {
                // Fallback to English if translation not found
                $translation = $this->translations[$domain][$this->fallbackLanguage] ?? null;
                foreach ($keys as $k) {
                    if (is_array($translation) && isset($translation[$k])) {
                        $translation = $translation[$k];
                    } else {
                        return $key; // Return the key if nothing found
                    }
                }
                break;
            }
        }

        return $translation ?? $key;
    }

    /**
     * Get all translations for a domain in current language
     */
    public function getDomainTranslations(string $domain = 'notifications'): array
    {
        return $this->translations[$domain][$this->currentLanguage] ?? [];
    }

    /**
     * Load all translation files
     */
    private function loadTranslations(): void
    {
        // Notifications translations
        $this->translations['notifications'] = [
            'en' => [
                'signalment_created_subject' => 'Your Report Has Been Successfully Created',
                'signalment_created_greeting' => 'Hello',
                'signalment_created_body' => 'Your report has been successfully registered.',
                'signalment_created_details' => 'Details:',
                'signalment_created_title' => 'Title:',
                'signalment_created_category' => 'Category:',
                'signalment_created_status' => 'Status:',
                'signalment_created_date' => 'Date:',
                'signalment_created_updates' => 'You will receive updates on the progress of your report.',
                'signalment_created_received' => 'Report Received',

                'status_changed_subject' => 'Report Status Update',
                'status_changed_greeting' => 'Hello',
                'status_changed_title' => 'Report Status Update',
                'status_changed_details' => 'Details:',
                'status_changed_title_label' => 'Title:',
                'status_changed_status' => 'New Status:',
                'status_changed_updated' => 'Last Updated:',
                'status_new' => 'Your report has been registered',
                'status_in_progress' => 'Your report is being processed',
                'status_resolved' => 'Your report has been resolved',
                'status_rejected' => 'Your report has been rejected',

                'intervention_assigned_subject' => 'New Intervention Assigned to You',
                'intervention_assigned_greeting' => 'Hello Agent,',
                'intervention_assigned_body' => 'A new intervention has been assigned to you.',
                'intervention_assigned_report_details' => 'Report Details:',
                'intervention_assigned_title' => 'Title:',
                'intervention_assigned_priority' => 'Priority:',
                'intervention_assigned_location' => 'Location:',
                'intervention_assigned_description' => 'Description:',

                'overdue_intervention_subject' => 'Overdue Intervention - Action Required',
                'overdue_intervention_title' => 'Overdue Intervention',
                'overdue_intervention_body' => 'The following intervention exceeds the expected timeframe:',
                'overdue_intervention_report_details' => 'Report Details:',
                'overdue_intervention_title_label' => 'Title:',
                'overdue_intervention_priority' => 'Priority:',
                'overdue_intervention_start_date' => 'Start Date:',
                'overdue_intervention_action' => 'Please take appropriate action.',
            ],
            'fr' => [
                'signalment_created_subject' => 'Votre Signalement a Été Créé Avec Succès',
                'signalment_created_greeting' => 'Bonjour',
                'signalment_created_body' => 'Votre signalement a été enregistré avec succès.',
                'signalment_created_details' => 'Détails:',
                'signalment_created_title' => 'Titre:',
                'signalment_created_category' => 'Catégorie:',
                'signalment_created_status' => 'Statut:',
                'signalment_created_date' => 'Date:',
                'signalment_created_updates' => 'Vous recevrez des mises à jour sur l\'évolution de votre signalement.',
                'signalment_created_received' => 'Signalement Reçu',

                'status_changed_subject' => 'Mise à Jour du Statut du Signalement',
                'status_changed_greeting' => 'Bonjour',
                'status_changed_title' => 'Mise à Jour de votre Signalement',
                'status_changed_details' => 'Détails:',
                'status_changed_title_label' => 'Titre:',
                'status_changed_status' => 'Nouveau Statut:',
                'status_changed_updated' => 'Dernière Mise à Jour:',
                'status_new' => 'Votre signalement a été enregistré',
                'status_in_progress' => 'Votre signalement est en cours de traitement',
                'status_resolved' => 'Votre signalement a été résolu',
                'status_rejected' => 'Votre signalement a été rejeté',

                'intervention_assigned_subject' => 'Nouvelle Intervention Vous Assignée',
                'intervention_assigned_greeting' => 'Bonjour Agent,',
                'intervention_assigned_body' => 'Une nouvelle intervention vous a été assignée.',
                'intervention_assigned_report_details' => 'Détails du Signalement:',
                'intervention_assigned_title' => 'Titre:',
                'intervention_assigned_priority' => 'Priorité:',
                'intervention_assigned_location' => 'Localisation:',
                'intervention_assigned_description' => 'Description:',

                'overdue_intervention_subject' => 'Intervention en Retard - Action Requise',
                'overdue_intervention_title' => 'Intervention en Retard',
                'overdue_intervention_body' => 'L\'intervention suivante dépasse le délai attendu:',
                'overdue_intervention_report_details' => 'Détails du Signalement:',
                'overdue_intervention_title_label' => 'Titre:',
                'overdue_intervention_priority' => 'Priorité:',
                'overdue_intervention_start_date' => 'Date de Début:',
                'overdue_intervention_action' => 'Veuillez prendre les mesures appropriées.',
            ],
            'ar' => [
                'signalment_created_subject' => 'تم إنشاء تقريرك بنجاح',
                'signalment_created_greeting' => 'مرحبا',
                'signalment_created_body' => 'تم تسجيل تقريرك بنجاح.',
                'signalment_created_details' => 'التفاصيل:',
                'signalment_created_title' => 'العنوان:',
                'signalment_created_category' => 'الفئة:',
                'signalment_created_status' => 'الحالة:',
                'signalment_created_date' => 'التاريخ:',
                'signalment_created_updates' => 'سوف تتلقى تحديثات حول تقدم تقريرك.',
                'signalment_created_received' => 'تم استقبال التقرير',

                'status_changed_subject' => 'تحديث حالة التقرير',
                'status_changed_greeting' => 'مرحبا',
                'status_changed_title' => 'تحديث التقرير الخاص بك',
                'status_changed_details' => 'التفاصيل:',
                'status_changed_title_label' => 'العنوان:',
                'status_changed_status' => 'الحالة الجديدة:',
                'status_changed_updated' => 'آخر تحديث:',
                'status_new' => 'تم تسجيل تقريرك',
                'status_in_progress' => 'جاري معالجة تقريرك',
                'status_resolved' => 'تم حل تقريرك',
                'status_rejected' => 'تم رفض تقريرك',

                'intervention_assigned_subject' => 'تم تعيين مهمة جديدة لك',
                'intervention_assigned_greeting' => 'مرحبا يا الوكيل,',
                'intervention_assigned_body' => 'تم تعيين مهمة جديدة لك.',
                'intervention_assigned_report_details' => 'تفاصيل التقرير:',
                'intervention_assigned_title' => 'العنوان:',
                'intervention_assigned_priority' => 'الأولوية:',
                'intervention_assigned_location' => 'الموقع:',
                'intervention_assigned_description' => 'الوصف:',

                'overdue_intervention_subject' => 'مهمة متأخرة - إجراء مطلوب',
                'overdue_intervention_title' => 'مهمة متأخرة',
                'overdue_intervention_body' => 'المهمة التالية تتجاوز الإطار الزمني المتوقع:',
                'overdue_intervention_report_details' => 'تفاصيل التقرير:',
                'overdue_intervention_title_label' => 'العنوان:',
                'overdue_intervention_priority' => 'الأولوية:',
                'overdue_intervention_start_date' => 'تاريخ البدء:',
                'overdue_intervention_action' => 'يرجى اتخاذ الإجراء المناسب.',
            ],
            'es' => [
                'signalment_created_subject' => 'Su Reporte Ha Sido Creado Exitosamente',
                'signalment_created_greeting' => 'Hola',
                'signalment_created_body' => 'Su reporte ha sido registrado exitosamente.',
                'signalment_created_details' => 'Detalles:',
                'signalment_created_title' => 'Título:',
                'signalment_created_category' => 'Categoría:',
                'signalment_created_status' => 'Estado:',
                'signalment_created_date' => 'Fecha:',
                'signalment_created_updates' => 'Recibirá actualizaciones sobre el progreso de su reporte.',
                'signalment_created_received' => 'Reporte Recibido',

                'status_changed_subject' => 'Actualización de Estado del Reporte',
                'status_changed_greeting' => 'Hola',
                'status_changed_title' => 'Actualización de su Reporte',
                'status_changed_details' => 'Detalles:',
                'status_changed_title_label' => 'Título:',
                'status_changed_status' => 'Nuevo Estado:',
                'status_changed_updated' => 'Última Actualización:',
                'status_new' => 'Su reporte ha sido registrado',
                'status_in_progress' => 'Su reporte está siendo procesado',
                'status_resolved' => 'Su reporte ha sido resuelto',
                'status_rejected' => 'Su reporte ha sido rechazado',

                'intervention_assigned_subject' => 'Nueva Intervención Asignada',
                'intervention_assigned_greeting' => 'Hola Agente,',
                'intervention_assigned_body' => 'Se le ha asignado una nueva intervención.',
                'intervention_assigned_report_details' => 'Detalles del Reporte:',
                'intervention_assigned_title' => 'Título:',
                'intervention_assigned_priority' => 'Prioridad:',
                'intervention_assigned_location' => 'Ubicación:',
                'intervention_assigned_description' => 'Descripción:',

                'overdue_intervention_subject' => 'Intervención Retrasada - Acción Requerida',
                'overdue_intervention_title' => 'Intervención Retrasada',
                'overdue_intervention_body' => 'La siguiente intervención excede el plazo esperado:',
                'overdue_intervention_report_details' => 'Detalles del Reporte:',
                'overdue_intervention_title_label' => 'Título:',
                'overdue_intervention_priority' => 'Prioridad:',
                'overdue_intervention_start_date' => 'Fecha de Inicio:',
                'overdue_intervention_action' => 'Tome las medidas apropiadas.',
            ],
        ];

        // Messages translations for general UI
        $this->translations['messages'] = [
            'en' => [
                'welcome' => 'Welcome to BLEDI',
                'login_success' => 'You have been logged in successfully',
                'logout_success' => 'You have been logged out',
                'profile_updated' => 'Your profile has been updated',
                'language_updated' => 'Language preference updated',
                'report_created' => 'Report created successfully',
                'report_updated' => 'Report updated successfully',
                'report_deleted' => 'Report deleted successfully',
            ],
            'fr' => [
                'welcome' => 'Bienvenue sur BLEDI',
                'login_success' => 'Vous êtes connecté avec succès',
                'logout_success' => 'Vous êtes déconnecté',
                'profile_updated' => 'Votre profil a été mis à jour',
                'language_updated' => 'Préférence linguistique mise à jour',
                'report_created' => 'Signalement créé avec succès',
                'report_updated' => 'Signalement mis à jour',
                'report_deleted' => 'Signalement supprimé',
            ],
            'ar' => [
                'welcome' => 'أهلا بك في BLEDI',
                'login_success' => 'تم تسجيل الدخول بنجاح',
                'logout_success' => 'تم تسجيل الخروج',
                'profile_updated' => 'تم تحديث ملفك الشخصي',
                'language_updated' => 'تم تحديث تفضيل اللغة',
                'report_created' => 'تم إنشاء التقرير بنجاح',
                'report_updated' => 'تم تحديث التقرير',
                'report_deleted' => 'تم حذف التقرير',
            ],
            'es' => [
                'welcome' => 'Bienvenido a BLEDI',
                'login_success' => 'Ha iniciado sesión exitosamente',
                'logout_success' => 'Ha cerrado la sesión',
                'profile_updated' => 'Su perfil ha sido actualizado',
                'language_updated' => 'Preferencia de idioma actualizada',
                'report_created' => 'Reporte creado exitosamente',
                'report_updated' => 'Reporte actualizado',
                'report_deleted' => 'Reporte eliminado',
            ],
        ];
    }

    /**
     * Get list of supported languages
     */
    public function getSupportedLanguages(): array
    {
        return [
            'en' => 'English',
            'fr' => 'Français',
            'ar' => 'العربية',
            'es' => 'Español',
        ];
    }

    /**
     * Check if language is supported
     */
    public function isLanguageSupported(string $language): bool
    {
        return in_array($language, $this->supportedLanguages);
    }
}
