<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * One demo account per canonical role, plus enough board members to make
 * quorum and voting scenarios meaningful. All fictional.
 */
class UserSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        foreach ($this->canonicalAccounts() as $role => $attributes) {
            $user = User::query()->updateOrCreate(
                ['email' => $attributes['email']],
                [
                    ...$attributes,
                    'password' => Hash::make(self::DEMO_PASSWORD),
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'locale' => 'en',
                ],
            );

            $user->syncRoles([$role]);
        }

        User::query()->updateOrCreate(
            ['email' => 'chamber-capture@sentria.local'],
            [
                'first_name' => 'Chamber',
                'last_name' => 'Capture',
                'display_name' => 'Chamber Capture',
                'password' => Hash::make(Str::password(48)),
                'email_verified_at' => now(),
                'is_active' => false,
                'is_seated_member' => false,
                'locale' => 'en',
            ],
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function canonicalAccounts(): array
    {
        return [
            UserRole::SystemAdministrator->value => [
                'first_name' => 'Marisol',
                'middle_name' => 'Reyes',
                'last_name' => 'Bautista',
                'display_name' => 'Marisol Bautista',
                'honorific' => null,
                'email' => 'admin@sentria.test',
                'position_title' => 'ICT Administrator',
                'employee_number' => 'EMP-0001',
                'is_seated_member' => false,
            ],
            UserRole::Secretariat->value => [
                'first_name' => 'Joselito',
                'middle_name' => 'Cruz',
                'last_name' => 'Fernandez',
                'display_name' => 'Joselito Fernandez',
                'honorific' => null,
                'email' => 'secretariat@sentria.test',
                'position_title' => 'Secretary to the Sanggunian',
                'employee_number' => 'EMP-0002',
                'is_seated_member' => false,
            ],
            UserRole::PresidingOfficer->value => [
                'first_name' => 'Teresita',
                'middle_name' => 'Lim',
                'last_name' => 'Villanueva',
                'display_name' => 'Teresita Villanueva',
                'honorific' => 'Hon.',
                'email' => 'presiding@sentria.test',
                'position_title' => 'Vice Governor / Presiding Officer',
                'employee_number' => 'EMP-0003',
                'is_seated_member' => true,
            ],
            UserRole::BoardMember->value => [
                'first_name' => 'Rafael',
                'middle_name' => 'Santos',
                'last_name' => 'Dizon',
                'display_name' => 'Rafael Dizon',
                'honorific' => 'Hon.',
                'email' => 'member@sentria.test',
                'position_title' => 'Board Member',
                'district' => '1st District',
                'employee_number' => 'EMP-0004',
                'is_seated_member' => true,
            ],
            UserRole::CommitteeChair->value => [
                'first_name' => 'Corazon',
                'middle_name' => 'Aguilar',
                'last_name' => 'Manalo',
                'display_name' => 'Corazon Manalo',
                'honorific' => 'Hon.',
                'email' => 'chair@sentria.test',
                'position_title' => 'Board Member / Committee Chair',
                'district' => '2nd District',
                'employee_number' => 'EMP-0005',
                'is_seated_member' => true,
            ],
            UserRole::CommitteeMember->value => [
                'first_name' => 'Danilo',
                'middle_name' => 'Ocampo',
                'last_name' => 'Salazar',
                'display_name' => 'Danilo Salazar',
                'honorific' => 'Hon.',
                'email' => 'committee@sentria.test',
                'position_title' => 'Board Member / Committee Member',
                'district' => '3rd District',
                'employee_number' => 'EMP-0006',
                'is_seated_member' => true,
            ],
            UserRole::LegalTechnicalReviewer->value => [
                'first_name' => 'Anna',
                'middle_name' => 'Perez',
                'last_name' => 'Katigbak',
                'display_name' => 'Atty. Anna Katigbak',
                'honorific' => 'Atty.',
                'email' => 'legal@sentria.test',
                'position_title' => 'Provincial Legal Officer',
                'employee_number' => 'EMP-0007',
                'is_seated_member' => false,
            ],
            UserRole::PublicUser->value => [
                'first_name' => 'Miguel',
                'middle_name' => null,
                'last_name' => 'Navarro',
                'display_name' => 'Miguel Navarro',
                'honorific' => null,
                'email' => 'public@sentria.test',
                'position_title' => null,
                'is_seated_member' => false,
            ],
        ];
    }

    private function seedAdditionalBoardMembers(): void
    {
        $existing = User::query()->where('is_seated_member', true)->count();
        $target = 14;

        if ($existing >= $target) {
            return;
        }

        User::factory()
            ->count($target - $existing)
            ->seatedMember()
            ->create([
                'password' => Hash::make(self::DEMO_PASSWORD),
                'position_title' => 'Board Member',
            ])
            ->each(fn (User $user) => $user->syncRoles([UserRole::BoardMember->value]));
    }
}
