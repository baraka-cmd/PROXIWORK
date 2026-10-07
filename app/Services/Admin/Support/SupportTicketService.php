<?php
declare(strict_types=1);
namespace App\Services\Admin\Support;
use App\Enums\{SupportTicketCategory,SupportTicketPriority,SupportTicketStatus};
use App\Models\{SupportTicket,TicketMessage,User};
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class SupportTicketService {
 public function __construct(private AuditLogService $audit){}
 public function create(User $user,array $data,Request $request):SupportTicket {
  return DB::transaction(function()use($user,$data,$request){$ticket=SupportTicket::create(['user_id'=>$user->id,'subject'=>$data['subject'],'category'=>SupportTicketCategory::from($data['category']),'priority'=>SupportTicketPriority::from($data['priority'] ?? SupportTicketPriority::NORMAL->value),'status'=>SupportTicketStatus::OPEN]); $message=$ticket->messages()->create(['sender_id'=>$user->id,'body'=>$data['body']]); $ticket->update(['last_message_at'=>$message->created_at]); $this->audit->record('support.ticket_created',$ticket,$user,[],$request); return $ticket->load(['user','messages.sender']);});
 }
 public function message(User $user,SupportTicket $ticket,string $body,Request $request):TicketMessage {
  return DB::transaction(function()use($user,$ticket,$body,$request){$ticket=SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id); if($ticket->status===SupportTicketStatus::CLOSED) throw ValidationException::withMessages(['status'=>'Un ticket fermé ne peut plus recevoir de message.']); $message=$ticket->messages()->create(['sender_id'=>$user->id,'body'=>$body]); $next=$user->hasPermissionTo('support.manage')?SupportTicketStatus::IN_PROGRESS:SupportTicketStatus::OPEN; if($ticket->status===SupportTicketStatus::WAITING && !$user->hasPermissionTo('support.manage')) $next=SupportTicketStatus::IN_PROGRESS; $ticket->update(['last_message_at'=>$message->created_at,'status'=>$next]); return $message->load('sender');});
 }
 public function transition(User $actor,SupportTicket $ticket,SupportTicketStatus $to,Request $request):SupportTicket {
  return DB::transaction(function()use($actor,$ticket,$to,$request){$ticket=SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id); $from=$ticket->status; $valid=match($from){SupportTicketStatus::OPEN=>in_array($to,[SupportTicketStatus::IN_PROGRESS,SupportTicketStatus::CLOSED],true),SupportTicketStatus::IN_PROGRESS=>in_array($to,[SupportTicketStatus::WAITING,SupportTicketStatus::RESOLVED],true),SupportTicketStatus::WAITING=>$to===SupportTicketStatus::IN_PROGRESS,SupportTicketStatus::RESOLVED=>$to===SupportTicketStatus::CLOSED,SupportTicketStatus::CLOSED=>false}; if(!$valid) throw ValidationException::withMessages(['status'=>'Transition de ticket invalide.']); $ticket->update(['status'=>$to,'resolved_at'=>$to===SupportTicketStatus::RESOLVED?now():$ticket->resolved_at,'closed_at'=>$to===SupportTicketStatus::CLOSED?now():$ticket->closed_at]); $this->audit->record('support.ticket_status_changed',$ticket,$actor,['from'=>$from->value,'to'=>$to->value],$request); return $ticket->fresh(['user','assignee','messages.sender']);});
 }
 public function assign(User $actor,SupportTicket $ticket,User $assignee,Request $request):SupportTicket { return DB::transaction(function()use($actor,$ticket,$assignee,$request){$ticket=SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id); if($ticket->status===SupportTicketStatus::CLOSED) throw ValidationException::withMessages(['status'=>'Un ticket fermé ne peut plus être assigné.']); $ticket->update(['assigned_to'=>$assignee->id]); $this->audit->record('support.ticket_assigned',$ticket,$actor,['assigned_to'=>$assignee->id],$request); return $ticket->fresh(['user','assignee']);});}
}
