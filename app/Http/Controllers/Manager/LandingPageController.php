<?php

namespace App\Http\Controllers\Manager;

use App\Models\ActivityLog;
use App\Models\SystemSetting;
use App\Support\LandingConfig;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** The Manager's control over the public driver page and the "Find my car" lookup. Manager role only (see the route group). */
class LandingPageController extends ManagerController
{
    public function edit(): View
    {
        return $this->managerPage('Landing page', 'landing', view('staff.manager.landing.edit', [
            'config' => LandingConfig::get(),
            'defaults' => LandingConfig::defaults(),
            'sections' => LandingConfig::SECTIONS,
            'layouts' => LandingConfig::LOOKUP_LAYOUTS,
            'limits' => LandingConfig::LIMITS,
        ]));
    }

    public function update(Request $request): RedirectResponse
    {
        $before = LandingConfig::get();

        if ($request->input('action') === 'reset') {
            $this->store(LandingConfig::defaults(), $before, $request, 'landing_page_reset');

            return redirect()->route('manager.landing.edit')->with('status', 'The landing page is back to its default content and layout.');
        }

        $keys = array_keys(LandingConfig::SECTIONS);
        $text = fn (string $field) => $request->merge([$field => preg_replace('/\s+/u', ' ', trim((string) $request->input($field))) ?? '']);
        foreach (['hero_eyebrow', 'hero_title', 'hero_lede', 'lookup_heading', 'lookup_intro'] as $field) {
            $text($field);
        }

        $noMarkup = 'not_regex:/[<>]/';
        $data = $request->validate([
            'hero_eyebrow' => ['required', 'string', 'min:2', 'max:'.LandingConfig::LIMITS['hero_eyebrow'], $noMarkup],
            'hero_title' => ['required', 'string', 'min:3', 'max:'.LandingConfig::LIMITS['hero_title'], $noMarkup, 'regex:/^[^*]*(\*[^*]+\*)?[^*]*$/'],
            'hero_lede' => ['required', 'string', 'min:10', 'max:'.LandingConfig::LIMITS['hero_lede'], $noMarkup],
            'lookup_on_login' => ['nullable', 'boolean'],
            'lookup_layout' => ['required', Rule::in(array_keys(LandingConfig::LOOKUP_LAYOUTS))],
            'lookup_heading' => ['required', 'string', 'min:2', 'max:'.LandingConfig::LIMITS['lookup_heading'], $noMarkup],
            'lookup_intro' => ['required', 'string', 'min:5', 'max:'.LandingConfig::LIMITS['lookup_intro'], $noMarkup],
            'sections' => ['required', 'array', 'size:'.count($keys)],
            'sections.*.key' => ['required', Rule::in($keys)],
            'sections.*.enabled' => ['nullable', 'boolean'],
            'sections.*.position' => ['required', 'integer', 'between:1,'.count($keys)],
        ], [
            'hero_eyebrow.required' => 'Write the small line above the headline.',
            'hero_eyebrow.max' => 'The small line can have up to '.LandingConfig::LIMITS['hero_eyebrow'].' characters.',
            'hero_title.required' => 'Write the headline.',
            'hero_title.max' => 'The headline can have up to '.LandingConfig::LIMITS['hero_title'].' characters.',
            'hero_title.regex' => 'Put *stars* around at most one word or phrase to emphasise it, for example Your bay is *waiting*.',
            'hero_lede.required' => 'Write the text under the headline.',
            'hero_lede.min' => 'Write at least 10 characters under the headline.',
            'hero_lede.max' => 'The text under the headline can have up to '.LandingConfig::LIMITS['hero_lede'].' characters.',
            'lookup_layout.in' => 'Choose one of the layouts in the list.',
            'lookup_heading.required' => 'Write the heading of the lookup.',
            'lookup_heading.max' => 'The lookup heading can have up to '.LandingConfig::LIMITS['lookup_heading'].' characters.',
            'lookup_intro.required' => 'Write the text of the lookup.',
            'lookup_intro.max' => 'The lookup text can have up to '.LandingConfig::LIMITS['lookup_intro'].' characters.',
            'sections.*.position.between' => 'Each section needs its own place from 1 to '.count($keys).'.',
            '*.not_regex' => 'Use plain text only. The characters < and > are not allowed.',
        ]);

        // Every section appears exactly once, and every place from 1 to N is used once.
        $given = collect($data['sections']);
        if ($given->pluck('key')->sort()->values()->all() !== collect($keys)->sort()->values()->all()) {
            throw ValidationException::withMessages(['sections' => 'Each section must be listed once.']);
        }
        if ($given->pluck('position')->unique()->count() !== count($keys)) {
            throw ValidationException::withMessages(['sections' => 'Give each section its own place in the order (1 to '.count($keys).').']);
        }

        $config = LandingConfig::clean([
            'hero_eyebrow' => $data['hero_eyebrow'],
            'hero_title' => $data['hero_title'],
            'hero_lede' => $data['hero_lede'],
            'sections' => $given->sortBy('position')->map(fn (array $s) => ['key' => $s['key'], 'enabled' => ! empty($s['enabled'])])->values()->all(),
            'lookup' => [
                'on_login' => $request->boolean('lookup_on_login'),
                'layout' => $data['lookup_layout'],
                'heading' => $data['lookup_heading'],
                'intro' => $data['lookup_intro'],
            ],
        ]);

        $this->store($config, $before, $request, 'landing_page_updated');

        return redirect()->route('manager.landing.edit')->with('status', 'The landing page was saved. The driver page and the sign in page show it now.');
    }

    /** @param array<string, mixed> $config */
    private function store(array $config, array $before, Request $request, string $action): void
    {
        DB::transaction(function () use ($config, $before, $request, $action): void {
            SystemSetting::updateOrCreate(['key' => LandingConfig::KEY], ['value' => $config, 'group' => 'landing']);
            ActivityLog::record($action, $request->user(), $request, 'Landing page', 'manager', ['before' => $before, 'after' => $config]);
        });
    }
}
