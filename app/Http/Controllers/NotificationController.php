<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = DB::table('tb_notif')
            ->where('user_id', $request->user()->id)
            ->select('id', 'judul', 'pesan', 'is_read', 'created_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($notifications);
    }
}