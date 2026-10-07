<?php
declare(strict_types=1);
namespace App\Http\Requests\Admin\Moderation;
use Illuminate\Foundation\Http\FormRequest;
class StartReportReviewRequest extends FormRequest { public function authorize():bool{return true;} public function rules():array{return [];} }
