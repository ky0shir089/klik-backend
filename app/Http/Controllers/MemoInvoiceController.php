<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Str;

class MemoInvoiceController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Invoice $invoice)
    {
        $invoice->load([
            "supplier_account",
            "supplier_account.supplier",
            "supplier_account.bank",
            "details",
            "details.coa",
            "details.pph",
            "user:id,name"
        ]);

        $slug = Str::slug($invoice->invoice_no);

        $username = strtoupper($invoice->user->name);
        $splitName = explode(" ", $username);
        if (count($splitName) >= 3) {
            $alias = $splitName[0][0] . $splitName[1][0] . $splitName[2][0];
        } elseif (count($splitName) == 2) {
            $alias = $splitName[0][0] . $splitName[1][0] . $splitName[1][1];
        } else {
            $alias = substr($username, 0, 3);
        }


        $data = [
            'invoice' => $invoice,
            'invoice_no' => $invoice->invoice_no,
            'invoice_date' => Carbon::parse($invoice->date)->format('d F Y'),
            'sum_amount' => $invoice->details->sum('item_amount'),
            'sum_pph' => $invoice->details->sum('pph_amount'),
            'sum_ppn' => $invoice->details->sum('ppn_amount'),
            'alias' => $alias,
        ];

        return Pdf::loadView('invoice', $data)->download("memo-{$slug}.pdf");
    }
}
