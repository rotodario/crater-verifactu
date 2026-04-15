<?php

namespace Crater\Policies;

use Crater\Models\Invoice;
use Crater\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

class InvoicePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        if (BouncerFacade::can('view-invoice', Invoice::class)) {
            return true;
        }

        return false;
    }

    public function view(User $user, Invoice $invoice)
    {
        if (BouncerFacade::can('view-invoice', $invoice) && $user->hasCompany($invoice->company_id)) {
            return true;
        }

        return false;
    }

    public function create(User $user)
    {
        if (BouncerFacade::can('create-invoice', Invoice::class)) {
            return true;
        }

        return false;
    }

    public function update(User $user, Invoice $invoice)
    {
        if (BouncerFacade::can('edit-invoice', $invoice) && $user->hasCompany($invoice->company_id)) {
            return $invoice->allow_edit;
        }

        return false;
    }

    public function delete(User $user, Invoice $invoice)
    {
        if (BouncerFacade::can('delete-invoice', $invoice) && $user->hasCompany($invoice->company_id)) {
            return ! $invoice->isFiscalLocked();
        }

        return false;
    }

    public function restore(User $user, Invoice $invoice)
    {
        if (BouncerFacade::can('delete-invoice', $invoice) && $user->hasCompany($invoice->company_id)) {
            return ! $invoice->isFiscalLocked();
        }

        return false;
    }

    public function forceDelete(User $user, Invoice $invoice)
    {
        if (BouncerFacade::can('delete-invoice', $invoice) && $user->hasCompany($invoice->company_id)) {
            return ! $invoice->isFiscalLocked();
        }

        return false;
    }

    public function send(User $user, Invoice $invoice)
    {
        if (BouncerFacade::can('send-invoice', $invoice) && $user->hasCompany($invoice->company_id)) {
            return true;
        }

        return false;
    }

    public function deleteMultiple(User $user)
    {
        if (BouncerFacade::can('delete-invoice', Invoice::class)) {
            return true;
        }

        return false;
    }
}
