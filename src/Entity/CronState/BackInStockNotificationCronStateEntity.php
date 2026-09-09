<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license MIT
 */

namespace Fkuenzel\FkBackInStockNotification\Entity\CronState;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class BackInStockNotificationCronStateEntity extends Entity
{
    use EntityIdTrait;

    protected ?\DateTimeInterface $lastRunAt = null;

    protected int $consecutiveFailures = 0;

    protected ?string $lastErrorMessage = null;

    protected ?\DateTimeInterface $lastErrorAt = null;

    protected ?\DateTimeInterface $adminNotifiedAt = null;

    public function getLastRunAt(): ?\DateTimeInterface
    {
        return $this->lastRunAt;
    }

    public function setLastRunAt(?\DateTimeInterface $lastRunAt): void
    {
        $this->lastRunAt = $lastRunAt;
    }

    public function getConsecutiveFailures(): int
    {
        return $this->consecutiveFailures;
    }

    public function setConsecutiveFailures(int $consecutiveFailures): void
    {
        $this->consecutiveFailures = $consecutiveFailures;
    }

    public function getLastErrorMessage(): ?string
    {
        return $this->lastErrorMessage;
    }

    public function setLastErrorMessage(?string $lastErrorMessage): void
    {
        $this->lastErrorMessage = $lastErrorMessage;
    }

    public function getLastErrorAt(): ?\DateTimeInterface
    {
        return $this->lastErrorAt;
    }

    public function setLastErrorAt(?\DateTimeInterface $lastErrorAt): void
    {
        $this->lastErrorAt = $lastErrorAt;
    }

    public function getAdminNotifiedAt(): ?\DateTimeInterface
    {
        return $this->adminNotifiedAt;
    }

    public function setAdminNotifiedAt(?\DateTimeInterface $adminNotifiedAt): void
    {
        $this->adminNotifiedAt = $adminNotifiedAt;
    }
}
