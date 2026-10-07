<?php
declare(strict_types=1);
namespace App\Http\Resources\Audit;
use Illuminate\Http\Resources\Json\JsonResource;
class AuditLogResource extends JsonResource {
 public function toArray($request):array{return [
 'id'=>$this->id,
 'actor'=>$this->whenLoaded('user',fn()=>['id'=>$this->user->id,'name'=>$this->user->name,'email'=>$this->user->email]),
 'actor_id'=>$this->user_id,'action'=>$this->action,'resource_type'=>$this->subject_type,'resource_id'=>$this->subject_id,
 'ip_address'=>$this->ip_address,'user_agent'=>$this->user_agent,'metadata'=>$this->metadata??[],'created_at'=>$this->created_at?->toISOString()
 ];}
}
