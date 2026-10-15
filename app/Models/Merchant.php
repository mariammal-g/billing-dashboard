<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Merchant extends Model { protected $guarded=[]; public function plans(){return $this->hasMany(Plan::class);} public function customers(){return $this->hasMany(Customer::class);} }
