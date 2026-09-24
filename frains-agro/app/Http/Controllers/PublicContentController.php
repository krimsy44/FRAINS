<?php

namespace App\Http\Controllers;

use App\Models\GalleryImage;
use App\Models\Publication;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicContentController extends Controller
{
    public function page(string $page)
    {
        abort_unless(in_array($page, ['a-propos', 'activites', 'vente-en-gros', 'actualites', 'galerie', 'contact']), 404);

        return view('public.page', ['page' => $page, 'settings' => Setting::values(), 'publications' => Publication::published()->when($page === 'activites', fn ($q) => $q->where('category', 'ACTIVITY'))->latest('published_at')->paginate(9), 'images' => GalleryImage::where('is_active', true)->latest()->paginate(12)]);
    }

    public function publication(Publication $publication)
    {
        abort_unless($publication->status === 'PUBLISHED' && $publication->published_at?->isPast(), 404);

        return view('public.publication', compact('publication'));
    }

    public function contact(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'email' => 'required|email|max:255', 'phone' => 'nullable|string|max:30', 'subject' => 'required|string|max:255', 'message' => 'required|string|max:5000']);
        DB::table('contact_messages')->insert($data + ['status' => 'NEW', 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Votre message a été enregistré. FRAINS pourra le consulter dans son espace de gestion.');
    }
}
