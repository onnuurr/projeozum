<?php

namespace Modules\Creative\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Media;
use App\Support\ModuleSettingsRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Creative\Http\Requests\DeleteStylistOptionsRequest;
use Modules\Creative\Http\Requests\SaveCategoryRegionsRequest;
use Modules\Creative\Http\Requests\SaveMockupSettingsRequest;
use Modules\Creative\Http\Requests\SaveStylistOptionRequest;
use Modules\Creative\Http\Requests\UpdatePoseMockupTagsRequest;
use Modules\Creative\Models\CategoryRegion;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\Pose;
use Modules\Creative\Models\StylistOption;
use Modules\Creative\Services\Ai\Drivers\Gemini\MockupPromptBuilder;
use Modules\Creative\Services\Mockup\MockupSettings;
use Modules\Product\Models\Category;

/**
 * Creative modülünün tek ayar sayfası (creative-settings.view; çark ikonu). Mokap sekmeleri
 * (Genel, Kategoriler, Stilist seçenekleri, Pozlar) mockup-settings.manage ile kilitli: yetkisi olmayan
 * kullanıcıya veri hiç gönderilmez (prop null). Yeni sekme eklenince CreativeServiceProvider'daki
 * tabPermissions'a yetkisi eklenir.
 */
class CreativeSettingsController extends Controller
{
    public function index(Request $request, ModuleSettingsRegistry $registry, MockupSettings $settings): Response
    {
        abort_unless($registry->canAccess($request->user(), 'creative'), 403);

        return Inertia::render('Creative::Settings', [
            'tab'    => $request->query('tab'),
            'mockup' => $request->user()->can('mockup-settings.manage') ? $this->mockup($settings) : null,
        ]);
    }

    public function saveGeneral(SaveMockupSettingsRequest $request, MockupSettings $settings): RedirectResponse
    {
        $settings->save($request->validated());

        return back()->with('success', __('creative::messages.mockup.settings_saved'));
    }

    public function saveCategories(SaveCategoryRegionsRequest $request, MockupSettings $settings): RedirectResponse
    {
        $settings->saveCategoryRegions($request->validated('rows'));

        return back()->with('success', __('creative::messages.mockup.settings_saved'));
    }

    public function storeOption(SaveStylistOptionRequest $request, MockupSettings $settings): RedirectResponse
    {
        $settings->saveOption(null, $request->payload());

        return back()->with('success', __('creative::messages.mockup.settings_saved'));
    }

    public function updateOption(StylistOption $option, SaveStylistOptionRequest $request, MockupSettings $settings): RedirectResponse
    {
        $settings->saveOption($option, $request->payload());

        return back()->with('success', __('creative::messages.mockup.settings_saved'));
    }

    public function destroyOptions(DeleteStylistOptionsRequest $request, MockupSettings $settings): RedirectResponse
    {
        $settings->deleteOptions($request->validated('ids'));

        return back()->with('success', __('creative::messages.mockup.settings_saved'));
    }

    public function updatePose(Pose $pose, UpdatePoseMockupTagsRequest $request, MockupSettings $settings): RedirectResponse
    {
        $settings->savePoseTags($pose, $request->validated());

        return back()->with('success', __('creative::messages.mockup.settings_saved'));
    }

    /** @return array<string,mixed> */
    private function mockup(MockupSettings $settings): array
    {
        $regions = CategoryRegion::query()->get()->keyBy('category_id');

        return [
            'general' => [
                'auto_trigger'  => $settings->autoTrigger(),
                'daily_limit'   => $settings->dailyLimit(),
                'started_today' => $settings->startedToday(),
                'mannequins'    => $settings->mannequinIds(),
            ],
            'mannequins' => Mannequin::query()->where('status', Mannequin::STATUS_READY)
                ->where('review_status', Mannequin::REVIEW_APPROVED)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])->all(),
            'categories' => Category::query()->with('parent:id,name')->orderBy('parent_id')->orderBy('name')->get(['id', 'name', 'parent_id'])
                ->map(fn (Category $c) => [
                    'id'     => $c->id,
                    'name'   => $c->name,
                    'parent' => $c->parent?->name,
                    'region' => $regions[$c->id]->region ?? null,
                    'length' => $regions[$c->id]->length ?? null,
                ])->all(),
            'options' => StylistOption::query()->orderBy('kind')->orderBy('sort_order')->get()
                ->map(fn (StylistOption $o) => $o->only(['id', 'kind', 'key', 'label_tr', 'prompt_en', 'tags', 'is_active', 'sort_order']))->all(),
            'poses' => Pose::query()->where('status', Pose::STATUS_READY)->orderBy('sort_order')->get()
                ->map(fn (Pose $p) => [
                    'id'             => $p->id,
                    'pose_key'       => $p->pose_key,
                    'label'          => $p->label,
                    'preview_url'    => $p->preview_image_path ? Media::url($p->preview_image_path) : null,
                    'mockup_regions' => (array) $p->mockup_regions,
                    'requires'       => (array) $p->requires,
                    'view'           => $p->view ?: 'front',
                    'is_master'      => $p->pose_key === config('creative.mockup.master_pose'),
                ])->all(),
            'features' => MockupPromptBuilder::PRODUCT_FEATURES,
        ];
    }
}
