<?php
namespace Tests\Feature;

use App\Models\{User, Region, LoginPopupSetting};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SbgV23MasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_actual_login_uses_only_own_enabled_popup_and_consumes_the_session_flag_once(): void
    {
        $own=User::factory()->create(['role'=>'admin']);
        $other=User::factory()->create(['role'=>'admin']);
        LoginPopupSetting::create(['user_id'=>$own->id,'enabled'=>true,'title'=>'OWN-SYNTHETIC-LOGIN','message'=>'<script>window.untrusted=1</script>']);
        LoginPopupSetting::create(['user_id'=>$other->id,'enabled'=>true,'title'=>'OTHER-SYNTHETIC-LOGIN','message'=>'Private']);
        $this->post('/login',['email'=>$own->email,'password'=>'password'])->assertRedirect(route('dashboard'))->assertSessionHas('show_login_popup',true);
        $this->get('/dashboard')->assertOk()->assertViewHas('showLoginPopup',true)->assertSee('OWN-SYNTHETIC-LOGIN')->assertDontSee('OTHER-SYNTHETIC-LOGIN')->assertSee('&lt;script&gt;window.untrusted=1&lt;/script&gt;',false);
        $this->get('/dashboard')->assertViewHas('showLoginPopup',false)->assertDontSee('OWN-SYNTHETIC-LOGIN');
    }

    public function test_actual_login_with_disabled_popup_never_displays_configured_content(): void
    {
        $own=User::factory()->create(['role'=>'admin']);
        LoginPopupSetting::create(['user_id'=>$own->id,'enabled'=>false,'title'=>'DISABLED-SYNTHETIC-LOGIN','message'=>'Not shown']);
        $this->post('/login',['email'=>$own->email,'password'=>'password'])->assertRedirect(route('dashboard'));
        $this->get('/dashboard')->assertViewHas('showLoginPopup',false)->assertDontSee('DISABLED-SYNTHETIC-LOGIN');
    }

    public function test_valid_version_updates_only_the_selected_account_and_does_not_leak_passwords(): void
    {
        $super=User::factory()->create(['role'=>'super_admin']);
        $target=User::factory()->create(['role'=>'admin']);
        $setting=LoginPopupSetting::create(['user_id'=>$target->id,'enabled'=>true,'title'=>'Before','message'=>'Original']);
        $version=\App\Support\PopupVersion::token($setting);
        $this->actingAs($super)->postJson(route('dashboard.login-popup.update',$target),['enabled'=>false,'title'=>'After','message'=>'Edited','_version'=>$version])->assertOk()->assertJsonPath('success',true)->assertDontSee($target->password,false);
        $this->assertFalse($setting->fresh()->enabled);
        $this->assertSame('After',$setting->fresh()->title);
        $this->assertDatabaseCount('login_popup_settings',1);
        $this->postJson(route('dashboard.login-popup.update',$target),['enabled'=>true,'title'=>'Stale','message'=>'Unsaved','_version'=>$version])->assertStatus(409)->assertJsonPath('current.title','After');
        $this->assertSame('After',$setting->fresh()->title);
        $this->post(route('dashboard.login-popup.toggle',$target),['enabled'=>1])->assertRedirect();
        $this->assertTrue($setting->fresh()->enabled);
        $this->post(route('dashboard.login-popup.toggle',$target),['enabled'=>0])->assertRedirect();
        $this->assertFalse($setting->fresh()->enabled);
    }

    public function test_popup_row_toggle_reports_json_validation_without_optimistically_enabling_empty_content(): void
    {
        $super=User::factory()->create(['role'=>'super_admin']);
        $target=User::factory()->create(['role'=>'admin']);
        $this->actingAs($super)->postJson(route('dashboard.login-popup.toggle',$target),['enabled'=>true])->assertUnprocessable()->assertJsonValidationErrors('enabled');
        $this->assertDatabaseMissing('login_popup_settings',['user_id'=>$target->id]);
    }

    public function test_explicit_popup_disabled_and_v23_lengths_are_supported_without_changing_legacy_requests(): void
    {
        $super = User::factory()->create(['role'=>'super_admin']);
        $target = User::factory()->create();
        $this->actingAs($super)->postJson(route('dashboard.login-popup.update',$target), ['enabled'=>false,'title'=>'','message'=>''])->assertSuccessful();
        $this->assertFalse(LoginPopupSetting::where('user_id',$target->id)->firstOrFail()->enabled);
        $this->postJson(route('dashboard.login-popup.update',$target), ['enabled'=>true,'title'=>str_repeat('x',101),'message'=>'Halo'])->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->postJson(route('dashboard.login-popup.update',$target), ['enabled'=>true,'title'=>'Halo','message'=>str_repeat('x',501)])->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->post(route('dashboard.login-popup.update',$target), ['title'=>str_repeat('x',120)])->assertRedirect();
        $this->assertTrue(LoginPopupSetting::where('user_id',$target->id)->firstOrFail()->enabled);
    }

    public function test_stale_popup_draft_cannot_overwrite_newer_config(): void
    {
        $super = User::factory()->create(['role'=>'super_admin']);
        $setting = LoginPopupSetting::create(['user_id'=>$super->id,'enabled'=>true,'title'=>'Current','message'=>'Server']);
        $this->actingAs($super)->postJson(route('dashboard.login-popup.update',$super), ['enabled'=>true,'title'=>'Stale','message'=>'Draft','_version'=>'stale'])->assertStatus(409);
        $this->assertSame('Current',$setting->fresh()->title);
    }

    public function test_region_edit_validation_is_atomic_and_password_is_write_only(): void
    {
        $super=User::factory()->create(['role'=>'super_admin']);
        $region=Region::create(['code'=>'SYN','name'=>'Synthetic']);
        $target=User::factory()->create(['role'=>'admin','region'=>$region->name]);
        $password=$target->password;
        $payload=['account_id'=>$target->id,'account_role'=>'kepala_dapur','code'=>'SYN2','name'=>'Synthetic New','admin_name'=>'Changed','admin_email'=>'not-an-email','password'=>''];
        $this->actingAs($super)->putJson('/admin/region/'.$region->id,$payload)->assertUnprocessable()->assertJsonValidationErrors('admin_email');
        $this->assertSame('Synthetic',$region->fresh()->name);
        $payload['admin_email']='changed@example.test';
        $this->putJson('/admin/region/'.$region->id,$payload)->assertSuccessful();
        $this->assertSame($password,$target->fresh()->password);
        $this->assertSame('kepala_dapur',$target->fresh()->role);
        $this->get('/dashboard')->assertOk()->assertDontSee($password,false)->assertSee('sbg-v23-master')->assertSee('Belum Ada Akun');
    }

    public function test_region_target_must_belong_to_selected_region_before_any_write(): void
    {
        $super=User::factory()->create(['role'=>'super_admin']);
        $region=Region::create(['code'=>'ONE','name'=>'One']);
        $other=User::factory()->create(['role'=>'admin','region'=>'Other']);
        $this->actingAs($super)->putJson('/admin/region/'.$region->id,['account_id'=>$other->id,'account_role'=>'admin','code'=>'CHANGED','name'=>'Changed','admin_name'=>'Wrong','admin_email'=>'wrong@example.test'])->assertNotFound();
        $this->assertSame('One',$region->fresh()->name);
    }

    public function test_permissions_crud_scoped_login_and_escaped_popup_remain_intact(): void
    {
        $super=User::factory()->create(['role'=>'super_admin']);
        $region=Region::create(['code'=>'SYN','name'=>'Synthetic']);
        $head=User::factory()->create(['role'=>'kepala_dapur','region'=>$region->name]);
        $account=User::factory()->create(['role'=>'admin','region'=>$region->name]);
        $other=User::factory()->create(['role'=>'admin','region'=>'Other']);
        $this->actingAs($head)->putJson('/admin/region/'.$region->id,[])->assertForbidden();
        $this->putJson(route('admin.account.update',$other),['admin_name'=>'No','admin_email'=>'no@example.test'])->assertForbidden();
        $this->putJson(route('admin.account.update',$account),['admin_name'=>'Allowed','admin_email'=>'allowed@example.test','account_role'=>'super_admin'])->assertSuccessful();
        $this->assertSame('admin',$account->fresh()->role);
        $this->delete(route('admin.account.destroy',$head))->assertForbidden();
        $this->actingAs($super)->post(route('dashboard.admin-region.store'),['create_new_region'=>1,'region_name'=>'New Synthetic','region_code'=>'NEW','account_role'=>'admin','admin_name'=>'New','email'=>'new@example.test','password'=>'synthetic-only'])->assertRedirect();
        $this->assertDatabaseHas('regions',['name'=>'New Synthetic']);
        $this->delete(route('admin.account.destroy',$account))->assertRedirect();
        $this->assertSoftDeleted($account);
        LoginPopupSetting::create(['user_id'=>$head->id,'enabled'=>true,'title'=>'<script>window.evil=1</script>','message'=>'<img src=x onerror=alert(1)>']);
        $this->actingAs($head)->withSession(['show_login_popup'=>true])->get('/dashboard')->assertSee('&lt;script&gt;',false)->assertDontSee('<script>window.evil=1</script>',false);
        $this->actingAs($other)->withSession(['show_login_popup'=>true])->get('/dashboard')->assertDontSee('window.evil=1',false);
        $this->actingAs($super)->delete(route('admin.region.destroy',$region))->assertRedirect();
        $this->assertDatabaseMissing('regions',['id'=>$region->id]);
    }
}
