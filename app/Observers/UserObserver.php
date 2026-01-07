<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Group;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    public $afterCommit = true;

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        //
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        //
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        Log::info('UserObserver: пользователь удален', [
            'user_id' => $user->id,
            'user_name' => $user->last_name . ' ' . $user->first_name,
            'group_id' => $user->group_id
        ]);

        // Проверяем, была ли у пользователя группа
        if ($user->group_id) {
            $group = Group::find($user->group_id);
            
            if ($group) {
                // Проверяем, есть ли еще пользователи в этой группе
                $usersInGroup = User::where('group_id', $group->id)->count();
                
                Log::info('UserObserver: проверка группы', [
                    'group_id' => $group->id,
                    'group_number' => $group->number,
                    'users_in_group' => $usersInGroup
                ]);
                
                if ($usersInGroup === 0) {
                    // Удаляем пустую группу
                    $group->delete();
                    
                    Log::info('UserObserver: пустая группа удалена', [
                        'group_id' => $group->id,
                        'group_number' => $group->number,
                        'deleted_by_user_id' => $user->id
                    ]);
                }
            }
        }
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
