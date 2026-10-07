<?php
declare(strict_types=1);
namespace App\Http\Requests\Support;
use Illuminate\Foundation\Http\FormRequest;
class StoreSupportTicketRequest extends FormRequest { public function authorize():bool{return true;} public function rules():array{return ['subject'=>'required|string|max:180','category'=>'required|string|max:64','priority'=>'nullable|in:low,normal,high,urgent','body'=>'required|string|max:10000'];} }
