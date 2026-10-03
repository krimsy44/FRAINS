<?php

namespace Tests\Feature;

use App\Models\Publication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicationVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_video_can_be_published_viewed_and_kept_when_editing(): void
    {
        $this->seed();
        Storage::fake('public');
        $this->actingAs(User::whereHas('role', fn ($q) => $q->where('name', 'ADMIN'))->firstOrFail());
        $data = ['title'=>'Notre recolte en video', 'category'=>'NEWS', 'content'=>'Une nouvelle recolte.', 'status'=>'PUBLISHED'];
        $this->post(route('admin.resources.store', 'publications'), $data + [
            'image'=>UploadedFile::fake()->create('recolte.mp4', 100, 'video/mp4'),
        ])->assertSessionHasNoErrors();
        $publication = Publication::where('title', $data['title'])->firstOrFail();
        $this->assertSame('video', $publication->media_type);
        Storage::disk('public')->assertExists($publication->image);
        foreach ([route('public.page', 'actualites'), route('public.publication', $publication)] as $url) {
            $this->get($url)->assertOk()->assertSee('<video controls playsinline', false)
                ->assertSee('storage/'.$publication->image, false);
        }
        $path = $publication->image;
        $this->put(route('admin.resources.update', ['publications', $publication->id]), $data)->assertSessionHasNoErrors();
        $this->assertSame($path, $publication->fresh()->image);
        $this->assertSame('video', $publication->fresh()->media_type);
        $this->put(route('admin.resources.update', ['publications', $publication->id]), $data + [
            'image'=>UploadedFile::fake()->image('photo.jpg'),
        ])->assertSessionHasNoErrors();
        $this->assertSame('image', $publication->fresh()->media_type);
    }

    public function test_invalid_or_oversized_uploads_are_rejected(): void
    {
        $this->seed();
        Storage::fake('public');
        $this->actingAs(User::whereHas('role', fn ($q) => $q->where('name', 'ADMIN'))->firstOrFail());
        foreach ([UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'), UploadedFile::fake()->create('large.mp4', 35841, 'video/mp4')] as $file) {
            $this->post(route('admin.resources.store', 'publications'), [
                'title'=>'Fichier invalide', 'category'=>'NEWS', 'content'=>'Description', 'status'=>'PUBLISHED', 'image'=>$file,
            ])->assertSessionHasErrors('image');
        }
        $this->assertFalse(Publication::where('title', 'Fichier invalide')->exists());
    }
}
