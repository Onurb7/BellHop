<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleAndDemoUserSeeder extends Seeder
{
    /**
     * Seed the four roles and one user per role. Idempotent — safe to
     * re-run (e.g. after rotating a demo password in .env).
     */
    public function run(): void
    {
        collect(['super-admin', 'admin', 'staff', 'guest'])
            ->each(fn (string $role) => Role::findOrCreate($role));

        $this->seedUser('Super Admin', config('demo.super_admin'), 'super-admin');

        foreach (config('demo.accounts') as $role => $account) {
            $this->seedUser(ucfirst($role).' Demo', $account, $role);
        }
    }

    /**
     * @param  array{email: ?string, password: ?string}  $account
     */
    private function seedUser(string $name, array $account, string $role): void
    {
        if (! $account['email'] || ! $account['password']) {
            return;
        }

        $user = User::updateOrCreate(
            ['email' => $account['email']],
            ['name' => $name, 'password' => $account['password']],
        );

        $user->syncRoles([$role]);
    }
}
