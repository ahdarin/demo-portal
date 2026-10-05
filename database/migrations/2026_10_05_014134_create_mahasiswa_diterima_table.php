<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penyimpanan permanen per-mahasiswa (upsert berdasarkan NIM), menggantikan
 * perilaku lama yang cuma dump "payload terakhir" ke file dan menimpanya
 * tiap kiriman. Tujuannya membuktikan ke tim SPMB: kirim ulang NIM yang
 * sama TIDAK membuat baris baru — cuma receive_count bertambah dan data
 * ter-update, baris tetap satu (unique di kolom nim).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mahasiswa_diterima', function (Blueprint $table) {
            $table->id();
            $table->string('nim')->unique();
            $table->string('no_pendaftaran')->nullable();
            $table->string('nama_lengkap')->nullable();
            $table->json('payload'); // objek mahasiswa[] lengkap, persis yang terakhir diterima
            $table->string('batch_id')->nullable();
            $table->unsignedInteger('receive_count')->default(0);
            $table->timestamp('first_received_at')->nullable();
            $table->timestamp('last_received_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mahasiswa_diterima');
    }
};
