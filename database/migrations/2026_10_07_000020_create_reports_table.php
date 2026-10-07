<?php
declare(strict_types=1);
use App\Enums\{ReportPriority,ReportStatus};
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::create('reports',function(Blueprint $table):void{
  $table->id(); $table->foreignId('reporter_id')->constrained('users')->restrictOnDelete();
  $table->string('target_type',100); $table->unsignedBigInteger('target_id');
  $table->string('reason_code',64); $table->text('description')->nullable();
  $table->string('status',32)->default(ReportStatus::PENDING->value); $table->string('priority',16)->default(ReportPriority::NORMAL->value);
  $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
  $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
  $table->timestamp('resolved_at')->nullable(); $table->text('resolution_note')->nullable(); $table->timestamps();
  $table->index(['status','priority','created_at']); $table->index(['target_type','target_id']); $table->index(['assigned_to','status']);
 });}
 public function down():void{Schema::dropIfExists('reports');}
};
