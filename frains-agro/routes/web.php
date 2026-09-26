<?php

use App\Http\Controllers\Account\OrderController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Admin\AgricultureController;
use App\Http\Controllers\Admin\CatalogueController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OperationsController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ResourceController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PublicContentController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/api/v1/products', [ApiController::class, 'products'])->middleware('throttle:60,1')->name('api.products');
Route::get('/api/v1/orders', [ApiController::class, 'orders'])->middleware(['auth', 'role:CUSTOMER', 'throttle:60,1'])->name('api.orders');
Route::get('/api/v1/orders/{order}', [ApiController::class, 'order'])->middleware(['auth', 'role:CUSTOMER', 'throttle:60,1'])->name('api.orders.show');
Route::get('/informations/{page}', [PublicContentController::class, 'page'])->name('public.page');
Route::get('/actualites/{publication}', [PublicContentController::class, 'publication'])->name('public.publication');
Route::post('/contact', [PublicContentController::class, 'contact'])->middleware('throttle:5,1')->name('public.contact');
Route::get('/produits', [StorefrontController::class, 'products'])->name('products.index');
Route::get('/produits/{product:slug}', [StorefrontController::class, 'show'])->name('products.show');
Route::get('/demander-un-devis', [QuoteController::class, 'start'])->name('quotes.start');
Route::get('/panier', [CartController::class, 'index'])->name('cart.index');
Route::post('/panier/commander', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->block(10, 10)->name('guest.checkout');
Route::get('/commande/confirmation', [CheckoutController::class, 'confirmation'])->name('guest.confirmation');
Route::post('/panier/{product}', [CartController::class, 'store'])->name('cart.store');
Route::patch('/panier/{productId}', [CartController::class, 'update'])->name('cart.update');
Route::get('/connexion', [AuthController::class, 'create'])->name('login');
Route::get('/admin/connexion', [AuthController::class, 'adminCreate'])->middleware('guest:admin')->name('admin.login');
Route::post('/admin/connexion', [AuthController::class, 'adminStore'])->middleware(['guest:admin', 'throttle:10,1'])->name('admin.login.store');
Route::post('/admin/deconnexion', [AuthController::class, 'destroy'])->middleware('auth:admin')->name('admin.logout');
Route::post('/connexion', [AuthController::class, 'store'])->middleware(['guest', 'throttle:10,1'])->name('login.store');
Route::post('/deconnexion', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:CUSTOMER'])->group(function () {
    Route::get('/mon-espace/{section?}', [ProfileController::class, 'index'])->name('account.section');
    Route::put('/mon-espace/profil', [ProfileController::class, 'update'])->name('account.profile.update');
    Route::post('/mon-espace/adresses', [ProfileController::class, 'address'])->name('account.addresses.store');
    Route::delete('/mon-espace/adresses/{address}', [ProfileController::class, 'remove'])->name('account.addresses.destroy');
    Route::get('/commande', [CheckoutController::class, 'create'])->name('checkout.create');
    Route::post('/commande', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/mon-compte/commandes', [OrderController::class, 'index'])->name('account.orders.index');
    Route::get('/mon-compte/commandes/{order}', [OrderController::class, 'show'])->name('account.orders.show');
    Route::post('/mon-compte/commandes/{order}/annuler', [OrderController::class, 'cancel'])->name('account.orders.cancel');
});

Route::middleware(['auth:web', 'role:CUSTOMER'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/lues', [NotificationController::class, 'read'])->name('notifications.read');
});

Route::prefix('admin')->name('admin.')->middleware(['auth:admin', 'role:ADMIN,MANAGER,COMMERCIAL,STOCK_MANAGER,DELIVERY_MANAGER,PRODUCER,DRIVER'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/lues', [NotificationController::class, 'read'])->name('notifications.read');
    Route::middleware('role:ADMIN,MANAGER,COMMERCIAL')->group(function () {
        Route::get('/devis', [QuoteController::class, 'index'])->name('quotes.index');
        Route::get('/devis/{quote}', [QuoteController::class, 'show'])->name('quotes.show');
        Route::put('/devis/{quote}', [QuoteController::class, 'offer'])->name('quotes.offer');
        Route::get('/factures/commande/{order}', [InvoiceController::class, 'show'])->name('invoices.show');
    });
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/rapports', [ReportController::class, 'index'])->middleware('role:ADMIN,MANAGER,COMMERCIAL,STOCK_MANAGER,DELIVERY_MANAGER')->name('reports');
    Route::get('/catalogue/{product}', [CatalogueController::class, 'edit'])->middleware('role:ADMIN,MANAGER,COMMERCIAL')->name('catalogue.edit');
    Route::put('/catalogue/{product}', [CatalogueController::class, 'update'])->middleware('role:ADMIN,MANAGER,COMMERCIAL')->name('catalogue.update');
    Route::post('/catalogue/{product}/tarifs', [CatalogueController::class, 'price'])->middleware('role:ADMIN,MANAGER,COMMERCIAL')->name('catalogue.price');
    Route::delete('/catalogue/{product}/tarifs/{price}', [CatalogueController::class, 'disable'])->middleware('role:ADMIN,MANAGER,COMMERCIAL')->name('catalogue.disable');
    Route::resource('products', ProductController::class)->except('show')->middleware('role:ADMIN,MANAGER,COMMERCIAL');
    Route::resource('orders', App\Http\Controllers\Admin\OrderController::class)->only(['index', 'show', 'update'])->middleware('role:ADMIN,MANAGER,COMMERCIAL');
    Route::delete('/orders/{order}', [App\Http\Controllers\Admin\OrderController::class, 'destroy'])->middleware('role:ADMIN')->name('orders.destroy');
    Route::get('/gestion/{resource}', [ResourceController::class, 'index'])->name('resources.index');
    Route::get('/gestion/{resource}/nouveau', [ResourceController::class, 'create'])->name('resources.create');
    Route::post('/gestion/{resource}', [ResourceController::class, 'store'])->name('resources.store');
    Route::get('/gestion/{resource}/{record}/modifier', [ResourceController::class, 'edit'])->name('resources.edit');
    Route::put('/gestion/{resource}/{record}', [ResourceController::class, 'update'])->name('resources.update');
    Route::delete('/galerie/{record}', [ResourceController::class, 'removeGallery'])->name('gallery.destroy');
    Route::middleware('role:ADMIN,MANAGER,STOCK_MANAGER')->group(function () {
        Route::get('/stocks', [OperationsController::class, 'stocks'])->name('stocks.index');
        Route::post('/stocks', [OperationsController::class, 'movement'])->name('stocks.store');
    });
    Route::middleware('role:ADMIN,MANAGER,COMMERCIAL')->group(function () {
        Route::get('/finance/{section}', [OperationsController::class, 'finance'])->name('finance');
        Route::get('/clients', [OperationsController::class, 'clients'])->name('clients.index');
        Route::post('/clients', [OperationsController::class, 'storeClient'])->name('clients.store');
        Route::get('/clients/{customer}', [OperationsController::class, 'client'])->name('clients.show');
        Route::put('/clients/{customer}', [OperationsController::class, 'updateClient'])->name('clients.update');
        Route::post('/orders/{order}/paiements', [OperationsController::class, 'payment'])->name('payments.store');
        Route::put('/orders/{order}/echeance', [OperationsController::class, 'dueDate'])->name('orders.due');
    });
    Route::get('/livraisons', [OperationsController::class, 'deliveries'])->middleware('role:ADMIN,MANAGER,DELIVERY_MANAGER,DRIVER')->name('deliveries.index');
    Route::delete('/clients', [OperationsController::class, 'destroyClients'])->middleware('role:ADMIN,MANAGER')->name('clients.destroyMany');
    Route::delete('/clients/{customer}', [OperationsController::class, 'destroyClient'])->middleware('role:ADMIN,MANAGER')->name('clients.destroy');
    Route::post('/livraisons/{delivery}/affecter', [OperationsController::class, 'assign'])->middleware('role:ADMIN,MANAGER,DELIVERY_MANAGER')->name('deliveries.assign');
    Route::post('/livraisons/{delivery}/reception', [OperationsController::class, 'deliver'])->middleware('role:ADMIN,MANAGER,DELIVERY_MANAGER,DRIVER')->name('deliveries.receive');
    Route::post('/livraisons/{delivery}/depart', [OperationsController::class, 'dispatch'])->middleware('role:ADMIN,MANAGER,DELIVERY_MANAGER,DRIVER')->name('deliveries.dispatch');
    Route::get('/agriculture', [AgricultureController::class, 'index'])->middleware('role:ADMIN,MANAGER,PRODUCER')->name('agriculture.index');
    Route::post('/recoltes', [AgricultureController::class, 'harvest'])->middleware('role:ADMIN,MANAGER,PRODUCER')->name('harvests.store');
    Route::get('/recoltes/{harvest}/modifier', [AgricultureController::class, 'edit'])->middleware('role:ADMIN,MANAGER,PRODUCER')->name('harvests.edit');
    Route::put('/recoltes/{harvest}', [AgricultureController::class, 'update'])->middleware('role:ADMIN,MANAGER,PRODUCER')->name('harvests.update');
    Route::middleware('role:ADMIN')->group(function () {
        Route::get('/parametres', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/parametres', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('/utilisateurs', [SettingsController::class, 'users'])->name('users.index');
        Route::post('/utilisateurs', [SettingsController::class, 'storeUser'])->name('users.store');
        Route::put('/utilisateurs/{user}', [SettingsController::class, 'updateUser'])->name('users.update');
        Route::delete('/utilisateurs/{user}', [SettingsController::class, 'destroyUser'])->name('users.destroy');
    });
});

Route::middleware(['auth:web', 'role:CUSTOMER'])->group(function () {
    Route::get('/devis', [QuoteController::class, 'index'])->name('quotes.index');
    Route::get('/devis/nouveau', [QuoteController::class, 'create'])->middleware('role:CUSTOMER')->name('quotes.create');
    Route::post('/devis', [QuoteController::class, 'store'])->middleware('role:CUSTOMER')->name('quotes.store');
    Route::get('/devis/{quote}', [QuoteController::class, 'show'])->name('quotes.show');
    Route::post('/devis/{quote}/accepter', [QuoteController::class, 'accept'])->middleware('role:CUSTOMER')->name('quotes.accept');
    Route::get('/factures/commande/{order}', [InvoiceController::class, 'show'])->name('invoices.show');
});

