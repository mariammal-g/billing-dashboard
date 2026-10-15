<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class StoreUsageRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array { return ['merchant_id'=>['required','integer','exists:merchants,id'],'customer_id'=>['required','integer','exists:customers,id'],'units'=>['required','integer','min:1'],'occurred_at'=>['required','date']]; }
}
