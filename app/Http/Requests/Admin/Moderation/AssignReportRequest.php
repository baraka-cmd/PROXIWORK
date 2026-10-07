<?php
declare(strict_types=1);
namespace App\Http\Requests\Admin\Moderation;
use Illuminate\Foundation\Http\FormRequest;
class AssignReportRequest extends FormRequest { public function authorize():bool{return true;} public function rules():array{return ['assigned_to'=>'required|integer|exists:users,id'];} }
