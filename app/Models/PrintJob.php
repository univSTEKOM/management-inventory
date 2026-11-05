<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintJob extends Model
{
    protected $fillable = [
        'printer_station', 'inventory_number', 'location_label',
        'status', 'attempts', 'error_message', 'requested_by', 'printed_at',
    ];
    protected $casts = ['printed_at' => 'datetime'];

    public function requestedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'requested_by');
    }

    public function asset()
    {
        return $this->belongsTo(\App\Models\Asset::class, 'inventory_number', 'inventory_number')->withTrashed();
    }

    public function station()
    {
        return $this->belongsTo(\App\Models\PrinterStation::class, 'printer_station', 'station_key');
    }
}
