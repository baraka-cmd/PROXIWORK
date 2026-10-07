<?php
declare(strict_types=1);
namespace Tests\Feature\Web;
use App\Enums\ServiceStatus;
use App\Models\Category;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ProfessionalProfileServicesTest extends TestCase{
use RefreshDatabase;
public function test_professional_can_view_profile():void{$u=User::factory()->create();$u->assignRole('professional');$p=ProfessionalProfile::factory()->create(['user_id'=>$u->id,'professional_title'=>'Développeur Laravel']);$this->actingAs($u)->get(route('professional.profile'))->assertOk()->assertSee('Développeur Laravel')->assertViewHas('professional',fn($v)=>$v->is($p));}
public function test_client_cannot_access_professional_area():void{$u=User::factory()->create();$u->assignRole('client');$this->actingAs($u)->get(route('professional.profile'))->assertForbidden();$this->actingAs($u)->get(route('professional.services.index'))->assertForbidden();}
public function test_services_are_scoped_to_authenticated_professional():void{$a=User::factory()->create();$a->assignRole('professional');$ap=ProfessionalProfile::factory()->create(['user_id'=>$a->id]);$b=User::factory()->create();$b->assignRole('professional');$bp=ProfessionalProfile::factory()->create(['user_id'=>$b->id]);Service::factory()->create(['professional_profile_id'=>$ap->id,'title'=>'Service visible']);$foreign=Service::factory()->create(['professional_profile_id'=>$bp->id,'title'=>'Service secret']);$this->actingAs($a)->get(route('professional.services.index'))->assertOk()->assertSee('Service visible')->assertDontSee('Service secret');$this->actingAs($a)->get(route('professional.services.edit',$foreign))->assertForbidden();}
public function test_create_and_publish_respects_backend_rules():void{$u=User::factory()->create();$u->assignRole('professional');$p=ProfessionalProfile::factory()->create(['user_id'=>$u->id]);$c=Category::factory()->create(['status'=>'active']);$r=$this->actingAs($u)->post(route('professional.services.store'),['category_id'=>$c->id,'title'=>'Développement Laravel','short_description'=>'Application web','description'=>'Création et maintenance d’une application Laravel sécurisée.','pricing_type'=>'fixed','price'=>150,'currency'=>'USD']);$s=Service::where('professional_profile_id',$p->id)->firstOrFail();$r->assertRedirect(route('professional.services.edit',$s));$this->assertSame(ServiceStatus::DRAFT,$s->status);$this->actingAs($u)->post(route('professional.services.publish',$s))->assertSessionHasErrors('images');}
}