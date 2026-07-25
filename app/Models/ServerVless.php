<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerVless extends Model
{
    protected $table = 'v2_server_vless';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'group_id' => 'array',
        'route_id' => 'array',
        'tlsSettings' => 'array',
        'networkSettings' => 'array',
        'realitySettings' => 'array',
        'ruleSettings' => 'array',
        'dnsSettings' => 'array',
        'tags' => 'array'
    ];
}
