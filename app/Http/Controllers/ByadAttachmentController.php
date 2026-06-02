<?php

namespace App\Http\Controllers;

use App\Models\ByadPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Rap2hpoutre\FastExcel\FastExcel;

class ByadAttachmentController extends Controller
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
    public function __invoke(ByadPayment $byad)
    {
        $id = "reports/byad/" . Str::random(6) . ".xlsx";

        $data = $byad->details->flatMap(function ($paymentDetail) {
            $units =  $paymentDetail->byad->details->load([
                'unit:id,auction_id,police_number,chassis_number,engine_number,price,byad_amount,brand,color,year,no_lot,type',
                'unit.auction:klik_auction_id,auction_date,customer_id,branch_name',
                'unit.auction.customer:klik_bidder_id,name,address,phone'
            ]);
            return $units->pluck('unit');
        })->values();

        $columns = function ($row) {
            return [
                'Tgl Lelang' => $row->auction->auction_date->format('d-m-Y'),
                'No Lot' => $row->no_lot,
                'Nopol' => $row->police_number,
                'Merk' => $row->brand,
                'Tipe' => $row->type,
                'Warna' => $row->color,
                'Tahun' => $row->year,
                'Noka' => $row->chassis_number,
                'Nosin' => $row->engine_number,
                'Nama Bidder' => $row->auction->customer->name,
                'Alamat Bidder' => $row->auction->customer->address,
                'Telepon Bidder' => $row->auction->customer->phone,
                'Harga Terbentuk' => $row->price,
                'Nominal BYAD' => $row->byad_amount,
                'Cabang' => $row->auction->branch_name,
            ];
        };

        $this->generateExcelReport($data, $columns, $id);

        return response()->download(storage_path('app/public/' . $id), "byad-attachment.xlsx");
    }
}
