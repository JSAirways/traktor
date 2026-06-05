<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceChildVisibility extends Model
{
    protected $table = 'device_child_visibility';

    protected $fillable = [
        'device_registration_id',
        'child_user_id',
        'is_visible',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
    ];

    public function deviceRegistration()
    {
        return $this->belongsTo(DeviceRegistration::class);
    }

    public function child()
    {
        return $this->belongsTo(User::class, 'child_user_id');
    }
}


