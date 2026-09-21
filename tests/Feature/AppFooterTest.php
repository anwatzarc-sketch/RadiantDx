<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

/**
 * The global footer.
 *
 * Two properties are worth pinning. The footer is navigation, so it must obey
 * the same permission filtering the sidebar does — a link offered here that
 * answers 403 is a worse experience than no link. And it is configuration
 * driven, so a value the deploying site has not set must be left out rather
 * than printed blank or pointed at a dead anchor.
 */
class AppFooterTest extends TestCase
{
    use CreatesStaffUsers, RefreshDatabase;

    // ------------------------------------------------------------- presence

    #[Test]
    public function it_appears_on_application_pages(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Support and security')
            ->assertSee('All rights reserved', false);
    }

    #[Test]
    public function it_names_the_deploying_site_rather_than_a_hard_coded_one(): void
    {
        config([
            'laboratory.organisation.name' => 'Kebele Health Centre',
            'laboratory.version' => '9.9.9',
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Kebele Health Centre')
            ->assertSee('v9.9.9');
    }

    // ---------------------------------------------------------- permissions

    #[Test]
    public function its_links_are_filtered_to_what_the_viewer_can_open(): void
    {
        // A result-entry technologist has no business being offered the
        // security matrix, and following the link would only earn a 403.
        $technologist = $this->userWithPermissions([
            'dashboard.view',
            'laboratory.result.view',
        ]);

        $response = $this->actingAs($technologist)->get(route('dashboard'))->assertOk();

        $response->assertSee('Results entry and validation');
        $response->assertDontSee('Roles and security matrix');
        $response->assertDontSee('System users and access');
    }

    #[Test]
    public function a_whole_column_disappears_once_none_of_it_is_reachable(): void
    {
        $technologist = $this->userWithPermissions([
            'dashboard.view',
            'laboratory.result.view',
        ]);

        $this->actingAs($technologist)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Administration</h2>', false);
    }

    #[Test]
    public function an_administrator_is_offered_the_administration_column(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Roles and security matrix')
            ->assertSee('Staff directory');
    }

    // -------------------------------------------------------- configuration

    #[Test]
    public function configured_support_details_and_accreditations_are_shown(): void
    {
        config([
            'laboratory.footer.support_hotline' => 'Ext. 4400',
            'laboratory.footer.support_email' => 'lab-support@example.org',
            'laboratory.footer.accreditations' => 'ISO 15189 Accredited, HIPAA Compliant',
        ]);

        $response = $this->actingAs($this->superAdmin())->get(route('dashboard'))->assertOk();

        $response->assertSee('Ext. 4400');
        $response->assertSee('lab-support@example.org');
        $response->assertSee('ISO 15189 Accredited');
        $response->assertSee('HIPAA Compliant');
    }

    #[Test]
    public function unset_support_details_are_omitted_rather_than_left_blank(): void
    {
        config([
            'laboratory.footer.support_hotline' => '',
            'laboratory.footer.support_email' => '',
            'laboratory.footer.accreditations' => '',
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Laboratory hotline')
            ->assertDontSee('mailto:');
    }

    #[Test]
    public function a_policy_link_is_never_printed_without_somewhere_to_point(): void
    {
        config([
            'laboratory.footer.privacy_url' => 'https://example.org/privacy',
            'laboratory.footer.terms_url' => '',
            'laboratory.footer.data_protection_url' => '',
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('https://example.org/privacy')
            ->assertSee('Privacy policy')
            ->assertDontSee('Terms of service');
    }

    // ---------------------------------------------------------------- print

    #[Test]
    public function the_printed_report_does_not_carry_the_application_footer(): void
    {
        // The report is a standalone A4 template with its own signature block.
        // app.css hides any <footer> outside <main> when printing, but the
        // report must not be rendering this one in the first place.
        $this->assertStringNotContainsString(
            'x-app-footer',
            (string) file_get_contents(resource_path('views/laboratory/reports/report.blade.php')),
        );
    }
}
