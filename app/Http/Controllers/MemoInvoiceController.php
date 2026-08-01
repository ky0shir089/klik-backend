<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\AliasService;
use App\Services\MergePdf;
use App\Services\SignatureSvgService;
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
            "user:id,name",
            "wf_histories:processable_id,user_id,signature"
        ]);

        $slug = Str::slug($invoice->invoice_no);

        $username = strtoupper($invoice->user->name);

        $from = (new AliasService())->handle($username);

        $wfHistories = $invoice->wf_histories;
        $approvals = [];
        foreach ($wfHistories as $wf) {
            $wf->load("user:id,name");
            $alias = (new AliasService())->handle(strtoupper($wf->user->name));
            $points = json_decode($wf->signature, true);
            if (isset($points)) {
                $svg = (new SignatureSvgService())->generateSignatureSvg($points);
                $html = '<img src="data:image/svg+xml;base64,' . base64_encode($svg) . '"  width="100" height="100" />';
            } else {
                $html = null;
            }
            $approvals[] = [
                'name' => $alias,
                'signature' => $html,
            ];
        }

        if (count($approvals) > 0) {
            $to = $approvals[count($approvals) - 1]['name'];
        } else {
            $to = null;
        }

        $data = [
            'invoice' => $invoice,
            'invoice_no' => $invoice->invoice_no,
            'invoice_date' => Carbon::parse($invoice->date)->format('d F Y'),
            'sum_amount' => $invoice->details->sum('item_amount'),
            'sum_pph' => $invoice->details->sum('pph_amount'),
            'sum_ppn' => $invoice->details->sum('ppn_amount'),
            'from' => $from,
            'to' => $to,
            'approvals' => $approvals,
        ];

        if (isset($invoice->attachment)) {
            $pdf = Pdf::loadView('invoice', $data);
            $fileName = "memo-{$slug}.pdf";
            $directory = storage_path('app/public/pdfs');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            $pdf->save($directory . '/' . $fileName);

            $files[] = $directory . '/' . $fileName;
            $files[] = storage_path("app/public/" . $invoice->attachment?->path);
            $output = storage_path("app/public/pdfs/memo-{$slug}_merged.pdf");

            (new MergePdf())->mergePdf($files, $output);

            return response()->download($output, "memo-{$slug}.pdf");
        } else {
            return Pdf::loadView('invoice', $data)->download("memo-{$slug}.pdf");
        }
    }
}
