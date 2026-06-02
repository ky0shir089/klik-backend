<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Spp;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Rap2hpoutre\FastExcel\FastExcel;

class SppAttachmentController extends Controller
{
    private function resultsGenerator($results)
    {
        foreach ($results as $result) {
            yield $result;
        }
    }

    private function generateExcelReport($results, $columns, $filename)
    {
        (new FastExcel($this->resultsGenerator($results)))->configureOptionsUsing(function ($writer) {
            $writer->DEFAULT_COLUMN_WIDTH = 18;
        })->export(storage_path('app/public/' . $filename), function ($row) use ($columns) {
            return $columns($row);
        });
    }

    /**
     * Handle the incoming request.
     */
    public function __invoke(Payment $payment)
    {
        $id = "reports/spp/" . Str::random(6) . ".xlsx";

        $data = $payment->spps->flatMap(function ($paymentDetail) {
            $units =  $paymentDetail->spp->details->load([
                'unit:id,auction_id,police_number,chassis_number,engine_number,price',
                'unit.auction:klik_auction_id,auction_date,customer_id,branch_name',
                'unit.auction.customer:klik_bidder_id,name'
            ]);
            return $units->pluck('unit');
        })->values();

        $columns = function ($row) {
            return [
                'Tgl Lelang' => $row->auction->auction_date->format('d-m-Y'),
                'Nama Bidder' => $row->auction->customer->name,
                'Nopol' => $row->police_number,
                'Noka' => $row->chassis_number,
                'Nosin' => $row->engine_number,
                'Harga Terbentuk' => $row->price,
                'Cabang' => $row->auction->branch_name,
            ];
        };

        $this->generateExcelReport($data, $columns, $id);

        return response()->download(storage_path('app/public/' . $id), "spp-attachment.xlsx");
    }
}
