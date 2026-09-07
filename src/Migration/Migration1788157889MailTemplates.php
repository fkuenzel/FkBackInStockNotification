<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license GPL-2.0-only
 */

namespace fKuenzel\BackInStockNotification\Migration;

use Doctrine\DBAL\Connection;
use fKuenzel\BackInStockNotification\Service\NotificationMailService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1788157889MailTemplates extends MigrationStep
{
    /**
     * @var array<string, array{register: string, available: string}>
     */
    private const TYPE_NAMES = [
        'de' => [
            'register' => 'Wieder-verfügbar: Anmeldebestätigung',
            'available' => 'Wieder-verfügbar: Verfügbarkeits-Benachrichtigung',
        ],
        'en' => [
            'register' => 'Back in stock: registration confirmation',
            'available' => 'Back in stock: availability notification',
        ],
    ];

    /**
     * @var array<string, array{register: string, available: string}>
     */
    private const SUBJECTS = [
        'de' => [
            'register' => 'Benachrichtigung aktiviert: {{ product.translated.name }}',
            'available' => '{% if count == 1 %}{{ items.0.product.translated.name }} ist wieder verfügbar!{% else %}Artikel wieder verfügbar{% endif %}',
        ],
        'en' => [
            'register' => 'Notification activated: {{ product.translated.name }}',
            'available' => '{% if count == 1 %}{{ items.0.product.translated.name }} is back in stock!{% else %}Products back in stock{% endif %}',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1788157889;
    }

    public function update(Connection $connection): void
    {
        $this->createMailTemplate(
            $connection,
            NotificationMailService::TYPE_REGISTER,
            'register',
            'fk_back_in_stock_notification_register'
        );
        $this->createMailTemplate(
            $connection,
            NotificationMailService::TYPE_AVAILABLE,
            'available',
            'fk_back_in_stock_notification_available'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // Mail templates are removed by the plugin uninstall (FkBackInStockNotification::uninstall).
    }

    private function createMailTemplate(
        Connection $connection,
        string $technicalName,
        string $key,
        string $templateDir
    ): void {
        if ($this->mailTemplateTypeExists($connection, $technicalName)) {
            return;
        }

        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $typeId = Uuid::randomBytes();
        $templateId = Uuid::randomBytes();

        $connection->insert('mail_template_type', [
            'id' => $typeId,
            'technical_name' => $technicalName,
            'available_entities' => json_encode(['product' => 'product'], \JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);

        $connection->insert('mail_template', [
            'id' => $templateId,
            'mail_template_type_id' => $typeId,
            'system_default' => 0,
            'created_at' => $now,
        ]);

        $languages = [
            'de' => $this->languageIdByLocale($connection, 'de-DE'),
            'en' => $this->languageIdByLocale($connection, 'en-GB'),
        ];

        $writtenLanguageIds = [];

        foreach ($languages as $lang => $languageId) {
            if ($languageId === null) {
                continue;
            }
            $writtenLanguageIds[] = $languageId;

            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => $typeId,
                'language_id' => $languageId,
                'name' => self::TYPE_NAMES[$lang][$key],
                'created_at' => $now,
            ]);

            $connection->insert('mail_template_translation', [
                'mail_template_id' => $templateId,
                'language_id' => $languageId,
                'sender_name' => null,
                'subject' => self::SUBJECTS[$lang][$key],
                'description' => self::TYPE_NAMES[$lang][$key],
                'content_html' => $this->readTemplate($templateDir, $lang, 'html.html.twig'),
                'content_plain' => $this->readTemplate($templateDir, $lang, 'plaintext.html.twig'),
                'created_at' => $now,
            ]);
        }

        // Guarantee a translation for the system default language (English fallback).
        if (!\in_array(Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), $writtenLanguageIds, true)) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => $typeId,
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'name' => self::TYPE_NAMES['en'][$key],
                'created_at' => $now,
            ]);

            $connection->insert('mail_template_translation', [
                'mail_template_id' => $templateId,
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'sender_name' => null,
                'subject' => self::SUBJECTS['en'][$key],
                'description' => self::TYPE_NAMES['en'][$key],
                'content_html' => $this->readTemplate($templateDir, 'en', 'html.html.twig'),
                'content_plain' => $this->readTemplate($templateDir, 'en', 'plaintext.html.twig'),
                'created_at' => $now,
            ]);
        }
    }

    private function mailTemplateTypeExists(Connection $connection, string $technicalName): bool
    {
        $id = $connection->fetchOne(
            'SELECT id FROM mail_template_type WHERE technical_name = :name',
            ['name' => $technicalName]
        );

        return $id !== false;
    }

    private function languageIdByLocale(Connection $connection, string $localeCode): ?string
    {
        $languageId = $connection->fetchOne(
            'SELECT language.id FROM language
             INNER JOIN locale ON locale.id = language.locale_id
             WHERE locale.code = :code',
            ['code' => $localeCode]
        );

        return $languageId === false ? null : $languageId;
    }

    private function readTemplate(string $templateDir, string $lang, string $file): string
    {
        $path = __DIR__ . '/../Resources/mail-templates/' . $templateDir . '/' . $lang . '/' . $file;
        $content = file_get_contents($path);

        return $content === false ? '' : $content;
    }
}
