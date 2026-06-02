<?php

namespace App\Services;

use App\Models\GL;
use App\Models\Invoice;
use App\Models\Settlement;
use Illuminate\Support\Str;

class LpjService
{
    /**
     * Create a new class instance.
     */
    public function __construct($invoice)
    {
        $authId = auth()->id();

        $invoice->status = "PAID";
        $invoice->save();

        $lpj = Settlement::where('lpj_invoice_id', $invoice->id)->first();
        if ($lpj) {
            $lpj->balance = $lpj->prepayment_amount - $lpj->lpj_amount;
            $lpj->status = $lpj->balance == 0 ? "CLOSED" : ($lpj->balance < 0 ? "OVER" : "OPEN");
            $lpj->save();

            $gl = [
                "gl_no" => $invoice->invoice_no,
                "date" => now(),
                "type" => 'OUT',
                "created_by" => $authId,
                "created_at" => now(),
                "updated_at" => null,
            ];

            foreach ($invoice->details as $detail) {
                $ledger[] = [
                    ...$gl,
                    "description" => $detail->description,
                    "coa_id" => $detail->inv_coa_id,
                    "debit" => $detail->item_amount,
                    "credit" => 0,
                ];
            }

            $ledger[] = [
                ...$gl,
                "description" => "LPJ PREPAYMENT " . $invoice->invoice_no,
                "coa_id" => 21,
                "debit" => 0,
                "credit" =>  $invoice->total_amount >= $lpj->prepayment_amount ? $lpj->prepayment_amount : $invoice->total_amount,
            ];

            if ($lpj->balance < 0) {
                $countInv = Invoice::query()
                    ->whereNotNull("invoice_no")
                    ->where("created_at", ">=", date('Y') . "-01-01")
                    ->where("created_at", "<=", date('Y') . "-12-31")
                    ->count() + 1;

                $invoice_no = 'KLIK/' . date("m") . '/' . date('y') . '/' . Str::padLeft($countInv++, 3, '0');

                $invoice =  Invoice::create([
                    "date" => now(),
                    "invoice_no" => $invoice_no,
                    "trx_id" => 9,
                    "supplier_id" => $invoice->supplier_id,
                    "payment_method" => "BANK",
                    "supplier_account_id" => $invoice->supplier_account_id,
                    "description" => "Prepayment Kekurangan LPJ",
                    "total_amount" => abs($lpj->balance),
                    "status" => "APPROVE",
                    "created_by" => $authId,
                ]);

                $invoice->details()->create([
                    "inv_coa_id" => 55,
                    "description" => "Prepayment Kekurangan LPJ",
                    "item_amount" => abs($lpj->balance),
                    "total_amount" => abs($lpj->balance),
                    "created_by" => $authId,
                ]);

                $invoice->pv()->create([
                    "payment_method" => "BANK",
                    "supplier_id" => $invoice->supplier_account->supplier->id,
                    "supplier_account_id" => $invoice->supplier_account_id,
                    "pv_amount" => $invoice->total_amount,
                    "status" => "NEW",
                    "trx_dtl_id" => $invoice->trx_id,
                    "created_by" => $authId,
                ]);

                $lpj->byhmd_invoice_id = $invoice->id;
                $lpj->byhmd_amount = abs($lpj->balance);
                $lpj->save();

                $ledger[] = [
                    ...$gl,
                    "description" => "KEKURANGAN PREPAYMENT " . $invoice->invoice_no,
                    "coa_id" => 55,
                    "debit" => 0,
                    "credit" => abs($lpj->balance),
                ];
            }

            GL::insert($ledger);
        }

        return;
    }
}
