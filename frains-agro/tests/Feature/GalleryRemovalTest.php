<?php

namespace Tests\Feature;

use App\Models\GalleryImage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_staff_can_remove_a_photo_from_public_gallery(): void
    {
        $this->seed();
        foreach (['ADMIN', 'MANAGER', 'COMMERCIAL', 'STOCK_MANAGER'] as $role) {
            $user = User::create(['first_name' => 'Test', 'last_name' => $role, 'email' => strtolower($role).'@gallery.test',
                'password' => 'password123456', 'role_id' => Role::where('name', $role)->firstOrFail()->id, 'status' => 'active']);
            $photo = GalleryImage::create(['title' => 'Photo test '.$role, 'category' => 'FIELDS', 'image' => 'media/test.jpg', 'is_active' => true]);
            $this->get(route('public.page', 'galerie'))->assertOk()->assertSee($photo->title);
            $this->actingAs($user, 'admin');
            if ($role === 'STOCK_MANAGER') {
                $this->delete(route('admin.gallery.destroy', $photo->id))->assertForbidden();
                $this->assertDatabaseHas('gallery_images', ['id' => $photo->id]);
            } else {
                $this->get(route('admin.resources.index', 'gallery'))->assertOk()->assertSee(route('admin.gallery.destroy', $photo->id));
                $this->delete(route('admin.gallery.destroy', $photo->id))->assertRedirect(route('admin.resources.index', 'gallery'));
                $this->assertDatabaseMissing('gallery_images', ['id' => $photo->id]);
                $this->get(route('public.page', 'galerie'))->assertOk()->assertDontSee($photo->title);
            }
        }
    }
}
