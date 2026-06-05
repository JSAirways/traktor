<?php

namespace App\Services;

use App\Events\UserApproved;
use App\Events\UserRejected;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserApprovalService
{
    public function approve(User $user, User $admin): void
    {
        $user->approve($admin);

        event(new UserApproved($user, $admin));
    }

    public function reject(User $user, string $reason, User $admin): void
    {
        $user->reject($reason, $admin);

        event(new UserRejected($user, $reason, $admin));
    }

    public function bulkApprove(array $userIds, User $admin): int
    {
        $count = 0;

        DB::transaction(function () use ($userIds, $admin, &$count) {
            foreach ($userIds as $userId) {
                $user = User::find($userId);

                if ($user && ($user->isPending() || $user->isRejected())) {
                    $this->approve($user, $admin);
                    $count++;
                }
            }
        });

        return $count;
    }

    public function bulkReject(array $userIds, string $reason, User $admin): int
    {
        $count = 0;

        DB::transaction(function () use ($userIds, $reason, $admin, &$count) {
            foreach ($userIds as $userId) {
                $user = User::find($userId);

                if ($user && $user->isPending()) {
                    $this->reject($user, $reason, $admin);
                    $count++;
                }
            }
        });

        return $count;
    }
}


