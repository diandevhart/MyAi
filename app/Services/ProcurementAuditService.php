<?php

namespace App\Services;

use App\Models\ProcurementActivity;
use Illuminate\Support\Facades\Log;

class ProcurementAuditService
{
    public function log(
        ?int $userId,
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?int $internalRequestId = null,
        ?int $supplierRfqId = null,
        array $metadata = []
    ): void {
        try {
            ProcurementActivity::create([
                'user_id' => $userId,
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'internal_rfq_request_id' => $internalRequestId,
                'supplier_quote_request_id' => $supplierRfqId,
                'metadata' => $metadata,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Procurement audit log failed', [
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
