<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Staff accounts. Admin-only, enforced by the `admin` middleware on the route.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        return view('admin.users', [
            'users' => User::query()
                ->when($search !== '', fn ($query) => $query->where('username', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%"))
                ->orderBy('username')
                ->paginate(20)
                ->withQueryString(),
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'role' => ['required', Rule::in(['admin', 'staff'])],
        ]);

        User::create([
            'name' => ($data['name'] ?? null) ?: str($data['username'])->headline()->toString(),
            'username' => $data['username'],
            'password' => $data['password'],
            'role' => $data['role'],
            'is_active' => true,
        ]);

        return redirect()
            ->route('admin.users')
            ->with('status', 'Account '.$data['username'].' created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'role' => ['required', Rule::in(['admin', 'staff'])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $isSelf = $user->id === $request->user()->id;

        // Guard against an admin locking themselves — and possibly everyone —
        // out of the admin area.
        if ($isSelf && ($data['role'] !== 'admin' || $request->boolean('is_active') === false)) {
            return back()->withErrors(['role' => 'You cannot remove your own administrator access.']);
        }

        if ($user->isAdmin() && $data['role'] !== 'admin' && User::where('role', 'admin')->where('is_active', true)->count() <= 1) {
            return back()->withErrors(['role' => 'At least one active administrator must remain.']);
        }

        $user->fill([
            'name' => ($data['name'] ?? null) ?: $user->name,
            'username' => $data['username'],
            'role' => $data['role'],
            'is_active' => $isSelf ? true : $request->boolean('is_active'),
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        return redirect()
            ->route('admin.users')
            ->with('status', 'Account '.$user->username.' updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'You cannot delete the account you are signed in with.']);
        }

        if ($user->isAdmin() && User::where('role', 'admin')->count() <= 1) {
            return back()->withErrors(['user' => 'At least one administrator must remain.']);
        }

        $username = $user->username;
        $user->delete();

        return redirect()
            ->route('admin.users')
            ->with('status', 'Account '.$username.' deleted.');
    }
}
