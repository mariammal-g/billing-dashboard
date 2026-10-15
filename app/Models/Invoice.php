<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Invoice extends Model { protected $guarded=[]; protected $casts=['period_start'=>'date','period_end'=>'date','subtotal_cents'=>'integer','total_cents'=>'integer']; public function items(){return $this->hasMany(InvoiceItem::class);} }
