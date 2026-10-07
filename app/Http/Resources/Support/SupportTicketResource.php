<?php
declare(strict_types=1);
namespace App\Http\Resources\Support;
use Illuminate\Http\Resources\Json\JsonResource;
class SupportTicketResource extends JsonResource {
 public function toArray($request):array{return ['id'=>$this->id,'subject'=>$this->subject,'category'=>$this->category,'priority'=>$this->priority->value,'status'=>$this->status->value,'user'=>$this->whenLoaded('user',fn()=>['id'=>$this->user->id,'name'=>$this->user->name]),'assignee'=>$this->whenLoaded('assignee',fn()=>['id'=>$this->assignee->id,'name'=>$this->assignee->name]),'last_message_at'=>$this->last_message_at?->toISOString(),'resolved_at'=>$this->resolved_at?->toISOString(),'closed_at'=>$this->closed_at?->toISOString(),'messages'=>$this->whenLoaded('messages',fn()=>TicketMessageResource::collection($this->messages)),'created_at'=>$this->created_at?->toISOString()];}
}
