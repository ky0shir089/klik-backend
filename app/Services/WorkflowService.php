<?php

namespace App\Services;

use App\Models\WorkflowHeader;

class WorkflowService
{
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

        $notifications = "";

        if ($wf) {
            $steps = $wf->details()->oldest("sequence")->get();
            $notifications = $steps[0]->user->phone;

            foreach ($steps as $step) {
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

            (new FonnteService($invoice, $notifications));
        } else {
            $invoice->status = "APPROVE";
            $invoice->save();

            $invoice->pv()->create([
                "payment_method" => $invoice->payment_method,
                "supplier_id" => $invoice->supplier_id,
                "supplier_account_id" => $invoice->payment_method == "BANK" ? $invoice->supplier_account_id : null,
                "pv_amount" => $invoice->total_amount,
                "status" => "NEW",
                "trx_dtl_id" => $invoice->trx_id,
                "created_by" => auth()->id(),
            ]);

            (new FonnteService($invoice, '6289518901400'));
        }

        return $notifications;
    }
}
