<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

/**
 * The public marketing pages.
 *
 * They are the one part of the site a stranger reaches, so three properties
 * are pinned: they open without signing in, they never offer to install the
 * laboratory application, and contact details that the site has not
 * configured are left out rather than rendered as empty links.
 */
class MarketingPagesTest extends TestCase
{
    use CreatesStaffUsers, RefreshDatabase;

    #[Test]
    public function the_home_page_is_public_and_links_to_the_product(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('From requisition to validated report', false)
            ->assertSee(route('marketing.products.laboratory'))
            ->assertSee(route('login'));
    }

    #[Test]
    public function the_product_page_is_public(): void
    {
        $this->get(route('marketing.products.laboratory'))
            ->assertOk()
            ->assertSee('Laboratory Management System')
            ->assertSee('Two-step validation')
            ->assertSee('id="workflow"', false);
    }

    #[Test]
    public function a_signed_in_visitor_is_offered_the_dashboard_instead_of_sign_in(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('home'))
            ->assertOk()
            ->assertSee(route('dashboard'))
            ->assertDontSee('Staff sign in');
    }

    #[Test]
    public function the_pages_do_not_register_the_installable_app(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('pwa.manifest'));
    }

    #[Test]
    public function unconfigured_contact_details_are_left_out(): void
    {
        config([
            'laboratory.organisation.phone' => '',
            'laboratory.organisation.email' => '',
            'laboratory.footer.support_email' => '',
        ]);

        $this->get(route('marketing.products.laboratory'))
            ->assertOk()
            ->assertDontSee('tel:', false)
            ->assertDontSee('mailto:', false);
    }

    #[Test]
    public function configured_contact_details_become_the_demo_request(): void
    {
        config(['laboratory.organisation.email' => 'sales@example.test']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('mailto:sales@example.test', false);
    }
}
