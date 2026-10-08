<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Payment;

use App\Enums\PaymentProvider;
useuse App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminPaymentIndexRequest extends FormRequest
{
    public function authorize(): bool{return $this->user()?->hasPermissionTo('payments.view')??false;}
    public function rules():array{return[
        'search'=>['nullable','string','max:120'],'status'=>['nullable',Rule::enum(PaymentStatus::class)],
        'provider'=>['nullable',Rule::enum(PaymentProvider::class)],'from'=>['nullable','date'],
        'to'=>['nullable','date','after_or_equal:from'],'per_page'=>['nullable','integer','min:10','max:100'],
    ];}
}
