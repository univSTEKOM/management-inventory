<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrinterStation extends Model
{
    protected $fillable = ['name', 'station_key', 'api_token', 'last_seen_at'];
    protected $hidden = ['api_token'];
    protected $casts = ['last_seen_at' => 'datetime'];

    public function isOnline(int $thresholdSeconds = 60): bool
    {
        // Threshold dinaikkan ke 60 detik agar konsisten dengan interval heartbeat
        // agent (30 detik). Threshold 15 detik sebelumnya menyebabkan station selalu
        // terlihat "offline" meski agent berjalan normal (30s > 15s threshold).
        return $this->last_seen_at !== null
            && $this->last_seen_at->diffInSeconds(now()) <= $thresholdSeconds;
    }
}
