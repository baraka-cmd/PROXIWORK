<?php
declare(strict_types=1);
namespace App\Http\Requests\Admin\Moderation;
use Illuminate\Foundation\Http\FormRequest;
class StoreReportRequest extends FormRequest { public function authorize():bool{return true;} public function rules():array{return ['target_type'=>'required|in:profile,professional_profile,service,message,review','target_id'=>'required|integer|min:1','reason_code'=>'required|string|max:64','description'=>'nullable|string|max:5000'];} }
