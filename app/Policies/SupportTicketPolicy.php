<?php
declare(strict_types=1);
namespace App\Policies;
use App\Models\{SupportTicket,User};
class SupportTicketPolicy {
 public function view(User $user,SupportTicket $ticket):bool{return $ticket->user_id===$user->id || $user->hasPermissionTo('support.manage');}
 public function create(User $user):bool{return $user->isActive();}
 public function update(User $user,SupportTicket $ticket):bool{return $user->hasPermissionTo('support.manage');}
 public function message(User $user,SupportTicket $ticket):bool{return $ticket->user_id===$user->id || $user->hasPermissionTo('support.manage');}
}
