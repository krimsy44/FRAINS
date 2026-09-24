<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        return view('notifications', ['notifications' => auth()->user()->notifications()->latest()->paginate(30)]);
    }

    public function read(Request $request)
    {
        auth()->user()->unreadNotifications->markAsRead();

        return back();
    }
}
