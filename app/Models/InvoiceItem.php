<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InvoiceItem extends Model { protected $guarded=[]; protected $casts=['quantity'=>'integer','unit_price_cents'=>'integer','amount_cents'=>'integer']; public function invoice(){return $this->belongsTo(Invoice::class);} }
