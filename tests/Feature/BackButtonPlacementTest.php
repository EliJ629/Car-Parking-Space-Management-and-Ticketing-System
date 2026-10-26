<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The back icon sits right below the yellow stripe, the theme toggle at the bottom, and neither has a border. */
class BackButtonPlacementTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_back_icon_sits_right_below_the_yellow_stripe(): void
    {
        $pages = [
            'driver.login' => 'driver.landing',
            'driver.register' => 'driver.landing',
            'driver.password.request' => 'driver.login',
        ];

        foreach ($pages as $route => $target) {
            $html = $this->get(route($route))->assertOk()->getContent();
            $this->assertStringNotContainsString('class="back"', $html, $route.' still has the top link');
            $this->assertStringNotContainsString('backrow', $html, $route.' still has the bottom button');

            $stripe = strpos($html, 'class="stripe"');
            $icon = strpos($html, 'class="tt backico"');
            $heading = strpos($html, '<h1', $stripe);

            $this->assertNotFalse($icon, $route);
            $this->assertGreaterThan($stripe, $icon, "{$route}: icon is below the stripe");
            $this->assertLessThan($heading, $icon, "{$route}: icon comes before the heading");
            $this->assertStringContainsString('href="'.route($target).'" aria-label="Back to', $html, $route.' icon is a link with a label');
            $this->assertStringNotContainsString('>&larr;', $html);
        }
    }

    public function test_the_theme_toggle_is_inside_the_card_at_the_bottom_right(): void
    {
        foreach (['driver.login', 'driver.register', 'driver.password.request'] as $route) {
            $html = $this->get(route($route))->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, 'id="theme"'), $route);
            $this->assertStringNotContainsString('pagefoot', $html, $route);
            $this->assertGreaterThan(strrpos($html, 'class="appbody"'), strpos($html, 'class="cardfoot"'), "{$route}: the toggle row is at the end of the card");
            $this->assertGreaterThan(strpos($html, 'class="cardfoot"'), strpos($html, 'id="theme"'), $route);
        }

        foreach (['staff.login', 'staff.password.request'] as $route) {
            $html = $this->get(route($route))->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, 'id="theme"'), $route);
            $this->assertGreaterThan(strpos($html, 'class="card"'), strpos($html, 'class="cardfoot"'), "{$route}: inside the sign in card");
            $this->assertStringNotContainsString('barfoot', $html, $route);
        }

        $css = file_get_contents(public_path('css/bayline.css'));
        $this->assertMatchesRegularExpression('/\.cardfoot\{display:flex;justify-content:flex-end/', $css);
    }

    public function test_the_icon_buttons_have_no_border(): void
    {
        $css = file_get_contents(public_path('css/bayline.css'));
        $this->assertMatchesRegularExpression('/\.tt\{[^}]*border:0;/', $css);
        $this->assertDoesNotMatchRegularExpression('/\.tt\{[^}]*border:1\.5px/', $css);
    }
}
