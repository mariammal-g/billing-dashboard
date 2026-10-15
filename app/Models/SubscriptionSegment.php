<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SubscriptionSegment extends Model { protected $guarded=[]; protected $casts=['starts_on'=>'date','ends_on'=>'date','base_price_cents'=>'integer','included_units'=>'integer','overage_rate_cents'=>'integer']; public function subscription(){return $this->belongsTo(Subscription::class);} public function plan(){return $this->belongsTo(Plan::class);} public function usageEvents(){return $this->hasMany(UsageEvent::class);} }
