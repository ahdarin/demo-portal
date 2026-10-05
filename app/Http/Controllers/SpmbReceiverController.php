<?php

namespace App\Http\Controllers;

use App\Models\MahasiswaDiterima;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Demo penerima data mahasiswa dari SPMB Adzkia — cuma untuk memastikan
 * format payload/header/signature sampai dengan benar, BUKAN implementasi
 * Portal Akademik yang sebenarnya.
 *
 * Tiap mahasiswa di-upsert ke tabel mahasiswa_diterima berdasarkan NIM
 * (bukan sekadar menimpa file "payload terakhir" seperti versi awal) —
 * supaya bisa dibuktikan ke tim SPMB: kirim ulang NIM yang sama TIDAK
 * membuat baris baru, cuma receive_count bertambah & data ter-update.
 */
class SpmbReceiverController extends Controller
{
    public function receive(Request $request)
    {
        $expectedKey = config('services.spmb.api_key');
        $secret = config('services.spmb.hmac_secret');

        $authHeader = (string) $request->header('Authorization', '');
        $token = str_starts_with($authHeader, 'Bearer ') ? substr($authHeader, 7) : null;

        if (empty($expectedKey) || $token !== $expectedKey) {
            return response()->json(['message' => 'Unauthorized — API key tidak cocok'], 401);
        }

        $rawBody = $request->getContent();
        $signature = (string) $request->header('X-Signature', '');
        $expectedSignature = hash_hmac('sha256', $rawBody, (string) $secret);

        if (empty($secret) || !hash_equals($expectedSignature, $signature)) {
            return response()->json(['message' => 'Unauthorized — signature tidak valid'], 401);
        }

        $payload = json_decode($rawBody, true);

        if (!is_array($payload)) {
            return response()->json(['message' => 'Body bukan JSON yang valid'], 422);
        }

        Storage::disk('local')->put(
            'portal-demo/latest.json',
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $mahasiswaList = $payload['mahasiswa'] ?? [];
        $diterima = 0;

        foreach ($mahasiswaList as $m) {
            if (empty($m['nim'])) {
                continue; // tidak bisa di-upsert tanpa NIM, lewati
            }

            $row = MahasiswaDiterima::firstOrNew(['nim' => $m['nim']]);
            $baru = !$row->exists;

            $row->no_pendaftaran = $m['no_pendaftaran'] ?? null;
            $row->nama_lengkap = $m['identitas']['nama_lengkap'] ?? null;
            $row->payload = $m;
            $row->batch_id = $payload['batch_id'] ?? null;
            $row->last_received_at = now();
            if ($baru) {
                $row->first_received_at = now();
            }
            $row->receive_count = ($row->receive_count ?? 0) + 1;
            $row->save();

            $diterima++;
        }

        $baris = now()->toDateTimeString()." — batch {$payload['batch_id']} — {$diterima} mahasiswa diterima\n";
        Storage::disk('local')->append('portal-demo/riwayat.log', $baris);

        return response()->json([
            'message' => 'OK',
            'diterima' => $diterima,
        ]);
    }

    public function tampilkan()
    {
        if (!Storage::disk('local')->exists('portal-demo/latest.json')) {
            return response("Belum ada data yang diterima dari SPMB.\n", 200)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $data = Storage::disk('local')->get('portal-demo/latest.json');
        $riwayat = Storage::disk('local')->exists('portal-demo/riwayat.log')
            ? Storage::disk('local')->get('portal-demo/riwayat.log')
            : '(belum ada riwayat)';

        $output = "=== RIWAYAT PENERIMAAN (batch) ===\n{$riwayat}\n=== DATA TERAKHIR DITERIMA (mentah, persis yang dikirim SPMB) ===\n{$data}\n\nLihat seluruh mahasiswa yang tersimpan (bukti anti-dobel): /mahasiswa\n";

        return response($output, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /** Daftar semua mahasiswa yang pernah diterima — satu baris per NIM, walau dikirim berkali-kali. */
    public function daftarMahasiswa()
    {
        $daftar = MahasiswaDiterima::orderByDesc('last_received_at')->get();

        return view('mahasiswa-diterima.index', compact('daftar'));
    }

    /** Detail payload lengkap terakhir yang tersimpan untuk satu NIM. */
    public function detailMahasiswa(string $nim)
    {
        $mahasiswa = MahasiswaDiterima::where('nim', $nim)->firstOrFail();

        return view('mahasiswa-diterima.show', compact('mahasiswa'));
    }
}
