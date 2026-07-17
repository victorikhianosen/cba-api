<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\Document;
use App\Services\Audit\AuditService;

class DocumentService
{
    public function __construct(
        private AuditService $audit,
    ) {}

    public function find(Customer $customer, int $documentId): Document
    {
        return $customer->documents()->findOrFail($documentId);
    }

    public function update(Document $document, array $data): Document
    {
        $before = $document->only(['title', 'name', 'type', 'status']);
        $actor  = auth()->user();

        if (isset($data['status']) && $data['status'] !== $document->status) {
            if ($data['status'] === 'approved') {
                $data['approved_by'] = $actor?->id;
                $data['approved_at'] = now();
            }

            if ($data['status'] === 'rejected') {
                $data['rejected_by'] = $actor?->id;
                $data['rejected_at'] = now();
            }
        }

        $document->update($data);
        $document->refresh();

        $this->audit->log(
            action: 'document_updated',
            module: 'customers',
            auditable: $document,
            before: $before,
            after: $document->only(['title', 'name', 'type', 'status']),
            description: "Document '{$document->title}' for customer #{$document->customer_id} was updated by '{$actor?->username}'.",
        );

        return $document;
    }
}
