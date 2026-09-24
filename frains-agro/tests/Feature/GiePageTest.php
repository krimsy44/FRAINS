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
