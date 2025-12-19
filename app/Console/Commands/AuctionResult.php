<?php

namespace App\Console\Commands;

use App\Models\Auction;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\Unit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AuctionResult extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auction-result';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Auction Result';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.klik')['token'],
        ])->get('https://api.kliklelang.co.id/api/report/v3/hasil_lelang', [
            'date_start' => now()->subDay()->format("Y-m-d"),
            'date_end' => now()->subDay()->format("Y-m-d"),
        ]);

        $results = $response["data"] ?? [];
        $chunkResults = collect($results)->chunk(100);

        DB::transaction(function () use ($chunkResults) {
            foreach ($chunkResults as $chunk) {
                $customers = [];
                $auctions = [];
                $units = [];

                foreach ($chunk as $customer) {
                    $customers[] = [
                        'klik_bidder_id' => $customer["id_bidder"],
                        'ktp' => $customer["identitas_ktp"],
                        'name' => $customer["nama_ktp"],
                        'va_number' => $customer["nomor_va"],
                        'created_by' => 1,
                        'updated_at' => null
                    ];

                    foreach ($customer['lelang'] as $lelang) {
                        $auctions[] = [
                            'customer_id' => $customer["id_bidder"],
                            'klik_auction_id' => $lelang['id_lelang'],
                            'auction_name' => $lelang['nama_lelang'],
                            'auction_date' => $lelang['tgl_lelang'],
                            'branch_id' => $lelang['id_cabang'],
                            'branch_name' => $lelang['balai_lelang'],
                            'created_by' => 1,
                            "created_at" => now(),
                        ];

                        foreach ($lelang['unit'] as $unit) {
                            $units[] = [
                                'auction_id' => $lelang['id_lelang'],
                                'klik_unit_id' => $unit['id_unit'],
                                'lot_number' => $lelang['no_lot'],
                                'police_number' => $unit['nopol'],
                                'chassis_number' => $unit['noka'],
                                'engine_number' => $unit['nosin'],
                                'price' => $unit['harga'] - $unit['potongan_tiket'],
                                'admin_fee' => $unit['biaya_admin'],
                                'final_price' => $unit['harga_total'],
                                'created_by' => 1,
                                'created_at' => now(),
                            ];

                            if (isset($customer['transaksi'])) {
                                foreach ($customer['transaksi'] as $transaction) {
                                    $transactions[] = [
                                        'unit_id' => $unit['id_unit'],
                                        'date' => $transaction['tanggal_upload'],
                                        'receipt_link' => $transaction['url'],
                                        'created_by' => 1,
                                        'created_at' => now(),
                                        'updated_at' => null
                                    ];
                                }
                            }
                        }
                    }
                }

                Customer::upsert($customers, ["klik_bidder_id"]);
                Auction::upsert($auctions, ["customer_id", "klik_auction_id"]);
                Unit::upsert($units, ["klik_unit_id"]);
                Transaction::insert($transactions);
            }
        });
    }
}
