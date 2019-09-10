<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 *      ESPN Fantasy Football transaction details
 */
class TransactionDetail extends Model
{
    protected $connection = 'fantasyfootball';

    protected $table = 'transactionDetails';

    protected $fillable = ['hash', 'leagueId', 'draftOverallSelection', 'playerId', 'fromTeamId', 'fromSlotCategoryId', 'toTeamId', 'rating', 'toSlotCategoryId', 'keeper', 'moveTypeId'];

    protected $primaryKey = 'primary';
    public $incrementing  = true;

    public $timestamps = false;

    public function player()
    {
        return $this->hasOne('\App\Models\EspnAllPlayers', 'playerId', 'playerId');
    }

    public static function boot()
    {
        parent::boot();
    }
}
