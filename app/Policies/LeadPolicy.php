<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('leads.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Lead $lead): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->canAccessLead($lead) && $user->hasPermission('leads.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('leads.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Lead $lead): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->canAccessLead($lead) && $user->hasPermission('leads.edit');
    }

    /**
     * Determine whether the user can delete (archive) the model.
     */
    public function delete(User $user, Lead $lead): bool
    {
        return $user->isAdmin() || $user->hasPermission('leads.delete') || $user->hasPermission('leads.archive');
    }

    /**
     * Determine whether the user can restore the archived model.
     */
    public function restore(User $user, Lead $lead): bool
    {
        return $user->isAdmin() || $user->hasPermission('leads.restore');
    }

    /**
     * Determine whether the user can permanently force delete the model.
     */
    public function forceDelete(User $user, Lead $lead): bool
    {
        return $user->isAdmin() || $user->hasPermission('leads.force_delete');
    }

    /**
     * Determine whether the user can reassign the model.
     */
    public function assign(User $user, Lead $lead): bool
    {
        return $user->isAdmin() || $user->hasPermission('leads.assign');
    }

    /**
     * Determine whether the user can export leads.
     */
    public function export(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('leads.export');
    }

    /**
     * Determine whether the user can import leads.
     */
    public function import(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('leads.import');
    }
}
