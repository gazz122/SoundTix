<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(private UserRepository $users) {}

    public function index(Request $request): ResourceCollection
    {
        $search = $request->filled('search') ? $request->string('search')->trim()->toString() : null;
        $role = $request->filled('role') ? $request->string('role')->toString() : null;

        $users = $this->users->paginateForAdmin($search, $role, $request->integer('per_page', 15));

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): UserResource
    {
        $user = $this->users->create([...$request->validated()]);

        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $isSelfMutation = $user->is(Auth::user())
            && ($request->has('role') || ($request->has('is_active') && $request->boolean('is_active') === false));

        if ($isSelfMutation) {
            throw ValidationException::withMessages([
                'user' => ['Anda tidak dapat mengubah peran atau menonaktifkan akun sendiri.'],
            ]);
        }

        $user->update($request->validated());

        return new UserResource($user->fresh());
    }
}
