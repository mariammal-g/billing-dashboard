<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Subscription extends Model { protected $guarded=[]; protected $casts=['current_period_start'=>'date','current_period_end'=>'date']; public function customer(){return $this->belongsTo(Customer::class);} public function plan(){return $this->belongsTo(Plan::class);} public function segments(){return $this->hasMany(SubscriptionSegment::class);} }
