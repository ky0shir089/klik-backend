<!doctype html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport"
        content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Memo Pelunasan</title>
    <link rel="stylesheet" href="{{ resource_path('css/pdf.css') }}" type="text/css">
</head>

<body>
    <div class="min-h-screen p-6">
        <div>
            <h1 class="text-3xl font-medium text-center">Memo Pelunasan</h1>

            <table>
                <tbody>
                    <tr>
                        <th class="text-left">No. Memo</th>
                        <td>:</td>
                        <td>{{ $memo_no }}</td>
                    </tr>
                    <tr>
                        <th class="text-left">Kepada Yth</th>
                        <td>:</td>
                        <td>Finance Dept</td>
                    </tr>
                    <tr>
                        <th class="text-left">Tanggal</th>
                        <td>:</td>
                        <td>{{ $memo->created_at->format('d F Y') }}</td>
                    </tr>
                    <tr>
                        <th class="text-left">Perihal</th>
                        <td>:</td>
                        <td>Pelunasan Lelang KLIK_FIF</td>
                    </tr>
                </tbody>
            </table>

            <br />

            <div class="text-justify">
                <span>Dengan Hormat,</span><br />
                <span>Berdasarkan proses lelang yang telah dilakukan oleh PT Klik Lelang Indonesia terhadap
                    kendaraan bermotor roda dua milik PT Federal International Finance (PT FIF), maka dengan ini kami
                    menginformasikan detail pembayaran pelunasan unit Lelang sebagai berikut:</span>
            </div>

            <br />

            <table class="w-full border border-black table-auto">
                <thead>
                    <tr class="bg-red-500">
                        <th class="border border-black">Cabang</th>
                        <th class="border border-black">Jumlah Unit</th>
                        <th class="border border-black">Harga Terbentuk</th>
                        <th class="border border-black">Berita Acara</th>
                    </tr>
                </thead>

                <tbody>
                    <tr>
                        <td class="p-1 text-xs border border-black">{{ $memo->branch_name }}</td>
                        <td class="p-1 text-xs text-center border border-black">{{ $memo->total_unit }}</td>
                        <td class="p-1 text-xs text-right border border-black">
                            {{ Number::format($memo->total_amount) }}
                        </td>
                        <td class="p-1 text-xs border border-black">
                            PELUNASAN FIF_KLIK EVENT
                            {{ $memo->units[0]->unit->auction->auction_date->format('d M Y') }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <br />

            <div>
                <span>Mohon pelunasan tersebut ditransfer/ dilakukan pembayaran ke rekening:</span><br />
                <span>Bank: BNI</span><br />
                <span>No Rekening: 8070231</span><br />
                <span>A/n Rekening: PT FEDERAL INTERNATIONAL FINANCE</span><br />
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
                            <td>SUR</td>
                            <td>RUS</td>
                            <td>JAR</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>
