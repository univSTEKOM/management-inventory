<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Bukti {{ $mutation->mutation_number }}</title>
<style>body{font-family:sans-serif;margin:2rem}td,th{border:1px solid #999;padding:.5rem}@media print{.no-print{display:none}}</style></head>
<body><button class="no-print" onclick="window.print()">Cetak</button><h1>Bukti Mutasi Barang</h1>
@include('documents.mutation-detail')
</body></html>
