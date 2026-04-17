<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BoxZone extends Model
{
    use HasFactory;

    protected $fillable = ['name','zone_id','client'];

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function generateCode()
    {
        $district = $this->zone->district->id;
        $zone = $this->zone->id;
        $box = $this->id;
        $client = $this->client->id;

        return "D{$district}Z{$zone}C{$box}:{$client}";
    }

}
