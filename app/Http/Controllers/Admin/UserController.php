<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlayerName;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public const ROLES = ['user', 'admin'];

    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q'));

        $users = User::query()
            ->with('playerNames')
            ->withCount('replay')
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
                $query->where(fn($q) => $q
                    ->where('email', 'like', $like)
                    ->orWhereHas('playerNames', fn($n) => $n->where('name', 'like', $like)));
            })
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'search'));
    }

    public function edit(User $user): View
    {
        $user->load('playerNames')->loadCount('replay');

        return view('admin.users.edit', ['user' => $user, 'roles' => self::ROLES]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'role' => ['required', Rule::in(self::ROLES)],
        ]);

        // An admin demoting themselves could leave nobody able to undo it.
        if ($user->is($request->user()) && $validated['role'] !== 'admin') {
            return back()->with('error', 'You cannot remove your own admin role.');
        }

        $user->email = $validated['email'];
        $user->role = $validated['role'];
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $changes = $user->getDirty();
        $user->save();

        $this->audit($request, 'user.update', $user, ['changed' => array_keys($changes)]);

        return back()->with('status', 'Account updated.');
    }

    public function addName(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validateWithBag('playerName', ['name' => PlayerName::rulesFor($user)]);

        $playerName = PlayerName::addFor($user, $validated['name']);
        $this->audit($request, 'user.name.add', $user, ['name' => $playerName->name]);

        return back()->with('status', "Added username {$playerName->name}.");
    }

    public function removeName(Request $request, User $user, PlayerName $playerName): RedirectResponse
    {
        if ($blocker = $playerName->removalBlocker()) {
            return back()->with('error', $blocker);
        }

        $playerName->delete();
        $this->audit($request, 'user.name.remove', $user, ['name' => $playerName->name]);

        return back()->with('status', "Removed username {$playerName->name}.");
    }

    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        $status = Password::sendResetLink(['email' => $user->email]);
        $this->audit($request, 'user.password_reset', $user, ['status' => $status]);

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', "Password reset email sent to {$user->email}.")
            : back()->with('error', __($status));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account here.');
        }
        // Deleting the account would delete its replay rows and leave the
        // other players' games half-recorded. Invalidate the games instead.
        if ($user->replay()->exists()) {
            return back()->with('error', 'This account has uploaded replays and cannot be deleted.');
        }

        $this->audit($request, 'user.delete', $user, ['names' => $user->playerNames->pluck('name')->all()]);
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', "Deleted {$user->player_name}.");
    }

    private function audit(Request $request, string $action, User $user, array $context = []): void
    {
        Log::info("admin: {$action}", [
            'admin_id' => $request->user()->id,
            'admin' => $request->user()->player_name,
            'user_id' => $user->id,
            'user' => $user->player_name,
        ] + $context);
    }
}
