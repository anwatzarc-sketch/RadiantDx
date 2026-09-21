<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RequisitionPriority;
use App\Enums\RequisitionStatus;
use App\Models\LaboratoryRequisition;
use App\Models\User;
use App\Services\Laboratory\RequisitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

/**
 * The dashboard's summary cards.
 *
 * The in-process card is the only one whose mark moves, and that is a claim
 * about the work rather than decoration: it counts specimens being handled
 * right now. If the turning ever spreads to the cards either side of it, it
 * stops meaning anything — so the opt-in is pinned here.
 */
class DashboardCardTest extends TestCase
{
    use CreatesStaffUsers, RefreshDatabase;

    #[Test]
    public function the_in_process_card_turns(): void
    {
        $response = $this->actingAs($this->superAdmin())
            ->get(route('dashboard'))
            ->assertOk();

        $response->assertSee('In process');
        $response->assertSee('icon-spin-slow', false);
    }

    #[Test]
    public function only_the_in_process_card_turns(): void
    {
        // A dashboard where everything spins says nothing at all.
        $html = (string) $this->actingAs($this->superAdmin())
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            substr_count($html, 'icon-spin-slow'),
            'Exactly one card should carry the turning mark.',
        );
    }

    #[Test]
    public function the_in_process_card_counts_collected_and_processing_work(): void
    {
        // The count the turning mark is describing has to be the real one.
        $user = $this->superAdmin();

        $this->requisitionWithStatus($user, RequisitionStatus::Processing);
        $this->requisitionWithStatus($user, RequisitionStatus::Processing);
        $this->requisitionWithStatus($user, RequisitionStatus::Collected);
        $this->requisitionWithStatus($user, RequisitionStatus::Draft);

        $cards = collect($this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->viewData('cards'));

        $inProcess = $cards->firstWhere('label', 'In process');

        $this->assertNotNull($inProcess);
        $this->assertSame(3, $inProcess['value']);
        $this->assertTrue($inProcess['icon_spin']);
    }

    #[Test]
    public function the_workflow_cards_use_the_tone_that_matches_their_meaning(): void
    {
        /*
         * Tone carries meaning on this dashboard rather than being decoration
         * — the key is written down in components/stat-card, where sky is work
         * in progress and amber is a queue waiting on somebody. These two were
         * the wrong way round, which made a specimen nobody has collected yet
         * look calmer than one already on the bench.
         *
         * The hint icon follows the tone, so getting this right also gets the
         * clock onto the card that is actually waiting.
         */
        $cards = collect($this->actingAs($this->superAdmin())
            ->get(route('dashboard'))
            ->assertOk()
            ->viewData('cards'));

        $this->assertSame('amber', $cards->firstWhere('label', 'Awaiting collection')['tone']);
        $this->assertSame('sky', $cards->firstWhere('label', 'In process')['tone']);
    }

    /** Built through the service the application itself uses, then moved on. */
    private function requisitionWithStatus(User $user, RequisitionStatus $status): LaboratoryRequisition
    {
        $requisition = app(RequisitionService::class)->create([
            'patient_identifier' => 'P-'.fake()->unique()->numberBetween(1000, 9999),
            'patient_name' => 'Test Patient',
            'patient_age_years' => 40,
            'requested_date' => now()->toDateString(),
            'priority' => RequisitionPriority::Routine->value,
        ], [], $user);

        $requisition->forceFill(['status' => $status->value])->save();

        return $requisition;
    }
}
