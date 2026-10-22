<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function __construct(private UserRepository $users) {}

    public function index(Request $request): Response
    {
        $search = $request->filled('search') ? $request->string('search')->trim()->toString() : null;
        $role = $request->filled('role') ? $request->string('role')->toString() : null;

        $paginated = $this->users->paginateForAdmin($search, $role, $request->integer('per_page', 15));

        return Inertia::render('Admin/Users/Index', [
            'users' => UserResource::collection($paginated->withQueryString()),
            'filters' => ['search' => $search, 'role' => $role],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Form', [
            'user' => null,
            'roles' => $this->manageableRoles([UserRole::Organizer->value]),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->users->create([...$request->validated()]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Pengguna berhasil dibuat.');
    }

    public function edit(int $id): Response
    {
        return Inertia::render('Admin/Users/Form', [
            'user' => (new UserResource($this->findEditable($id)))->resolve(),
            'roles' => $this->manageableRoles(),
        ]);
    }

    public function update(UpdateUserRequest $request, int $id): RedirectResponse
    {
        $user = $this->findEditable($id);
        $user->update($request->validated());

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Data pengguna berhasil diperbarui.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $user = $this->findEditable($id);
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Akun pengguna dihapus.');
    }

    private function findEditable(int $id): User
    {
        $user = $this->users->findByIdOrFail($id);

        abort_if($user->id === Auth::id(), 403, 'Anda tidak dapat mengubah akun sendiri.');

        return $user;
    }

    /**
     * @param  array<int, string>|null  $only  Batasi ke peran tertentu; null berarti semua peran yang bisa dikelola.
     * @return array<int, array{value: string, label: string}>
     */
    private function manageableRoles(?array $only = null): array
    {
        return collect(UserRole::cases())
            ->filter(fn (UserRole $role) => in_array($role->value, $only ?? UserRole::manageable(), true))
            ->map(fn (UserRole $role) => ['value' => $role->value, 'label' => $role->label()])
            ->values()
            ->all();
    }
}
