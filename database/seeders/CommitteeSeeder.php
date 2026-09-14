<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Committee;
use App\Models\CommitteeMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CommitteeSeeder extends Seeder
{
    public function run(): void
    {
        $seatedMembers = User::query()->where('is_seated_member', true)->get();
        $chair = User::query()->where('email', 'chair@sentria.test')->firstOrFail();
        $committeeMember = User::query()->where('email', 'committee@sentria.test')->firstOrFail();

        foreach ($this->committees() as $index => [$name, $type, $mandate]) {
            $committee = Committee::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'code' => Str::upper(Str::substr(Str::slug($name, ''), 10, 10)),
                    'type' => $type,
                    'mandate' => $mandate,
                    'established_on' => now()->subYears(3)->startOfYear(),
                    'is_active' => true,
                ],
            );

            $roster = $seatedMembers->shuffle()->take(5);

            // The demo Committee Chair and Committee Member accounts always sit
            // on the first committee so their dashboards are not empty.
            if ($index === 0) {
                $this->attach($committee, $chair, 'chair');
                $this->attach($committee, $committeeMember, 'member');
                $roster = $roster->reject(fn (User $user): bool => in_array(
                    $user->getKey(),
                    [$chair->getKey(), $committeeMember->getKey()],
                    true,
                ))->take(3);
            } else {
                $this->attach($committee, $roster->first(), 'chair');
                $roster = $roster->skip(1);
            }

            $roster->each(fn (User $user) => $this->attach($committee, $user, 'member'));
        }

        $chair->syncRoles([UserRole::CommitteeChair->value]);
    }

    private function attach(Committee $committee, ?User $user, string $position): void
    {
        if (! $user instanceof User) {
            return;
        }

        CommitteeMember::query()->updateOrCreate(
            ['committee_id' => $committee->getKey(), 'user_id' => $user->getKey()],
            [
                'position' => $position,
                'appointed_on' => now()->subYears(2)->startOfYear(),
                'is_active' => true,
            ],
        );
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function committees(): array
    {
        return [
            ['Committee on Rules and Privileges', 'standing', 'Reviews the rules of procedure and matters of privilege.'],
            ['Committee on Appropriations', 'standing', 'Reviews the annual and supplemental budgets of the province.'],
            ['Committee on Ways and Means', 'standing', 'Reviews revenue measures, fees, and local taxation.'],
            ['Committee on Health and Sanitation', 'standing', 'Reviews measures on public health, hospitals, and sanitation.'],
            ['Committee on Education and Culture', 'standing', 'Reviews measures on schools, scholarships, and cultural heritage.'],
            ['Committee on Environment and Natural Resources', 'standing', 'Reviews measures on watersheds, waste, and natural resources.'],
            ['Committee on Public Works and Infrastructure', 'standing', 'Reviews measures on provincial roads, bridges, and facilities.'],
            ['Committee on Peace and Order', 'standing', 'Reviews measures on public safety and disaster preparedness.'],
        ];
    }
}
