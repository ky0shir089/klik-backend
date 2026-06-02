<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Rap2hpoutre\FastExcel\FastExcel;

class UploadSppController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        if (!auth()->user()->tokenCan("upload-spp:add")) {
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
                "contract_number" => trim($line["NO KONTRAK"]),
                "police_number" => trim($line["NO POL"]),
                "chassis_number" => trim($line["NO RANGKA"]),
                "engine_number" => trim($line["NO MESIN"]),
                "package_number" => trim($line["NOPAKET"]),
                "distributed_price" => trim($line["HARGA BID/DISTRIBUSI"]),
            ];
        });

        $array = $collection->toArray();

        $validator = Validator::make($array, [
            '*.contract_number' => 'required',
            '*.police_number' => 'required',
            '*.chassis_number' => 'required',
            '*.engine_number' => 'required',
            '*.package_number' => 'required',
            '*.distributed_price' => 'required',
        ], [
            '*.contract_number.required' => 'Baris #:position: No Kontrak Kosong',
            '*.police_number.required' => 'Baris #:position: Nopol Kosong',
            '*.chassis_number.required' => 'Baris #:position: Noka Kosong',
            '*.engine_number.required' => 'Baris #:position: Nosin Kosong',
            '*.package_number.required' => 'Baris #:position: No Paket Kosong',
            '*.distributed_price.required' => 'Baris #:position: Harga Distribusi Kosong',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            foreach ($errors->all() as $error) {
                $data[] = $error;
            }
        } else {
            DB::beginTransaction();

            $collections = collect($array)->chunk(100);
            $authId = auth()->id();

            try {
                foreach ($collections as $rows) {
                    foreach ($rows as $item) {
                        $unit = Unit::query()
                            ->where(function ($query) use ($item) {
                                $query->where("police_number", $item["police_number"])
                                    ->orWhere("chassis_number", $item["chassis_number"])
                                    ->orWhere("engine_number", $item["engine_number"]);
                            })
                            ->whereNull("contract_number")
                            ->whereNull("package_number")
                            ->first();

                        if ($unit) {
                            $unit->contract_number = $item["contract_number"];
                            $unit->package_number = $item["package_number"];
                            $unit->distributed_price = $item["distributed_price"];
                            $unit->diff_price = $item["distributed_price"] - $unit->price + $unit->ticket_price;
                            $unit->spp_status = "UPLOADED";
                            $unit->updated_by =  $authId;
                            $unit->updated_at = now();
                            $unit->save();
                        }
                    }
                }

                $success = true;
                $message = "Data has been imported";
                $data = $collection;
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
