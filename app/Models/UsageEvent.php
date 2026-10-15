<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UsageEvent extends Model { protected $guarded=[]; protected $casts=['occurred_at'=>'datetime','metadata'=>'array','units'=>'integer']; public function segment(){return $this->belongsTo(SubscriptionSegment::class,'subscription_segment_id');} }
