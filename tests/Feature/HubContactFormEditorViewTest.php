<?php

namespace Tests\Feature;

use Tests\TestCase;

class HubContactFormEditorViewTest extends TestCase
{
    public function test_hub_contact_form_editor_renders_internal_title_field(): void
    {
        $response = $this
            ->withoutMiddleware()
            ->get('/studio/linkparamform_part/hub_contact_form/0');

        $response->assertOk();
        $response->assertSee('name="internal_title"', false);
        $response->assertSee('name="title"', false);
        $response->assertSee('name="form_description"', false);
        $response->assertSee('name="form_style"', false);
        $response->assertSee(__('messages.hub_contact_form.editor.internal_title'), false);
        $response->assertSee(__('messages.hub_contact_form.editor.public_title'), false);
        $response->assertSee(__('messages.hub_contact_form.editor.visual_style'), false);
    }
}
