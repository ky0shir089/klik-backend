<!doctype html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport"
        content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Memo Invoice</title>
    <link rel="stylesheet" href="{{ resource_path('css/pdf.css') }}" type="text/css">
</head>

<body>
    <div class="min-h-screen p-6">
        <div>
            <img src="{{ storage_path('app/public/images/klik_logo2.svg') }}" alt="logo"
                style="height: 100px; float:left;" />

            <h1 class="text-3xl font-medium text-center">Memo Invoice</h1>
        </div>

        <br /><br />

        <table>
            <tbody>
                <tr>
                    <th class="text-left">No. Memo</th>
                    <td>:</td>
                    <td>{{ $invoice_no }}</td>
                </tr>
                <tr>
                    <th class="text-left">Tanggal</th>
                    <td>:</td>
                    <td>{{ $invoice_date }}</td>
                </tr>
                <tr>
                    <th class="text-left">Kepada</th>
                    <td>:</td>
                    <td></td>
                </tr>
                <tr>
                    <th class="text-left">Dari</th>
                    <td>:</td>
                    <td></td>
                </tr>
                <tr>
                    <th class="text-left">Perihal</th>
                    <td>:</td>
                    <td>{{ $invoice->description }}</td>
                </tr>
            </tbody>
        </table>

        <br />

        <div class="text-justify">
            <span>Dengan Hormat,</span><br />
            <span>Sehubungan dengan adanya {{ $invoice->description }}, maka dengan ini saya ingin mengajukan
                pembayaran dengan rincian sebagai berikut: </span>
        </div>

        <br />

        <table class="w-full border-black table-auto">
            <thead>
                <tr class="bg-red-500">
                    <th class="border-black">No</th>
                    <th class="border-black">Kode Trx</th>
                    <th class="border-black">Keterangan</th>
                    <th class="border-black">Jumlah</th>
                    <th class="border-black">PPh</th>
                    <th class="border-black">PPn</th>
                    <th class="border-black">Total</th>
                </tr>
            </thead>

            <tbody>
                @php($index = 1)
                @foreach ($invoice->details as $detail)
                    <tr>
                        <td class="p-1 text-xs border-black">{{ $index++ }}</td>
                        <td class="p-1 text-xs border-black">{{ $detail->coa->description }}</td>
                        <td class="p-1 text-xs text-center border-black">{{ $detail->description }}</td>
                        <td class="p-1 text-xs text-right border-black">
                            {{ Number::format($detail->item_amount) }}
                        </td>
                        <td class="p-1 text-xs text-right border-black">
                            {{ Number::format($detail->pph_amount) }}
                        </td>
                        <td class="p-1 text-xs text-right border-black">
                            {{ Number::format($detail->ppn_amount) }}
                        </td>
                        <td class="p-1 text-xs text-right border-black">
                            {{ Number::format($detail->total_amount) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>

            <tfoot>
                <tr>
                    <th colspan="3" class="p-1 text-xs border-black text-center">Total</th>
                    <th class="p-1 text-xs border-black text-right">
                        {{ Number::format($sum_amount) }}
                    </th>
                    <th class="p-1 text-xs border-black text-right">
                        {{ Number::format($sum_pph) }}
                    </th>
                    <th class="p-1 text-xs border-black text-right">
                        {{ Number::format($sum_ppn) }}
                    </th>
                    <th class="p-1 text-xs border-black text-right">
                        {{ Number::format($invoice->total_amount) }}
                    </th>
                </tr>
            </tfoot>
        </table>

        <br />

        <div>
            <span>Mohon pengajuan tersebut ditransfer/ dilakukan pembayaran ke rekening:</span><br />
            <span>Bank: {{ $invoice->supplier_account->bank->name }}</span><br />
            <span>No Rekening: {{ $invoice->supplier_account->account_number }}</span><br />
            <span>A/n Rekening: {{ $invoice->supplier_account->account_name }}</span><br />
            <span>Atas perhatian dan kerjasamanya kami ucapkan terima kasih.</span>
        </div>

        <br />

        <table class="w-full">
            <thead>
                <tr>
                    <th>Mengajukan,</th>
                    <th>Mengetahui,</th>
                    <th>Menyetujui,</th>
                </tr>
            </thead>
        </table>

        <br />

        <div class="mt-14">
            <table class="w-full">
                <tbody>
                    <tr class="text-center">
                        <td>(..............................)</td>
                        <td>(..............................)</td>
                        <td>(..............................)</td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</body>

</html>
