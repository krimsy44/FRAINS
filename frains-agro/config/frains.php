<?php

use App\Models\Category;
use App\Models\Cultivation;
use App\Models\DeliveryZone;
use App\Models\Driver;
use App\Models\GalleryImage;
use App\Models\Parcel;
use App\Models\Producer;
use App\Models\Publication;

return [
    'resources' => [
        'categories' => ['title' => 'Catégories', 'model' => Category::class, 'roles' => ['ADMIN', 'MANAGER', 'COMMERCIAL'], 'fields' => [
            'name' => ['Nom', 'text', 'required|string|max:120'],
            'description' => ['Description', 'textarea', 'nullable|string|max:5000'],
            'is_active' => ['Active', 'checkbox', 'boolean'],
        ]],
        'zones' => ['title' => 'Zones de livraison', 'model' => DeliveryZone::class, 'roles' => ['ADMIN', 'MANAGER', 'DELIVERY_MANAGER'], 'fields' => [
            'name' => ['Zone', 'text', 'required|string|max:120'],
            'description' => ['Description', 'textarea', 'nullable|string|max:1000'],
            'base_fee' => ['Frais de livraison (FCFA)', 'number', 'required|numeric|min:0|max:99999999|decimal:0,2'],
            'is_active' => ['Active', 'checkbox', 'boolean'],
        ]],
        'drivers' => ['title' => 'Livreurs', 'model' => Driver::class, 'roles' => ['ADMIN', 'MANAGER', 'DELIVERY_MANAGER'], 'fields' => [
            'name' => ['Nom', 'text', 'required|string|max:120'], 'phone' => ['Téléphone', 'text', 'required|string|max:30'],
            'user_id' => ['Compte livreur', 'select', 'nullable|exists:users,id', 'drivers'],
            'vehicle' => ['Véhicule / immatriculation', 'text', 'nullable|string|max:120'],
            'vehicle_type' => ['Type de véhicule', 'text', 'nullable|string|max:120'],
            'delivery_zone_id' => ['Zone', 'select', 'nullable|exists:delivery_zones,id', 'zones'],
            'is_available' => ['Disponible', 'checkbox', 'boolean'],
        ]],
        'producers' => ['title' => 'Producteurs', 'model' => Producer::class, 'roles' => ['ADMIN', 'MANAGER'], 'fields' => [
            'name' => ['Nom', 'text', 'required|string|max:120'], 'phone' => ['Téléphone', 'text', 'required|string|max:30'],
            'user_id' => ['Compte producteur', 'select', 'nullable|exists:users,id', 'producers'],
            'address' => ['Adresse', 'textarea', 'nullable|string|max:1000'],
            'zone' => ['Zone de production', 'text', 'required|string|max:120'],
            'joined_at' => ['Adhésion', 'date', 'required|date|before_or_equal:today'],
            'status' => ['Statut', 'select', 'required|in:active,inactive', ['active' => 'Actif', 'inactive' => 'Inactif']],
        ]],
        'parcels' => ['title' => 'Parcelles', 'model' => Parcel::class, 'roles' => ['ADMIN', 'MANAGER'], 'fields' => [
            'reference' => ['Référence', 'text', 'required|string|max:80'],
            'producer_id' => ['Producteur', 'select', 'required|exists:producers,id', 'producer_records'],
            'location' => ['Localisation', 'text', 'required|string|max:255'],
            'area' => ['Superficie (hectares)', 'number', 'required|numeric|min:0.01|max:999999|decimal:0,2'],
            'crop_type' => ['Type de culture', 'text', 'nullable|string|max:120'],
            'status' => ['Statut', 'select', 'required|in:ACTIVE,FALLOW,CLOSED', ['ACTIVE' => 'En exploitation', 'FALLOW' => 'Jachère', 'CLOSED' => 'Fermée']],
            'started_at' => ['Début d’exploitation', 'date', 'required|date'],
        ]],
        'cultivations' => ['title' => 'Cultures et productions', 'model' => Cultivation::class, 'roles' => ['ADMIN', 'MANAGER', 'PRODUCER'], 'fields' => [
            'parcel_id' => ['Parcelle', 'select', 'required|exists:parcels,id', 'parcels'],
            'product_id' => ['Culture', 'select', 'required|exists:products,id', 'products'],
            'area' => ['Superficie cultivée (ha)', 'number', 'required|numeric|min:0.01|max:999999|decimal:0,2'],
            'planted_at' => ['Plantation', 'date', 'required|date'],
            'expected_harvest_at' => ['Récolte prévue', 'date', 'required|date|after_or_equal:planted_at'],
            'expected_quantity' => ['Production estimée (unité du produit)', 'number', 'required|numeric|min:0|max:999999999|decimal:0,2'],
            'status' => ['Statut', 'select', 'required|in:GROWING,HARVESTING,CLOSED', ['GROWING' => 'En croissance', 'HARVESTING' => 'En récolte', 'CLOSED' => 'Terminée']],
            'notes' => ['Suivi agricole', 'textarea', 'nullable|string|max:5000'],
        ]],
        'publications' => ['title' => 'Publications et activités', 'model' => Publication::class, 'roles' => ['ADMIN', 'MANAGER', 'COMMERCIAL'], 'fields' => [
            'title' => ['Titre', 'text', 'required|string|max:255'],
            'category' => ['Catégorie', 'select', 'required|in:NEWS,ACTIVITY,HARVEST,EVENT,ANNOUNCEMENT,INFORMATION,ADVICE', ['NEWS' => 'Actualité', 'ACTIVITY' => 'Activité agricole', 'HARVEST' => 'Récolte', 'EVENT' => 'Événement', 'ANNOUNCEMENT' => 'Annonce', 'INFORMATION' => 'Information du GIE', 'ADVICE' => 'Conseil agricole']],
            'content' => ['Contenu', 'textarea', 'required|string|max:30000'],
            'image' => ['Image JPG, PNG ou WebP (4 Mo maximum)', 'file', 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096'],
            'status' => ['Statut', 'select', 'required|in:DRAFT,PUBLISHED', ['DRAFT' => 'Brouillon', 'PUBLISHED' => 'Publié']],
            'published_at' => ['Date de publication', 'datetime-local', 'nullable|date'],
        ]],
        'gallery' => ['title' => 'Galerie photos et videos', 'model' => GalleryImage::class, 'roles' => ['ADMIN', 'MANAGER', 'COMMERCIAL'], 'fields' => [
            'title' => ['Titre / texte alternatif', 'text', 'required|string|max:255'],
            'category' => ['Categorie', 'select', 'required|in:FIELDS,CROPS,HARVESTS,PRODUCERS,PRODUCTS,ACTIVITIES,EVENTS,DELIVERIES', ['FIELDS' => 'Champs', 'CROPS' => 'Cultures', 'HARVESTS' => 'Recoltes', 'PRODUCERS' => 'Producteurs', 'PRODUCTS' => 'Produits', 'ACTIVITIES' => 'Activites', 'EVENTS' => 'Evenements', 'DELIVERIES' => 'Livraisons']],
            'media_type' => ['Type de media', 'select', 'required|in:image,video', ['image' => 'Photo', 'video' => 'Video']],
            'image' => ['Fichier photo ou video (JPG, PNG, WebP, MP4, MOV, WebM - 50 Mo maximum)', 'file', 'nullable|file|mimes:jpg,jpeg,png,webp,mp4,mov,webm|max:51200'],
            'description' => ['Legende', 'textarea', 'nullable|string|max:2000'],
            'is_active' => ['Visible', 'checkbox', 'boolean'],
        ]],
    ],
    'settings' => [
        'facebook_url' => 'Lien de la page Facebook',
        'instagram_url' => 'Lien du compte Instagram',
        'snapchat_url' => 'Lien du compte Snapchat',
        'tiktok_url' => 'Lien du compte TikTok',
        'gie_name' => 'Nom du GIE', 'address' => 'Adresse', 'phone' => 'Téléphone', 'email' => 'E-mail', 'registration' => 'NINEA / immatriculation',
        'history' => 'Histoire', 'mission' => 'Mission', 'vision' => 'Vision', 'values' => 'Valeurs', 'objectives' => 'Objectifs', 'partners' => 'Partenaires',
        'production_zones' => 'Zones de production', 'testimonials' => 'Témoignages autorisés', 'bank_instructions' => 'Instructions de virement', 'mobile_instructions' => 'Instructions de paiement mobile',
    ],
];
