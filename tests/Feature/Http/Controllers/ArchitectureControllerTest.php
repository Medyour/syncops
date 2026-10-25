<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;

class ArchitectureControllerTest extends TestCase
{
    public function test_architecture_explains_the_pipeline_and_exposes_complete_navigation(): void
    {
        $response = $this->get(route('architecture'));

        $response
            ->assertOk()
            ->assertViewIs('architecture')
            ->assertSeeInOrder([
                'Réception',
                'Signature',
                'Idempotence',
                'Mise en file',
                'Traitement',
            ])
            ->assertSee('Reprise, puis quarantaine.')
            ->assertSee('Vue d’ensemble')
            ->assertSee('Événements')
            ->assertSee('Quarantaine')
            ->assertSee('Connecteurs')
            ->assertSee('Architecture');
    }
}
