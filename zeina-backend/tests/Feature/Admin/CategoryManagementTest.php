<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

/**
 * Step 1 of the Zaina admin workflow: managing service Categories.
 *
 * Routes: apiResource `/api/v1/admin/categories` behind `auth:sanctum` + `IsAdmin`.
 * Responses use the ApiResponseTrait envelope: { status, message, data }.
 * Validation failures use the custom `params` key (NOT Laravel's `errors`).
 */
class CategoryManagementTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/categories';

    // ---------------------------------------------------------------------
    // Authorization (auth:sanctum + IsAdmin)
    // ---------------------------------------------------------------------

    public function test_guest_cannot_list_categories(): void
    {
        $this->getJson(self::ENDPOINT)->assertStatus(401);
    }

    public function test_customer_cannot_access_admin_categories(): void
    {
        $this->actingAsCustomer();

        $this->getJson(self::ENDPOINT)->assertStatus(403);
    }

    public function test_admin_can_access_categories(): void
    {
        $this->actingAsAdmin();

        $this->getJson(self::ENDPOINT)->assertStatus(200);
    }

    // ---------------------------------------------------------------------
    // Index
    // ---------------------------------------------------------------------

    public function test_admin_can_list_categories(): void
    {
        $this->actingAsAdmin();
        Category::factory()->count(3)->create();

        $response = $this->getJson(self::ENDPOINT);

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.items')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'items' => [
                        ['id', 'name_ar', 'name_en', 'has_subcategories', 'status'],
                    ],
                ],
            ]);
    }

    // ---------------------------------------------------------------------
    // Store (create)
    // ---------------------------------------------------------------------

    public function test_admin_can_create_a_top_level_category(): void
    {
        $admin = $this->actingAsAdmin();

        // Note: the API returns 200 (not 201) on create — asserting current behavior.
        $response = $this->postJson(self::ENDPOINT, [
            'name_ar' => 'العناية بالشعر',
            'name_en' => 'Hair Care',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name_en', 'Hair Care');

        // The DB row defaults status to 'active' even though it was not sent.
        $this->assertDatabaseHas('categories', [
            'name_en' => 'Hair Care',
            'parent_id' => null,
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        // sort_order is auto-assigned by the model's creating hook.
        $this->assertNotNull(Category::where('name_en', 'Hair Care')->value('sort_order'));
    }

    /**
     * Characterization of a known quirk: when `status` is omitted on create, the
     * DB applies the `active` default but the immediate response serializes the
     * un-refreshed model, so `data.status` comes back null. Candidate fix later:
     * `$category->refresh()` before returning, or default `status` in the request.
     */
    public function test_create_response_omits_defaulted_status_until_refresh(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson(self::ENDPOINT, [
            'name_ar' => 'بشرة',
            'name_en' => 'Skin',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.status', null);
        $this->assertSame('active', Category::where('name_en', 'Skin')->value('status'));
    }

    public function test_admin_can_create_a_subcategory_under_a_top_level_category(): void
    {
        $this->actingAsAdmin();
        $parent = Category::factory()->create();

        $response = $this->postJson(self::ENDPOINT, [
            'name_ar' => 'قص الشعر',
            'name_en' => 'Haircut',
            'parent_id' => $parent->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('categories', [
            'name_en' => 'Haircut',
            'parent_id' => $parent->id,
        ]);
    }

    public function test_create_category_requires_arabic_and_english_names(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::ENDPOINT, [])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['name_ar', 'name_en']]);
    }

    public function test_create_category_name_must_be_unique(): void
    {
        $this->actingAsAdmin();
        $existing = Category::factory()->create();

        $this->postJson(self::ENDPOINT, [
            'name_ar' => $existing->name_ar,
            'name_en' => 'A Brand New English Name',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['name_ar']]);
    }

    public function test_cannot_create_a_subcategory_under_another_subcategory(): void
    {
        $this->actingAsAdmin();
        $parent = Category::factory()->create();
        $child = Category::factory()->childOf($parent)->create();

        // parent_id must reference a TOP-LEVEL category (whereNull parent_id).
        $this->postJson(self::ENDPOINT, [
            'name_ar' => 'تصفيف',
            'name_en' => 'Styling',
            'parent_id' => $child->id,
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['parent_id']]);
    }

    public function test_create_category_rejects_invalid_status(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::ENDPOINT, [
            'name_ar' => 'حالة غير صحيحة',
            'name_en' => 'Invalid Status',
            'status' => 'archived',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['status']]);
    }

    // ---------------------------------------------------------------------
    // Update
    // ---------------------------------------------------------------------

    public function test_admin_can_update_a_category(): void
    {
        $admin = $this->actingAsAdmin();
        $category = Category::factory()->create();

        $response = $this->putJson(self::ENDPOINT.'/'.$category->id, [
            'name_ar' => $category->name_ar,
            'name_en' => 'Updated English Name',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name_en', 'Updated English Name');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name_en' => 'Updated English Name',
            'updated_by' => $admin->id,
        ]);
    }

    public function test_update_unique_rule_ignores_the_category_itself(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create();

        // Re-sending the row's own names must not trip the unique rule.
        $this->putJson(self::ENDPOINT.'/'.$category->id, [
            'name_ar' => $category->name_ar,
            'name_en' => $category->name_en,
        ])->assertStatus(200);
    }

    public function test_a_category_cannot_be_its_own_parent(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create();

        $this->putJson(self::ENDPOINT.'/'.$category->id, [
            'name_ar' => $category->name_ar,
            'name_en' => $category->name_en,
            'parent_id' => $category->id,
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['params' => ['parent_id']]);
    }

    // ---------------------------------------------------------------------
    // Destroy (safe delete with impact / policy)
    // ---------------------------------------------------------------------

    public function test_admin_can_soft_delete_an_empty_category(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create();

        $this->deleteJson(self::ENDPOINT.'/'.$category->id)->assertStatus(200);

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'status' => 'inactive',
        ]);
    }

    public function test_check_impact_returns_linked_subcategory_count_without_deleting(): void
    {
        $this->actingAsAdmin();
        $parent = Category::factory()->create();
        Category::factory()->count(2)->childOf($parent)->create();

        $this->deleteJson(self::ENDPOINT.'/'.$parent->id, ['check_impact' => true])
            ->assertStatus(200)
            ->assertJsonPath('data.linked_subcategories_count', 2);

        $this->assertNotSoftDeleted('categories', ['id' => $parent->id]);
    }

    public function test_destroy_is_blocked_when_category_has_links_and_no_policy(): void
    {
        $this->actingAsAdmin();
        $parent = Category::factory()->create();
        Category::factory()->childOf($parent)->create();

        $this->deleteJson(self::ENDPOINT.'/'.$parent->id)
            ->assertStatus(400)
            ->assertJsonPath('error', 'Action required');

        $this->assertNotSoftDeleted('categories', ['id' => $parent->id]);
    }

    public function test_cascade_hide_policy_deactivates_subcategories_and_soft_deletes_parent(): void
    {
        $this->actingAsAdmin();
        $parent = Category::factory()->create();
        $child = Category::factory()->childOf($parent)->create();

        $this->deleteJson(self::ENDPOINT.'/'.$parent->id, ['policy' => 'cascade_hide'])
            ->assertStatus(200);

        $this->assertSoftDeleted('categories', ['id' => $parent->id]);
        $this->assertDatabaseHas('categories', [
            'id' => $child->id,
            'status' => 'inactive',
        ]);
    }

    public function test_check_impact_reports_linked_service_count(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create();
        Service::factory()->create(['category_id' => $category->id]);

        $this->deleteJson(self::ENDPOINT.'/'.$category->id, ['check_impact' => true])
            ->assertStatus(200)
            ->assertJsonPath('data.linked_services_count', 1);

        $this->assertNotSoftDeleted('categories', ['id' => $category->id]);
    }

    public function test_cascade_hide_policy_also_deactivates_linked_services(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create();
        $service = Service::factory()->create([
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        $this->deleteJson(self::ENDPOINT.'/'.$category->id, ['policy' => 'cascade_hide'])
            ->assertStatus(200);

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'status' => 'inactive',
        ]);
    }

    /**
     * GAP: `sort_order` is accepted by StoreCategoryRequest but is NOT in the
     * Category $fillable, so an admin-supplied value is silently dropped and the
     * model's creating hook auto-assigns it (max + 1). Documented so a future
     * change (add to $fillable) flips this test.
     */
    public function test_admin_supplied_sort_order_is_ignored_on_create(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::ENDPOINT, [
            'name_ar' => 'ترتيب',
            'name_en' => 'Ordering',
            'sort_order' => 999,
        ])->assertStatus(200);

        $this->assertNotEquals(999, (int) Category::where('name_en', 'Ordering')->value('sort_order'));
    }

    public function test_guest_cannot_toggle_category_status(): void
    {
        $category = Category::factory()->create(['status' => 'active']);

        $this->postJson(self::ENDPOINT.'/'.$category->id.'/toggle-status')->assertStatus(401);
    }

    public function test_customer_cannot_toggle_category_status(): void
    {
        $this->actingAsCustomer();
        $category = Category::factory()->create(['status' => 'active']);

        $this->postJson(self::ENDPOINT.'/'.$category->id.'/toggle-status')->assertStatus(403);
    }

    public function test_admin_can_deactivate_an_active_category(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create(['status' => 'active']);

        $this->postJson(self::ENDPOINT.'/'.$category->id.'/toggle-status')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'status' => 'inactive',
        ]);
    }

    public function test_admin_can_reactivate_an_inactive_category(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->create(['status' => 'inactive']);

        $this->postJson(self::ENDPOINT.'/'.$category->id.'/toggle-status')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'status' => 'active',
        ]);
    }
}
