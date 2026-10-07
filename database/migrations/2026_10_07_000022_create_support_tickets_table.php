<?php
declare(strict_types=1);
use App\Enums\{SupportTicketPriority,SupportTicketStatus};
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::create('support_tickets',function(Blueprint $table):void{
  $table->id(); $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
  $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
  $table->string('subject',180); $table->string('category',64); $table->string('priority',16)->default(SupportTicketPriority::NORMAL->value);
  $table->string('status',32)->default(SupportTicketStatus::OPEN->value); $table->timestamp('last_message_at')->nullable();
  $table->timestamp('resolved_at')->nullable(); $table->timestamp('closed_at')->nullable(); $table->timestamps();
  $table->index(['status','priority','updated_at']); $table->index(['user_id','status']); $table->index(['assigned_to','status']);
 });}
 public function down():void{Schema::dropIfExists('support_tickets');}
};
