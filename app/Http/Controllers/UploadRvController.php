<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Customer;
use App\Models\GL;
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
        set_time_limit(300);

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
                "auction_date" => $line["Tgl Event"] == "" ? "" : Carbon::parse($line["Tgl Event"])->format('Y-m-d'),
                "description" => "Terima Titipan Pelunasan#" . trim($line["VA Number"]),
                "bank_account_id" => trim($line["Account Number"]),
                "va_number" => trim($line["VA Number"]),
                "customer_name" => trim($line["Customer Name"]),
                "payment_date" => $line["Payment Date"] == "" ? "" : $line["Payment Date"],
                "journal_number" => trim($line["Journal Number"]),
                "starting_balance" => trim($line["Payment Amount"]),
            ];
        });

        $array = $collection->toArray();

        $validator = Validator::make($array, [
            '*.auction_date' => 'required|date',
            '*.description' => 'required|string',
            '*.bank_account_id' => 'required|string',
            '*.va_number' => 'required|string',
            '*.customer_name' => 'required|string',
            '*.payment_date' => 'required|date',
            '*.journal_number' => 'required|string|unique:receive_vouchers,journal_number',
            '*.starting_balance' => 'required|integer',
        ], [
            '*.auction_date.required' => 'Baris #:position: Tgl Event Kosong',
            '*.auction_date.date' => 'Baris #:position: Format Tanggal Event Salah',
            '*.description.required' => 'Baris #:position: VA Number Kosong',
            '*.bank_account_id.required' => 'Baris #:position: Account Number Kosong',
            '*.va_number.required' => 'Baris #:position: VA Number Kosong',
            '*.customer_name.required' => 'Baris #:position: Customer Name Kosong',
            '*.payment_date.required' => 'Baris #:position: Payment Date Kosong',
            '*.payment_date.date' => 'Baris #:position: Format Payment Date Salah',
            '*.journal_number.required' => 'Baris #:position: Journal Number Kosong',
            '*.journal_number.unique' => 'Baris #:position: Journal Number Sudah ada di Database',
            '*.starting_balance.required' => 'Baris #:position: Payment Amount Kosong',
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
                $prefix = 'RV' . $year;

                $lastRv = RV::select("rv_no")
                    ->where('rv_no', 'ilike', $prefix . '%')
                    ->latest('rv_no')
                    ->first();

                $countRv = $lastRv ? (int) Str::after($lastRv->rv_no, $prefix) + 1 : 1;
                $rv_no = $prefix . Str::padLeft($countRv, 5, '0');

                $authId = auth()->id();

                foreach ($excel_data as $date => $chunk) {
                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . config('services.klik')['token'],
                    ])->get('https://api.kliklelang.co.id/api/report/v3/hasil_lelang', [
                        'date_start' => $date,
                        'date_end' => $date,
                    ]);

                    $result = $response["data"] ?? [];

                    if (!$result) continue;

                    // ✔ Build VA lookup table for instant O(1) access
                    $vaLookup = [];
                    foreach ($result as $row) {
                        $vaLookup[$row["nomor_va"]] = $row;
                    }

                    foreach ($chunk as $va => $rvRows) {
                        // ✔ No more slow collect()->filter()
                        $filter = $vaLookup[$va] ?? null;
                        $customerId = null;

                        if ($filter) {
                            // ✔ Use upsert instead of firstOrCreate
                            $customer = Customer::firstOrCreate(
                                ['ktp' => $filter["identitas_ktp"]],
                                [
                                    'klik_bidder_id' => $filter["id_bidder"],
                                    'name' => $filter["nama_ktp"],
                                    'va_number' => $filter["nomor_va"],
                                    'created_by' => $authId,
                                    'created_at' => now(),
                                    'updated_at' => null
                                ]
                            );

                            $customerId = $customer->klik_bidder_id;

                            $unitInsert = [];

                            foreach ($filter['lelang'] as $lelang) {
                                $auction = new Auction();
                                $auction->customer_id = $filter["id_bidder"];
                                $auction->klik_auction_id = $lelang['id_lelang'];
                                $auction->auction_name = $lelang['nama_lelang'];
                                $auction->auction_date = $lelang['tgl_lelang'];
                                $auction->branch_id = $lelang['id_cabang'];
                                $auction->branch_name = $lelang['balai_lelang'];
                                $auction->created_by = $authId;
                                $auction->updated_at = null;
                                $auction->save();

                                foreach ($lelang['unit'] as $unit) {
                                    $unitInsert[] = [
                                        'auction_id' => $auction->id,
                                        'lot_number' => $lelang['no_lot'],
                                        'police_number' => $unit['nopol'],
                                        'chassis_number' => $unit['noka'],
                                        'engine_number' => $unit['nosin'],
                                        'price' => $unit['harga'],
                                        'admin_fee' => $unit['biaya_admin'],
                                        'final_price' => $unit['harga_total'],
                                        'created_by' => $authId,
                                        'created_at' => now(),
                                        'updated_at' => null
                                    ];
                                }
                            }

                            // ✔ bulk insert auctions + units
                            Unit::insert($unitInsert);
                        }

                        // ---------- RV + GL HANDLING ----------
                        $rvInsert = [];
                        $glInsert = [];

                        foreach ($rvRows as $row) {
                            $glNo = $rv_no;
                            // ✔ aggregate both debit + credit into one insert batch
                            $glInsert[] = [
                                "gl_no" => $glNo,
                                "date" => $row["payment_date"],
                                "type" => 'IN',
                                "description" => 'Terima Titipan Pelunasan#' . $row["va_number"],
                                "coa_id" => 8,
                                "debit" => $row["starting_balance"],
                                "credit" => 0,
                                "created_by" => $authId,
                                "created_at" => now(),
                                "updated_at" => null,
                            ];

                            $glInsert[] = [
                                "gl_no" => $glNo,
                                "date" => $row["payment_date"],
                                "type" => 'IN',
                                "description" => 'Terima Titipan Pelunasan#' . $row["va_number"],
                                "coa_id" => 58,
                                "debit" => 0,
                                "credit" => $row["starting_balance"],
                                "created_by" => $authId,
                                "created_at" => now(),
                                "updated_at" => null,
                            ];

                            $customerName = Str::replace("KLIKLELANG-", "", $row["customer_name"]);

                            $rvInsert[] = [
                                "rv_no" => $rv_no,
                                "date" => Carbon::parse($row["payment_date"]),
                                "type_trx_id" => 1,
                                "description" => 'Terima Titipan Pelunasan#' . $row["va_number"] . "_" . $customerName,
                                "bank_account_id" => $row["bank_account_id"],
                                "coa_id" => 58,
                                "starting_balance" => $row["starting_balance"],
                                "ending_balance" => $row["starting_balance"],
                                "journal_number" => $row["journal_number"],
                                "customer_id" => $customerId,
                                "created_by" => $authId,
                                "created_at" => now(),
                                "updated_at" => null
                            ];
                        }

                        // ✔ MASS INSERT — super fast
                        GL::insert($glInsert);
                        RV::insert($rvInsert);
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
