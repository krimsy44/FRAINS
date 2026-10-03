<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GiePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_remove_photos_and_logo_without_removing_gie_text(): void
    {
        Storage::fake('public');
        $this->seed();
        $this->actingAs(User::where('email', 'admin@frains-agro.sn')->firstOrFail());
        $this->put(route('admin.settings.update'), [
            'history' => 'Histoire à conserver', 'objectives' => 'Objectif à conserver',
            'logo' => UploadedFile::fake()->image('logo.png'),
            'history_image' => UploadedFile::fake()->image('histoire.jpg'),
            'objectives_image' => UploadedFile::fake()->image('objectif.jpg'),
        ])->assertSessionHasNoErrors();
        $before = Setting::values();
        $this->get(route('admin.settings.index'))->assertOk()->assertSee('Retirer cette photo')->assertSee('Retirer le logo actuel');
        $this->put(route('admin.settings.update'), ['remove_history_image' => 1])->assertSessionHasNoErrors();
        $after = Setting::values();
        $this->assertNull($after['history_image_path']);
        $this->assertSame($before['objectives_image_path'], $after['objectives_image_path']);
        $this->assertSame('Histoire à conserver', $after['history']);
        $this->get(route('public.page', 'a-propos'))->assertOk()->assertDontSee('storage/'.$before['history_image_path'])->assertSee('Histoire à conserver');
        $this->put(route('admin.settings.update'), ['remove_objectives_image' => 1, 'remove_logo' => 1])->assertSessionHasNoErrors();
        $this->assertNull(Setting::values()['objectives_image_path']);
        $this->assertNull(Setting::values()['logo_path']);
        $this->assertSame('Objectif à conserver', Setting::values()['objectives']);
        $this->get(route('home'))->assertOk()->assertDontSee('storage/'.$before['logo_path']);
    }

    public function test_upload_and_removal_cannot_be_requested_together(): void
    {
        Storage::fake('public');
        $this->seed();
        Setting::updateOrCreate(['key'=>'history_image_path'], ['value'=>'media/original.jpg']);
        $this->actingAs(User::where('email', 'admin@frains-agro.sn')->firstOrFail());
        $this->put(route('admin.settings.update'), ['remove_history_image'=>1, 'history_image'=>UploadedFile::fake()->image('replacement.jpg')])->assertSessionHasErrors('history_image');
        $this->assertSame('media/original.jpg', Setting::values()['history_image_path']);
    }

    public function test_gie_content_and_real_photos_can_be_published_from_admin(): void
    {
        Storage::fake('public');
        $this->seed();
        $this->get(route('public.page', 'a-propos'))->assertOk()->assertSee('Notre objectif')->assertSee('Notre historique')->assertSee('L’historique officiel');
        $this->actingAs(User::where('email', 'admin@frains-agro.sn')->firstOrFail());
        $this->put(route('admin.settings.update'), [
            'objectives' => 'Objectif officiel de test', 'history' => 'Historique officiel de test',
            'objectives_image' => UploadedFile::fake()->image('activites.jpg'),
            'history_image' => UploadedFile::fake()->image('equipe.png'),
            'objectives_image_caption' => 'Nos activités', 'history_image_caption' => 'Notre équipe',
        ])->assertSessionHasNoErrors();
        $settings = Setting::values();
        foreach (['objectives', 'history'] as $section) Storage::disk('public')->assertExists($settings[$section.'_image_path']);
        $this->get(route('public.page', 'a-propos'))->assertOk()->assertSee('Objectif officiel de test')->assertSee('Historique officiel de test')->assertSee('Notre équipe')->assertSee('storage/'.$settings['history_image_path'])->assertDontSee('L’historique officiel');
        $this->put(route('admin.settings.update'), ['history' => 'Texte actualisé'])->assertSessionHasNoErrors();
        $this->assertSame($settings['history_image_path'], Setting::values()['history_image_path']);
        $this->put(route('admin.settings.update'), ['history_image' => UploadedFile::fake()->create('document.pdf', 10)])->assertSessionHasErrors('history_image');
    }
}
