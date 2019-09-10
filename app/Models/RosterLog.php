<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 *      ESPN Fantasy Football roster log item
 */
class RosterLog extends Model
{
    protected $connection = 'fantasyfootball';

    protected $table = 'rostersLog';

    protected $fillable = ['leagueId', 'teamId', 'hash', 'highestPlayerId', 'highestPlayerScore', 'lowestPlayerId', 'lowestPlayerScore', 'roster'];

    protected $primaryKey = 'primary';
    public $incrementing  = true;

    public $timestamps = false;

    public static function boot()
    {
        parent::boot();
    }
}
