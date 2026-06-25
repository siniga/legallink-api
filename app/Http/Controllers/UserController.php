<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::latest()->paginate(15);

        return $this->success($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => $request->role,
            'status' => $request->status ?? UserStatus::Active,
        ]);

        return $this->success($user, 'User created successfully', 201);
    }

    public function show(User $user): JsonResponse
    {
        return $this->success($user);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());

        return $this->success($user->fresh(), 'User updated successfully');
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === auth()->id()) {
            return $this->error('You cannot delete your own account.', 403);
        }

        $user->delete();

        return $this->success(null, 'User deleted successfully');
    }
}
