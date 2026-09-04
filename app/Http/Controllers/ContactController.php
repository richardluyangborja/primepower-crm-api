<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Models\AuditLog;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    public function store(StoreContactRequest $request): JsonResponse
    {
        $this->authorize('create', Contact::class);

        $contact = Contact::create($request->validated());

        $contact->load('company');

        $contactName = "{$contact->first_name} {$contact->last_name}";

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Contact',
            'action' => 'Created',
            'subject_type' => 'Contact',
            'subject_id' => (string) $contact->id,
            'subject_name' => $contactName,
            'description' => "Contact '{$contactName}' was created"
                .($contact->company?->name ? " for company '{$contact->company->name}'" : '')
                .'.',
            'metadata' => [
                'company_name' => $contact->company?->name,
                'title' => $contact->title,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'is_primary' => $contact->is_primary,
            ],
        ]);

        return response()->json([
            'data' => [
                'id' => $contact->id,
                'name' => $contactName,
                'title' => $contact->title,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'is_primary' => $contact->is_primary,
            ],
        ], 201);
    }

    public function update(Contact $contact): JsonResponse
    {
        $this->authorize('update', $contact);

        Contact::where('company_id', $contact->company_id)
            ->update(['is_primary' => false]);

        $contact->update(['is_primary' => true]);

        $contact->load('company');

        $contactName = "{$contact->first_name} {$contact->last_name}";

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Contact',
            'action' => 'Marked Primary',
            'subject_type' => 'Contact',
            'subject_id' => (string) $contact->id,
            'subject_name' => $contactName,
            'description' => "Contact '{$contactName}' was set as the primary contact"
                .($contact->company?->name ? " for company '{$contact->company->name}'" : '')
                .'.',
            'metadata' => [
                'company_name' => $contact->company?->name,
                'is_primary' => true,
            ],
        ]);

        return response()->json([
            'data' => [
                'id' => $contact->id,
                'name' => $contactName,
                'title' => $contact->title,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'is_primary' => true,
            ],
        ]);
    }

    public function destroy(Contact $contact): JsonResponse
    {
        $this->authorize('delete', $contact);

        $contactName = "{$contact->first_name} {$contact->last_name}";
        $companyName = $contact->company?->name;

        $contact->delete();

        AuditLog::log([
            ...AuditLog::actor(),
            'module' => 'Contact',
            'action' => 'Deleted',
            'subject_type' => 'Contact',
            'subject_id' => (string) $contact->id,
            'subject_name' => $contactName,
            'description' => "Contact '{$contactName}' was deleted"
                .($companyName ? " from company '{$companyName}'" : '')
                .'.',
            'metadata' => [
                'company_name' => $companyName,
                'title' => $contact->title,
                'email' => $contact->email,
            ],
        ]);

        return response()->json(null, 204);
    }
}
