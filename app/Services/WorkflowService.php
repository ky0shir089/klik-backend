<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\WorkflowHeader;

class WorkflowService
{
    public static function assertCanRestart(Invoice $invoice): void
    {
        abort_unless(in_array($invoice->status, ['REQUEST', 'REJECT'], true), 409, 'Invoice cannot be restarted or cancelled.');
        abort_if($invoice->pv()->exists(), 409, 'Invoice already has a payment voucher.');
    }

    /**
     * Create a new class instance.
     */
    public function __construct($invoice)
    {
        $wf = WorkflowHeader::query()
            ->whereJsonContains("type_trx", $invoice->trx_id)
            ->where("min_amount", "<=", $invoice->total_amount)
            ->where(fn($q) => $q->whereNull('max_amount')->orWhere('max_amount', '>=', $invoice->total_amount))
            ->where("is_active", true)
            ->first();

        if (!$wf) {
            $invoice->update(['status' => 'APPROVE']);
            $invoice->pv()->create([
                "payment_method" => $invoice->payment_method,
                "supplier_id" => $invoice->supplier_id,
                "supplier_account_id" => $invoice->payment_method == "BANK" ? $invoice->supplier_account_id : null,
                "pv_amount" => $invoice->total_amount,
                "status" => "NEW",
                "trx_dtl_id" => $invoice->trx_id,
                "created_by" => auth()->id(),
            ]);
            return;
        }
        $steps = $wf->details()->oldest("sequence")->get();
        abort_if($steps->isEmpty(), 409, 'Workflow must have at least one approval step.');

        $histories = [];
        foreach ($steps as $index => $step) {
            abort_unless((int) $step->sequence === $index + 1, 409, 'Workflow sequences must be consecutive starting at 1.');
            $histories[] = [
                "wf_id" => $wf->id,
                "sequence" => $step->sequence,
                "status" => 'PENDING',
                "user_id" => $step->user_id,
                "updated_at" => null,
            ];
        }

        $invoice->wf_histories()->createMany($histories);
        $invoice->wf_approval()->create([
            "approve_count" => 0,
        ]);
        $invoice->update(['status' => 'REQUEST']);

        (new WhatsAppService($invoice, $steps->first()->user->phone));
    }
}
