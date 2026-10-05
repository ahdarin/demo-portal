<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $mahasiswa->nim }} — Mahasiswa Diterima</title>
    <style>
        body { font-family: ui-monospace, Consolas, monospace; margin: 2rem; color: #1a1a1a; }
        h1 { font-size: 1.1rem; }
        p.meta { color: #555; font-size: 0.9rem; }
        pre { background: #f5f5f5; border: 1px solid #ddd; padding: 1rem; overflow-x: auto; white-space: pre-wrap; }
        a { color: #1c5fae; }
    </style>
</head>
<body>
    <p><a href="{{ route('mahasiswa.index') }}">&larr; kembali ke daftar semua mahasiswa</a></p>
    <h1>{{ $mahasiswa->nama_lengkap }} — NIM {{ $mahasiswa->nim }}</h1>
    <p class="meta">
        Diterima {{ $mahasiswa->receive_count }}x &middot;
        pertama {{ $mahasiswa->first_received_at?->format('Y-m-d H:i:s') }} &middot;
        terakhir {{ $mahasiswa->last_received_at?->format('Y-m-d H:i:s') }} &middot;
        batch terakhir {{ $mahasiswa->batch_id }}
    </p>
    <pre>{{ json_encode($mahasiswa->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
</body>
</html>
