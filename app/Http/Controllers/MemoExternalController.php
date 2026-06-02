<?php

namespace App\Http\Controllers;

use App\Models\InvoiceExternal;
use App\Services\TerbilangService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MemoExternalController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(InvoiceExternal $invoiceExternal)
    {
        $query = $invoiceExternal->load([
            "supplier",
        ]);

        $slug = Str::slug($query->invoice_external_no);

        $total_amount = $query->total_amount_manual > 0 ? $query->total_amount_manual : $query->total_amount_real;

        $data = [
            'invoice_external_no' => $query->invoice_external_no,
            'date' => Carbon::parse($query->date)->format('d F Y'),
            'due_date' => Carbon::parse($query->due_date)->format('d F Y'),
            'supplier_name' => $query->supplier->name,
            'description' => $query->description,
            'total_amount' => $total_amount,
            'pph23' => $query->pph23,
            'jumlah_tagihan' => $query->grand_total,
            'terbilang' => (new TerbilangService)->terbilang($query->grand_total),
            'signatory' => $query->signatory
        ];

        return Pdf::loadView('external', $data)->download("invoice-{$slug}.pdf");
    }
}
