<?php

declare(strict_types=1);

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\Document;
use App\Services\Audit\AuditService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentService
{
    public function __construct(
        private AuditService $audit,
    ) {}

    public function find(Customer $customer, int $documentId): Document
    {
        return $customer->documents()->findOrFail($documentId);
    }

    // Field name becomes the document's type as-is; its humanized form becomes the title (e.g. profile_picture -> "Profile Picture").
    /**
     * @param array<string, UploadedFile> $files
     * @return Collection<int, Document>
     */
    public function upload(Customer $customer, array $files): Collection
    {
        $actor = Auth::guard('user')->user();

        $documents = DB::transaction(function () use ($customer, $files, $actor) {
            return collect($files)->map(function (UploadedFile $file, string $type) use ($customer, $actor) {
                $path = $file->store("customer-documents/{$customer->id}", 's3');

                return $customer->documents()->create([
                    'title'       => Str::of($type)->replace('_', ' ')->title()->value(),
                    'type'        => $type,
                    'path'        => $path,
                    'uploaded_by' => $actor?->id,
                    'status'      => 'pending',
                ]);
            });
        });

        $this->audit->log(
            action: 'document_uploaded',
            module: 'customers',
            auditable: $customer,
            after: ['types' => $documents->pluck('type')->values()->all()],
            description: "{$documents->count()} document(s) were uploaded for customer '{$customer->cif_number}' by '{$actor?->username}'.",
        );

        return $documents;
    }

    public function update(Document $document, array $data): Document
    {
        $before = $document->only(['title', 'type', 'status']);
        $actor  = Auth::guard('user')->user();

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
            after: $document->only(['title', 'type', 'status']),
            description: "Document '{$document->title}' for customer #{$document->customer_id} was updated by '{$actor?->username}'.",
        );

        return $document;
    }
}
