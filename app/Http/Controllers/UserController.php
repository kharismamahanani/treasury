<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! auth()->user()->isAdmin()) {
                return response()->json(['message' => 'Akses ditolak.'], 403);
            }
            return $next($request);
        });
    }

    public function index()
    {
        return response()->json(User::orderBy('name')->get(['id', 'name', 'username', 'email', 'role', 'is_active', 'last_login_at', 'created_at']));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:100',
            'username' => 'required|string|max:50|unique:users',
            'email'    => 'nullable|email|unique:users',
            'password' => 'required|string|min:8',
            'role'     => 'required|in:admin,editor,viewer',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $user = User::create($validated);

        return response()->json(['success' => true, 'user' => $user->only(['id', 'name', 'username', 'role'])]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'nullable|email|unique:users,email,' . $user->id,
            'role'     => 'required|in:admin,editor,viewer',
            'is_active' => 'boolean',
        ]);

        if ($request->filled('password')) {
            $request->validate(['password' => 'min:8']);
            $validated['password'] = Hash::make($request->password);
        }

        $user->update($validated);
        return response()->json(['success' => true]);
    }

    public function destroy(User $user)
    {
        if ($user->username === 'admin') {
            return response()->json(['success' => false, 'message' => 'Akun admin utama tidak dapat dihapus.'], 422);
        }
        if ($user->id === auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Tidak dapat menghapus akun sendiri.'], 422);
        }

        $user->delete();
        return response()->json(['success' => true]);
    }
    public function exportExcel()
    {
        $users = User::orderBy('name')->get(['id','name','username','email','role','is_active','last_login_at','created_at']);

        $columns = [
            ['key'=>'name',        'label'=>'Nama',          'width'=>28, 'locked'=>true],
            ['key'=>'username',    'label'=>'Username',      'width'=>18, 'locked'=>true],
            ['key'=>'email',       'label'=>'Email',         'width'=>30, 'locked'=>true],
            ['key'=>'role',        'label'=>'Role',          'width'=>12, 'locked'=>true],
            ['key'=>'status',      'label'=>'Status',        'width'=>12, 'locked'=>true],
            ['key'=>'last_login',  'label'=>'Login Terakhir','width'=>20, 'locked'=>true],
            ['key'=>'created',     'label'=>'Dibuat',        'width'=>18, 'locked'=>true],
        ];

        $rows = $users->map(fn($u) => [
            'name'      => $u->name,
            'username'  => $u->username,
            'email'     => $u->email ?? '-',
            'role'      => ucfirst($u->role),
            'status'    => $u->is_active ? 'Aktif' : 'Nonaktif',
            'last_login'=> $u->last_login_at?->format('d/m/Y H:i') ?? 'Belum pernah',
            'created'   => $u->created_at?->format('d/m/Y'),
        ])->toArray();

        \App\Models\ExportLog::record('users_excel', [], $users->count());
        return \App\Services\ExcelHelper::download(
            filename: 'daftar_pengguna',
            columns:  $columns,
            rows:     $rows,
            sheetTitle: 'Pengguna Sistem',
            meta: ['Total' => $users->count(), 'Export' => now()->format('d/m/Y H:i')]
        );
    }

}
