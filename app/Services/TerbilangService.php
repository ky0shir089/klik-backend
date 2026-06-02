<?php

namespace App\Services;

class TerbilangService
{
    protected array $huruf = [
        "",
        "satu",
        "dua",
        "tiga",
        "empat",
        "lima",
        "enam",
        "tujuh",
        "delapan",
        "sembilan",
        "sepuluh",
        "sebelas"
    ];

    public function convert(int|float $angka): string
    {
        if ($angka < 0) {
            return 'minus ' . trim($this->terbilang(abs($angka)));
        }

        return trim($this->terbilang($angka));
    }

    public function rupiah(int|float $angka): string
    {
        return $this->convert($angka) . ' rupiah';
    }

    public function terbilang($angka): string
    {
        $angka = abs($angka);

        if ($angka < 12) {
            return ' ' . $this->huruf[$angka];
        } elseif ($angka < 20) {
            return $this->terbilang($angka - 10) . ' belas';
        } elseif ($angka < 100) {
            return $this->terbilang(intval($angka / 10)) . ' puluh' . $this->terbilang($angka % 10);
        } elseif ($angka < 200) {
            return ' seratus' . $this->terbilang($angka - 100);
        } elseif ($angka < 1000) {
            return $this->terbilang(intval($angka / 100)) . ' ratus' . $this->terbilang($angka % 100);
        } elseif ($angka < 2000) {
            return ' seribu' . $this->terbilang($angka - 1000);
        } elseif ($angka < 1000000) {
            return $this->terbilang(intval($angka / 1000)) . ' ribu' . $this->terbilang($angka % 1000);
        } elseif ($angka < 1000000000) {
            return $this->terbilang(intval($angka / 1000000)) . ' juta' . $this->terbilang($angka % 1000000);
        } elseif ($angka < 1000000000000) {
            return $this->terbilang(intval($angka / 1000000000)) . ' miliar' . $this->terbilang($angka % 1000000000);
        } else {
            return $this->terbilang(intval($angka / 1000000000000)) . ' triliun' . $this->terbilang($angka % 1000000000000);
        }
    }
}
