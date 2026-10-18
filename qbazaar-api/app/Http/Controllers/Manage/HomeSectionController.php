<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Enums\HomeSection;
use App\Enums\HomeSectionMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\HomeSections\AddHomeSectionItemRequest;
use App\Http\Requests\Manage\HomeSections\UpdateHomeSectionModeRequest;
use App\Http\Requests\Manage\ReorderPositionsRequest;
use App\Models\HomeSectionItem;
use App\Models\User;
use App\Services\Catalog\HomeSectionCurator;
use App\Services\Catalog\HomeSections;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The "recommended" and "best selling" rails of the home screen: each runs
 * automatically or on the ads staff pick and order here.
 */
class HomeSectionController extends Controller
{
    public function index(HomeSections $sections): View
    {
        return view('admin.home-sections.index', [
            'panels' => $sections->panels(),
            'modes' => HomeSectionMode::cases(),
        ]);
    }

    public function updateMode(UpdateHomeSectionModeRequest $request, #[CurrentUser] User $actor, HomeSection $section, HomeSectionCurator $curator): RedirectResponse
    {
        $curator->setMode($actor, $section, $request->mode());

        return $this->backTo($section, 'admin.home_sections.flash.mode');
    }

    public function storeItem(AddHomeSectionItemRequest $request, #[CurrentUser] User $actor, HomeSection $section, HomeSectionCurator $curator): RedirectResponse
    {
        $curator->add($actor, $section, $request->ad());

        return $this->backTo($section, 'admin.home_sections.flash.added');
    }

    public function destroyItem(#[CurrentUser] User $actor, HomeSection $section, HomeSectionItem $item, HomeSectionCurator $curator): RedirectResponse
    {
        abort_unless($item->section === $section->value, 404);

        $curator->remove($actor, $section, $item);

        return $this->backTo($section, 'admin.home_sections.flash.removed');
    }

    public function reorder(ReorderPositionsRequest $request, #[CurrentUser] User $actor, HomeSection $section, HomeSectionCurator $curator): RedirectResponse
    {
        $curator->reorder($actor, $section, $request->positions());

        return $this->backTo($section, 'admin.home_sections.flash.reordered');
    }

    private function backTo(HomeSection $section, string $message): RedirectResponse
    {
        return redirect()
            ->to(route('admin.home-sections.index') . '#section-' . $section->value)
            ->with('status', __($message));
    }
}
