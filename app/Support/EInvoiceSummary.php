<?php

namespace App\Support;

use EInvoiceSdk\Enums\Status;
use EInvoiceSdk\Models\EInvoiceDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * What the pages need about a record's latest e-invoice.
 */
class EInvoiceSummary
{
    /**
     * @return array<string, mixed>|null
     */
    public static function of(Model $model): ?array
    {
        if (! method_exists($model, 'einvoiceDocuments')) {
            return null;
        }

        /** @var MorphMany<EInvoiceDocument, Model> $relation */
        $relation = $model->einvoiceDocuments();
        /** @var EInvoiceDocument|null $doc */
        $doc = $relation->latest('id')->first();

        return $doc ? [
            'id' => $doc->id,
            'status' => $doc->status->value,
            'environment' => $doc->environment->value,
            'uuid' => $doc->uuid,
            'validation_url' => $doc->validationUrl(),
            'can_cancel' => $doc->canCancel(),
            'errors' => match ($doc->status) {
                Status::Invalid => $doc->logs()->latest('id')->first()->errors ?? [],
                Status::Failed => ['Could not reach LHDN. Try sending again in a few minutes.'],
                default => [],
            },
        ] : null;
    }
}
