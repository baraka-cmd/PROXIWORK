<?php
declare(strict_types=1);
namespace App\Models;
use App\Enums\{SupportTicketPriority,SupportTicketStatus};
use App\Enums\SupportTicketCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
class SupportTicket extends Model {
 use HasFactory;
 protected $fillable=['user_id','assigned_to','subject','category','priority','status','last_message_at','resolved_at','closed_at'];
 protected function casts():array{return ['priority'=>SupportTicketPriority::class,'status'=>SupportTicketStatus::class,'category'=>SupportTicketCategory::class,'last_message_at'=>'datetime','resolved_at'=>'datetime','closed_at'=>'datetime'];}
 public function user():BelongsTo{return $this->belongsTo(User::class);}
 public function assignee():BelongsTo{return $this->belongsTo(User::class,'assigned_to');}
 public function messages():HasMany{return $this->hasMany(TicketMessage::class,'ticket_id');}
}
