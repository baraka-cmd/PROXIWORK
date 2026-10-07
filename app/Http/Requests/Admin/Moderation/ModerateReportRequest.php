<?php
declare(strict_types=1);
namespace App\Http\Requests\Admin\Moderation;
use Illuminate\Foundation\Http\FormRequest;
class ModerateReportRequest extends FormRequest { public function authorize():bool{return true;} public function rules():array{return ['action_type'=>'required|in:warning,hide_review,unpublish_service,suspend_user,suspend_professional','reason_code'=>'nullable|string|max:64','note'=>'nullable|string|max:5000'];} }
