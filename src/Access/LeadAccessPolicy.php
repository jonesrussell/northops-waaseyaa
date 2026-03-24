<?php

declare(strict_types=1);

namespace App\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Attribute\AccessPolicy;
use Waaseyaa\Access\FieldAccessPolicyInterface;
use Waaseyaa\Entity\EntityInterface;

/**
 * Field-level access control for Lead entities.
 *
 * - Members cannot modify: brand_id, finder_fee_percent, deleted_at.
 * - Admins can modify all fields.
 */
#[AccessPolicy(
    id: 'lead_field_access',
    entityTypes: ['lead'],
    label: 'Lead Field Access Policy',
    description: 'Controls field-level access on Lead entities.',
)]
final class LeadAccessPolicy implements AccessPolicyInterface, FieldAccessPolicyInterface
{
    /** Fields that only admins can edit. */
    private const array ADMIN_ONLY_FIELDS = [
        'brand_id',
        'finder_fee_percent',
        'deleted_at',
    ];

    /**
     * Check whether a role can edit a specific field.
     *
     * Convenience method for use outside the entity access handler pipeline.
     */
    public function canEditField(string $role, string $fieldName): bool
    {
        if ($role === 'admin') {
            return true;
        }

        if ($role === 'member') {
            return !in_array($fieldName, self::ADMIN_ONLY_FIELDS, true);
        }

        return false;
    }

    public function fieldAccess(
        EntityInterface $entity,
        string $fieldName,
        string $operation,
        AccountInterface $account,
    ): AccessResult {
        // Viewing fields is unrestricted for authenticated users.
        if ($operation === 'view') {
            return AccessResult::neutral();
        }

        $roles = $account->getRoles();

        // Admins can edit all fields.
        if (in_array('admin', $roles, true)) {
            return AccessResult::allowed('Admin role grants full field access.');
        }

        // Members are restricted from admin-only fields.
        if (in_array('member', $roles, true) && in_array($fieldName, self::ADMIN_ONLY_FIELDS, true)) {
            return AccessResult::forbidden(
                sprintf('Members cannot modify the "%s" field.', $fieldName),
            );
        }

        return AccessResult::neutral();
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        // Entity-level access is handled by DashboardAccessPolicy.
        // This policy focuses on field-level access only.
        return AccessResult::neutral();
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        return AccessResult::neutral();
    }

    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'lead';
    }
}
