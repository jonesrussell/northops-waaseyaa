<?php

declare(strict_types=1);

namespace App\Access;

use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Attribute\AccessPolicy;
use Waaseyaa\Entity\EntityInterface;

/**
 * Access policy for the lead pipeline dashboard.
 *
 * - admin: full access to everything.
 * - member: can view dashboard and update leads assigned to them.
 */
#[AccessPolicy(
    id: 'dashboard_access',
    entityTypes: ['lead'],
    label: 'Dashboard Access Policy',
    description: 'Controls access to the lead pipeline dashboard and lead management.',
)]
final class DashboardAccessPolicy implements AccessPolicyInterface
{
    /**
     * Check whether a given role can access the dashboard.
     */
    public function canAccess(string $role): bool
    {
        return in_array($role, ['admin', 'member'], true);
    }

    /**
     * Check whether a role can manage (update/delete) a specific lead.
     *
     * Admins can manage any lead. Members can only manage leads assigned to them.
     */
    public function canManageLead(string $role, ?string $assignedTo, ?string $currentUserId): bool
    {
        if ($role === 'admin') {
            return true;
        }

        if ($role === 'member') {
            return $assignedTo !== null
                && $currentUserId !== null
                && $assignedTo === $currentUserId;
        }

        return false;
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if (!$account->isAuthenticated()) {
            return AccessResult::unauthenticated('Authentication required for dashboard access.');
        }

        $roles = $account->getRoles();

        // Admins have full access to all operations.
        if (in_array('admin', $roles, true)) {
            return AccessResult::allowed('Admin role grants full access.');
        }

        // Members can view any lead.
        if (in_array('member', $roles, true) && $operation === 'view') {
            return AccessResult::allowed('Members can view leads.');
        }

        // Members can update leads assigned to them.
        if (in_array('member', $roles, true) && $operation === 'update') {
            $assignedTo = $entity->get('assigned_to');
            $currentUserId = (string) $account->id();

            if ($assignedTo === $currentUserId) {
                return AccessResult::allowed('Member can update their assigned lead.');
            }

            return AccessResult::forbidden('Members can only update leads assigned to them.');
        }

        // Members cannot delete leads.
        if (in_array('member', $roles, true) && $operation === 'delete') {
            return AccessResult::forbidden('Members cannot delete leads.');
        }

        return AccessResult::forbidden('Insufficient permissions.');
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        if (!$account->isAuthenticated()) {
            return AccessResult::unauthenticated('Authentication required.');
        }

        $roles = $account->getRoles();

        if (in_array('admin', $roles, true)) {
            return AccessResult::allowed('Admin role grants lead creation access.');
        }

        // Members can create leads.
        if (in_array('member', $roles, true)) {
            return AccessResult::allowed('Members can create leads.');
        }

        return AccessResult::forbidden('Insufficient permissions to create leads.');
    }

    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'lead';
    }
}
