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
                    ->orWhereHas('allPlayerNames', fn($n) => $n->where('name', 'like', $like)));
            })
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'search'));
    }

    public function edit(User $user): View
    {
        $user->load('playerNames')->loadCount('replay');

        return view('admin.users.edit', ['user' => $user, 'roles' => User::ROLES]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManage($request, $user);
        $actor = $request->user();

        $validated = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'role' => [$actor->isOwner() ? 'required' : 'nullable', Rule::in(User::ROLES)],
        ]);
        $role = $validated['role'] ?? $user->role;

        if ($role !== $user->role) {
            if (!$actor->isOwner()) {
                return back()->with('error', 'Only an owner can change roles.');
            }
            // An owner demoting themselves could leave nobody able to undo it.
            if ($user->is($actor)) {
                return back()->with('error', 'You cannot change your own role.');
            }
        }

        $user->email = $validated['email'];
        $user->role = $role;
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $changes = $user->getDirty();
        $user->save();

        $this->audit($request, 'user.update', $user, ['changed' => array_keys($changes)]);

        return back()->with('status', 'Account updated.');
    }

    /** Role picker on the users list. Owners only; nobody changes their own role. */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->isOwner(), 403, 'Only an owner can change roles.');

        $validated = $request->validate(['role' => ['required', Rule::in(User::ROLES)]]);

        if ($user->is($actor)) {
            return back()->with('error', 'You cannot change your own role.');
        }
        if ($validated['role'] === $user->role) {
            return back();
        }

        $from = $user->role;
        $user->role = $validated['role'];
        $user->save();

        $this->audit($request, 'user.role', $user, ['from' => $from, 'to' => $user->role]);

        return back()->with('status', "{$user->player_name} is now {$user->role}.");
    }

    public function addName(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManage($request, $user);
        $validated = $request->validateWithBag('playerName', ['name' => PlayerName::rulesFor($user)]);

        $playerName = PlayerName::addFor($user, $validated['name']);
        $this->audit($request, 'user.name.add', $user, ['name' => $playerName->name]);

        return back()->with('status', "Added username {$playerName->name}.");
    }

    public function removeName(Request $request, User $user, PlayerName $playerName): RedirectResponse
    {
        $this->authorizeManage($request, $user);
        if ($blocker = $playerName->removalBlocker()) {
            return back()->with('error', $blocker);
        }

        $playerName->remove();
        $this->audit($request, 'user.name.remove', $user, ['name' => $playerName->name, 'retired' => $playerName->exists]);

        return back()->with('status', "Removed username {$playerName->name}.");
    }

    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManage($request, $user);
        $status = Password::sendResetLink(['email' => $user->email]);
        $this->audit($request, 'user.password_reset', $user, ['status' => $status]);

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', "Password reset email sent to {$user->email}.")
            : back()->with('error', __($status));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManage($request, $user);
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account here.');
        }
        // Deleting the account would delete its replay rows and leave the
        // other players' games half-recorded. Invalidate the games instead.
        if ($user->replay()->exists()) {
            return back()->with('error', 'This account has uploaded replays and cannot be deleted.');
        }

        $this->audit($request, 'user.delete', $user, ['names' => $user->allPlayerNames->pluck('name')->all()]);
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', "Deleted {$user->player_name}.");
    }

    /**
     * Only an owner may touch an owner's account: an admin could otherwise change
     * the owner's email, send a reset link to it and take the account over.
     */
    private function authorizeManage(Request $request, User $user): void
    {
        abort_if($user->isOwner() && !$request->user()->isOwner(), 403, 'Only an owner can change an owner account.');
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
