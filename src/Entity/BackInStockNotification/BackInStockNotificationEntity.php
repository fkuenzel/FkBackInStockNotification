<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license GPL-2.0-only
 */

namespace fKuenzel\BackInStockNotification\Entity\BackInStockNotification;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class BackInStockNotificationEntity extends Entity
{
    use EntityIdTrait;

    protected string $productId;

    protected string $productVersionId;

    protected ?string $productVariantId = null;

    protected ?string $productVariantVersionId = null;

    protected ?string $customerId = null;

    protected ?string $salesChannelId = null;

    protected ?string $languageId = null;

    protected string $email;

    protected bool $isActive = true;

    protected bool $isPending = true;

    protected string $unsubscribeToken;

    protected \DateTimeInterface $tokenExpiresAt;

    protected \DateTimeInterface $expiresAt;

    protected ?\DateTimeInterface $lastNotifiedAt = null;

    protected string $ipAddress;

    protected ?string $userAgent = null;

    protected ?ProductEntity $product = null;

    protected ?ProductEntity $productVariant = null;

    protected ?CustomerEntity $customer = null;

    protected ?SalesChannelEntity $salesChannel = null;

    protected ?LanguageEntity $language = null;

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getProductVersionId(): string
    {
        return $this->productVersionId;
    }

    public function setProductVersionId(string $productVersionId): void
    {
        $this->productVersionId = $productVersionId;
    }

    public function getProductVariantId(): ?string
    {
        return $this->productVariantId;
    }

    public function setProductVariantId(?string $productVariantId): void
    {
        $this->productVariantId = $productVariantId;
    }

    public function getProductVariantVersionId(): ?string
    {
        return $this->productVariantVersionId;
    }

    public function setProductVariantVersionId(?string $productVariantVersionId): void
    {
        $this->productVariantVersionId = $productVariantVersionId;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function setCustomerId(?string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function isPending(): bool
    {
        return $this->isPending;
    }

    public function setPending(bool $isPending): void
    {
        $this->isPending = $isPending;
    }

    public function getUnsubscribeToken(): string
    {
        return $this->unsubscribeToken;
    }

    public function setUnsubscribeToken(string $unsubscribeToken): void
    {
        $this->unsubscribeToken = $unsubscribeToken;
    }

    public function getTokenExpiresAt(): \DateTimeInterface
    {
        return $this->tokenExpiresAt;
    }

    public function setTokenExpiresAt(\DateTimeInterface $tokenExpiresAt): void
    {
        $this->tokenExpiresAt = $tokenExpiresAt;
    }

    public function getExpiresAt(): \DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeInterface $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getLastNotifiedAt(): ?\DateTimeInterface
    {
        return $this->lastNotifiedAt;
    }

    public function setLastNotifiedAt(?\DateTimeInterface $lastNotifiedAt): void
    {
        $this->lastNotifiedAt = $lastNotifiedAt;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): void
    {
        $this->ipAddress = $ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getLanguageId(): ?string
    {
        return $this->languageId;
    }

    public function setLanguageId(?string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(?LanguageEntity $language): void
    {
        $this->language = $language;
    }

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getProductVariant(): ?ProductEntity
    {
        return $this->productVariant;
    }

    public function setProductVariant(?ProductEntity $productVariant): void
    {
        $this->productVariant = $productVariant;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(?CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }
}
