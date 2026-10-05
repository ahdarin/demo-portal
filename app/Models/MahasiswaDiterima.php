<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MahasiswaDiterima extends Model
{
    protected $table = 'mahasiswa_diterima';

    protected $guarded = [];

    protected $casts = [
        'payload'            => 'array',
        'first_received_at'  => 'datetime',
        'last_received_at'   => 'datetime',
    ];
}
