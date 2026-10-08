<?php
declare(strict_types=1);
namespace Tests\Feature\Professional;
use App\Enums\WithdrawalStatus;
use App\Models\ProfessionalProfile;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Wallet\WalletService;
use App\Services\Wallet\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class WebProfessionalWalletWithdrawalsTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void{parent::setUp();$this->seed(\Database\Seeders\RbacSeeder::class);}
 public function test_professional_can_view_wallet():void{$u=User::factory()->create();$u->assignRole('professional');$p=ProfessionalProfile::factory()->create(['user_id'=>$u->id]);app(WalletService::class)->getOrCreate($p,'USD');$this->actingAs($u)->get(route('professional.wallet'))->assertOk()->assertViewIs('professional.wallet.index')->assertSee('Disponible');}
 public function test_client_cannot_access_wallet():void{$u=User::factory()->create();$u->assignRole('client');$this->actingAs($u)->get(route('professional.wallet'))->assertForbidden();}
 public function test_professional_can_request_withdrawal_and_amount_is_locked():void{$u=User::factory()->create();$u->assignRole('professional');$p=ProfessionalProfile::factory()->create(['user_id'=>$u->id]);$w=app(WalletService::class)->getOrCreate($p,'USD');$w->forceFill(['available_balance'=>'100.00'])->save();$response=$this->actingAs($u)->post(route('professional.withdrawals.store'),['amount'=>'25.50','currency'=>'USD','provider'=>'mobile_money','destination'=>'0000000000','idempotency_key'=>'web-'.Str::random(32)]);$withdrawal=Withdrawal::query()->latest('id')->firstOrFail();$response->assertRedirect(route('professional.withdrawals.show',$withdrawal));$w->refresh();$this->assertSame('74.50',$w->available_balance);$this->assertSame('25.50',$w->locked_balance);$this->assertSame(WithdrawalStatus::REQUESTED,$withdrawal->status);}
 public function test_professional_cannot_view_another_professional_withdrawal():void{$a=User::factory()->create();$a->assignRole('professional');$b=User::factory()->create();$b->assignRole('professional');$p=ProfessionalProfile::factory()->create(['user_id'=>$b->id]);$w=app(WalletService::class)->getOrCreate($p,'USD');$withdrawal=Withdrawal::query()->forceCreate(['wallet_id'=>$w->id,'professional_id'=>$p->id,'provider'=>'mobile_money','destination'=>'0000000000','status'=>WithdrawalStatus::REQUESTED,'currency'=>'USD','amount'=>'10.00','idempotency_key'=>'test-'.Str::random(32),'requested_at'=>now()]);$this->actingAs($a)->get(route('professional.withdrawals.show',$withdrawal))->assertForbidden();}
 public function test_failed_withdrawal_returns_locked_amount():void{$u=User::factory()->create();$u->assignRole('professional');$p=ProfessionalProfile::factory()->create(['user_id'=>$u->id]);$w=app(WalletService::class)->getOrCreate($p,'USD');$w->forceFill(['available_balance'=>'90.00'])->save();$withdrawal=app(WithdrawalService::class)->request($p,'20.00','USD','mobile_money','0000000000','test-'.Str::random(32));app(WithdrawalService::class)->markFailed($withdrawal,'provider_failed','Test failure');$w->refresh();$this->assertSame('90.00',$w->available_balance);$this->assertSame('0.00',$w->locked_balance);}
 public function test_duplicate_idempotency_key_does_not_lock_balance_twice():void{$u=User::factory()->create();$u->assignRole('professional');$p=ProfessionalProfile::factory()->create(['user_id'=>$u->id]);$w=app(WalletService::class)->getOrCreate($p,'USD');$w->forceFill(['available_balance'=>'100.00'])->save();$key='web-'.Str::random(32);$service=app(WithdrawalService::class);$first=$service->request($p,'30.00','USD','mobile_money','0000000000',$key);$second=$service->request($p,'30.00','USD','mobile_money','0000000000',$key);$w->refresh();$this->assertSame($first->id,$second->id);$this->assertSame('70.00',$w->available_balance);$this->assertSame('30.00',$w->locked_balance);$this->assertSame(1,Withdrawal::query()->where('idempotency_key',$key)->count());}

}