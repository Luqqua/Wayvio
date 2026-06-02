<?php

namespace Tests\Unit;

use Tests\TestCase;

class SidebarAutofillIsolationScriptTest extends TestCase
{
    public function test_shared_autofill_guard_script_contains_strict_off_logic(): void
    {
        $partialPath = resource_path('views/layouts/autofill-strict-off.blade.php');
        $source = file_get_contents($partialPath);

        $this->assertIsString($source);
        $this->assertStringContainsString('data-global-autofill-isolation="strict-off"', $source);
        $this->assertStringContainsString("field.setAttribute('autocomplete', 'off')", $source);
        $this->assertStringContainsString("'input'", $source);
        $this->assertStringContainsString("'select'", $source);
        $this->assertStringContainsString('supportsReadonlyIsolation', $source);
        $this->assertStringContainsString('MutationObserver', $source);
        $this->assertStringContainsString("querySelectorAll('form')", $source);
    }

    public function test_base_layouts_include_shared_autofill_guard_script(): void
    {
        $sidebar = file_get_contents(resource_path('views/layouts/sidebar.blade.php'));
        $guest = file_get_contents(resource_path('views/layouts/guest.blade.php'));
        $app = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $wayvio = file_get_contents(resource_path('views/wayvio/layout.blade.php'));
        $help = file_get_contents(resource_path('views/help/layout.blade.php'));
        $installing = file_get_contents(resource_path('views/layouts/installing.blade.php'));
        $updater = file_get_contents(resource_path('views/layouts/updater.blade.php'));
        $report = file_get_contents(resource_path('views/report.blade.php'));
        $linkInfo = file_get_contents(resource_path('views/linkinfo.blade.php'));

        $this->assertIsString($sidebar);
        $this->assertIsString($guest);
        $this->assertIsString($app);
        $this->assertIsString($wayvio);
        $this->assertIsString($help);
        $this->assertIsString($installing);
        $this->assertIsString($updater);
        $this->assertIsString($report);
        $this->assertIsString($linkInfo);

        $this->assertStringContainsString("@include('layouts.autofill-strict-off')", $sidebar);
        $this->assertStringContainsString("@include('layouts.autofill-strict-off')", $guest);
        $this->assertStringContainsString("@include('layouts.autofill-strict-off')", $app);
        $this->assertStringContainsString("@include('layouts.autofill-strict-off')", $wayvio);
        $this->assertStringContainsString("@include('layouts.autofill-strict-off')", $help);
        $this->assertStringContainsString("@include('layouts.autofill-strict-off')", $installing);
        $this->assertStringContainsString("@include('layouts.autofill-strict-off')", $updater);
        $this->assertStringContainsString("@include('layouts.autofill-strict-off')", $report);
        $this->assertStringContainsString("@include('layouts.autofill-strict-off')", $linkInfo);
    }
}
