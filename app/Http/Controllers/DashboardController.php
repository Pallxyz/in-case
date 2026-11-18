<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Dasbor = halaman kegiatan yang sedang aktif.
     * Kalau belum ada kegiatan aktif, arahkan ke daftar kegiatan.
     */
    public function index(): RedirectResponse
    {
        $activity = Auth::user()->activeActivity;

        return $activity
            ? redirect()->route('activities.show', $activity)
            : redirect()->route('activities.index');
    }
}
