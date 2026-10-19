<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\View;
use Tests\TestCase;

class ProjectManagementViewsTest extends TestCase
{
    public function test_project_management_views_are_registered_with_the_correct_namespace(): void
    {
        $this->assertTrue(View::exists('ProjectManagement::projects.index'));
        $this->assertTrue(View::exists('ProjectManagement::projects.create'));
        $this->assertTrue(View::exists('ProjectManagement::tasks.index'));
        $this->assertTrue(View::exists('ProjectManagement::tasks.my-tasks'));
    }
}
