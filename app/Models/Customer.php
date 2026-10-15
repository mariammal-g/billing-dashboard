<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Customer extends Model { protected $guarded=[]; public function merchant(){return $this->belongsTo(Merchant::class);} public function subscriptions(){return $this->hasMany(Subscription::class);} }
