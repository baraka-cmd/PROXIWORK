<?php
declare(strict_types=1);
namespace App\Http\Resources\Admin\Moderation;
use App\Http\Resources\Admin\Moderation\ModerationActionResource;
use Illuminate\Http\Resources\Json\JsonResource;
class ReportResource extends JsonResource {
 public function toArray($request):array{return [
 'id'=>$this->id,
 'reporter'=>$this->whenLoaded('reporter',fn()=>['id'=>$this->reporter->id,'name'=>$this->reporter->name]),
 'target_type'=>$this->target_type,'target_id'=>$this->target_id,'reason_code'=>$this->reason_code,'description'=>$this->description,
 'status'=>$this->status->value,'priority'=>$this->priority->value,
 'assignee'=>$this->whenLoaded('assignee',fn()=>['id'=>$this->assignee->id,'name'=>$this->assignee->name]),
 'resolution_note'=>$this->resolution_note,'resolved_at'=>$this->resolved_at?->toISOString(),'created_at'=>$this->created_at?->toISOString(),
 'moderation_actions'=>$this->whenLoaded('moderationActions',fn()=>ModerationActionResource::collection($this->moderationActions))
 ];}}
