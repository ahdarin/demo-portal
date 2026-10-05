<?php

use App\Http\Controllers\SpmbReceiverController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SpmbReceiverController::class, 'tampilkan'])->name('tampilkan');
Route::post('/api/spmb/mahasiswa', [SpmbReceiverController::class, 'receive'])->name('spmb.receive');

Route::get('/mahasiswa', [SpmbReceiverController::class, 'daftarMahasiswa'])->name('mahasiswa.index');
Route::get('/mahasiswa/{nim}', [SpmbReceiverController::class, 'detailMahasiswa'])->name('mahasiswa.show');
