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
        <h1 class="text-3xl font-medium text-center">INVOICE</h1>

        <br />

        <table class="text-xs fixed-width">
            <tbody>
                <tr>
                    <td>
                        <table>
                            <tbody>
                                <tr>
                                    <th class="text-left">No. Invoice</th>
                                    <td>:</td>
                                    <td>{{ $invoice_external_no }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </td>

                    <td>
                        <table>
                            <tbody>
                                <tr>
                                    <th class="text-left">Tanggal Invoice</th>
                                    <td>:</td>
                                    <td>{{ $date }}</td>
                                </tr>

                                <tr>
                                    <th class="text-left">Jatuh Tempo</th>
                                    <td>:</td>
                                    <td>{{ $due_date }}</td>
                                </tr>

                            </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>

        <br />

        <table class="text-xs fixed-width">
            <tbody>
                <tr>
                    <td>
                        <table>
                            <tbody>
                                <tr>
                                    <td class="border-bottom">Info kami</td>
                                </tr>
                                <tr>
                                    <td><b>PT. Klik Lelang Indonesia</b></td>
                                </tr>
                                <tr>
                                    <td>1000 0000 0237 4245</td>
                                </tr>
                                <tr>
                                    <td>Jalan Raya Pulo Gebang No. 9</td>
                                </tr>
                                <tr>
                                    <td>Kel. Pulo Gebang, Kec. Cakung</td>
                                </tr>
                                <tr>
                                    <td>Jakarta Timur, DKI Jakarta</td>
                                </tr>
                            </tbody>
                        </table>
                    </td>

                    <td>
                        <table>
                            <tbody>
                                <tr>
                                    <td class="border-bottom">Ditagihkan kepada</td>
                                </tr>
                                <tr>
                                    <td><b>{{ $supplier_name }}</b></td>
                                </tr>
                                <tr>
                                    <td>0013 3146 5309 1000</td>
                                </tr>
                                <tr>
                                    <td>Jl. TB Simatupang Blok Kav no. 15</td>
                                </tr>
                                <tr>
                                    <td>Kel. Lebak Bulus, Kec. Cilandak</td>
                                </tr>
                                <tr>
                                    <td>Jakarta Selatan, DKI Jakarta</td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>

        <br />

        <table class="w-full border-black table-auto">
            <thead>
                <tr class="bg-red-500">
                    <th class="border-black">Keterangan</th>
                    <th class="border-black">Jumlah</th>
                </tr>
            </thead>

            <tbody>
                <tr>
                    <td class="p-1 text-xs border-black">{{ $description }}</td>
                    <td class="p-1 text-xs text-right border-black">
                        Rp {{ Number::format($total_amount) }}
                    </td>
                </tr>
                <tr>
                    <td class="p-1 text-xs text-right">Jumlah</td>
                    <td class="p-1 text-xs text-right border-black">
                        Rp {{ Number::format($total_amount) }}
                    </td>
                </tr>
                <tr>
                    <td class="p-1 text-xs text-right">PPN</td>
                    <td class="p-1 text-xs text-right border-black">
                        Rp 0
                    </td>
                </tr>
                <tr>
                    <td class="p-1 text-xs text-right">PPh 23</td>
                    <td class="p-1 text-xs text-right border-black">
                        Rp {{ Number::format($pph23) }}
                    </td>
                </tr>
                <tr>
                    <td class="p-1 text-xs text-right"><b>Jumlah Tagihan</b></td>
                    <td class="p-1 text-xs text-right border-black">
                        <b>Rp {{ Number::format($jumlah_tagihan) }}</b>
                    </td>
                </tr>
            </tbody>
        </table>

        <br />

        <div class="border-all">Terbilang: # {{ Str::ucwords($terbilang) }} Rupiah #</div>

        <br />

        <div class="text-xs border-all-width">
            @php
                $content = "Note:
Pembayaran dapat dilakukan dengan transfer.
Ditujukan melalui Rekening:
<b>Rekening: Bank BNI</b>
<b>Cabang: KCP Sunter Agung</b>
<b>No Rek: 8555888788</b>
<b>An. PT. Klik Lelang Indonesia</b>";
            @endphp

            {!! nl2br(strip_tags($content, '<b>')) !!}
        </div>

        <div class="float-right">
            <p>Hormat Kami,</p>
            <br /><br /><br />
            <p>{{ $signatory }}</p>
        </div>
    </div>
</body>

</html>
