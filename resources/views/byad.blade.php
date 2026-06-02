<!doctype html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport"
        content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Lampiran BYAD</title>
    <link rel="stylesheet" href="{{ resource_path('css/pdf.css') }}" type="text/css">
</head>

<body>
    <div class="min-h-screen p-6">
        <h1 class="text-3xl font-medium text-center">Lampiran BYAD</h1>

        <br />

        <table class="w-full border-black table-auto">
            <thead>
                <tr>
                    <th class="border-black">No</th>
                    <th class="border-black">Tgl Lelang</th>
                    <th class="border-black">Nama Bidder</th>
                    <th class="border-black">Nopol</th>
                    <th class="border-black">Noka</th>
                    <th class="border-black">Nosin</th>
                    <th class="border-black">Harga Terbentuk</th>
                    <th class="border-black">Nominal BYAD</th>
                    <th class="border-black">Cabang</th>
                </tr>
            </thead>

            <tbody>
                @php($index = 1)
                @foreach ($units as $unit)
                    <tr>
                        <td class="p-1 text-xs border-black">{{ $index++ }}</td>
                        <td class="p-1 text-xs border-black">{{ $unit->auction->auction_date->format('d-m-Y') }}</td>
                        <td class="p-1 text-xs text-center border-black">{{ $unit->auction->customer->name }}</td>
                        <td class="p-1 text-xs border-black">{{ $unit->police_number }}</td>
                        <td class="p-1 text-xs border-black">{{ $unit->chassis_number }}</td>
                        <td class="p-1 text-xs border-black">{{ $unit->engine_number }}</td>
                        <td class="p-1 text-xs text-right border-black">
                            {{ Number::format($unit->price) }}
                        </td>
                        <td class="p-1 text-xs text-right border-black">
                            {{ Number::format($unit->byad_amount) }}
                        </td>
                        <td class="p-1 text-xs border-black">
                            {{ $unit->auction->branch_name }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>

</html>
