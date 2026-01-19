<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\AuctionCustomer;
use App\Models\Customer;
use App\Models\GL;
use App\Models\RV;
use App\Models\RvClassification;
use App\Services\FileUploadService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Rap2hpoutre\FastExcel\FastExcel;

use function Symfony\Component\Clock\now;

class RvUploadController extends Controller
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

        $collections = (new FastExcel())->import(storage_path("app/public/" . $path), function ($line) {
            return [
                "description" => "Terima Titipan Pelunasan#" . trim($line["VA Number"]),
                "bank_account_id" => trim($line["Account Number"]),
                "va_number" => trim($line["VA Number"]),
                "customer_name" => trim($line["Customer Name"]),
                "payment_date" => $line["Payment Date"] == "" ? "" : $line["Payment Date"],
                "journal_number" => trim($line["Journal Number"]),
                "starting_balance" => trim($line["Payment Amount"]),
            ];
        });

        $array = $collections->toArray();

        $validator = Validator::make($array, [
            '*.description' => 'required',
            '*.bank_account_id' => 'required',
            '*.va_number' => 'required',
            '*.customer_name' => 'required',
            '*.payment_date' => 'required',
            '*.journal_number' => 'required|unique:receive_vouchers,journal_number',
            '*.starting_balance' => 'required',
        ], [
            '*.description.required' => 'Baris #:position: VA Number Kosong',
            '*.bank_account_id.required' => 'Baris #:position: Account Number Kosong',
            '*.va_number.required' => 'Baris #:position: VA Number Kosong',
            '*.customer_name.required' => 'Baris #:position: Customer Name Kosong',
            '*.payment_date.required' => 'Baris #:position: Payment Date Kosong',
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
            $excelData = collect($array)->groupBy(["va_number"]);

            $currentYear = date('y');
            $findLastRvDate = RV::select("date")->latest()->first();
            $lastRvDate = $findLastRvDate->date ?? now();
            $lastRvYear = Carbon::parse($lastRvDate)->format('y');
            if ($currentYear > $lastRvYear) {
                $countRv = 1;
            } else {
                $countRv = RV::query()
                    ->where("date", ">=", date('Y') . "-01-01")
                    ->where("date", "<=", date('Y') . "-12-31")
                    ->count() + 1;
            }
            $rvNo = 'RV' . $currentYear;

            $authId = auth()->id();

            DB::beginTransaction();

            try {
                $countError = 0;
                $errors = [];
                $glInsert = [];
                $rvInsert = [];

                foreach ($excelData as $va => $rows) {
                    $customer = Customer::query()
                        ->where("va_number", $va)
                        ->first();

                    if (!$customer) {
                        $countError++;
                        $errors[] = [
                            "Customer dengan VA Number " . $va . " Tidak Ditemukan"
                        ];
                    } else {
                        foreach ($rows as $row) {
                            $glNo = $rvNo . Str::padLeft($countRv, 5, '0');
                            $customerName = Str::replace("KLIKLELANG-", "", $row["customer_name"]);

                            $glInsert[] = [
                                "gl_no" => $glNo,
                                "date" => $row["payment_date"],
                                "type" => 'IN',
                                "description" => 'Terima Titipan Pelunasan#' . $row["va_number"] . "_" . $customerName,
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
                                "description" => 'Terima Titipan Pelunasan#' . $row["va_number"] . "_" . $customerName,
                                "coa_id" => 58,
                                "debit" => 0,
                                "credit" => $row["starting_balance"],
                                "created_by" => $authId,
                                "created_at" => now(),
                                "updated_at" => null,
                            ];

                            $rvInsert[] = [
                                "rv_no" => $rvNo . Str::padLeft($countRv++, 5, '0'),
                                "date" => Carbon::parse($row["payment_date"]),
                                "type_trx_id" => 1,
                                "description" => 'Terima Titipan Pelunasan#' . $row["va_number"] . "_" . $customerName,
                                "bank_account_id" => $row["bank_account_id"],
                                "coa_id" => 58,
                                "starting_balance" => $row["starting_balance"],
                                "ending_balance" => $row["starting_balance"],
                                "journal_number" => $row["journal_number"],
                                "customer_id" => $customer->klik_bidder_id,
                                "created_by" => $authId,
                                "created_at" => now(),
                                "updated_at" => null
                            ];
                        }
                    }
                }

                GL::insert($glInsert);
                RV::insert($rvInsert);

                if ($countError > 0) {
                    $success = false;
                    $message = "Customer dengan VA Number tidak ditemukan";
                    $data = $errors;
                    $code = 500;
                } else {
                    $success = true;
                    $message = "Data has been imported";
                    $data = $collections;
                    $code = 200;
                }

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
