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
            ['Committee on Good Government', 'standing', 'Reviews measures on transparency, good governance, and public accountability.'],
            ['Committee on Rules and Ordinances', 'standing', 'Reviews the rules of procedure and proposed ordinances.'],
            ['Committee on Public Ethics and Accountability', 'standing', 'Reviews measures on public ethics, integrity, and official accountability.'],
            ['Committee on Games and Amusements', 'standing', 'Reviews measures on games, amusement, and related permits.'],
            ['Committee on Industrialization', 'standing', 'Reviews measures on industrial development and related enterprises.'],
            ['Committee on Oversight', 'standing', 'Reviews implementation of ordinances, programs, and official actions.'],
            ['Committee on Livelihood and Cooperative Development', 'standing', 'Reviews measures on livelihood programs and cooperative development.'],
            ['Committee on Youth and Sports Development', 'standing', 'Reviews measures on youth affairs and sports development.'],
            ['Committee on Education', 'standing', 'Reviews measures on schools, scholarships, and educational programs.'],
            ['Committee on Public Communications and Information Technology', 'standing', 'Reviews measures on public communications and information technology.'],
            ['Committee on Environmental Protection, Ecology, and Natural Resources', 'standing', 'Reviews measures on environmental protection, ecology, and natural resources.'],
            ['Committee on Investment Promotion and Tourism', 'standing', 'Reviews measures on investment promotion and tourism.'],
            ['Committee on Public Health & Sanitation', 'standing', 'Reviews measures on public health, hospitals, and sanitation.'],
            ['Committee on Women, Family Welfare, Children\'s Affair', 'standing', 'Reviews measures on women, family welfare, and children\'s affairs.'],
            ['Committee on Social Welfare', 'standing', 'Reviews measures on social welfare programs and services.'],
            ['Committee on Labor and Employment', 'standing', 'Reviews measures on labor, employment, and worker welfare.'],
            ['Committee on Human Resource', 'standing', 'Reviews measures on human resource development and personnel programs.'],
            ['Committee on Civil Service', 'standing', 'Reviews measures on civil service, appointments, and personnel administration.'],
            ['Committee on People\'s Organization and Community Development', 'standing', 'Reviews measures on people\'s organizations and community development.'],
            ['Committee on Peace and Order, Justice, Police Matters, and Human Rights', 'standing', 'Reviews measures on peace and order, justice, police matters, and human rights.'],
            ['Committee on Public Order & Safety', 'standing', 'Reviews measures on public order, safety, and related enforcement.'],
            ['Committee on Disaster Resiliency', 'standing', 'Reviews measures on disaster preparedness, response, and resiliency.'],
            ['Committee on Transportation', 'standing', 'Reviews measures on transportation systems, terminals, and traffic.'],
            ['Committee on Power and Energy', 'standing', 'Reviews measures on power, energy, and related utilities.'],
            ['Committee on Fisheries and Agriculture', 'standing', 'Reviews measures on fisheries, agriculture, and related livelihoods.'],
            ['Committee on Food Sufficiency', 'standing', 'Reviews measures on food security and agricultural sufficiency.'],
            ['Committee on Ways and Means', 'standing', 'Reviews revenue measures, fees, and local taxation.'],
            ['Committee on Local Economic Enterprise and Revenue', 'standing', 'Reviews local economic enterprises and revenue-generating operations.'],
            ['Committee on Trade and Commerce and Industry', 'standing', 'Reviews measures on trade, commerce, and industry.'],
            ['Committee on Patrimonial Properties', 'standing', 'Reviews measures on patrimonial properties and government assets.'],
            ['Committee on Research and Development', 'standing', 'Reviews measures on research, development, and related programs.'],
            ['Committee on National and International Relations', 'standing', 'Reviews measures on national and international relations and partnerships.'],
            ['Committee on Indigenous People and Muslim Affairs and Cultural Minorities', 'standing', 'Reviews measures on indigenous peoples, Muslim affairs, and cultural minorities.'],
            ['Committee on History, Culture and Arts', 'standing', 'Reviews measures on history, culture, and the arts.'],
            ['Committee on Public Works & Infrastructure', 'standing', 'Reviews measures on public works, roads, and infrastructure.'],
            ['Committee on Town Planning, Zonification & Land Use', 'standing', 'Reviews measures on town planning, zonification, and land use.'],
            ['Committee on Housing & Human Settlement', 'standing', 'Reviews measures on housing and human settlements.'],
            ['Committee on Finance and Appropriation', 'standing', 'Reviews the annual and supplemental budgets and appropriations.'],
            ['Committee on Barangay Affairs', 'standing', 'Reviews measures on barangay affairs and local coordination.'],
        ];
    }
}
