<html lang="en">

<head>
    <title>Invoice</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body>
    <div class="min-h-screen p-6">
        <div class="flex flex-col gap-8">
            <h1 class="mx-auto text-3xl font-medium">Memo Pelunasan</h1>

            <div>
                <table class="table-auto">
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
            </div>

            <div class="text-justify">
                <p>Dengan Hormat,</p>
                <p>Berdasarkan proses lelang yang telah dilakukan oleh PT Klik Lelang Indonesia terhadap
                    kendaraan bermotor roda dua milik PT Federal International Finance (PT FIF), maka dengan ini kami
                    menginformasikan detail pembayaran pelunasan unit Lelang sebagai berikut:</p>
            </div>

            <div>
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
                            <td class="p-1 border border-black">{{ $memo->branch_name }}</td>
                            <td class="p-1 text-center border border-black">{{ $memo->total_unit }}</td>
                            <td class="p-1 text-right border border-black">{{ Number::format($memo->total_amount) }}
                            </td>
                            <td class="p-1 border border-black">{{ $memo->units[0]->unit->auction->auction_name }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div>
                <p>Mohon pelunasan tersebut ditransfer/ dilakukan pembayaran ke rekening:</p>
                <p>Bank: BNI</p>
                <p>No Rekening: 8070231</p>
                <p>A/n Rekening: PT FEDERAL INTERNATIONAL FINANCE</p>
                <p>Atas perhatian dan kerjasamanya kami ucapkan terima kasih.</p>
            </div>

            <div>
                <table class="w-full table-fixed">
                    <thead>
                        <tr>
                            <th>Mengajukan,</th>
                            <th>Mengetahui,</th>
                            <th>Menyetujui,</th>
                        </tr>
                    </thead>
                </table>
            </div>

            <div class="mt-10">
                <table class="w-full table-fixed">
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
