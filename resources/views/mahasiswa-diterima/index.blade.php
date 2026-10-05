<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Mahasiswa Diterima — Demo Portal Akademik</title>
    <style>
        body { font-family: ui-monospace, Consolas, monospace; margin: 2rem; color: #1a1a1a; }
        h1 { font-size: 1.1rem; }
        p.hint { color: #555; font-size: 0.9rem; }
        table { border-collapse: collapse; width: 100%; margin-top: 1rem; }
        th, td { border: 1px solid #ccc; padding: 0.5rem 0.75rem; text-align: left; font-size: 0.9rem; }
        th { background: #f0f0f0; }
        tr:hover { background: #fafafa; }
        .count { font-weight: bold; }
        .count-1 { color: #555; }
        .count-more { color: #0a7a2f; }
        a { color: #1c5fae; }
        .empty { color: #777; margin-top: 1rem; }
    </style>
</head>
<body>
    <h1>Mahasiswa yang pernah diterima dari SPMB</h1>
    <p class="hint">
        Satu baris = satu NIM, walau dikirim berkali-kali (upsert, bukan insert).
        Kolom "Diterima Ke-" menunjukkan berapa kali NIM ini dikirim ulang — baris tidak akan bertambah, cuma kolom ini yang naik.
        <br>&larr; <a href="{{ route('tampilkan') }}">lihat dump mentah / riwayat batch</a>
    </p>

    @if($daftar->isEmpty())
        <p class="empty">Belum ada mahasiswa yang diterima.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>NIM</th>
                    <th>Nama Lengkap</th>
                    <th>No. Pendaftaran</th>
                    <th>Diterima Ke-</th>
                    <th>Pertama Diterima</th>
                    <th>Terakhir Diterima</th>
                    <th>Batch Terakhir</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($daftar as $m)
                <tr>
                    <td>{{ $m->nim }}</td>
                    <td>{{ $m->nama_lengkap ?? '-' }}</td>
                    <td>{{ $m->no_pendaftaran ?? '-' }}</td>
                    <td class="count {{ $m->receive_count > 1 ? 'count-more' : 'count-1' }}">{{ $m->receive_count }}x</td>
                    <td>{{ $m->first_received_at?->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $m->last_received_at?->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $m->batch_id }}</td>
                    <td><a href="{{ route('mahasiswa.show', $m->nim) }}">lihat payload</a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
