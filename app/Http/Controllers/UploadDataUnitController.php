<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Rap2hpoutre\FastExcel\FastExcel;

class UploadDataUnitController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        if (!auth()->user()->tokenCan("repayment:add")) {
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
                "contract_number" => $line["NO KONTRAK"],
                "police_number" => $line["NO POL"],
                "chassis_number" => $line["NO RANGKA"],
                "engine_number" => $line["NO MESIN"],
                "package_number" => $line["NOPAKET"],
            ];
        });

        $array = $collection->toArray();

        $validator = Validator::make($array, [
            '*.contract_number' => 'required',
            '*.police_number' => 'required',
            '*.package_number' => 'required',
        ], [
            '*.contract_number.required' => 'Baris #:position: No Kontrak Kosong',
            '*.police_number.required' => 'Baris #:position: Nopol Kosong',
            '*.package_number.required' => 'Baris #:position: No Paket Kosong',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            foreach ($errors->all() as $error) {
                $data[] = $error;
            }
        } else {
            DB::beginTransaction();

            try {
                foreach ($array as $item) {
                    $unit = Unit::query()
                        ->where("police_number", $item["police_number"])
                        ->where("chassis_number", $item["chassis_number"])
                        ->where("engine_number", $item["engine_number"])
                        ->first();
                        
                    if ($unit) {
                        $unit->contract_number = $item["contract_number"];
                        $unit->package_number = $item["package_number"];
                        $unit->updated_by = auth()->id();
                        $unit->updated_at = now();
                        $unit->save();
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
