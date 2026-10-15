<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Plan extends Model { protected $guarded=[]; protected $casts=['base_price_cents'=>'integer','included_units'=>'integer','overage_rate_cents'=>'integer','active'=>'boolean']; public function merchant(){return $this->belongsTo(Merchant::class);} }
