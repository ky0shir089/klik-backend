<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Customer;
use App\Models\RV;
use App\Models\Unit;
use App\Services\FileUploadService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Rap2hpoutre\FastExcel\FastExcel;

class UploadRvController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        if (!auth()->user()->tokenCan("rv:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $success = false;
        $message = "error";
        $data = [];
        $code = 400;

        $upload = (new FileUploadService)->handleUpload($request->file('file'));
        $path = $upload->path;

        $collection = (new FastExcel())->import(storage_path("app/public/" . $path), function ($line) {
            return [
                "auction_date" => Carbon::parse($line["Tgl Event"])->format('Y-m-d'),
                "description" => "Terima Titipan Pelunasan#" . $line["No VA"],
                "bank_account_id" => $line["Account Number"],
                "va_number" => $line["VA Number"],
                "payment_date" => Carbon::parse($line["Payment Date"])->format('Y-m-d H:i:s'),
                "journal_number" => $line["Journal Number"],
                "starting_balance" => $line["Payment Amount"],
            ];
        });

        $array = $collection->toArray();

        $validator = Validator::make($array, [
            '*.auction_date' => 'required|date',
            '*.description' => 'required|string',
            '*.bank_account_id' => 'required|string',
            '*.va_number' => 'required|string',
            '*.payment_date' => 'required|date',
            '*.journal_number' => 'required|integer|unique:receive_vouchers,journal_number',
            '*.starting_balance' => 'required|integer',
        ], [
            '*.auction_date.required' => 'Baris #:position: Nama Cabang Kosong',
            '*.auction_date.date' => 'Baris #:position: Format Tanggal Event Salah',
            '*.description.required' => 'Baris #:position: Kode Titik Kosong',
            '*.bank_account_id.required' => 'Baris #:position: Tanggal Order Kosong',
            '*.va_number.required' => 'Baris #:position: VA Number Kosong',
            '*.payment_date.required' => 'Baris #:position: Payment Date Kosong',
            '*.payment_date.date' => 'Baris #:position: Format Payment Date Salah',
            '*.journal_number.required' => 'Baris #:position: Journal Number Kosong',
            '*.journal_number.unique' => 'Baris #:position: Journal Number Sudah ada di Database',
            '*.starting_balance.required' => 'Baris #:position: Paymount Amount Kosong',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            foreach ($errors->all() as $error) {
                $data[] = $error;
            }
        } else {
            $excel_data = collect($array)->groupBy(["auction_date", "va_number"]);

            DB::beginTransaction();

            try {
                $year = date('y');
                $count_rv = RV::count() + 1;

                foreach ($excel_data as $date => $chunk) {
                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . config('services.klik')['token'],
                    ])->get('https://api.kliklelang.co.id/api/report/v3/hasil_lelang', [
                        'date_start' => $date,
                        'date_end' => $date,
                    ]);
                    $result = $response["data"];

                    $va_number = collect($chunk)->keys()->first();

                    $filter = collect($result)->filter(function ($item) use ($va_number) {
                        return $item['identitas_ktp'] == $va_number;
                    })->first();

                    if ($filter) {
                        Customer::firstOrCreate(
                            ['ktp' => $filter["identitas_ktp"]],
                            [
                                'klik_bidder_id' => $filter["id_bidder"],
                                'ktp' => $filter["identitas_ktp"],
                                'name' => $filter["nama_ktp"],
                                'va_number' => $filter["identitas_ktp"],
                                'created_by' => auth()->id(),
                                'updated_at' => null,
                            ]
                        );

                        foreach ($filter['lelang'] as $lelang) {
                            $auction = new Auction();
                            $auction->customer_id = $filter["id_bidder"];
                            $auction->klik_auction_id  = $lelang['id_lelang'];
                            $auction->auction_name = $lelang['nama_lelang'];
                            $auction->auction_date = $lelang['tgl_lelang'];
                            $auction->branch_id = $filter['id_cabang'];
                            $auction->branch_name = $filter['balai_lelang'];
                            $auction->created_by = auth()->id();
                            $auction->save();

                            $data_unit = [];

                            foreach ($lelang['unit'] as $unit) {
                                $data_unit[] = [
                                    'auction_id' => $auction->id,
                                    'lot_number' => $lelang['no_lot'],
                                    'police_number' => $unit['nopol'],
                                    'chassis_number' => $unit['noka'],
                                    'engine_number' => $unit['nosin'],
                                    'price' => $unit['harga'],
                                    'admin_fee' => 150000,
                                    'final_price' => $unit['harga'] + 150000,
                                    'created_by' => auth()->id(),
                                    'created_at' => now(),
                                ];
                            }

                            Unit::insert($data_unit);
                        }

                        $values = [];

                        foreach ($chunk->first() as $row) {
                            $values[] = [
                                "rv_no" => 'RV' . $year . Str::padLeft($count_rv++, 5, '0'),
                                "date" => $row["payment_date"],
                                "type_trx_id" => 1,
                                "description" => 'Terima Titipan Pelunasan#' . $row["va_number"],
                                "bank_account_id" => $row["bank_account_id"],
                                "coa_id" => 58,
                                "starting_balance" => $row["starting_balance"],
                                "ending_balance" => $row["starting_balance"],
                                "journal_number" => $row["journal_number"],
                                "customer_id" => $filter["id_bidder"] ?? 0,
                                "created_by" => auth()->id(),
                                "updated_at" => null
                            ];
                        }

                        RV::insert($values);
                    }
                }

                $success = true;
                $message = "Data has been imported";
                $data = $excel_data;
                $code = 200;

                DB::commit();
            } catch (\Throwable $th) {
                info($th->getMessage());

                DB::rollBack();

                return response()->json([
                    "success" => false,
                    "message" => $th->getMessage(),
                ], 500);
            }
        }

        return response()->json([
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ], $code);
    }
}
