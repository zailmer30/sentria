<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\LegislativeSession;
use App\Models\SessionAttendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionAttendance>
 */
class SessionAttendanceFactory extends Factory
{
    protected $model = SessionAttendance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement([
            AttendanceStatus::Present,
            AttendanceStatus::Present,
            AttendanceStatus::Present,
            AttendanceStatus::Late,
            AttendanceStatus::Excused,
            AttendanceStatus::OnOfficialBusiness,
        ]);

        return [
            'session_id' => LegislativeSession::factory(),
            'user_id' => User::factory(),
            'status' => $status->value,
            'checked_in_at' => $status->countsTowardQuorum() ? fake()->dateTimeBetween('-1 hour', 'now') : null,
            'check_in_method' => $status->countsTowardQuorum() ? 'tablet' : null,
            'remarks' => $status === AttendanceStatus::OnOfficialBusiness ? 'Attending an inter-agency conference.' : null,
        ];
    }

    public function present(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AttendanceStatus::Present->value,
            'checked_in_at' => now(),
            'check_in_method' => 'tablet',
        ]);
    }
}
