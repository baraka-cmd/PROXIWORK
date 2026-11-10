<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunicationSegment extends Model
{
    protected $fillable = ['name', 'rules', 'created_by'];

    protected function casts(): array
    {
        return ['rules' => 'array'];
    }
}
