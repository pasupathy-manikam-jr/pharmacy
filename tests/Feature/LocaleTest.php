<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Locales;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_switch_applies_to_the_login_page(): void
    {
        $this->post(route('locale.update'), ['locale' => 'ms'])->assertRedirect();

        $this->get(route('login'))->assertInertia(fn (AssertableInertia $p) => $p
            ->where('locale', 'ms')
            ->where('locales', Locales::ALL)
            ->where('translations.Password', fn ($v) => is_string($v) && $v !== 'Password'));
    }

    public function test_staff_choice_is_saved_and_follows_them(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['branch_id' => null])->assignRole('owner');

        $this->actingAs($user)->post(route('locale.update'), ['locale' => 'zh'])->assertRedirect();
        $this->assertSame('zh', $user->fresh()?->locale);

        // A new session still gets the saved language.
        $this->flushSession();
        $this->actingAs($user->fresh() ?? $user)->get(route('profile.edit'))->assertInertia(fn (AssertableInertia $p) => $p->where('locale', 'zh'));
    }

    public function test_unknown_locale_is_rejected(): void
    {
        $this->post(route('locale.update'), ['locale' => 'fr'])->assertSessionHasErrors('locale');
        $this->get(route('login'))->assertInertia(fn (AssertableInertia $p) => $p->where('locale', 'en')->where('translations', []));
    }

    public function test_server_messages_follow_the_language(): void
    {
        $this->post(route('locale.update'), ['locale' => 'ms']);
        $this->post(route('login.store'), ['email' => '', 'password' => ''])->assertSessionHasErrors('email');

        $this->assertNotSame('The email field is required.', session('errors')->first('email'));
    }

    /**
     * Every fixed string the interface asks to translate exists in each language file,
     * so a new English string can't slip through untranslated.
     */
    public function test_every_interface_string_is_translated(): void
    {
        $keys = [];
        $files = array_merge(
            glob(resource_path('js/{pages,layouts,components}/**/*.vue'), GLOB_BRACE) ?: [],
            glob(resource_path('js/{pages,layouts,components}/*.vue'), GLOB_BRACE) ?: [],
            glob(resource_path('js/pages/*/*.vue')) ?: [],
        );
        foreach (array_unique($files) as $file) {
            if (str_contains($file, '/components/ui/')) {
                continue;
            }
            preg_match_all("/\\\$t\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", (string) file_get_contents($file), $m);
            array_push($keys, ...$m[1]);
        }
        foreach (array_merge(glob(app_path('*/*.php')) ?: [], glob(app_path('*/*/*.php')) ?: [], glob(app_path('*/*/*/*.php')) ?: []) as $file) {
            preg_match_all("/__\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", (string) file_get_contents($file), $m);
            array_push($keys, ...$m[1]);
        }
        $keys = array_unique(array_map(fn ($k) => str_replace("\\'", "'", $k), $keys));

        foreach (['ms', 'zh'] as $locale) {
            $lines = Locales::lines($locale);
            $missing = array_values(array_filter($keys, fn ($k) => ! array_key_exists($k, $lines)));
            $this->assertSame([], $missing, "Missing {$locale} translations");
        }
    }
}
