<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ChatAccessSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = ['chat.view', 'chat.send', 'chat.manage'];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $matrix = [
            'super_admin' => ['chat.view', 'chat.send', 'chat.manage'],
            'admin'       => ['chat.view', 'chat.send'],
            'team'        => ['chat.view', 'chat.send'],
        ];

        foreach ($matrix as $roleName => $grant) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            if (! $role) {
                $this->command?->warn("Role [{$roleName}] not found — skipped chat permission grant.");
                continue;
            }

            $role->givePermissionTo($grant);
        }

        $flags = [
            'chat.feature.edit'          => '0',
            'chat.feature.delete'        => '0',
            'chat.feature.read_receipts' => '0',
        ];

        foreach ($flags as $key => $default) {
            if (Setting::get($key) === null) {
                Setting::set($key, $default);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
