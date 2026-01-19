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
            "details.pph"
        ]);

        $slug = Str::slug($invoice->invoice_no);

        $data = [
            'invoice' => $invoice,
            'invoice_no' => $invoice->invoice_no,
            'invoice_date' => Carbon::parse($invoice->date)->format('d F Y'),
            'sum_amount' => $invoice->details->sum('item_amount'),
            'sum_pph' => $invoice->details->sum('pph_amount'),
            'sum_ppn' => $invoice->details->sum('ppn_amount'),
        ];

        return Pdf::loadView('invoice', $data)->download("memo-{$slug}.pdf");
    }
}
